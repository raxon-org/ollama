<?php
namespace Package\Raxon\Ollama\Trait;

use Raxon\Module\Core;
use Raxon\Module\Dir;

trait Setup {

    public function install_ollama(string $command = ''): void
    {
        if(!Dir::exist('/root/.ollama/')){
            Core::interactive();
            $command = 'curl -fsSL https://ollama.com/install.sh | sh';
            exec($command, $output);
            echo implode(PHP_EOL, $output);
        }
    }
}