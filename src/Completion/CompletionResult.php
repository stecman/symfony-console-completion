<?php

namespace Stecman\Component\Symfony\Console\BashCompletion\Completion;

class CompletionResult implements CompletionResultInterface
{
    /**
     * @var string[]
     */
    private $values;

    /**
     * @var bool
     */
    private $descriptive;

    public function __construct(
        $values,
        $descriptive = false
    ) {
        $this->values = $values;
        $this->descriptive = $descriptive;
    }

    /**
     * @return bool
     */
    public function isDescriptive()
    {
        return $this->descriptive;
    }

    /**
     * @return string[]
     */
    public function getValues()
    {
        return $this->values;
    }
}
