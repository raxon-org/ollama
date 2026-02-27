<?php
namespace Package\Raxon\Ollama\Trait;

use Raxon\Module\Core;
use Raxon\Module\Dir;

trait Setup {

    public function install_ollama(object $flags, object $options): void
    {
        if(!Dir::exist('/root/.ollama/')){
            Core::interactive();
            $command = 'curl -fsSL https://ollama.com/install.sh | sh';
            exec($command, $output);
            echo implode(PHP_EOL, $output);
            $this->pull_model($flags, $options);
        }
    }

    public function pull_model(object $flags, object $options): void
    {
        $command = 'ollama pull qwen3-embedding';
        exec($command, $output);
        echo implode(PHP_EOL, $output);
    }
}