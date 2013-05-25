<?php

namespace Stecman\Component\Symfony\Console\BashCompletion;

use Stecman\Component\Symfony\Console\BashCompletion\CompletionHandler;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CompletionCommand extends SymfonyCommand {

    /**
     * @var CompletionHandler
     */
    protected $handler;

    protected function configure()
    {
        $this
            ->setName('_completion')
            ->setDescription('BASH completion hook.')
            ->setHelp(<<<END
To enable BASH completion, run: <comment>eval `beam completion --genhook`</comment>
END
            )
            ->addOption(
                'genhook',
                null,
                InputOption::VALUE_NONE,
                'Generate BASH script to enable completion using this command'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->handler = new CompletionHandler( $this->getApplication() );
        $handler = $this->handler;

        if ($input->getOption('genhook')) {
            $bash = $handler->generateBashCompletionHook();
            $output->write($bash, true);
        } else {
            $handler->configureFromEnvironment();
            $output->write($this->runCompletion(), true);
        }
    }

    protected function runCompletion()
    {
        return $this->handler->runCompletion();
    }

}