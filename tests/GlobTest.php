<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Tests;

use PHPUnit\Framework\Attributes\Test;
use Stolt\LeanPackage\Glob;

class GlobTest extends TestCase
{
    #[Test]
    public function globArrayWorksAsExpected(): void
    {
        $expectedResult = ['README.md', 'LICENSE.md', 'docs/'];

        $actualResult = Glob::globArray(
            '{READ*.md,LICENSE.md,docs*}',
            ['spec.dist.yml', 'phpunit.dist.xml', 'README.md', 'LICENSE.md', 'docs/']
        );
        $this->assertEquals($expectedResult, $actualResult);

        $actualResult = Glob::globArray(
            '{composer.lock}',
            ['spec.dist.yml', 'phpunit.dist.xml', 'README.md', 'LICENSE.md', 'docs/']
        );
        $this->assertEquals([], $actualResult);
    }
}
