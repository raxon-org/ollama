<?php
namespace Package\Raxon\Ollama\Trait;

use Exception;
use Package\Raxon\Basic\Trait\Install;
use Raxon\Exception\ObjectException;
use Raxon\Module\Link;

trait Setup {

    use Install;

    /**
     * @throws ObjectException
     * @throws Exception
     */
    public function install_ollama(object $flags, object $options): void
    {
        $application_list = $this->install_system_application(
            $flags,
            $options,
        );
        $object = $this->object();
        foreach($application_list as $application){
            $this->install_api($options, $application);
        }
        $command = Core::binary($object) . ' raxon/ollama reset';
        exec($command, $output);
        echo implode(PHP_EOL, $output);
    }

    public function pull_model(object $flags, object $options): void
    {
        $command = 'ollama pull qwen3-embedding';
        exec($command, $output);
        echo implode(PHP_EOL, $output);
    }
}