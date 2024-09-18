<?php
namespace Package\Raxon\Ollama\Trait;

use Raxon\App;
use Raxon\Config;

use Raxon\Exception\ObjectException;
use Raxon\Module\Core;
use Raxon\Module\File;

use Exception;
use Raxon\Node\Model\Node;


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
                d($command);
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

        echo 'Starting guarding ollama serve...' . PHP_EOL;
        while(true){
            $info = $this->info('ollama serve');
            if($info['pid'] === null){
                //check retry strategy.
                $command = 'ollama serve &';
                Core::execute($object, $command, $output, $notification, Core::SHELL_PROCESS);
                echo $output;
            }
            sleep(1);
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
            $info = $this->info('app raxon/ollama guard');
            if($info['pid'] !== null){
                $command = 'kill  ' . escapeshellcmd($info['pid']);
                exec($command, $output);
            }
            $command = 'app raxon/ollama guard &';
            Core::execute($object, $command, $output, $notification, Core::SHELL_PROCESS);
            echo $output;
        }
    }

    public function stop($flags, $options): void {
        $info = $this->info('ollama serve');
        if($info['pid'] !== null){
            //check retry strategy.
            $command = 'kill  ' . escapeshellcmd($info['pid']);
            exec($command, $output);
            echo implode(PHP_EOL, $output);
        }
    }

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public function process($flags, $options): void {
        $object = $this->object();
        $node = new Node($object);

        $class = 'Raxon.Ollama.Input';
        $role = $node->role_system();
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
            echo 'Patch to Process...' . PHP_EOL;
            /*
            $patch = [
                'uuid' => $input['node']->uuid,
                'status' => 'process'
            ];
            $node->patch($class, $role, $patch);
            */
        }
        $url = $object->config('ramdisk.url') .
            $object->config(Config::POSIX_ID) .
            $object->config('ds') .
            'Ollama' .
            $object->config('ds') .
            $input['node']['uuid'] .
            $object->config('extension.jsonl')
        ;
        File::append($url, Core::object($input['node'], Core::OBJECT_JSON));
        $command = 'app raxon/ollama generate -url=' . $url . ' &';

        ddd($command);
    }


    public function generate($flags, $options): void {
        if(property_exists($options, 'url')){
            $object = $this->object();
            $data = $object->data_read($options->url);
            ddd($data);
            Core::interactive();
            $ch = curl_init();
            // Set the URL of the localhost stream
            curl_setopt($ch, CURLOPT_URL, "http://localhost:11434/api/generate");
            // Set the POST method
            curl_setopt($ch, CURLOPT_POST, true);
            // Set the POST fields
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            // Disable CURLOPT_RETURNTRANSFER to output directly
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
            // Set option to receive data in chunks
            $result = [];
            curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $chunk) use ($options) {
                $command = $chunk . ' >> ' . $options->url;
                exec($command);
                // Output each chunk as it comes in
                echo $chunk;
                // Optionally flush the output buffer to ensure it's displayed immediately
                flush();
                // Return the number of bytes processed in this chunk
                return strlen($chunk);
            });
            curl_exec($ch);
            // Check for errors
            if (curl_errno($ch)) {
                echo 'Curl error: ' . curl_error($ch);
            }
            // Close the cURL session
            curl_close($ch);
        }
    }
}