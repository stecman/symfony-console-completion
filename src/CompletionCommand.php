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
To enable BASH completion, run:

    <comment>eval `[program] _completion -g`</comment>.

Or for an alias:

    <comment>eval `[program] _completion -g -p [alias]`</comment>.

END
            )
            ->addOption(
                'genhook',
                'g',
                InputOption::VALUE_NONE,
                'Generate BASH script to use completion with this application.'
            )
            ->addOption(
                'program',
                'p',
                InputOption::VALUE_REQUIRED,
                'Program name to add completion for.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->handler = new CompletionHandler( $this->getApplication() );
        $handler = $this->handler;

        if ( $input->getOption('genhook') ) {
            $output->write( $handler->generateBashCompletionHook($input->getOption('program')), true );
        } else {
            $handler->setContext(new EnvironmentCompletionContext());
            $output->write($this->runCompletion(), true);
        }
    }

    protected function runCompletion()
    {
        return $this->handler->runCompletion();
    }

}