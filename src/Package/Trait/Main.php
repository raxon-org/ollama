<?php
namespace Package\Raxon\Ollama\Trait;

use Raxon\App;
use Raxon\Config;

use Raxon\Module\Core;

use Exception;


trait Main {



    public function info($command = ''){
        $object = $this->object();
        $command = 'ps -aux';
        $default = $object->config('core.execute.stream.is.default');
        $object->config('core.execute.mode', 'stream');
        $object->config('core.execute.stream.is.default', false);
        Core::execute($object, $command, $output, $notification);
        $object->config('core.execute.stream.is.default', $default);
        //we should have result in the output
        $explode = explode("\n", $output);
        $user = null;
        $pid =null;
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
                        break 2;
                    }

                }
            }
        }
        return [
            'pid' => $pid,
            'user' => $user
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
        }
    }
}