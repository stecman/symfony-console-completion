<?php


namespace Stecman\Component\Symfony\Console\BashCompletion;

/**
 * Command line context for completion
 *
 * Represents the current state of the command line that is being completed
 */
class CompletionContext
{
    /**
     * COMP_WORDS
     * An array consisting of the individual words in the current command line.
     * @var array|null
     */
    protected $words = null;

    /**
     * COMP_CWORD
     * The index in COMP_WORDS of the word containing the current cursor position.
     * @var int
     */
    protected $wordIndex = null;

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
     * @var int
     */
    protected $charIndex = 0;

    /**
     * COMP_WORDBREAKS
     * Characters that $commandLine should be split on to get a list of words in a command
     * @var string
     */
    protected $wordBreaks = "'\"()= \t\n";

    /**
     * @param string $commandLine
     */
    public function setCommandLine($commandLine)
    {
        $this->commandLine = $commandLine;
        $this->reset();
    }

    /**
     * @return string
     */
    public function getCommandLine()
    {
        return $this->commandLine;
    }

    public function getCurrentWord()
    {
        if (isset($this->words[$this->wordIndex])) {
            return $this->words[$this->wordIndex];
        }

        return '';
    }

    public function getWordAtIndex($index)
    {
        if (isset($this->words[$index])) {
            return $this->words[$index];
        }

        return '';
    }

    /**
     * @return array
     */
    public function getWords()
    {
        if ($this->words === null) {
            $this->splitCommand();
        }

        return $this->words;
    }

    /**
     * @return int
     */
    public function getWordIndex()
    {
        if ($this->wordIndex === null) {
            $this->splitCommand();
        }

        return $this->wordIndex;
    }

    /**
     * @return int
     */
    public function getCharIndex()
    {
        return $this->charIndex;
    }

    /**
     * @param $index
     */
    public function setCharIndex($index)
    {
        $this->charIndex = $index;
        $this->reset();
    }

    /**
     * @param string $charList
     */
    public function setWordBreaks($charList)
    {
        $this->wordBreaks = $charList;
    }

    /**
     * Split commandLine into words using wordBreaks
     * @return array
     */
    protected function splitCommand()
    {
        $this->words = array();
        $this->wordIndex = null;
        $cursor = 1;

        $breaks = preg_quote($this->wordBreaks);

        if (!preg_match_all("/([^$breaks]*)([$breaks]*)/", $this->commandLine, $matches)) {
            return;
        }

        // Groups:
        // 1: Word
        // 2: Break characters
        foreach ($matches[0] as $index => $wholeMatch) {
            // Determine which word the cursor is in
            $cursor += strlen($wholeMatch);
            $word = $matches[1][$index];

            if ($this->wordIndex === null && $cursor >= $this->charIndex) {
                $this->wordIndex = $index;

                // Find the cursor position relative to the end of the word
                $cursorWordOffset = $this->charIndex - ($cursor - strlen($matches[2][$index]) - 1);

                if ($cursorWordOffset < 0) {
                    // Cursor is inside the word - truncate the word at the cursor
                    // (This emulates normal BASH completion behaviour I've observed, though I'm not entirely sure if it's useful)
                    $word = substr($word, 0, strlen($word) + $cursorWordOffset);

                } elseif ($cursorWordOffset > 0) {
                    // Cursor is in the break-space after the word
                    // Push an empty word at the cursor
                    $this->wordIndex++;
                    $this->words[] = $word;
                    $this->words[] = '';
                    continue;
                }
            }

            if ($word !== '') {
                $this->words[] = $word;
            }
        }

        if ($this->wordIndex > count($this->words) - 1) {
            $this->wordIndex = count($this->words) - 1;
        }
    }

    /**
     * Reset the computed words so that $this->splitWords is forced to run again
     */
    protected function reset()
    {
        $this->words = null;
        $this->wordIndex = null;
    }
}
