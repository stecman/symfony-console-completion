<?php

class TestSymfonyStyleCommand extends \Symfony\Component\Console\Command\Command
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
            );
    }
}
