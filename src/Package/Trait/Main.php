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
        $command = 'ps -aux | grep ollama';
        $default = $object->config('core.execute.stream.is.default');
        $object->config('core.execute.mode', 'stream');
        $object->config('core.execute.stream.is.default', false);
        Core::execute($object, $command, $output, $notification);
        $object->config('core.execute.stream.is.default', $default);
        d($output);
        d($notification);
        return null;
    }
}