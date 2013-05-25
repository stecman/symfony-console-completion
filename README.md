# Symfony Console completion

Automatic BASH completion for Symfony Console Component based applications. Completes commands and options by default, and allows for custom option/argument completion handlers to be set.

**Note that this is not entirely finished:**

* Long-form options do not support completion yet (only shortcuts do)

## Use

If you don't need any custom completion behaviour, just add an instance of `CompletionCommand` to your application's `Application::getDefaultCommands()` method. Once you've done this, you can run (or add to your bash profile):

    eval `[your-application] _completion --genhook`

This will generate and run a small bash script which creates a small BASH function and registers completion for your appliction name. Completion is then handled by running your application as `[your-application] _completion`.

### Custom completion

Custom completion behaviour for arguments and option values can be added by sub-classing `CompletionCommand` (this will change very soon):


    class BeamCompletionCommand extends CompletionCommand{

        protected function runCompletion()
        {
            $handler = $this->handler;
            $handler->addHandlers(array(

                ... // See below for what goes in here

            ));

            return $handler->runCompletion();
        }

    }

Imagine you have an application with this signature: `myapp (walk|run) [-w|--weather=""] direction`


**Command-specific argument completion with an array:**

    new Completion(
        'walk', 'direction', Completion::TYPE_ARGUMENT,
        array('north', 'east', 'south', 'west')
    )

This will complete for this:

    myapp walk [tab]

but not this:

    myapp run [tab]


**Non-command-specifc (global) argument completion with a function**

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

    Completion::makeGlobalHandler(
        'weather', Completion::TYPE_OPTION,
        array('raining', 'sunny', 'everything is on fire!')
    )

Option completion is only supported for shortcuts currently:

    myapp walk -w [tab]

## Notes

Option shorcuts are not offered as completion options, however requesting completion (ie. pressing tab) on a valid option shortcut will complete.