<?php

namespace Stecman\Component\Symfony\Console\BashCompletion;

use Symfony\Component\Console\Application as BaseApplication;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class CompletionHandler {

    /**
     * COMP_WORDS
     * An array consisting of the individual words in the current command line.
     * @var array|string
     */
    protected $words;

    /**
     * COMP_CWORD
     * The index in COMP_WORDS of the word containing the current cursor position.
     * @var string
     */
    protected $wordIndex;

    /**
     * COMP_LINE
     * The current contents of the command line.
     * @var string
     */
    protected $commandLine;

    /**
     * COMP_POINT
     * The index of the current cursor position relative to the beginning of the
     * current command. If the current cursor position is at the end of the current
     * command, the value of this variable is equal to the length of COMP_LINE.
     * @var string
     */
    protected $charIndex;

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
     * Array of completion helpers.
     * @var Completion[]
     */
    protected $helpers = array();

    public function __construct(BaseApplication $application)
    {
        $this->application = $application;
    }

    /**
     * Set completion context from the environment variables set by BASH completion
     */
    public function configureFromEnvironment()
    {
        $this->commandLine = getenv('COMP_LINE');

        if ($this->commandLine === false) {
            throw new \RuntimeException('Failed to configure from environment; Environment var COMP_LINE not set');
        }

        $this->wordIndex = intval(getenv('COMP_CWORD'));
        $this->index = intval(getenv('COMP_POINT'));

        $breaks = preg_quote(getenv('COMP_WORDBREAKS'));
        $this->words = array_filter(
            preg_split( "/[$breaks]+/", $this->commandLine),
            function($val){
                return $val != ' ';
            }
        );
    }

    /**
     * Set completion context with an array
     * @param $array
     */
    public function configureWithArray($array)
    {
        $this->wordIndex = $array['wordIndex'];
        $this->commandLine = $array['commandLine'];
        $this->charIndex = $array['charIndex'];
        $this->words = $array['words'];
    }

    /**
     * Do the actual completion, returning items delimited by spaces
     * @return string
     */
    public function runCompletion()
    {
        $cmdName = $this->getInput()->getFirstArgument();
        if($this->application->has($cmdName)){
            $this->command = $this->application->get($cmdName);
        }

        $process = array(
            'completeForOptionShortcuts',
            'completeForOptionShortcutValues',
            'completeForOptions',
            'completeForCommandName',
            'completeForCommandArgs'
        );

        foreach ($process as $methodName) {
            if ($result = $this->{$methodName}()) {
                return $this->filterResults($result);
            }
        }
    }

    /**
     * @return array
     */
    protected function completeForOptions()
    {
        if ($this->command && strpos($this->words[$this->wordIndex], '-') === 0) {
            return $this->formatOptions($this->command);
        }
    }

    /**
     * Complete an option shortcut if it exists, but don't offer a list of shortcuts
     * @return array
     */
    protected function completeForOptionShortcuts()
    {
        $word = $this->words[$this->wordIndex];
        if ($this->command && strpos($word, '-') === 0 && strlen($word) == 2) {
            foreach ($this->command->getDefinition()->getOptions() as $opt) {

                $shortcut = '-'.$opt->getShortcut();
                if ($shortcut == $this->words[$this->wordIndex]) {
                    return array($shortcut);
                }
            }
        }
    }

    /**
     * @return mixed
     */
    protected function completeForOptionShortcutValues()
    {
        if ($this->command && $this->wordIndex > 1) {
            $left = $this->words[$this->wordIndex-1];

            // Complete short options
            if ($left[0] == '-') {

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
     * If a command is not set, list available commands
     * @return array
     */
    protected function completeForCommandName()
    {
        if (!$this->command) {
            $commands = $this->application->all();
            $names = array();

            foreach ($commands as $cmd) {
                $name = $cmd->getName();
                if ($name != '_completion') {
                    $names[] = $name;
                }
            }

            return $names;
        }
    }

    /**
     * @return bool|mixed
     */
    protected function completeForCommandArgs()
    {
        if (strpos($this->words[$this->wordIndex], '-') !== 0) {
            if ($this->command) {
                return $this->formatArguments($this->command);
            }
        }
    }

    /**
     * @return ArrayInput
     */
    public function getInput()
    {
        // Filter the command line content to suit ArrayInput
        $words = $this->words;
        array_shift($words);
        $words = array_filter($words);

        return new ArrayInput($words);
    }

    /**
     * @param BaseCommand $cmd
     * @return array
     */
    protected function formatOptions(BaseCommand $cmd)
    {
        $options = array();
        foreach ($cmd->getDefinition()->getOptions() as $opt) {
            $string = '--'.$opt->getName();

            if ($opt->isValueRequired()) {
                $options[] = $string.'=';
            } else if ($opt->isValueOptional()) {
                $options[] = $string;
                $options[] = $string.'=';
            } else {
                $options[] = $string;
            }

        }
        return $options;
    }

    /**
     * @param BaseCommand $cmd
     * @return bool|mixed
     */
    protected function formatArguments(BaseCommand $cmd)
    {
        $argWords = $this->mapArgumentsToWords($cmd->getDefinition()->getArguments());

        foreach ($argWords as $name => $wordNum) {
            if ($this->wordIndex == $wordNum) {
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
        $argPositions = array();

        $words = $this->words;
        array_shift($words);

        $argsArray = array_keys($arguments);

        foreach ($this->words as $word) {
            $wordNum++;

            // Skip program name, command name and options
            if ($wordNum < 2 || ($word && '-' === $word[0])) {
                continue;
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
        return implode(' ',
            array_filter($array, function($val){
                return fnmatch($this->words[$this->wordIndex].'*', $val);
            })
        );
    }

    /**
     * Return the BASH script necessary to use bash completion with this addHandler
     * @param string $programName
     * @return string
     */
    public function generateBashCompletionHook($programName)
    {
        global $argv;
        $command = $argv[0];

        return <<<"END"
function _beamcomplete {
    export COMP_CWORD COMP_KEY COMP_LINE COMP_POINT COMP_WORDBREAKS;
    COMPREPLY=(`compgen -W "$($command _completion)"`);
};
complete -F _beamcomplete $programName;
END;
    }

    /**
     * @return array|string
     */
    public function getWords()
    {
        return $this->words;
    }

    /**
     * @param array|string $words
     */
    public function setWords($words)
    {
        $this->words = $words;
    }

    /**
     * @param string $wordIndex
     */
    public function setWordIndex($wordIndex)
    {
        $this->wordIndex = $wordIndex;
    }

    /**
     * @return string
     */
    public function getCommandLine()
    {
        return $this->commandLine;
    }

    /**
     * @param string $commandLine
     */
    public function setCommandLine($commandLine)
    {
        $this->commandLine = $commandLine;
    }

    /**
     * @param string $charIndex
     */
    public function setCharIndex($charIndex)
    {
        $this->charIndex = $charIndex;
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