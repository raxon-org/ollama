<?php
namespace Package\Raxon\Ollama\Trait;

use Raxon\Module\Dir;

trait Setup {

    public function ollama_install(string $command = ''): void
    {
        if(!Dir::exist('/root/.ollama/')){
            $command = 'curl -fsSL https://ollama.com/install.sh | sh';
            exec($command, $output);
            echo implode(PHP_EOL, $output);
        }
    }
}