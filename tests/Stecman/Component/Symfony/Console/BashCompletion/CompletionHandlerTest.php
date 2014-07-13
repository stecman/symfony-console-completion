<?php

use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionHandler;

class CompletionHandlerTest extends PHPUnit_Framework_TestCase {

    public function testGenerateBashCompletionHook()
    {
        $handler = $this->getCompletionHandler();

        $noArg = $handler->generateBashCompletionHook();
        $this->assertNotEmpty($noArg);

        $withArg = $handler->generateBashCompletionHook('program');
        $this->assertNotEmpty($withArg);
    }

    protected function getCompletionHandler()
    {
        $app = new \Symfony\Component\Console\Application();
        $context = new CompletionContext();
        return new CompletionHandler($app, $context);
    }

}
