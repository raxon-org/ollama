<?php
namespace Package\Raxon\Ollama\Trait;

use Raxon\App;
use Raxon\Exception\FileAppendException;
use Raxon\Exception\FileWriteException;
use Raxon\Module\Core;
use Raxon\Module\Data;
use Raxon\Module\Dir;
use Raxon\Module\File;
use Raxon\Node\Module\Node;
use Raxon\Parse\Module\Parse;

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
        $context_length = $options->context_length ?? 4096;
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
                $command = 'OLLAMA_CONTEXT_LENGTH='.  $context_length .' ollama serve >> ' . $log .' &';
                Core::execute($object, $command, $output, $notification, Core::SHELL_PROCESS);
                echo $output;
            }
            $info = $this->info('raxon/ollama process');
            if($info['pid'] === null){
                //check retry strategy.
                $command = 'app raxon/ollama process >> ' . $log .' &';
                exec($command);
//                Core::execute($object, $command, $output, $notification, Core::SHELL_PROCESS);
//                echo $output;
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
        $output = '';
        $notification = '';
        if($info['pid'] === null){
            //check retry strategy.
            while(true){
                $info = $this->info('raxon/ollama guard');
                if($info['pid'] !== null){
                    $command = 'kill  ' . escapeshellcmd($info['pid']);
                    exec($command);
                } else {
                    break;
                }
            }
            $command_options = '';
            foreach($options as $key => $value){
                $command_options .= ' -' . $key . '=' . $value;
            }
            $command_flags = '';
            foreach($flags as $key => $value){
                $command_flags .= ' --' . $key . '=' . $value;
            }
            $command = 'app raxon/ollama guard '. $command_options . ' ' . $command_flags .'&';
            Core::execute($object, $command, $output, $notification, Core::SHELL_PROCESS);
            if($output){
                echo $output;
            }
            if($notification){
                echo $notification;
            }
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
        $info = $this->info('raxon/ollama process');
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

        echo 'Processing ollama...' . PHP_EOL;

//        $object->logger('project.log.debug')->info('Processing ollama...');
        $counter =  $options->counter ?? 1;
        while(true){
            $instance = App::instance();
            $object->config('ramdisk.url', $instance->config('ramdisk.url'));
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
                $command = 'app raxon/ollama generate -source=' . $url;
                $logger = $object->config('project.log.ollama');
                if($logger){
                    $object->logger($logger)->info($command);
                }
//            echo $command . PHP_EOL;
//            flush();
                exec($command, $output);
                if(!empty($output)){
                    echo implode(PHP_EOL, $output) . PHP_EOL;
                }
                $counter = 1;
                unset($patch);
            }
            if($counter > 1800){
                //memory leak detected on this long-running process.
                //after 30 minutes of inactivity go to exit.
                exit(0);
            }
            elseif($counter > 600){
                //after 10 minutes of inactivity go to 5 seconds
                sleep(5);
            } else {
                sleep(1);
            }
            unset($node);
            unset($instance);
            unset($input);
            unset($role);
            $counter++;
        }
    }

    /**
     * @throws ObjectException
     * @throws FileWriteException
     * @throws FileAppendException
     * @throws Exception
     */
    public function generate($flags, $options): void {
        if(property_exists($options, 'source')){
            $object = $this->object();
            $dir_log = $object->config('project.dir.log');
            $url_log = $dir_log . 'ollama.log';
            try {
                $data = $object->data_read($options->source);
            }
            catch(Exception $e){
                File::append($url_log, $e->getMessage() . PHP_EOL);
                $data = false;
            }

            $postfields = [];
            ini_set('max_execution_time', 3600);
            set_time_limit(3600);
            if($data){
                $object->config('ollama.time.start', microtime(true));
                $url = $data->get('endpoint');            
                $uuid = $data->get('uuid');
                $postfields['model'] = $data->get('model');                
                $parseData = new Data($object->data());
                $source = $options->source;
                $options->source = 'Internal_' . hash('sha256', $url);
                $parse = new Parse($object, $parseData, $flags, $options);
                /*
                $parse->limit([
                    'function' => [
                        'file_read'
                    ]
                ]);
                */
                $parse->limit([
                    'File.read'
                ]);
                $data->data($parse->compile($data->data(), $object->data()));
                if(
                    str_contains($url, '/generate')
                ){
                    $postfields['prompt'] = $data->get('prompt');
                    $postfields['keep_alive'] = '30m';
                    $postfields['think'] = $data->get('think') ?? false;
                    $postfields['options'] = (array) $data->get('options');
                    $postfields['stream'] = $data->extract('options.stream');
                }
                elseif(
                    str_contains($url, '/chat')
                ){                    
                    $postfields['messages'] = $data->get('messages');    
                    $postfields['tools'] = $data->get('tools');    
                    $postfields['think'] = $data->get('think') ?? false;
                    $postfields['keep_alive'] = '30m';
                    $postfields['options'] = (array) $data->get('options');
                    $postfields['stream'] = $data->extract('options.stream');
                }
                if(str_contains($url, '/embed')){
//                    $postfields['input'] = $data->get('prompt');
                    $post = Core::object($postfields, Core::OBJECT_JSON_LINE);
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url); // Set the URL of the localhost
                    curl_setopt($ch, CURLOPT_POST, true); // Set the POST method
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $post); // Set the POST fields
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    $response = curl_exec($ch);
                    curl_close($ch);
                    File::append($options->source, $response);
                    $patch = [
                        'uuid' => $uuid,
                        'status' => 'finish',
                        'response' => $response
                    ];
                } else {
                    $post = Core::object($postfields, Core::OBJECT_JSON_LINE);
                    Core::interactive();
                    $options->source = $source;
                    $chunks = [];
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url); // Set the URL of the localhost
                    curl_setopt($ch, CURLOPT_POST, true); // Set the POST method
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $post); // Set the POST fields
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false); // Disable CURLOPT_RETURNTRANSFER to output directly // Set option to receive data in chunks
                    curl_setopt($ch, CURLOPT_TIMEOUT, 2 * 3600);           // 120 minutes for the full request
                    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);    // 10 seconds for the connection
                    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $chunk) use ($object, $options, $uuid) {
                        $chunks[] = $chunk;
                        File::append($options->source, $chunk);
                        $time_current = microtime(true);
                        /*
                        if($time_current - $object->config('ollama.time.start') > 2){
                            $object->config('ollama.time.start', $time_current);
                            $node = new Node($object);
                            $class = 'Raxon.Ollama.Input';
                            $role = $node->role_system();
                            $record = $node->read($class, $node->role_system(), [
                                'uuid'  => $uuid,
                            ]);
                            if(
                                $record &&
                                array_key_exists('node', $record) &&
                                property_exists($record['node'], 'status') &&
                                $record['node']->status === 'abort'
                            ){
                                $patch = [
                                    'uuid' => $uuid,
                                    'status' => 'aborted',
                                    'chunks' => $chunks
                                ];
                                $node = new Node($object);
                                $class = 'Raxon.Ollama.Input';
                                $role = $node->role_system();
                                $patch = $node->patch($class, $role, $patch);
                                curl_close($ch);
                                exit(0);
                            }
                        }
                        */
                        return strlen($chunk);
                    });
                    curl_exec($ch);
                    // Check for errors
                    if (curl_errno($ch)) {
                        //restart ollama ? need to record curl errors and if 5 or more, or specific error like cannot connect to http server
                        // restart ollama
                        // app raxon/ollama stop (stops ollama)
                        // app raxon/ollama start & (starts ollama)
                        File::append($url_log, 'Curl error: ' . curl_error($ch) . PHP_EOL);
                    }
                    // Close the cURL session
                    curl_close($ch);
                    $patch = [
                        'uuid' => $uuid,
                        'status' => 'finish',
                        'chunks' => $chunks
                    ];
                }
                $node = new Node($object);
                $class = 'Raxon.Ollama.Input';
                $role = $node->role_system();
                $source = File::read($options->source, ['return' => 'array']);
                $possible_error = end($source);
                if(str_starts_with($possible_error, '{"error"')){
                    $error = Core::object(trim($possible_error));
                    $patch['error'] = $patch['error'] ?? [];
                    $patch['error'][] = $error->error ?? null;
                }
                $patch = $node->patch($class, $role, $patch);
            }
        }
    }
}