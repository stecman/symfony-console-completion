<?php

namespace Stecman\Component\Symfony\Console\BashCompletion\Tests;

use Stecman\Component\Symfony\Console\BashCompletion\CompletionContext;
use Stecman\Component\Symfony\Console\BashCompletion\CompletionHandler;
use Symfony\Component\Console\Application;

class CompletionHandlerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Application
     */
    protected $application;

    public static function setUpBeforeClass()
    {
        require_once __DIR__ . '/Fixtures/CompletionAwareCommand.php';
        require_once __DIR__ . '/Fixtures/TestBasicCommand.php';
        require_once __DIR__ . '/Fixtures/TestSymfonyStyleCommand.php';
    }

    protected function setUp()
    {
        $this->application = new Application('Base application');
        $this->application->addCommands(array(
            new \CompletionAwareCommand(),
            new \TestBasicCommand(),
            new \TestSymfonyStyleCommand()
        ));
    }

    public function testCompleteAppName()
    {
        $handler = $this->createHandler('app');

        // It's not valid to complete the application name, so this should return nothing
        $this->assertEmpty($handler->runCompletion());
    }

    public function testCompleteCommandNames()
    {
        $handler = $this->createHandler('app ');
        $this->assertEquals(
            array('help', 'list', 'completion-aware', 'wave', 'walk:north'),
            $this->getTerms($handler->runCompletion())
        );
    }

    public function testCompleteCommandNameNonMatch()
    {
        $handler = $this->createHandler('app br');
        $this->assertEmpty($handler->runCompletion());
    }

    public function testCompleteCommandNamePartialTwoMatches()
    {
        $handler = $this->createHandler('app wa');
        $this->assertEquals(array('wave', 'walk:north'), $this->getTerms($handler->runCompletion()));
    }

    public function testCompleteCommandNamePartialOneMatch()
    {
        $handler = $this->createHandler('app wav');
        $this->assertEquals(array('wave'), $this->getTerms($handler->runCompletion()));
    }

    public function testCompleteCommandNameFull()
    {
        $handler = $this->createHandler('app wave');

        // Completing on a matching word should return that word so that completion can continue
        $this->assertEquals(array('wave'), $this->getTerms($handler->runCompletion()));
    }

    public function testCompleteSingleDash()
    {
        $handler = $this->createHandler('app wave -');

        // Short options are not given as suggestions
        $this->assertEmpty($handler->runCompletion());
    }

    public function testCompleteOptionShortcut()
    {
        $handler = $this->createHandler('app wave -j');

        // If a valid option shortcut is completed on, the shortcut is returned so that completion can continue
        $this->assertEquals(array('-j'), $this->getTerms($handler->runCompletion()));
    }

    public function testCompleteDoubleDash()
    {
        $handler = $this->createHandler('app wave --');
        $this->assertArraySubset(array('--vigorous', '--jazz-hands'), $this->getTerms($handler->runCompletion()));
    }

    public function testCompleteOptionFull()
    {
        $handler = $this->createHandler('app wave --jazz');
        $this->assertArraySubset(array('--jazz-hands'), $this->getTerms($handler->runCompletion()));
    }

    public function testCompleteOptionOrder()
    {
        // Completion of options should be able to happen anywhere after the command name
        $handler = $this->createHandler('app wave bruce --vi');
        $this->assertEquals(array('--vigorous'), $this->getTerms($handler->runCompletion()));

        // Completing an option mid-commandline should work as normal
        $handler = $this->createHandler('app wave --vi --jazz-hands bruce', 13);
        $this->assertEquals(array('--vigorous'), $this->getTerms($handler->runCompletion()));
    }

    public function testCompleteColonCommand()
    {
        // Normal bash behaviour is to count the colon character as a word break
        // Since a colon is used to namespace Symfony Framework console commands the
        // character in a command name should not be taken as a word break
        //
        // @see https://github.com/stecman/symfony-console-completion/pull/1
        $handler = $this->createHandler('app walk');
        $this->assertEquals(array('walk:north'), $this->getTerms($handler->runCompletion()));

        $handler = $this->createHandler('app walk:north');
        $this->assertEquals(array('walk:north'), $this->getTerms($handler->runCompletion()));

        $handler = $this->createHandler('app walk:north --deploy');
        $this->assertEquals(array('--deploy:jazz-hands'), $this->getTerms($handler->runCompletion()));
    }

    /**
     * @dataProvider completionAwareCommandDataProvider
     */
    public function testCompletionAwareCommand($commandLine, array $suggestions)
    {
        $handler = $this->createHandler($commandLine);
        $this->assertSame($suggestions, $this->getTerms($handler->runCompletion()));
    }

    public function completionAwareCommandDataProvider()
    {
        return array(
            'not complete aware command' => array('app wave --vigorous ', array()),
            'argument suggestions' => array('app completion-aware any-arg ', array('one-arg', 'two-arg')),
            'argument no suggestions' => array('app completion-aware ', array()),
            'argument suggestions + context' => array('app completion-aware any-arg one', array('one-arg', 'one-arg-context')),
            'option suggestions' => array('app completion-aware --option-with-suggestions ', array('one-opt', 'two-opt')),
            'option no suggestions' => array('app completion-aware --option-without-suggestions ', array()),
            'option suggestions + context' => array(
                'app completion-aware --option-with-suggestions one', array('one-opt', 'one-opt-context')
            ),
        );
    }

    /**
     * Create a handler set up with the given commandline and cursor position
     *
     * @param $commandLine
     * @param int $cursorIndex
     * @return CompletionHandler
     */
    protected function createHandler($commandLine, $cursorIndex = null)
    {
        $context = new CompletionContext();
        $context->setCommandLine($commandLine);
        $context->setCharIndex($cursorIndex === null ? strlen($commandLine) : $cursorIndex);

        return new CompletionHandler($this->application, $context);
    }

    /**
     * Convert the string output of CompletionHandler to an array of completion suggestions
     *
     * @param string $handlerOutput
     * @return string[]
     */
    protected function getTerms($handlerOutput)
    {
        if (null === $handlerOutput) {
            return array();
        }

        return explode("\n", $handlerOutput);
    }
}
