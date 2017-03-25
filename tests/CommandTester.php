<?php

namespace Stolt\LeanPackage\Tests;

use Symfony\Component\Console\Tester\CommandTester as ConsoleCommandTester;

class CommandTester extends ConsoleCommandTester
{
    /**
     * Gets the display returned by the last execution of the command.
     *
     * @param bool $normalize Whether to normalize end of lines to \n or not
     *
     * @return string The display
     */
    public function getDisplay($normalize = true)
    {
        return parent::getDisplay($normalize);
    }

    /**
     * Executes the command.
     *
     * Available execution options:
     *
     *  * interactive: Sets the input interactive flag
     *  * decorated:   Sets the output decorated flag
     *  * verbosity:   Sets the output verbosity flag
     *
     * @param array $input   An array of command arguments and options
     * @param array $options An array of execution options
     *
     * @return int The command exit code
     */
    public function execute(array $input, array $options = ['decorated' => false])
    {
        return parent::execute($input, $options);
    }
}
