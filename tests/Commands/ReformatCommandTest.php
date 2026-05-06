<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Tests\Commands;

use PHPUnit\Framework\Attributes\Test;
use Stolt\LeanPackage\Analyser;
use Stolt\LeanPackage\Commands\ReformatCommand;
use Stolt\LeanPackage\GitattributesFileRepository;
use Stolt\LeanPackage\Presets\Finder;
use Stolt\LeanPackage\Presets\PhpPreset;
use Stolt\LeanPackage\Tests\TestCase;
use Zenstruck\Console\Test\TestCommand;

final class ReformatCommandTest extends TestCase
{
    protected function setUp(): void
    {
        $this->setUpTemporaryDirectory();
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->temporaryDirectory);
    }

    #[Test]
    public function alignsOnlyExistingExportIgnoresAndRemovesPrecedingSlashes(): void
    {
        $analyser = (new Analyser(new Finder(new PhpPreset())))->setDirectory($this->temporaryDirectory);
        $repository = new GitattributesFileRepository($analyser);
        $command = new ReformatCommand($analyser, $repository);

        $this->createTemporaryFiles(['not-present-in-gitattributes'], ['not-present-directory']);

        $gitattributesContent = <<<CONTENT
# Keep this header untouched.
* text=auto eol=lf

/short export-ignore
much-longer export-ignore
# comment export-ignore
docs/ export-ignore # keep comment

/.example export-ignore

*.php text eol=lf
CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        TestCommand::for($command)
            ->addArgument($this->temporaryDirectory)
            ->execute()
            ->assertSuccessful()
            ->assertOutputContains('The export-ignore directives in ' . (\realpath($this->temporaryDirectory) ?: $this->temporaryDirectory) . ' have been reformatted.');

        $expectedGitattributesContent = <<<CONTENT
# Keep this header untouched.
* text=auto eol=lf

short       export-ignore
much-longer export-ignore
# comment export-ignore
docs/       export-ignore # keep comment

.example    export-ignore

*.php text eol=lf
CONTENT;

        $this->assertStringEqualsFile(
            $this->temporaryDirectory . DIRECTORY_SEPARATOR . '.gitattributes',
            $expectedGitattributesContent
        );
    }

    #[Test]
    public function printsAlignedExistingExportIgnoresWithoutWritingAFile(): void
    {
        $analyser = (new Analyser(new Finder(new PhpPreset())))->setDirectory($this->temporaryDirectory);
        $repository = new GitattributesFileRepository($analyser);
        $command = new ReformatCommand($analyser, $repository);

        $gitattributesContent = <<<CONTENT
short export-ignore
much-longer export-ignore
CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $result = TestCommand::for($command)
            ->addArgument($this->temporaryDirectory)
            ->addOption('dry-run')
            ->execute()
            ->assertSuccessful();

        $expectedGitattributesContent = <<<CONTENT
short       export-ignore
much-longer export-ignore
CONTENT;

        $this->assertSame($expectedGitattributesContent . PHP_EOL, $result->output());
        $this->assertStringEqualsFile(
            $this->temporaryDirectory . DIRECTORY_SEPARATOR . '.gitattributes',
            $gitattributesContent
        );
    }
}
