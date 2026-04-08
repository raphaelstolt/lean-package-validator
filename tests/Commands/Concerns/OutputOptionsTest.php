<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Tests\Commands\Concerns;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Stolt\LeanPackage\Commands\Concerns\OutputOptions;
use Symfony\Component\Console\Input\InputInterface;

class OutputOptionsTest extends TestCase
{
    use OutputOptions;

    protected function tearDown(): void
    {
        unset($_ENV['COPILOT_MODEL']);
    }

    #[Test]
    #[RunInSeparateProcess]
    public function itAutoDetectsAnAiAgent(): void
    {
        $mockedInput = $this->createMock(InputInterface::class);
        $mockedInput->method('getOption')->with('agentic-run')->willReturn(false);

        \putenv('COPILOT_MODEL=1');

        $this->assertTrue($this->isAgenticRun($mockedInput));
    }
}
