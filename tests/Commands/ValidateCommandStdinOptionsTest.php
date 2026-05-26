<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Tests\Commands;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use Stolt\LeanPackage\Analyser;
use Stolt\LeanPackage\Analysers\ClassicExportIgnoreAnalyser;
use Stolt\LeanPackage\Archive;
use Stolt\LeanPackage\Archive\Validator;
use Stolt\LeanPackage\Commands\ValidateCommand;
use Stolt\LeanPackage\Gitattributes\FileRepository as GitattributesFileRepository;
use Stolt\LeanPackage\Presets\Finder;
use Stolt\LeanPackage\Presets\PhpPreset;
use Stolt\LeanPackage\Tests\Helpers\FakeInputReader;
use Stolt\LeanPackage\Tests\TestCase;
use Symfony\Component\Console\Application;
use Zenstruck\Console\Test\TestCommand;

final class ValidateCommandStdinOptionsTest extends TestCase
{
    protected Analyser $analyser;

    protected function setUp(): void
    {
        $this->setUpTemporaryDirectory();

        if (!\defined('WORKING_DIRECTORY')) {
            \define('WORKING_DIRECTORY', $this->temporaryDirectory);
        }
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->temporaryDirectory);
    }

    private function getApplicationWithCommandInstance(string $stdinContent): array
    {
        $application = new Application();
        $fakeInputReader = new FakeInputReader();
        $fakeInputReader->set($stdinContent);

        $this->analyser = new Analyser(new ClassicExportIgnoreAnalyser(new Finder(new PhpPreset())));
        $this->analyser->getActualExportIgnoreAnalyser()->setDirectory($this->temporaryDirectory);

        $command = new ValidateCommand(
            $this->analyser,
            new Validator(new Archive($this->temporaryDirectory)),
            $fakeInputReader,
            new GitattributesFileRepository($this->analyser),
        );
        $application->addCommand($command);

        return [$application, $command];
    }

    #[Test]
    #[RunInSeparateProcess]
    public function stdinHonorsStrictOrderAndReportsInvalidOnShuffledOrder(): void
    {
        $this->createTemporaryFiles(
            ['README.md', '.gitignore'],
            ['tests']
        );

        $stdinContent = <<<GITATTR

README.md export-ignore
tests/ export-ignore
.gitignore export-ignore
.gitattributes export-ignore

GITATTR;

        [$application, $cmd] = $this->getApplicationWithCommandInstance($stdinContent);
        $command = $application->find('validate');

        TestCommand::for($command)
            ->addArgument($this->temporaryDirectory)
            ->addOption('stdin-input')
            ->addOption('enforce-strict-order')
            ->execute()
            ->assertOutputContains('The provided .gitattributes file is considered invalid.')
            ->assertFaulty();
    }

    #[Test]
    #[RunInSeparateProcess]
    public function stdinHonorsStrictOrderAndReportsValidOnExpectedOrder(): void
    {
        $this->createTemporaryFiles(
            ['README.md', '.gitignore'],
            ['tests']
        );

        $stdinContent = <<<GITATTR

.gitattributes export-ignore
.gitignore export-ignore
README.md export-ignore
tests/ export-ignore

GITATTR;

        [$application, $cmd] = $this->getApplicationWithCommandInstance($stdinContent);
        $command = $application->find('validate');

       TestCommand::for($command)
            ->addArgument($this->temporaryDirectory)
            ->addOption('stdin-input')
            ->addOption('enforce-strict-order')
            ->execute()
            ->assertOutputContains('The provided .gitattributes content is considered valid.')
            ->assertSuccessful();
    }

    #[Test]
    #[RunInSeparateProcess]
    public function stdinHonorsEnforceAlignmentAndReportsValidOnExpectedOrder(): void
    {
        $this->createTemporaryFiles(
            ['README.md', '.gitignore'],
            ['tests']
        );

        $stdinContent = <<<GITATTR

.gitattributes export-ignore
.gitignore     export-ignore
README.md      export-ignore
tests/         export-ignore

GITATTR;

        [$application, $cmd] = $this->getApplicationWithCommandInstance($stdinContent);
        $command = $application->find('validate');

        TestCommand::for($command)
            ->addArgument($this->temporaryDirectory)
            ->addOption('stdin-input')
            ->addOption('enforce-alignment')
            ->execute()
            ->assertOutputContains('The provided .gitattributes content is considered valid.')
            ->assertSuccessful();
    }
}
