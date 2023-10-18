<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Tests;

use PHPUnit\Framework\TestCase as PHPUnit;

final class ApplicationTest extends PHPUnit
{
    /**
     * @test
     * @group integration
     */
    public function executableIsAvailable(): void
    {
        $binaryCommand = 'php bin/lean-package-validator';

        \exec($binaryCommand, $output, $returnValue);

        $this->assertStringStartsWith(
            'Lean package validator',
            $output[1],
            'Expected application name not present.'
        );
        $this->assertEquals(0, $returnValue);
    }

    /**
     * @test
     * @group integration
     */
    public function expectedCommandsAreListed(): void
    {
        $binaryCommand = 'php bin/lean-package-validator list';

        \exec($binaryCommand, $output, $returnValue);

        $this->assertStringContainsString(
            'init',
            $output[17],
            'Expected init command not listed.'
        );
        $this->assertStringContainsString(
            'validate',
            $output[19],
            'Expected validate command not listed.'
        );
        $this->assertEquals(0, $returnValue);
    }
}
