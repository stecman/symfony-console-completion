<?php

namespace Stecman\Component\Symfony\Console\BashCompletion;

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
     * @var Completion[]
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
     * Do the actual completion, returning items delimited by spaces
     * @throws \RuntimeException
     * @return string
     */
    public function runCompletion()
    {
        if (!$this->context) {
            throw new \RuntimeException('A CompletionContext must be set before requesting completion.');
        }

        $cmdName = $this->getInput()->getFirstArgument();
        if($this->application->has($cmdName)){
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
            if ($result = $this->{$methodName}()) {

                // Return the result of the first completion method with any suggestions
                return $this->filterResults($result);
            }
        }
    }

    /**
     * @return array
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
    }

    /**
     * Complete an option shortcut if it exists, but don't offer a list of shortcuts
     * @return array
     */
    protected function completeForOptionShortcuts()
    {
        $word = $this->context->getCurrentWord();

        if ($this->command && strpos($word, '-') === 0 && strlen($word) == 2) {
            if ($this->command->getDefinition()->hasShortcut( substr($word, 1) )) {
                return array($word);
            }
        }
    }

    /**
     * @return mixed
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
     * @return mixed
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
     * @return array
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
    }

    /**
     * @return bool|mixed
     */
    protected function completeForCommandArgs()
    {
        if (strpos($this->context->getCurrentWord(), '-') !== 0) {
            if ($this->command) {
                return $this->formatArguments($this->command);
            }
        }
    }

    /**
     * Turn the context's commandline into an input for an application
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
     * @param BaseCommand $cmd
     * @return bool|mixed
     */
    protected function formatArguments(BaseCommand $cmd)
    {
        $argWords = $this->mapArgumentsToWords($cmd->getDefinition()->getArguments());

        foreach ($argWords as $name => $wordNum) {
            if ($this->context->getWordIndex() == $wordNum) {
                if ($helper = $this->getCompletionHelper($name, Completion::TYPE_ARGUMENT)) {
                    return $helper->run();
                }
            }
        }

        return false;
    }

    /**
     * @param $name
     * @param string $type
     * @return Completion
     */
    protected function getCompletionHelper($name, $type)
    {
        foreach ($this->helpers as $helper) {
            if ($helper->getType() != $type){
                continue;
            }

            if ($helper->isGlobal() || $helper->getCommandName() == $this->command->getName()) {
                if ($helper->getTargetName() == $name) {
                    return $helper;
                }
            }
        }
    }

    /**
     * @param InputOption $option
     * @return array|mixed
     */
    protected function completeOption(InputOption $option)
    {
        if ($helper = $this->getCompletionHelper($option->getName(), Completion::TYPE_OPTION)) {
            return $helper->run();
        }
    }

    /**
     * @param $arguments array|InputArgument
     * @return array
     */
    protected function mapArgumentsToWords($arguments)
    {
        $argIndex = 0;
        $wordNum = -1;
        $prevWord = null;
        $argPositions = array();

        $argsArray = array_keys($arguments);

        $optionsWithArgs = array();

        foreach ($this->getAllOptions() as $option) {
            if ($option->isValueRequired() && $option->getShortcut()) {
                $optionsWithArgs[] = '-'.$option->getShortcut();
            }
        }

        foreach ($this->context->getWords() as $word) {
            $wordNum++;

            // Skip program name, command name, options, and option values
            if ($wordNum < 2
                || ($word && '-' === $word[0])
                || in_array($prevWord, $optionsWithArgs))
            {
                $prevWord = $word;
                continue;
            } else {
                $prevWord = $word;
            }

            if (isset($argsArray[$argIndex])) {
                $argPositions[$argsArray[$argIndex]] = $wordNum;
            }
            $argIndex++;
        }

        return $argPositions;
    }

    /**
     * Return an completion result for use as COMPREPLY.
     * Only matches to the currently selected word are returned.
     * @param $array
     * @return string
     */
    protected function filterResults($array)
    {
        $curWord = $this->context->getCurrentWord();

        return implode("\n",
            array_filter($array, function($val) use ($curWord) {
                return fnmatch($curWord.'*', $val);
            })
        );
    }

    protected function getAllOptions(){
        return array_merge(
            $this->command->getDefinition()->getOptions(),
            $this->application->getDefinition()->getOptions()
        );
    }

    /**
     * @param array|Completion $array
     */
    public function addHandlers($array)
    {
        $this->helpers = array_merge($this->helpers, $array);
    }

    public function addHandler(Completion $helper)
    {
        $this->helpers[] = $helper;
    }
}
