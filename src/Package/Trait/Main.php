<?php
namespace Package\Raxon\Ollama\Trait;

use Raxon\App;
use Raxon\Config;

use Raxon\Module\Core;

use Exception;


trait Main {

    /**
     * @throws Exception
     */
    public function serve($flags, $options): mixed {
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
            if(str_contains($line, 'ollama serve')){
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
        if($pid === null){
            //check retry strategy.
            $command = 'ollama serve &';
            exec($command, $output);
        }
        d($pid);
        d($user);
        d($output);
        d($notification);
        return null;
    }
}