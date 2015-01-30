<?php


namespace Stecman\Component\Symfony\Console\BashCompletion;

use Stecman\Component\Symfony\Console\BashCompletion\Completion\CompletionInterface;

class Completion implements CompletionInterface
{
    /**
     * The option/argument name this helper should be run for
     * @var string
     */
    protected $type;

    /**
     * The command name the helper applies to.
     * Helper will apply to all commands if this is not set
     * @var string
     */
    protected $commandName;

    /**
     * The option/argument name the helper should be run for
     * @var string
     */
    protected $targetName;

    /**
     * Array of completion results or a callback to generate completion results
     * The callback can be in any form accepted by call_user_func
     * @var callable|array
     */
    protected $completion;

    public static function makeGlobalHandler($targetName, $type, $completion)
    {
        return new Completion(CompletionInterface::ALL_COMMANDS, $targetName, $type, $completion);
    }

    public function __construct($commandName, $targetName, $type, $completion)
    {
        $this->commandName = $commandName;
        $this->targetName = $targetName;
        $this->type = $type;
        $this->completion = $completion;
    }

    /**
     * Return the result of the completion helper
     * @return array
     */
    public function run()
    {
        if ($this->isCallable()) {
            return call_user_func($this->completion);
        }

        return $this->completion;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getCommandName()
    {
        return $this->commandName;
    }

    /**
     * @param string $commandName
     */
    public function setCommandName($commandName)
    {
        $this->commandName = $commandName;
    }

    /**
     * @return string
     */
    public function getTargetName()
    {
        return $this->targetName;
    }

    /**
     * @param string $targetName
     */
    public function setTargetName($targetName)
    {
        $this->targetName = $targetName;
    }

    /**
     * @return mixed
     */
    public function getCompletion()
    {
        return $this->completion;
    }

    /**
     * @param mixed $completion
     */
    public function setCompletion($completion)
    {
        $this->completion = $completion;
    }

    public function isCallable()
    {
        return is_callable($this->completion);
    }
}
