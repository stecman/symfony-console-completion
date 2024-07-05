<?php

use Symfony\Component\Console\Command\Command;

class HiddenCommand extends Command
{
    protected function configure()
    {
        $this->setName('internals')
            ->setHidden(true);
    }
}
