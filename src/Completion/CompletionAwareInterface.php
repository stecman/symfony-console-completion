<?php

namespace Stecman\Component\Symfony\Console\BashCompletion\Completion;

use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;

interface CompletionAwareInterface
{

    /**
     * Returns possible option values.
     *
     * @param string            $optionName Option name.
     * @param CompletionContext $context    Completion context.
     *
     * @return array
     */
    public function completeOptionValues($optionName, CompletionContext $context);

    /**
     * Returns possible argument values.
     *
     * @param string            $argumentName Argument name.
     * @param CompletionContext $context      Completion context.
     *
     * @return array
     */
    public function completeArgumentValues($argumentName, CompletionContext $context);
}
