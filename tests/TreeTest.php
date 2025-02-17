<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Tests;

use PHPUnit\Framework\Attributes\Test;
use Stolt\LeanPackage\Archive;
use Stolt\LeanPackage\Exceptions\GitHeadNotAvailable;
use Stolt\LeanPackage\Tree;

class TreeTest extends TestCase
{
    #[Test]
    public function throwsExpectedExceptionWhenNoGitHeadAvailable(): void
    {
        $this->expectException(GitHeadNotAvailable::class);
        $this->expectExceptionMessage('No Git HEAD present to create an archive from.');

        (new Tree(new Archive(\sys_get_temp_dir())))->getTreeForDistPackage(\sys_get_temp_dir());
    }
}
