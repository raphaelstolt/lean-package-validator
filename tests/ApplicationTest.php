<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Tests;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as PHPUnit;
use Symfony\Component\Console\Command\Command;

final class ApplicationTest extends PHPUnit
{
    #[Test]
    #[Group('integration')]
    public function executableIsAvailable(): void
    {
        $binaryCommand = 'php bin/lean-package-validator';

        \exec($binaryCommand, $output, $returnValue);

        $this->assertStringStartsWith(
            'Lean package validator',
            $output[1],
            'Expected application name not present.'
        );
        $this->assertEquals(Command::SUCCESS, $returnValue);
    }

    #[Test]
    #[Group('integration')]
    public function expectedCommandsAreListed(): void
    {
        $binaryCommand = 'php bin/lean-package-validator list';

        \exec($binaryCommand, $output, $returnValue);

        $this->assertStringContainsString(
            'init',
            \implode('', $output),
            'Expected init command not listed.'
        );
        $this->assertStringContainsString(
            'validate',
            \implode('', $output),
            'Expected validate command not listed.'
        );
        $this->assertEquals(Command::SUCCESS, $returnValue);
    }
}
