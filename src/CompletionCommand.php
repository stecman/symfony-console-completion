<?php

namespace Stecman\Component\Symfony\Console\BashCompletion;

use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CompletionCommand extends SymfonyCommand
{
    /**
     * @var CompletionHandler
     */
    protected $handler;

    protected function configure()
    {
        $this
            ->setName('_completion')
            ->setDefinition($this->createDefinition())
            ->setDescription('BASH completion hook.')
            ->setHelp(<<<END
To enable BASH completion, run:

    <comment>eval `[program] _completion -g`</comment>.

Or for an alias:

    <comment>eval `[program] _completion -g -p [alias]`</comment>.

END
            );

        // Hide this command from listing if supported
        // Command::setHidden() was not available before Symfony 3.2.0
        if (method_exists($this, 'setHidden')) {
            $this->setHidden(true);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getNativeDefinition()
    {
        return $this->createDefinition();
    }

    /**
     * Ignore user-defined global options
     *
     * Any global options defined by user-code are meaningless to this command.
     * Options outside of the core defaults are ignored to avoid name and shortcut conflicts.
     */
    public function mergeApplicationDefinition($mergeArgs = true)
    {
        // Get current application options
        $appDefinition = $this->getApplication()->getDefinition();
        $originalOptions = $appDefinition->getOptions();

        // Temporarily replace application options with a filtered list
        $appDefinition->setOptions(
            $this->filterApplicationOptions($originalOptions)
        );

        parent::mergeApplicationDefinition($mergeArgs);

        // Restore original application options
        $appDefinition->setOptions($originalOptions);
    }

    /**
     * Reduce the passed list of options to the core defaults (if they exist)
     *
     * @param InputOption[] $appOptions
     * @return InputOption[]
     */
    protected function filterApplicationOptions(array $appOptions)
    {
        return array_filter($appOptions, function(InputOption $option) {
            static $coreOptions = array(
                'help' => true,
                'quiet' => true,
                'verbose' => true,
                'version' => true,
                'ansi' => true,
                'no-ansi' => true,
                'no-interaction' => true,
            );

            return isset($coreOptions[$option->getName()]);
        });
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->handler = new CompletionHandler($this->getApplication());
        $handler = $this->handler;

        if ($input->getOption('generate-hook')) {
            global $argv;
            $program = $argv[0];

            $factory = new HookFactory();
            $alias = $input->getOption('program');
            $multiple = (bool)$input->getOption('multiple');

            if (!$alias) {
                $alias = basename($program);
            }

            $hook = $factory->generateHook(
                $input->getOption('shell-type') ?: $this->getShellType(),
                $program,
                $alias,
                $multiple
            );

            $output->write($hook, true);
        } else {
            $handler->setContext(new EnvironmentCompletionContext());

            // Get completion results
            $this->configureCompletion($handler);
            $results = $this->handler->runCompletion();

            // Escape results for the current shell
            $shellType = $input->getOption('shell-type') ?: $this->getShellType();

            return $this->writeForShell($results, $shellType, $output);
        }

        return 0;
    }

    /**
     * Escape each completion result for the specified shell
     *
     * @param Completion\CompletionResultInterface $result - Completion results that should appear in the shell
     * @param string $shellType - Valid shell type from HookFactory
     * @param OutputInterface $output
     * @return int
     */
    protected function writeForShell($result, $shellType, $output)
    {
        $desc = $result->isDescriptive();
        $values = $result->getValues();
        switch ($shellType) {
            // BASH requires special escaping for multi-word and special character results
            // This emulates registering completion with`-o filenames`, without side-effects like dir name slashes
            case 'bash':
                if ($desc) {
                    // BASH does not support autocompletion descriptions, so we just want the actual suggestions
                    $values = array_keys($values);
                }
                foreach ($values as &$value) {
                    $context = $this->handler->getContext();
                    $wordStart = substr($context->getRawCurrentWord(), 0, 1);

                    if ($wordStart == "'") {
                        // If the current word is single-quoted, escape any single quotes in the result
                        $value = str_replace("'", "\\'", $value);
                    } else if ($wordStart == '"') {
                        // If the current word is double-quoted, escape any double quotes in the result
                        $value = str_replace('"', '\\"', $value);
                    } else {
                        // Otherwise assume the string is unquoted and word breaks should be escaped
                        $value = preg_replace('/([\s\'"\\\\])/', '\\\\$1', $value);
                    }

                    // Escape output to prevent special characters being lost when passing results to compgen
                    $value = escapeshellarg($value);
                }
                $output->write($values, true);

                return 0;

            case 'zsh':
                if ($desc) {
                    $out = array();
                    foreach ($values as $cmd => $description) {
                        $out[] = sprintf("'%s:%s'", $cmd, $description);
                    }

                    $output->write(sprintf("(%s)", implode(" ", $out)), true);
                    return 100;
                }

            // No transformation by default
            default:
                if ($desc) {
                    $values = array_keys($values);
                }
                $output->write($values, true);
                return 0;
        }
    }

    /**
     * Configure the CompletionHandler instance before it is run
     *
     * @param CompletionHandler $handler
     * @return void
     */
    protected function configureCompletion(CompletionHandler $handler)
    {
        // Override this method to configure custom value completions
    }

    /**
     * Determine the shell type for use with HookFactory
     *
     * @return string
     */
    protected function getShellType()
    {
        if (!getenv('SHELL')) {
            throw new \RuntimeException('Could not read SHELL environment variable. Please specify your shell type using the --shell-type option.');
        }

        return basename(getenv('SHELL'));
    }

    protected function createDefinition()
    {
        return new InputDefinition(array(
            new InputOption(
                'generate-hook',
                'g',
                InputOption::VALUE_NONE,
                'Generate BASH code that sets up completion for this application.'
            ),
            new InputOption(
                'program',
                'p',
                InputOption::VALUE_REQUIRED,
                "Program name that should trigger completion\n<comment>(defaults to the absolute application path)</comment>."
            ),
            new InputOption(
                'multiple',
                'm',
                InputOption::VALUE_NONE,
                "Generated hook can be used for multiple applications."
            ),
            new InputOption(
                'shell-type',
                null,
                InputOption::VALUE_OPTIONAL,
                'Set the shell type (zsh or bash). Otherwise this is determined automatically.'
            ),
        ));
    }
}
