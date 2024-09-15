<?php
namespace Package\Raxon\Ollama\Trait;

use Raxon\App;
use Raxon\Config;

use Raxon\Module\Core;

use Exception;


trait Main {



    public function info(string $command = ''){
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
        d($info);
        if($info['pid'] !== null){
            //check retry strategy.
            $command = 'kill  ' . escapeshellcmd($info['pid']);
            exec($command, $output);
            echo implode(PHP_EOL, $output);
        }
    }
}