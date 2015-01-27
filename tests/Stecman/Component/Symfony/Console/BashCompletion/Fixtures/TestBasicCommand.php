<?php


class TestBasicCommand extends \Symfony\Component\Console\Command\Command
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
            ->addArgument(
                'target',
                \Symfony\Component\Console\Input\InputArgument::REQUIRED
            );
    }
}
