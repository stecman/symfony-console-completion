<?php

namespace Stecman\Component\Symfony\Console\BashCompletion;

use Stecman\Component\Symfony\Console\BashCompletion\Completion\CompletionAwareInterface;
use Stecman\Component\Symfony\Console\BashCompletion\Completion\CompletionInterface;
use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class CompletionHandler
{
    /**
     * Application to complete for
     * @var \Symfony\Component\Console\Application
     */
    protected $application;

    /**
     * @var BaseCommand
     */
    protected $command;

    /**
     * @var CompletionContext
     */
    protected $context;

    /**
     * Array of completion helpers.
     * @var CompletionInterface[]
     */
    protected $helpers = array();

    public function __construct(BaseApplication $application, CompletionContext $context = null)
    {
        $this->application = $application;
        $this->context = $context;
    }

    public function setContext(CompletionContext $context)
    {
        $this->context = $context;
    }

    /**
     * @return CompletionContext
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * @param CompletionInterface[] $array
     */
    public function addHandlers(array $array)
    {
        $this->helpers = array_merge($this->helpers, $array);
    }

    /**
     * @param CompletionInterface $helper
     */
    public function addHandler(CompletionInterface $helper)
    {
        $this->helpers[] = $helper;
    }

    /**
     * Do the actual completion, returning items delimited by spaces
     * @throws \RuntimeException
     * @return string[]
     */
    public function runCompletion()
    {
        if (!$this->context) {
            throw new \RuntimeException('A CompletionContext must be set before requesting completion.');
        }

        $cmdName = $this->getInput()->getFirstArgument();
        if ($this->application->has($cmdName)) {
            $this->command = $this->application->get($cmdName);
        }

        $process = array(
            'completeForOptionValues',
            'completeForOptionShortcuts',
            'completeForOptionShortcutValues',
            'completeForOptions',
            'completeForCommandName',
            'completeForCommandArgs'
        );

        foreach ($process as $methodName) {
            $result = $this->{$methodName}();

            if (false !== $result) {
                // Return the result of the first completion mode that matches
                return $this->filterResults((array) $result);
            }
        }

        return array();
    }

    /**
     * Get an InputInterface representation of the completion context
     * @return ArrayInput
     */
    public function getInput()
    {
        // Filter the command line content to suit ArrayInput
        $words = $this->context->getWords();
        array_shift($words);
        $words = array_filter($words);

        return new ArrayInput($words);
    }

    /**
     * @return array|false
     */
    protected function completeForOptions()
    {
        $word = $this->context->getCurrentWord();

        if ($this->command && strpos($word, '-') === 0) {
            $options = array();

            foreach ($this->getAllOptions() as $opt) {
                $options[] = '--'.$opt->getName();
            }

            return $options;
        }

        return false;
    }

    /**
     * Complete an option shortcut if it exists, but don't offer a list of shortcuts
     * @return array|false
     */
    protected function completeForOptionShortcuts()
    {
        $word = $this->context->getCurrentWord();

        if ($this->command && strpos($word, '-') === 0 && strlen($word) == 2) {
            if ($this->command->getDefinition()->hasShortcut(substr($word, 1))) {
                return array($word);
            }
        }

        return false;
    }

    /**
     * @return array|false
     */
    protected function completeForOptionShortcutValues()
    {
        $wordIndex = $this->context->getWordIndex();

        if ($this->command && $wordIndex > 1) {
            $left = $this->context->getWordAtIndex($wordIndex - 1);

            // Complete short options
            if ($left[0] == '-' && strlen($left) == 2) {
                $shortcut = substr($left, 1);
                $def = $this->command->getDefinition();

                if (!$def->hasShortcut($shortcut)) {
                    return false;
                }

                $opt = $def->getOptionForShortcut($shortcut);
                if ($opt->isValueRequired() || $opt->isValueOptional()) {
                    return $this->completeOption($opt);
                }
            }
        }

        return false;
    }

    /**
     * @return array|false
     */
    protected function completeForOptionValues()
    {
        $wordIndex = $this->context->getWordIndex();

        if ($this->command && $wordIndex > 1) {
            $left = $this->context->getWordAtIndex($wordIndex - 1);

            if (strpos($left, '--') === 0) {
                $name = substr($left, 2);
                $def = $this->command->getDefinition();

                if (!$def->hasOption($name)) {
                    return false;
                }

                $opt = $def->getOption($name);
                if ($opt->isValueRequired() || $opt->isValueOptional()) {
                    return $this->completeOption($opt);
                }
            }
        }

        return false;
    }

    /**
     * If a command is not set, list available commands
     * @return array|false
     */
    protected function completeForCommandName()
    {
        if (!$this->command || (count($this->context->getWords()) == 2 && $this->context->getWordIndex() == 1)) {
            $commands = $this->application->all();
            $names = array_keys($commands);

            if ($key = array_search('_completion', $names)) {
                unset($names[$key]);
            }

            return $names;
        }

        return false;
    }

    /**
     * @return array|false
     */
    protected function completeForCommandArgs()
    {
        if (strpos($this->context->getCurrentWord(), '-') !== 0) {
            if ($this->command) {
                return $this->formatArguments($this->command);
            }
        }

        return false;
    }

    /**
     * @param BaseCommand $cmd
     * @return array|false
     */
    protected function formatArguments(BaseCommand $cmd)
    {
        $argWords = $this->mapArgumentsToWords($cmd->getDefinition()->getArguments());

        foreach ($argWords as $name => $wordNum) {
            if ($this->context->getWordIndex() == $wordNum) {
                if ($helper = $this->getCompletionHelper($name, Completion::TYPE_ARGUMENT)) {
                    return $helper->run();
                }

                if ($this->command instanceof CompletionAwareInterface) {
                    return $this->command->completeArgumentValues($name, $this->context);
                }
            }
        }

        return false;
    }

    /**
     * @param $name
     * @param string $type
     * @return CompletionInterface
     */
    protected function getCompletionHelper($name, $type)
    {
        foreach ($this->helpers as $helper) {
            if ($helper->getType() != $type && $helper->getType() != CompletionInterface::ALL_TYPES) {
                continue;
            }

            if ($helper->getCommandName() == CompletionInterface::ALL_COMMANDS || $helper->getCommandName() == $this->command->getName()) {
                if ($helper->getTargetName() == $name) {
                    return $helper;
                }
            }
        }

        return null;
    }

    /**
     * @param InputOption $option
     * @return array|false
     */
    protected function completeOption(InputOption $option)
    {
        if ($helper = $this->getCompletionHelper($option->getName(), Completion::TYPE_OPTION)) {
            return $helper->run();
        }

        if ($this->command instanceof CompletionAwareInterface) {
            return $this->command->completeOptionValues($option->getName(), $this->context);
        }

        return false;
    }

    /**
     * Step through the command line to determine which words positions represent which argument values
     *
     * The word indexes of argument values are found by eliminating words that are known to not be arguments (options,
     * option values, and command names). Any word that doesn't match for elimination is assumed to be an argument value,
     *
     * @param InputArgument[] $argumentDefinitions
     * @return array as [argument name => word index on command line]
     */
    protected function mapArgumentsToWords($argumentDefinitions)
    {
        $argumentPositions = array();
        $argumentNumber = 0;
        $previousWord = null;
        $argumentNames = array_keys($argumentDefinitions);

        // Build a list of option values to filter out
        $optionsWithArgs = array();

        foreach ($this->getAllOptions() as $option) {
            if ($option->isValueRequired()) {
                $optionsWithArgs[] = '--' . $option->getName();

                if ($option->getShortcut()) {
                    $optionsWithArgs[] = '-' . $option->getShortcut();
                }
            }
        }

        foreach ($this->context->getWords() as $wordIndex => $word) {
            // Skip program name, command name, options, and option values
            if ($wordIndex < 2
                || ($word && '-' === $word[0])
                || in_array($previousWord, $optionsWithArgs)) {
                $previousWord = $word;
                continue;
            } else {
                $previousWord = $word;
            }

            // If argument n exists, pair that argument's name with the current word
            if (isset($argumentNames[$argumentNumber])) {
                $argumentPositions[$argumentNames[$argumentNumber]] = $wordIndex;
            }

            $argumentNumber++;
        }

        return $argumentPositions;
    }

    /**
     * Filter a list of results to those starting with the current word
     * The resulting list is the correct words to put in COMPREPLY.
     *
     * @param string[] $array
     * @return string[]
     */
    protected function filterResults(array $array)
    {
        $curWord = $this->context->getCurrentWord();

        return array_filter($array, function($val) use ($curWord) {
            return fnmatch($curWord.'*', $val);
        });
    }

    /**
     * Returns list of all options.
     *
     * @return InputOption[]
     */
    protected function getAllOptions()
    {
        return array_merge(
            $this->command->getDefinition()->getOptions(),
            $this->application->getDefinition()->getOptions()
        );
    }
}
