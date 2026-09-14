<?php

namespace Stecman\Component\Symfony\Console\BashCompletion;

use Stecman\Component\Symfony\Console\BashCompletion\Completion\CompletionAwareInterface;
use Stecman\Component\Symfony\Console\BashCompletion\Completion\CompletionInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
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
     * @var Command
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

    /**
     * Index the command name was detected at
     * @var int
     */
    private $commandWordIndex;

    public function __construct(Application $application, ?CompletionContext $context = null)
    {
        $this->application = $application;
        $this->context = $context;

        // Set up completions for commands that are built-into Application
        $this->addHandler(
            new Completion(
                'help',
                'command_name',
                Completion::TYPE_ARGUMENT,
                $this->getCommandNames()
            )
        );

        $this->addHandler(
            new Completion(
                'list',
                'namespace',
                Completion::TYPE_ARGUMENT,
                $application->getNamespaces()
            )
        );
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
     * Do the actual completion, returning an array of strings to provide to the parent shell's completion system
     *
     * @throws \RuntimeException
     * @return string[]
     */
    public function runCompletion()
    {
        if (!$this->context) {
            throw new \RuntimeException('A CompletionContext must be set before requesting completion.');
        }

        // Set the command to query options and arugments from
        $this->command = $this->detectCommand();

        $process = array(
            'completeForOptionValues',
            'completeForOptionShortcuts',
            'completeForOptionShortcutValues',
            'completeForOptions',
            'completeForCommandName',
            'completeForCommandArguments'
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
     *
     * @deprecated Incorrectly uses the ArrayInput API and is no longer needed.
     *             This will be removed in the next major version.
     *
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
     * Attempt to complete the current word as a long-form option (--my-option)
     *
     * @return array|false
     */
    protected function completeForOptions()
    {
        $word = $this->context->getCurrentWord();

        if (substr($word, 0, 2) === '--') {
            $options = array();

            foreach ($this->getAllOptions() as $opt) {
                $options[] = '--'.$opt->getName();
            }

            return $options;
        }

        return false;
    }

    /**
     * Attempt to complete the current word as an option shortcut.
     *
     * If the shortcut exists it will be completed, but a list of possible shortcuts is never returned for completion.
     *
     * @return array|false
     */
    protected function completeForOptionShortcuts()
    {
        $word = $this->context->getCurrentWord();

        if (strpos($word, '-') === 0 && strlen($word) == 2) {
            $definition = $this->command ? $this->command->getNativeDefinition() : $this->application->getDefinition();

            if ($definition->hasShortcut(substr($word, 1))) {
                return array($word);
            }
        }

        return false;
    }

    /**
     * Attempt to complete the current word as the value of an option shortcut
     *
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
                $def = $this->command->getNativeDefinition();

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
     * Attemp to complete the current word as the value of a long-form option
     *
     * @return array|false
     */
    protected function completeForOptionValues()
    {
        $wordIndex = $this->context->getWordIndex();

        if ($this->command && $wordIndex > 1) {
            $left = $this->context->getWordAtIndex($wordIndex - 1);

            if (strpos($left, '--') === 0) {
                $name = substr($left, 2);
                $def = $this->command->getNativeDefinition();

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
     * Attempt to complete the current word as a command name
     *
     * @return array|false
     */
    protected function completeForCommandName()
    {
        if (!$this->command || $this->context->getWordIndex() == $this->commandWordIndex) {
            return $this->getCommandNames();
        }

        return false;
    }

    /**
     * Attempt to complete the current word as a command argument value
     *
     * @see Symfony\Component\Console\Input\InputArgument
     * @return array|false
     */
    protected function completeForCommandArguments()
    {
        if (!$this->command || strpos($this->context->getCurrentWord(), '-') === 0) {
            return false;
        }

        $definition = $this->command->getNativeDefinition();
        $argWords = $this->mapArgumentsToWords($definition->getArguments());
        $wordIndex = $this->context->getWordIndex();

        if (isset($argWords[$wordIndex])) {
            $name = $argWords[$wordIndex];
        } elseif (!empty($argWords) && $definition->getArgument(end($argWords))->isArray()) {
            $name = end($argWords);
        } else {
            return false;
        }

        if ($helper = $this->getCompletionHelper($name, Completion::TYPE_ARGUMENT)) {
            return $this->withNativeFallback($helper->run(), $name, Completion::TYPE_ARGUMENT);
        }

        if ($this->command instanceof CompletionAwareInterface) {
            return $this->withNativeFallback(
                $this->command->completeArgumentValues($name, $this->context),
                $name,
                Completion::TYPE_ARGUMENT
            );
        }

        return $this->completeUsingNativeApi($name, Completion::TYPE_ARGUMENT);
    }

    /**
     * Find a CompletionInterface that matches the current command, target name, and target type
     *
     * @param string $name
     * @param string $type
     * @return CompletionInterface|null
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
     * Complete the value for the given option if a value completion is availble
     *
     * @param InputOption $option
     * @return array|false
     */
    protected function completeOption(InputOption $option)
    {
        $name = $option->getName();

        if ($helper = $this->getCompletionHelper($name, Completion::TYPE_OPTION)) {
            return $this->withNativeFallback($helper->run(), $name, Completion::TYPE_OPTION);
        }

        if ($this->command instanceof CompletionAwareInterface) {
            return $this->withNativeFallback(
                $this->command->completeOptionValues($name, $this->context),
                $name,
                Completion::TYPE_OPTION
            );
        }

        return $this->completeUsingNativeApi($name, Completion::TYPE_OPTION);
    }

    /**
     * Use symfony/console's native completion as a fallback when this library's completion produced nothing
     *
     * The result of this library's completion always wins, so that existing completion setups keep behaving
     * exactly as they did. When it produced no values, the original result is still returned if the native
     * API has nothing to offer either, to leave CompletionHandler::runCompletion's flow control untouched.
     *
     * @param array|false|null $result - result from a CompletionInterface or CompletionAwareInterface
     * @param string $name - name of the option or argument being completed
     * @param string $type - one of the Completion::TYPE_* constants
     * @return array|false
     */
    protected function withNativeFallback($result, $name, $type)
    {
        if (!empty($result)) {
            return $result;
        }

        $native = $this->completeUsingNativeApi($name, $type);

        return $native === false ? $result : $native;
    }

    /**
     * Complete an option or argument value using symfony/console's own completion API
     *
     * This picks up values declared through the $suggestedValues parameter of Command::addArgument() and
     * Command::addOption(), as well as commands that implement Symfony's Command::complete() method.
     *
     * @see \Symfony\Component\Console\Command\Command::complete()
     * @see \Symfony\Component\Console\Input\InputArgument::complete()
     * @see \Symfony\Component\Console\Input\InputOption::complete()
     *
     * @param string $name - name of the option or argument being completed
     * @param string $type - one of the Completion::TYPE_* constants
     * @return string[]|false - false when the native API offered no suggestions
     */
    protected function completeUsingNativeApi($name, $type)
    {
        if (!$this->command) {
            return false;
        }

        $input = $this->createCompletionInput();

        if (!$input) {
            return false;
        }

        $suggestions = new CompletionSuggestions();
        $definition = $this->command->getDefinition();

        $targetMatches = $type === Completion::TYPE_OPTION
            ? $input->mustSuggestOptionValuesFor($name)
            : $input->mustSuggestArgumentValuesFor($name);

        try {
            if ($targetMatches) {
                // Symfony's reading of the command line agrees with ours, so let the command resolve the
                // completion itself. This also covers commands that override Command::complete().
                $this->command->complete($input, $suggestions);
            } elseif ($type === Completion::TYPE_OPTION && $definition->hasOption($name)) {
                // Fall back to asking the option directly, so declared values still work when Symfony's
                // parsing of the command line differs from this library's.
                $definition->getOption($name)->complete($input, $suggestions);
            } elseif ($type === Completion::TYPE_ARGUMENT && $definition->hasArgument($name)) {
                $definition->getArgument($name)->complete($input, $suggestions);
            }
        } catch (\Exception $e) {
            // Never let a broken or unexpected completion definition break the user's shell
            return false;
        }

        $values = array();

        foreach ($suggestions->getValueSuggestions() as $suggestion) {
            // Suggestion descriptions are dropped as this library only emits plain values
            $values[] = (string) $suggestion;
        }

        return $values ? $values : false;
    }

    /**
     * Build a symfony/console CompletionInput bound to the detected command's definition
     *
     * @return CompletionInput|null - null if the context can't be represented as a CompletionInput
     */
    protected function createCompletionInput()
    {
        $tokens = $this->context->getWords();
        $currentIndex = $this->context->getWordIndex();

        // CompletionInput can't deal with an empty token under the cursor: it expects the cursor to be
        // "free" instead, which is signalled by the index being one past the end of the token list.
        if ('' === $this->context->getCurrentWord()) {
            $tokens = array_slice($tokens, 0, $currentIndex);
            $currentIndex = count($tokens);
        }

        // A command has been detected, so there is always at least a program name and a command name.
        // Bail out rather than tripping over CompletionInput's assumptions if that isn't the case.
        if ($currentIndex < 1 || count($tokens) < 1) {
            return null;
        }

        try {
            $input = CompletionInput::fromTokens(array_values($tokens), $currentIndex);

            // Application options and the command name argument need to be part of the definition for the
            // token list to line up with it, as the tokens include both.
            $this->command->mergeApplicationDefinition();
            $input->bind($this->command->getDefinition());
        } catch (\Exception $e) {
            return null;
        }

        return $input;
    }

    /**
     * Step through the command line to determine which word positions represent which argument values
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
        $optionsWithArgs = $this->getOptionWordsWithValues();

        foreach ($this->context->getWords() as $wordIndex => $word) {
            // Skip program name, command name, options, and option values
            if ($wordIndex == 0
                || $wordIndex === $this->commandWordIndex
                || ($word && '-' === $word[0])
                || in_array($previousWord, $optionsWithArgs)) {
                $previousWord = $word;
                continue;
            } else {
                $previousWord = $word;
            }

            // If argument n exists, pair that argument's name with the current word
            if (isset($argumentNames[$argumentNumber])) {
                $argumentPositions[$wordIndex] = $argumentNames[$argumentNumber];
            }

            $argumentNumber++;
        }

        return $argumentPositions;
    }

    /**
     * Build a list of option words/flags that will have a value after them
     * Options are returned in the format they appear as on the command line.
     *
     * @return string[] - eg. ['--myoption', '-m', ... ]
     */
    protected function getOptionWordsWithValues()
    {
        $strings = array();

        foreach ($this->getAllOptions() as $option) {
            if ($option->isValueRequired()) {
                $strings[] = '--' . $option->getName();

                if ($option->getShortcut()) {
                    $strings[] = '-' . $option->getShortcut();
                }
            }
        }

        return $strings;
    }

    /**
     * Filter out results that don't match the current word on the command line
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
     * Get the combined options of the application and entered command
     *
     * @return InputOption[]
     */
    protected function getAllOptions()
    {
        if (!$this->command) {
            return $this->application->getDefinition()->getOptions();
        }

        return array_merge(
            $this->command->getNativeDefinition()->getOptions(),
            $this->application->getDefinition()->getOptions()
        );
    }

    /**
     * Get command names available for completion
     *
     * Filters out hidden commands where supported.
     *
     * @return string[]
     */
    protected function getCommandNames()
    {
        $commands = array();

        foreach ($this->application->all() as $name => $command) {
            if (!$command->isHidden()) {
                $commands[] = $name;
            }
        }

        return $commands;
    }

    /**
     * Find the current command name in the command-line
     *
     * Note this only cares about flag-type options. Options with values cannot
     * appear before a command name in Symfony Console application.
     *
     * @return Command|null
     */
    private function detectCommand()
    {
        // Always skip the first word (program name)
        $skipNext = true;

        foreach ($this->context->getWords() as $index => $word) {

            // Skip word if flagged
            if ($skipNext) {
                $skipNext = false;
                continue;
            }

            // Skip empty words and words that look like options
            if (strlen($word) == 0 || $word[0] === '-') {
                continue;
            }

            // Return the first unambiguous match to argument-like words
            try {
                $cmd = $this->application->find($word);
                $this->commandWordIndex = $index;
                return $cmd;
            } catch (\InvalidArgumentException $e) {
                // Exception thrown, when multiple or no commands are found.
            }
        }

        // No command found
        return null;
    }
}
