<?php

namespace xiian\ComposerGenerator\Command;

use Composer\Plugin\Capability\CommandProvider;

class Provider implements CommandProvider
{
    public function getCommands()
    {
        return [new GenerateCommand()];
    }
}
