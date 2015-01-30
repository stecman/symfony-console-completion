# BASH/ZSH auto-complete for Symfony Console applications

[![Build Status](https://travis-ci.org/stecman/symfony-console-completion.svg?branch=master)](https://travis-ci.org/stecman/symfony-console-completion)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/stecman/symfony-console-completion/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/stecman/symfony-console-completion/?branch=master)

[![Latest Stable Version](https://poser.pugx.org/stecman/symfony-console-completion/v/stable.png)](https://packagist.org/packages/stecman/symfony-console-completion)
[![Total Downloads](https://poser.pugx.org/stecman/symfony-console-completion/downloads.png)](https://packagist.org/packages/stecman/symfony-console-completion)
[![Latest Unstable Version](https://poser.pugx.org/stecman/symfony-console-completion/v/unstable.svg)](https://packagist.org/packages/stecman/symfony-console-completion)
[![License](https://poser.pugx.org/stecman/symfony-console-completion/license.svg)](https://packagist.org/packages/stecman/symfony-console-completion)

This package provides automatic (tab) completion in BASH and ZSH for Symfony Console Component based applications. With zero configuration, this package allows completion of available command names and the options they provide. Custom completion behaviour can be added for option and argument values by name.

Example of zero-config use with Composer:

![Composer BASH completion](https://i.imgur.com/MoDWkby.gif)

## Zero-config use

If you don't need any custom completion behaviour, all you need to do is add the completion command to your application's available commands:

1. Install `stecman/symfony-console-completion` through composer
2. Add an instance of `CompletionCommand` to your application's `Application::getDefaultCommands()` method:
  ```php
  protected function getDefaultCommands()
  {
     //...
      $commands[] = new \Stecman\Component\Symfony\Console\BashCompletion\CompletionCommand();
     //...
  }
  ```

3. Register completion for your application by running one of the following in a terminal, replacing `[program]` with the command you use to run your application (eg. 'composer'):

  ```bash
  # BASH 4.x, ZSH
  source <([program] _completion --generate-hook)

  # BASH 3.x, ZSH
  [program] _completion --generate-hook | source /dev/stdin

  # BASH (any version)
  eval $([program] _completion --generate-hook)
  ```

  By default this registers completion for the absolute path to you application. You can specify a command name to complete for instead using the `-p` option, which is useful if you're using an alias to run the program.

4. Add the command from step 3 to your bash profile if you want the completion to apply automatically for all new terminal sessions.

Note: If `[program]` is an alias you will need to specify the aliased name with the `-p|--program` option, as completion may not work otherwise: `_completion --generate-hook -p [myalias]`.

Second note: The type of shell (ZSH/BASH) is automatically detected using the `SHELL` environment variable at run time. In some circumstances, you may need to explicitly specify the shell type with the `--shell-type` option.

### How it works

The `--generate-hook` option of `CompletionCommand` generates a few lines of BASH that, when executed, register your application as a completion handler for your itself in the current shell session. When you request completion for your program (by pressing tab), the completion command on your application is run with no arguments: `[program] _completion`. This uses environment variables set by BASH to get the current command line contents and cursor position, then completes from your console command definitions.


## Custom completion

Custom completion behaviour for argument and option values can be added by sub-classing `CompletionCommand`.

The following examples are for an application with this signature: `myapp (walk|run) [-w|--weather=""] direction`

```php
class MyCompletionCommand extends CompletionCommand {

    protected function runCompletion()
    {
        $this->handler->addHandlers(array(
            // Instances of Completion go here.
            // See below for examples.
        ));
        return $this->handler->runCompletion();
    }
}
```

##### Command-specific argument completion with an array

```php
$this->handler->addHandler(
    new Completion(
        'walk',
        'direction',
        Completion::TYPE_ARGUMENT,
        array(
            'north',
            'east',
            'south',
            'west'
        )
    )
);
```

This will complete for this:
```bash
$ myapp walk [tab]
```

but not this:
```bash
$ myapp run [tab]
```

##### Non-command-specific (global) argument completion with a function

```php
$this->handler->addHandler(
    Completion::makeGlobalHandler(
        'direction',
        Completion::TYPE_ARGUMENT,
        function() {
            $values = array();

            // Fill the array up with stuff
            return $values;
        }
    )
);
```

This will complete for both commands:
```bash
$ myapp walk [tab]
$ myapp run [tab]
```

##### Option completion

Option handlers work the same way as argument handlers, except you use `Completion::TYPE_OPTION` for the type..

```php
$this->handler->addHandler(
    Completion::makeGlobalHandler(
        'weather',
        Completion::TYPE_OPTION,
        array(
            'raining',
            'sunny',
            'everything is on fire!'
        )
    )
);
```

### Example: completing references from a Git repository

```php
Completion::makeGlobalHandler(
    'ref',
    Completion::TYPE_OPTION,
    function () {
        $raw = shell_exec('git show-ref --abbr');
        if (preg_match_all('/refs\/(?:heads|tags)?\/?(.*)/', $raw, $matches)) {
            return $matches[1];
        }
    }
)
```

### Example: completing filesystem paths

This library provides the completion class `ShellPathCompletion` which defers path completion to the shell's built-in path completion behaviour rather than implementing it in PHP.

```php
new Completion\ShellPathCompletion(
    Completion::ALL_COMMANDS,
    'path',
    Completion::TYPE_OPTION
)

```

## Behaviour notes

* Option shortcuts are not offered as completion options, however requesting completion (ie. pressing tab) on a valid option shortcut will complete.
* Completion is not implemented for the `--option="value"` style of passing a value to an option, however `--option value` and `--option "value"` work and are functionally identical.
