<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class TestBasicCommand extends Command
{
    protected function configure()
    {
        $this->setName('wave')
            ->addOption(
                'vigorous'
            )
            ->addOption(
                'jazz-hands',
                'j'
            )
            ->addOption(
                'style',
                's',
                InputOption::VALUE_REQUIRED
            )
            ->addArgument(
                'target',
                InputArgument::REQUIRED
            );
    }
}
