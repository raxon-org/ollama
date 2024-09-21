<?php
namespace Package\Raxon\Ollama\Trait;

use Raxon\App;
use Raxon\Config;

use Raxon\Exception\FileAppendException;
use Raxon\Exception\FileWriteException;
use Raxon\Module\Core;
use Raxon\Module\Dir;
use Raxon\Module\File;

use Raxon\Module\Parse;
use Raxon\Node\Model\Node;

use Exception;

use Raxon\Exception\ObjectException;

trait Main {

    public function info(string $command = ''){
        $object = $this->object();
        $ps_command = 'ps -aux';
        $default = $object->config('core.execute.stream.is.default');
        $object->config('core.execute.mode', 'stream');
        $object->config('core.execute.stream.is.default', false);
        Core::execute($object, $ps_command, $output, $notification);
        $object->config('core.execute.stream.is.default', $default);
        //we should have result in the output
        $explode = explode("\n", $output);
        $user = null;
        $pid =null;
        $cpu = null;
        $mem = null;
        $vsz = null;
        $rss = null;
        $tty = null;
        $stat = null;
        $start = null;
        $time = null;
        $execute = null;
        foreach($explode as $line){
            $line = trim($line);
            if(str_contains($line, $command)){
                $temp = explode(' ', $line);
                foreach($temp as $key => $value){
                    if(empty($value)){
                        continue;
                    }
                    if(!$user){
                        $user = $value;
                    }
                    elseif(!$pid){
                        $pid = $value;
                    }
                    elseif($cpu){
                        $cpu = $value;
                    }
                    elseif($mem){
                        $mem = $value;
                    }
                    elseif($vsz){
                        $vsz = $value;
                    }
                    elseif($rss){
                        $rss = $value;
                    }
                    elseif($tty){
                        $tty = $value;
                    }
                    elseif($stat){
                        $stat = $value;
                    }
                    elseif($start){
                        $start = $value;
                    }
                    elseif($time){
                        $time = $value;
                    }
                    elseif($execute){
                        $execute = $value;
                        break 2;
                    }

                }
            }
        }
        return [
            'pid' => $pid,
            'user' => $user,
            'command' => $execute,
        ];
    }

    /**
     * @throws Exception
     */
    public function guard($flags, $options): void {
        $object = $this->object();

        $log = $object->config('project.dir.log') . 'ollama.log';
        File::touch($log, File::CHMOD);
        File::permission($object, [
            'url' => $log,
        ]);
        echo 'Starting guarding ollama serve...' . PHP_EOL;
        while(true){
            $info = $this->info('ollama serve');
            if($info['pid'] === null){
                //check retry strategy.
                $command = 'ollama serve >> ' . $log .' &';
                Core::execute($object, $command, $output, $notification, Core::SHELL_PROCESS);
                echo $output;
            }
            sleep(5);
        }
    }


    /**
     * @throws Exception
     */
    public function start($flags, $options): void {
        $object = $this->object();
        $info = $this->info('ollama serve');
        if($info['pid'] !== null){
            echo 'Ollama serve already running...' . PHP_EOL;
            return;
        }
        if($info['pid'] === null){
            //check retry strategy.
            while(true){
                $info = $this->info('raxon/ollama guard');
                if($info['pid'] !== null){
                    $command = 'kill  ' . escapeshellcmd($info['pid']);
                    exec($command, $output);
                } else {
                    break;
                }
            }
            $command = 'app raxon/ollama guard &';
            Core::execute($object, $command, $output, $notification, Core::SHELL_PROCESS);
            echo $output;
        }
        exit(0);
    }

