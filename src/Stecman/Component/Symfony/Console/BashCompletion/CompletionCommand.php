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
To enable BASH completion, run: <comment>eval `beam completion --genhook="app-name"`</comment>
END
            )
            ->addOption(
                'genhook',
                'g',
                InputOption::VALUE_REQUIRED,
                'Generate BASH script to use completion with this application. <comment>Requires program name as value.</comment>'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->handler = new CompletionHandler( $this->getApplication() );
        $handler = $this->handler;

        if ($programName = $input->getOption('genhook')) {
            $output->write( $handler->generateBashCompletionHook($programName), true );
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