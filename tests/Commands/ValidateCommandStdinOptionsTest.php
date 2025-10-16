<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Tests\Commands;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use Stolt\LeanPackage\Analyser;
use Stolt\LeanPackage\Archive;
use Stolt\LeanPackage\Archive\Validator;
use Stolt\LeanPackage\Commands\ValidateCommand;
use Stolt\LeanPackage\Presets\Finder;
use Stolt\LeanPackage\Presets\PhpPreset;
use Stolt\LeanPackage\Tests\Helpers\FakeInputReader;
use Stolt\LeanPackage\Tests\TestCase;
use Symfony\Component\Console\Application;
use Zenstruck\Console\Test\TestCommand;

final class ValidateCommandStdinOptionsTest extends TestCase
{
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

    private function makeAppWithCommand(string $stdinContent): array
    {
        $application = new Application();
        $fakeInputReader = new FakeInputReader();
        $fakeInputReader->set($stdinContent);

        $command = new ValidateCommand(
            (new Analyser(new Finder(new PhpPreset())))->setDirectory($this->temporaryDirectory),
            new Validator(new Archive($this->temporaryDirectory)),
            $fakeInputReader
        );
        $application->add($command);

        return [$application, $command];
    }

    #[Test]
    #[RunInSeparateProcess]
    public function stdinHonorsStrictOrderAndReportsInvalidOnShuffledOrder(): void
    {
        // Arrange repository artifacts so expected export-ignores can be computed
        $this->createTemporaryFiles(
            ['README.md', '.gitignore'],
            ['tests']
        );

        // Shuffled order: README before dotfiles, etc.
        $stdinContent = <<<GITATTR

README.md export-ignore
tests/ export-ignore
.gitignore export-ignore
.gitattributes export-ignore

GITATTR;

        [$application, $cmd] = $this->makeAppWithCommand($stdinContent);
        $command = $application->find('validate');

        // Act/Assert: with strict order, shuffled input is invalid
        TestCommand::for($command)
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
        // Arrange repository artifacts
        $this->createTemporaryFiles(
            ['README.md', '.gitignore'],
            ['tests']
        );

        // Likely expected order: .gitattributes, .gitignore, README.md, tests/
        $stdinContent = <<<GITATTR

.gitattributes export-ignore
.gitignore export-ignore
README.md export-ignore
tests/ export-ignore

GITATTR;

        [$application, $cmd] = $this->makeAppWithCommand($stdinContent);
        $command = $application->find('validate');

        // Act/Assert: with strict order, correctly ordered input is valid
        TestCommand::for($command)
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
        // Arrange repository artifacts
        $this->createTemporaryFiles(
            ['README.md', '.gitignore'],
            ['tests']
        );

        // Likely expected order: .gitattributes, .gitignore, README.md, tests/
        $stdinContent = <<<GITATTR

.gitattributes export-ignore
.gitignore     export-ignore
README.md      export-ignore
tests/         export-ignore

GITATTR;

        [$application, $cmd] = $this->makeAppWithCommand($stdinContent);
        $command = $application->find('validate');

        // Act/Assert: with strict order, correctly ordered input is valid
        TestCommand::for($command)
            ->addOption('stdin-input')
            ->addOption('enforce-alignment')
            ->execute()
            ->assertOutputContains('The provided .gitattributes content is considered valid.')
            ->assertSuccessful();
    }
}
