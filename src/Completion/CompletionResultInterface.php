<?php
/**
 * @copyright Visma Digital Commerce AS 2019
 * @license Proprietary
 * @author Marcus Pettersen Irgens <marcus.irgens@visma.com>
 */

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
