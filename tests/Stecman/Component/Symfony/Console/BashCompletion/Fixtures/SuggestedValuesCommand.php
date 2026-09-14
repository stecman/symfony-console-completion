<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\Suggestion;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Command using symfony/console's native $suggestedValues parameter for completion
 */
class SuggestedValuesCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('suggested-values')
            ->addOption(
                'colour',
                'c',
                InputOption::VALUE_REQUIRED,
                'Option with statically suggested values',
                null,
                array('red', 'green', 'blue')
            )
            ->addOption(
                'described',
                null,
                InputOption::VALUE_REQUIRED,
                'Option suggesting Suggestion instances',
                null,
                array(new Suggestion('with-description', 'This description is not used'))
            )
            ->addOption(
                'plain',
                null,
                InputOption::VALUE_REQUIRED,
                'Option without any suggested values'
            )
            ->addArgument(
                'animal',
                InputArgument::OPTIONAL,
                'Argument with statically suggested values',
                null,
                array('cat', 'cow', 'dog')
            )
            ->addArgument(
                'sounds',
                InputArgument::IS_ARRAY,
                'Array argument suggesting values based on the current input',
                null,
                function (CompletionInput $input) {
                    return $input->getCompletionValue() === 'me'
                        ? array('meow', 'mew')
                        : array('moo', 'woof');
                }
            );
    }
}
