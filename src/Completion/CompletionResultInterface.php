<?php

namespace Stecman\Component\Symfony\Console\BashCompletion\Completion;

interface CompletionResultInterface
{
    /**
     * @return bool
     */
    public function isDescriptive();

    /**
     * @return string[]
     */
    public function getValues();
}
