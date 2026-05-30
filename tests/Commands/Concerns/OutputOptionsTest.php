<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Tests\Commands\Concerns;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Stolt\LeanPackage\Commands\Concerns\OutputOptions;

class OutputOptionsTest extends TestCase
{
    use OutputOptions;

    protected function tearDown(): void
    {
        \putenv('COPILOT_MODEL');
        unset($_ENV['COPILOT_MODEL']);
    }

    #[Test]
    #[RunInSeparateProcess]
    public function itAutoDetectsAnAiAgent(): void
    {
        \putenv('COPILOT_MODEL=1');

        $this->assertTrue($this->isAgenticRun());
    }
}
