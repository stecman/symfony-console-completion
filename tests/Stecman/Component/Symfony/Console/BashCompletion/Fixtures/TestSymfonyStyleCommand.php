<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;

class TestSymfonyStyleCommand extends Command
{
    protected function configure()
    {
        $this->setName('walk:north')
            ->addOption(
                'power',
                'p'
            )
            ->addOption(
                'deploy:jazz-hands',
                'j'
            )
            ->addOption(
                'style',
                's',
                InputOption::VALUE_REQUIRED
            )
            ->addOption(
                'target',
                't',
                InputOption::VALUE_REQUIRED
            );
    }
}
