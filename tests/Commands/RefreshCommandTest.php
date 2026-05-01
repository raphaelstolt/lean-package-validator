<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Tests\Commands;

use PHPUnit\Framework\Attributes\Test;
use Stolt\LeanPackage\Analyser;
use Stolt\LeanPackage\Commands\RefreshCommand;
use Stolt\LeanPackage\Exceptions\PresetNotAvailable;
use Stolt\LeanPackage\Presets\Finder;
use Stolt\LeanPackage\Presets\PhpPreset;
use Stolt\LeanPackage\Tests\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class RefreshCommandTest extends TestCase
{
    protected function setUp(): void
    {
        $this->setUpTemporaryDirectory();

        if (!\defined('WORKING_DIRECTORY')) {
            \define('WORKING_DIRECTORY', $this->temporaryDirectory);
        }

        $this->application = $this->getApplication(
            new RefreshCommand(new Analyser(new Finder(new PhpPreset())))
        );
    }

    protected function tearDown(): void
    {
        if (\is_dir($this->temporaryDirectory)) {
            $this->removeDirectory($this->temporaryDirectory);
        }
    }

    /**
     * @throws PresetNotAvailable
     */
    #[Test]
    public function printsMergedContentWithoutWritingAFile(): void
    {
        $this->createTemporaryGlobPatternFile(<<<CONTENT
.*
*.txt
*.lock
SOME_FILE
.SOME_DIRECTORY/
CONTENT);

        $command = $this->application->find('refresh');
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
            '--preset' => 'PHP',
            '--dry-run' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('phpunit*', $tester->getDisplay());
        $this->assertFileExists($this->temporaryDirectory . DIRECTORY_SEPARATOR . '.lpv');

        $existingLines = [
            '.*',
            '*.txt',
            '*.lock',
            'SOME_FILE',
            '.SOME_DIRECTORY/',
        ];

        $presetLines = (new Finder(new PhpPreset()))->getPresetGlobByLanguageName('PHP');

        $expectedLines = $existingLines;
        foreach ($presetLines as $presetLine) {
            if (\in_array($presetLine, $expectedLines, true)) { continue; }

$expectedLines[] = $presetLine;
        }

        $expectedContent = \implode(PHP_EOL, $expectedLines) . PHP_EOL;

        $this->assertSame($expectedContent, $tester->getDisplay());
    }

    #[Test]
    public function refreshKeepsExistingModifications(): void
    {
        $this->createTemporaryGlobPatternFile(<<<CONTENT
.*
*.txt
custom-entry*
CONTENT);

        $command = $this->application->find('refresh');
        $tester = new CommandTester($command);

        $tester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
            '--preset' => 'PHP',
        ]);

        $this->assertSame(0, $tester->getStatusCode());

        $content = (string) \file_get_contents($this->temporaryDirectory . DIRECTORY_SEPARATOR . '.lpv');

        $this->assertStringContainsString('custom-entry*', $content);
        $this->assertStringContainsString('phpunit*', $content);
        $this->assertStringContainsString('*.lock', $content);
    }

    #[Test]
    public function refreshingWithoutExistingFileFails(): void
    {
        $command = $this->application->find('refresh');
        $tester = new CommandTester($command);

        $tester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
            '--preset' => 'PHP',
        ]);

        $this->assertSame(
            'Warning: No default .lpv file exists to refresh.' . PHP_EOL,
            $tester->getDisplay()
        );
        $this->assertTrue($tester->getStatusCode() !== Command::SUCCESS);
    }

    #[Test]
    public function usingANonAvailablePresetShowsWarning(): void
    {
        $this->createTemporaryGlobPatternFile("*.txt\n");

        $command = $this->application->find('refresh');
        $tester = new CommandTester($command);

        $tester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
            '--preset' => 'assembler',
        ]);

        $this->assertSame(
            'Warning: Chosen preset assembler is not available. Maybe contribute it?.' . PHP_EOL,
            $tester->getDisplay()
        );
        $this->assertTrue($tester->getStatusCode() !== Command::SUCCESS);
    }

    #[Test]
    public function outputsJsonOnSuccessWhenAgenticRunOptionIsSet(): void
    {
        $this->createTemporaryGlobPatternFile("*.txt\n*.lock\n");

        $command = $this->application->find('refresh');
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
            '--preset' => 'PHP',
            '--agentic-run' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);

        $json = \json_decode(\trim($tester->getDisplay()), true);

        $this->assertIsArray($json);
        $this->assertSame('refresh', $json['command']);
        $this->assertSame('success', $json['status']);
        $this->assertArrayHasKey('lpv_file_path', $json);
        $this->assertStringContainsString('.lpv', $json['lpv_file_path']);
    }

    #[Test]
    public function outputsJsonOnFailureWhenAgenticRunOptionIsSet(): void
    {
        $command = $this->application->find('refresh');
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
            '--preset' => 'PHP',
            '--agentic-run' => true,
        ]);

        $this->assertTrue($exitCode !== Command::SUCCESS);

        $json = \json_decode(\trim($tester->getDisplay()), true);

        $this->assertIsArray($json);
        $this->assertSame('refresh', $json['command']);
        $this->assertSame('failure', $json['status']);
        $this->assertStringContainsString('No default .lpv file exists', $json['message']);
    }
}
