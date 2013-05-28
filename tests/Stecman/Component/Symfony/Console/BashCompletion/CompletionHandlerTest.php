<?php

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
        return new \Stecman\Component\Symfony\Console\BashCompletion\CompletionHandler($app);
    }

}
