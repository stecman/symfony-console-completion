<?php

namespace Stecman\Component\Symfony\Console\BashCompletion\Completion;

use Stecman\Component\Symfony\Console\BashCompletion\Completion;

/**
 * A completion that allows zsh-compatible descriptions
 */
class DescriptiveCompletion extends Completion
{
    /**
     * Return the stored completion, or the results returned from the completion callback
     *
     * @return \Stecman\Component\Symfony\Console\BashCompletion\Completion\CompletionResultInterface
     */
    public function run()
    {
        if ($this->isCallable()) {
            return new CompletionResult(call_user_func($this->completion), true);
        }

        return new CompletionResult($this->completion, true);
    }
}
