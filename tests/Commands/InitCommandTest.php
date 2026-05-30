<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Tests\Commands;

use phpmock\functions\FixedValueFunction;
use phpmock\MockBuilder;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use Stolt\LeanPackage\Analyser;
use Stolt\LeanPackage\Analysers\ClassicExportIgnoreAnalyser;
use Stolt\LeanPackage\Commands\InitCommand;
use Stolt\LeanPackage\Exceptions\PresetNotAvailable;
use Stolt\LeanPackage\Gitattributes\FileRepository as GitattributesFileRepository;
use Stolt\LeanPackage\Presets\Finder;
use Stolt\LeanPackage\Presets\PhpPreset;
use Stolt\LeanPackage\Tests\CommandTester;
use Stolt\LeanPackage\Tests\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

class InitCommandTest extends TestCase
{
    private function getCommandInstance(): InitCommand
    {
        return new InitCommand(new Analyser(new ClassicExportIgnoreAnalyser(new Finder(new PhpPreset()), new GitattributesFileRepository())));
    }

    /**
     * Set up a test environment.
     */
    protected function setUp(): void
    {
        $this->setUpTemporaryDirectory();

        if (!\defined('WORKING_DIRECTORY')) {
            \define('WORKING_DIRECTORY', $this->temporaryDirectory);
        }

        $this->application = $this->getApplication($this->getCommandInstance());
    }

    /**
     * Tear down the test environment.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        if (\is_dir($this->temporaryDirectory)) {
            $this->removeDirectory($this->temporaryDirectory);
        }
        $this->clearAgenticEnvironment();
    }

    #[Test]
    public function printsContentWithoutWritingAFile(): void
    {
        $command = $this->application->find('init');
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([
            '--preset' => 'PHP',
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $exitCode);

        $display = $tester->getDisplay();

        $this->assertStringContainsString('phpunit*', $display);

        $this->assertFileDoesNotExist($this->temporaryDirectory.DIRECTORY_SEPARATOR.'.lpv');
    }

    /**
     * @throws PresetNotAvailable
     */
    #[Test]
    #[RunInSeparateProcess]
    public function createsExpectedDefaultLpvFile(): void
    {
        $command = $this->application->find('init');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
        ]);

        $expectedDefaultLpvFile = $this->temporaryDirectory
            . DIRECTORY_SEPARATOR
            . '.lpv';
        $expectedDisplay = <<<CONTENT
Created default '{$expectedDefaultLpvFile}' file.

CONTENT;

        $expectedDefaultLpvFileContent = \implode(
            PHP_EOL,
            (new Finder(new PhpPreset()))->getPresetGlobByLanguageName('PHP')
        );

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $commandTester->assertCommandIsSuccessful();
        $this->assertFileExists($expectedDefaultLpvFile);
        $this->assertEquals($expectedDefaultLpvFileContent, \file_get_contents($expectedDefaultLpvFile));
    }

    #[Test]
    #[RunInSeparateProcess]
    public function failingInitReturnsExpectedStatusCode(): void
    {
        $builder = new MockBuilder();
        $builder->setNamespace('Stolt\LeanPackage\Commands')
            ->setName('file_put_contents')
            ->setFunctionProvider(new FixedValueFunction(false));

        $mock = $builder->build();
        $mock->enable();

        $command = $this->application->find('init');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
        ]);

        $expectedDisplay = <<<CONTENT
Warning: The creation of the default .lpv file failed.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() !== Command::SUCCESS);

        $mock->disable();
    }

    #[Test]
    public function existingDefaultLpvFileIsNotOverwritten(): void
    {
        $this->createTemporaryFilesInDirectory($this->temporaryDirectory, ['.lpv']);

        $command = $this->application->find('init');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
        ]);

        $expectedDisplay = <<<CONTENT
Warning: A default .lpv file already exists.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() !== Command::SUCCESS);
    }

    #[Test]
    public function usingANonAvailablePresetShowsWarning(): void
    {
        $expectedDisplay = <<<CONTENT
Warning: Chosen preset assembler is not available. Maybe contribute it!?

CONTENT;

        $command = $this->application->find('init');
        $commandTester = new CommandTester($command);
        $commandTester->execute(
            ['command' => $command->getName(),
             'directory' => WORKING_DIRECTORY,
             '--preset' => 'assembler'],
        );

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() !== Command::SUCCESS);
    }

    #[Test]
    public function verboseOutputIsAvailableWhenDesired(): void
    {
        $this->createTemporaryFilesInDirectory($this->temporaryDirectory, ['.lpv']);

        $expectedDisplay = <<<CONTENT
+ Checking .lpv file existence in {$this->temporaryDirectory}.
Warning: A default .lpv file already exists.

CONTENT;

        $command = $this->application->find('init');
        $commandTester = new CommandTester($command);
        $commandTester->execute(
            ['command' => $command->getName(),
            'directory' => WORKING_DIRECTORY],
            ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]
        );

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() !== Command::SUCCESS);
    }

    #[Test]
    public function existingDefaultLpvFileIsOverwrittenWhenDesired(): void
    {
        $expectedDefaultLpvFile = $this->temporaryDirectory
            . DIRECTORY_SEPARATOR
            . '.lpv';

        $this->createTemporaryFilesInDirectory($this->temporaryDirectory, ['.lpv']);

        $command = $this->application->find('init');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
            '--overwrite' => true,
        ]);

        $expectedDisplay = <<<CONTENT
Created default '{$expectedDefaultLpvFile}' file.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $commandTester->assertCommandIsSuccessful();
        $this->assertFileExists($expectedDefaultLpvFile);
    }

    #[Test]
    public function outputsJsonOnSuccessInAnAgenticRun(): void
    {
        $command = $this->application->find('init');
        $tester = new CommandTester($command);

        \putenv('COPILOT_MODEL=1');

        $exitCode = $tester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);

        $json = \json_decode(\trim($tester->getDisplay()), true);

        $this->assertIsArray($json);
        $this->assertSame('init', $json['command']);
        $this->assertSame('success', $json['status']);
        $this->assertArrayHasKey('lpv_file_path', $json);
        $this->assertStringContainsString('.lpv', $json['lpv_file_path']);
    }

    #[Test]
    public function outputsJsonOnFailureInAnAgenticRun(): void
    {
        $this->createTemporaryFilesInDirectory($this->temporaryDirectory, ['.lpv']);

        $command = $this->application->find('init');
        $tester = new CommandTester($command);

        \putenv('COPILOT_MODEL=1');

        $exitCode = $tester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
        ]);

        $this->assertTrue($exitCode !== Command::SUCCESS);

        $json = \json_decode(\trim($tester->getDisplay()), true);

        $this->assertIsArray($json);
        $this->assertSame('init', $json['command']);
        $this->assertSame('failure', $json['status']);
        $this->assertStringContainsString('already exists', $json['message']);
    }
}
