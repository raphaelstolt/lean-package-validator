<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Tests\Helpers;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Stolt\LeanPackage\Helpers\Str as OsHelper;

class StrTest extends TestCase
{
    #[Test]
    #[Group('unit')]
    public function canDetermineIfWindowsOrNot(): void
    {
        $osHelper = new OsHelper();
        if ($osHelper->isWindows()) {
            $this->assertTrue($osHelper->isWindows());
        } else {
            $this->assertFalse($osHelper->isWindows());
        }

        $this->assertTrue($osHelper->isWindows('WIn'));
        $this->assertFalse($osHelper->isWindows('Darwin'));
    }
}
