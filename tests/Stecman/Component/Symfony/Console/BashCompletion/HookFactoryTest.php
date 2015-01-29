<?php

namespace Stecman\Component\Symfony\Console\BashCompletion\Tests;

use Stecman\Component\Symfony\Console\BashCompletion\HookFactory;

class HookFactoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var HookFactory
     */
    protected $factory;

    protected function setUp()
    {
        $this->factory = new HookFactory();
    }

    public function testBashSyntax()
    {
        if ($this->hasProgram('bash')) {
            $script = $this->factory->generateHook('bash', '/path/to/myprogram', 'myprogram');
            $this->assertSyntaxIsValid($script, 'bash -n', 'BASH hook');

        } else {
            $this->markTestSkipped("Couldn't detect BASH program to run hook syntax check");
        }
    }

    public function testZshSyntax()
    {
        if ($this->hasProgram('zsh')) {
            $script = $this->factory->generateHook('zsh', '/path/to/myprogram', 'myprogram');
            $this->assertSyntaxIsValid($script, 'zsh -n', 'ZSH hook');
        } else {
            $this->markTestSkipped("Couldn't detect ZSH program to run hook syntax check");
        }
    }

    protected function hasProgram($programName)
    {
        exec(sprintf(
            'command -v %s',
            escapeshellarg($programName)
        ), $output, $return);

        return $return === 0;
    }

    /**
     * @param string $code - code to pipe to the syntax checking command
     * @param string $syntaxCheckCommand - equivalent to `bash -n`.
     * @param string $context - what the syntax check is for
     */
    protected function assertSyntaxIsValid($code, $syntaxCheckCommand, $context)
    {
        $process = proc_open(
            escapeshellcmd($syntaxCheckCommand),
            array(
                0 => array('pipe', 'r'),
                1 => array('pipe', 'w'),
                2 => array('pipe', 'w')
            ),
            $pipes
        );

        if (is_resource($process)) {
            // Push code into STDIN for the syntax checking process
            fwrite($pipes[0], $code);
            fclose($pipes[0]);

            $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            $status = proc_close($process);

            if ($status !== 0) {
                $this->fail("Syntax check for $context failed:\n$output");
            }
        } else {
            throw new \RuntimeException("Failed to start process with command '$syntaxCheckCommand'");
        }
    }
}