    public function stop($flags, $options): void {
        //remove guards first in a while loop
        while(true){
            $info = $this->info('raxon/ollama guard');
            if($info['pid'] !== null){
                $command = 'kill  ' . escapeshellcmd($info['pid']);
                exec($command, $output);
            } else {
                break;
            }
        }
        $info = $this->info('ollama serve');
        if($info['pid'] !== null){
            //check retry strategy.
            $command = 'kill  ' . escapeshellcmd($info['pid']);
            exec($command, $output);
            echo implode(PHP_EOL, $output);
        }
        exit(0);
    }

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public function process($flags, $options): void {
        $object = $this->object();

        $object->logger('project.log.debug')->info('Processing ollama...');


        $instance = App::instance();
        $object->config('ramdisk.url', $instance->config('ramdisk.url'));
        $node = new Node($object);

        $class = 'Raxon.Ollama.Input';
        $role = $node->role_system();
        $counter =  $options->counter ?? 1;
        $options_input = [
            'filter' => [
                'status' => 'start'
            ]
        ];
        $input = $node->record($class, $role, $options_input);
        if(
            $input &&
            array_key_exists('node', $input) &&
            property_exists($input['node'], 'uuid')
        ){
            $patch = [
                'uuid' => $input['node']->uuid,
                'status' => 'process'
            ];
            $patch = $node->patch($class, $role, $patch);
            $dir = $object->config('ramdisk.url') .
                '33' .
                $object->config('ds') .
                'Ollama' .
                $object->config('ds')
            ;
            $url = $dir .
                $input['node']->uuid .
                $object->config('extension.jsonl')
            ;
            Dir::create($dir, Dir::CHMOD);
            File::write($url, Core::object($input['node'], Core::OBJECT_JSON_LINE) . PHP_EOL);
            File::permission($object, [
                'url' => $url,
                'dir' => $dir,
            ]);
            $command = 'app raxon/ollama generate -url=' . $url;
//            echo $command . PHP_EOL;
//            flush();
            exec($command, $output);
            if(!empty($output)){
                echo implode(PHP_EOL, $output) . PHP_EOL;
            }
            $counter = 1;
        }
        if($counter > 600){
            //after 10 minutes of inactivity go to 5 seconds
            sleep(5);
        } else {
            sleep(1);
        }
        $options->counter = $counter + 1;
        $this->process($flags, $options);
    }

    /**
     * @throws ObjectException
     * @throws FileWriteException
     * @throws FileAppendException
     * @throws Exception
     */
    public function generate($flags, $options): void {
        if(property_exists($options, 'url')){
            $object = $this->object();
            $data = $object->data_read($options->url);
            $postfields = [];
            if($data){
                $uuid = $data->get('uuid');
                $postfields['model'] = $data->get('model');
                $postfields['prompt'] = $data->get('prompt');

                $parse = new Parse($object);
                $parse->limit([
                    'function' => [
                        'file_read'
                    ]
                ]);
                $postfields['prompt'] = $parse->compile($postfields['prompt'], $object->data());
                $postfields['stream'] = $data->get('options.stream');
                $post = Core::object($postfields, Core::OBJECT_JSON);
                Core::interactive();
                $ch = curl_init();
                // Set the URL of the localhost stream
                curl_setopt($ch, CURLOPT_URL, "http://localhost:11434/api/generate");
                // Set the POST method
                curl_setopt($ch, CURLOPT_POST, true);
                // Set the POST fields
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
                // Disable CURLOPT_RETURNTRANSFER to output directly
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
                // Set option to receive data in chunks
                $result = [];
                curl_setopt($ch, CURLOPT_TIMEOUT, 1800);           // 30 minutes for the full request
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);    // 10 seconds for the connection

                curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $chunk) use ($options) {
                    File::append($options->url, $chunk);
                    //make abort happen here
                    // Output each chunk as it comes in
//                    echo $chunk;
                    // Optionally flush the output buffer to ensure it's displayed immediately
//                    flush();
                    // Return the number of bytes processed in this chunk
                    return strlen($chunk);
                });
                curl_exec($ch);
                // Check for errors
                if (curl_errno($ch)) {
                    //restart ollama ? need to record curl errors and if 5 or more, or specific error like cannot connect to http server
                    // restart ollama
                    // app raxon/ollama stop (stops ollama)
                    // app raxon/ollama start & (starts ollama)
                    echo 'Curl error: ' . curl_error($ch);
                }
                // Close the cURL session
                curl_close($ch);
                $patch = [
                    'uuid' => $uuid,
                    'status' => 'finish'
                ];
                $node = new Node($object);

                $class = 'Raxon.Ollama.Input';
                $role = $node->role_system();
                $patch = $node->patch($class, $role, $patch);
            }
        }
    }
}