<?php

use Stecman\Component\Symfony\Console\BashCompletion\Completion\CompletionAwareInterface;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Command mixing this library's CompletionAwareInterface with symfony/console's own completion API
 *
 * This covers commands that were written against the old API and later extended using the new one.
 */
class NativeCompleteCommand extends Command implements CompletionAwareInterface
{
    protected function configure()
    {
        $this->setName('native-complete')
            ->addOption('legacy-option', null, InputOption::VALUE_REQUIRED)
            ->addOption('native-option', null, InputOption::VALUE_REQUIRED)
            ->addArgument('legacy-argument', InputArgument::OPTIONAL)
            ->addArgument('native-argument', InputArgument::OPTIONAL);
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestOptionValuesFor('native-option')) {
            $suggestions->suggestValues(array('native-opt-one', 'native-opt-two'));
        }

        if ($input->mustSuggestArgumentValuesFor('native-argument')) {
            $suggestions->suggestValues(array('native-arg-one', 'native-arg-two'));
        }

        // Values the legacy API already provides, to check that the legacy API takes precedence
        if ($input->mustSuggestOptionValuesFor('legacy-option')) {
            $suggestions->suggestValue('native-should-not-win');
        }
    }

    public function completeOptionValues($optionName, CompletionContext $context)
    {
        if ($optionName === 'legacy-option') {
            return array('legacy-opt');
        }

        return array();
    }

    public function completeArgumentValues($argumentName, CompletionContext $context)
    {
        if ($argumentName === 'legacy-argument') {
            return array('legacy-arg');
        }

        return array();
    }
}
