<?php

namespace Stecman\Component\Symfony\Console\BashCompletion\Tests;

require_once __DIR__ . '/Common/CompletionHandlerTestCase.php';

use Stecman\Component\Symfony\Console\BashCompletion\Completion;
use Stecman\Component\Symfony\Console\BashCompletion\Tests\Common\CompletionHandlerTestCase;

/**
 * Completion of values declared through symfony/console's own completion API
 *
 * @see \Symfony\Component\Console\Command\Command::addArgument()
 * @see \Symfony\Component\Console\Command\Command::addOption()
 */
class SuggestedValuesTest extends CompletionHandlerTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        require_once __DIR__ . '/Fixtures/SuggestedValuesCommand.php';
        require_once __DIR__ . '/Fixtures/NativeCompleteCommand.php';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->application->addCommands(array(
            new \SuggestedValuesCommand(),
            new \NativeCompleteCommand(),
        ));
    }

    /**
     * @dataProvider suggestedValuesDataProvider
     */
    public function testSuggestedValues($commandLine, array $suggestions)
    {
        $handler = $this->createHandler($commandLine);
        $this->assertSame($suggestions, $this->getTerms($handler->runCompletion()));
    }

    public static function suggestedValuesDataProvider(): array
    {
        return array(
            'option values' => array(
                'app suggested-values --colour ', array('red', 'green', 'blue')
            ),
            'option values partially typed' => array(
                'app suggested-values --colour g', array('green')
            ),
            'option values after equals sign' => array(
                'app suggested-values --colour=', array('red', 'green', 'blue')
            ),
            'option values after equals sign partially typed' => array(
                'app suggested-values --colour=b', array('blue')
            ),
            'option values by shortcut' => array(
                'app suggested-values -c ', array('red', 'green', 'blue')
            ),
            'option without suggested values' => array(
                'app suggested-values --plain ', array()
            ),
            'suggestion objects are reduced to their value' => array(
                'app suggested-values --described ', array('with-description')
            ),
            'argument values' => array(
                'app suggested-values ', array('cat', 'cow', 'dog')
            ),
            'argument values partially typed' => array(
                'app suggested-values co', array('cow')
            ),
            'argument values with an option in front' => array(
                'app suggested-values --colour red ', array('cat', 'cow', 'dog')
            ),
            'array argument values' => array(
                'app suggested-values cat ', array('moo', 'woof')
            ),
            'array argument repeats' => array(
                'app suggested-values cat moo ', array('moo', 'woof')
            ),
            'closure receives the completion input' => array(
                'app suggested-values cat me', array('meow', 'mew')
            ),
        );
    }

    /**
     * Values declared through symfony/console must not take precedence over this library's completion
     *
     * @dataProvider apiPrecedenceDataProvider
     */
    public function testApiPrecedence($commandLine, array $suggestions)
    {
        $handler = $this->createHandler($commandLine);
        $this->assertSame($suggestions, $this->getTerms($handler->runCompletion()));
    }

    public static function apiPrecedenceDataProvider(): array
    {
        return array(
            'CompletionAwareInterface wins for options' => array(
                'app native-complete --legacy-option ', array('legacy-opt')
            ),
            'CompletionAwareInterface wins for arguments' => array(
                'app native-complete ', array('legacy-arg')
            ),
            'Command::complete() fills in for options' => array(
                'app native-complete --native-option ', array('native-opt-one', 'native-opt-two')
            ),
            'Command::complete() fills in for arguments' => array(
                'app native-complete legacy ', array('native-arg-one', 'native-arg-two')
            ),
        );
    }

    /**
     * A Completion handler registered with this library takes precedence over suggested values
     */
    public function testRegisteredCompletionWins()
    {
        $handler = $this->createHandler('app suggested-values --colour ');
        $handler->addHandler(
            new Completion(
                'suggested-values',
                'colour',
                Completion::TYPE_OPTION,
                array('puce')
            )
        );

        $this->assertSame(array('puce'), $this->getTerms($handler->runCompletion()));
    }

    /**
     * An empty result from a registered handler still falls back to suggested values
     */
    public function testRegisteredCompletionFallsBackWhenEmpty()
    {
        $handler = $this->createHandler('app suggested-values --colour ');
        $handler->addHandler(
            new Completion(
                'suggested-values',
                'colour',
                Completion::TYPE_OPTION,
                array()
            )
        );

        $this->assertSame(array('red', 'green', 'blue'), $this->getTerms($handler->runCompletion()));
    }
}
