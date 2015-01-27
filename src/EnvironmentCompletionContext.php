<?php


namespace Stecman\Component\Symfony\Console\BashCompletion;

class EnvironmentCompletionContext extends CompletionContext
{
    /**
     * Set up completion context from the environment variables set by the parent shell
     */
    public function __construct()
    {
        $this->commandLine = getenv('COMP_LINE');
        $this->charIndex = intval(getenv('COMP_POINT'));

        if ($this->commandLine === false) {
            throw new \RuntimeException('Failed to configure from environment; Environment var COMP_LINE not set.');
        }
    }

    /**
     * Use the word break characters set by the parent shell.
     *
     * @throws \RuntimeException
     */
    public function useWordBreaksFromEnvironment()
    {
        $breaks = getenv('COMP_WORDBREAKS');

        if (!$breaks) {
            throw new \RuntimeException('Failed to read word breaks from environment; Environment var COMP_WORDBREAKS not set');
        }

        $this->wordBreaks = $breaks;
    }
}
