# Symfony Console completion

This package provides automatic BASH completion for Symfony Console Component based applications. With minimal configuration, this package allows completion of available command names and the options they provide. Custom completion behaviour can be added for option and argument values by name.

## Basic use

If you don't need any custom completion behaviour:

1. Install `stecman/symfony-console-completion` through composer
2. Add an instance of `CompletionCommand` to your application's `Application::getDefaultCommands()`:
```
    protected function getDefaultCommands()
    {
       ...
        $commands[] = new \Stecman\Component\Symfony\Console\BashCompletion\CompletionCommand();
       ...
    }
```

3. Run `eval $([your-application] _completion -g [program-name])` in a terminal, where `[program-name]` is the name you want to register bash completion for (this will be the same as `[your-application]` if your application is on your PATH).
4. Add the above command to your bash profile if you want the completion to apply automatically for new terminal sessions.

The command `_completion -g [program-name]` (`-g` being a shortcut for `--genhook`) generates a few lines of bash that, when run, register your application as a completion handler for `[program-name]`. Completion is handled by running the completion command on your application with no arguments: `[your-application] _completion`.

## Custom completion

Custom completion behaviour for argument and option values can be added by sub-classing `CompletionCommand`.

The following examples are for an application with this signature: `myapp (walk|run) [-w|--weather=""] direction`

    class MyCompletionCommand extends CompletionCommand{

        protected function runCompletion()
        {
            $handler = $this->handler;
            $handler->addHandlers(array(

                ... // See below for what goes in here

            ));

            return $handler->runCompletion();
        }

    }


**Command-specific argument completion with an array:**

    new Completion(
        'walk', 'direction', Completion::TYPE_ARGUMENT,
        array('north', 'east', 'south', 'west')
    )

This will complete for this:

    myapp walk [tab]

but not this:

    myapp run [tab]


**Non-command-specific (global) argument completion with a function**

    Completion::makeGlobalHandler(
        'direction', Completion::TYPE_ARGUMENT,
        function(){
            $values = array();

            // Fill the array up with stuff

            return $values;
        }
    )

This will complete for both commands:

    myapp walk [tab]
    myapp run [tab]


**Option completion**

Option handlers work the same way as argument handlers, except you use `Completion::TYPE_OPTION` for the type.

**Note this functionality is not yet complete:** long-form options (eg `--hello="world"`) do not support completion yet. Option completion is only supported for shortcuts (eg. `-h [tab]`).

    Completion::makeGlobalHandler(
        'weather', Completion::TYPE_OPTION,
        array('raining', 'sunny', 'everything is on fire!')
    )

Option completion is only supported for shortcuts currently:

    myapp walk -w [tab]

## Notes

* Option shorcuts are not offered as completion options, however requesting completion (ie. pressing tab) on a valid option shortcut will complete.