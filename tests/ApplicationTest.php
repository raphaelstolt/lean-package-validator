<?php

namespace Stolt\LeanPackage\Tests;

use PHPUnit\Framework\TestCase as PHPUnit;

class ApplicationTest extends PHPUnit
{
    /**
     * @test
     * @group integration
     */
    public function executableIsAvailable()
    {
        $binaryCommand = 'php bin/lean-package-validator';

        exec($binaryCommand, $output, $returnValue);

        $this->assertStringStartsWith(
            'Lean package validator',
            $output[1],
            'Expected application name not present.'
        );
        $this->assertEquals(0, $returnValue);
    }
}
