<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Tests\Commands;

use PHPUnit\Framework\Attributes\Test;
use Stolt\LeanPackage\Analyser;
use Stolt\LeanPackage\Analysers\ClassicExportIgnoreAnalyser;
use Stolt\LeanPackage\Commands\ReformatCommand;
use Stolt\LeanPackage\Gitattributes\FileRepository as GitattributesFileRepository;
use Stolt\LeanPackage\Presets\Finder;
use Stolt\LeanPackage\Presets\PhpPreset;
use Stolt\LeanPackage\Tests\TestCase;
use Zenstruck\Console\Test\TestCommand;

final class ReformatCommandTest extends TestCase
{
    protected Analyser $analyser;

    protected function setUp(): void
    {
        $this->setUpTemporaryDirectory();

        $this->analyser = new Analyser(
            new ClassicExportIgnoreAnalyser(new Finder(new PhpPreset()), new GitattributesFileRepository($this->temporaryDirectory))
        );
        $this->analyser->getActualExportIgnoreAnalyser()->setDirectory($this->temporaryDirectory);
    }

    /**
     * @return ReformatCommand
     */
    private function getCommandInstance(): ReformatCommand
    {
        return new ReformatCommand($this->analyser, new GitattributesFileRepository($this->temporaryDirectory));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->temporaryDirectory);
    }

    #[Test]
    public function reformatsNegatedExportIgnores(): void
    {
        $gitattributesContent = <<<CONTENT
* text=auto eol=lf

* export-ignore

composer.json -export-ignore
bin/                       -export-ignore
bin/lean-package-validator -export-ignore
resources/    -export-ignore
resources/**  -export-ignore
src/   -export-ignore
src/**  -export-ignore
CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $this->createTemporaryFiles(['.gitignore', 'composer.json'], ['example', 'tests', '.github', 'src', 'resources', 'bin']);

        $this->createTemporaryFilesInDirectory($this->temporaryDirectory . DIRECTORY_SEPARATOR . 'bin', ['lpv']);

        $result = TestCommand::for($this->getCommandInstance())
            ->addArgument($this->temporaryDirectory)
            ->addOption('dry-run')
            ->execute()
            ->assertSuccessful();

        $expectedGitattributesContent = <<<CONTENT
* text=auto eol=lf

* export-ignore

composer.json              -export-ignore
bin/                       -export-ignore
bin/lean-package-validator -export-ignore
resources/                 -export-ignore
resources/**               -export-ignore
src/                       -export-ignore
src/**                     -export-ignore
CONTENT;

        $this->assertSame(trim($expectedGitattributesContent), trim($result->output()));

        $this->assertStringEqualsFile(
            $this->temporaryDirectory . DIRECTORY_SEPARATOR . '.gitattributes',
            $gitattributesContent
        );
    }

    #[Test]
    public function sortsExportIgnoresAlphabetically(): void
    {
        $gitattributesContent = <<<CONTENT
/.gitattributes export-ignore
/.gitignore export-ignore
/CHANGELOG.md export-ignore
/example export-ignore
/README.rst export-ignore
/tests export-ignore
/.editorconfig export-ignore
/.github export-ignore
/contributing.rst export-ignore
CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $this->createTemporaryFiles(['.gitignore'], ['example', 'tests', '.github']);

        $result = TestCommand::for($this->getCommandInstance())
            ->addArgument($this->temporaryDirectory)
            ->addOption('dry-run')
            ->addOption('sort-alphabetically')
            ->execute()
            ->assertSuccessful();

        $expectedGitattributesContent = <<<CONTENT
.editorconfig     export-ignore
.gitattributes    export-ignore
.github/          export-ignore
.gitignore        export-ignore
CHANGELOG.md      export-ignore
README.rst        export-ignore
contributing.rst  export-ignore
example/          export-ignore
tests/            export-ignore
CONTENT;

        $this->assertSame(trim($expectedGitattributesContent), trim($result->output()));

        $this->assertStringEqualsFile(
            $this->temporaryDirectory . DIRECTORY_SEPARATOR . '.gitattributes',
            $gitattributesContent
        );
    }

    #[Test]
    public function sortsExportIgnoresFromDirectoriesToFiles(): void
    {
        $gitattributesContent = <<<CONTENT
/.gitattributes export-ignore
/.gitignore export-ignore
/CHANGELOG.md export-ignore
/example export-ignore
/README.rst export-ignore
/tests export-ignore
/.editorconfig export-ignore
/.github export-ignore
/contributing.rst export-ignore
CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $this->createTemporaryFiles(['.gitignore'], ['example', 'tests', '.github']);

        $result = TestCommand::for($this->getCommandInstance())
            ->addArgument($this->temporaryDirectory)
            ->addOption('dry-run')
            ->addOption('sort-from-directories-to-files')
            ->execute()
            ->assertSuccessful();

        $expectedGitattributesContent = <<<CONTENT
.github/          export-ignore
example/          export-ignore
tests/            export-ignore
.editorconfig     export-ignore
.gitattributes    export-ignore
.gitignore        export-ignore
CHANGELOG.md      export-ignore
contributing.rst  export-ignore
README.rst        export-ignore
CONTENT;

        $this->assertSame(\trim($expectedGitattributesContent), \trim($result->output()));

        $this->assertStringEqualsFile(
            $this->temporaryDirectory . DIRECTORY_SEPARATOR . '.gitattributes',
            $gitattributesContent
        );
    }

    #[Test]
    public function addASlashToExportIgnoredDirectories(): void
    {
        $gitattributesContent = <<<CONTENT
/.gitattributes export-ignore
/.gitignore export-ignore
/ChangeLog export-ignore
/example export-ignore
/README.rst export-ignore
/tests export-ignore
/.editorconfig export-ignore
/.github export-ignore
/contributing.rst export-ignore
CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $this->createTemporaryFiles(['.gitignore'], ['example', 'tests', '.github']);

        $result = TestCommand::for($this->getCommandInstance())
            ->addArgument($this->temporaryDirectory)
            ->addOption('dry-run')
            ->execute()
            ->assertSuccessful();

        $expectedGitattributesContent = <<<CONTENT
.gitattributes    export-ignore
.gitignore        export-ignore
ChangeLog         export-ignore
example/          export-ignore
README.rst        export-ignore
tests/            export-ignore
.editorconfig     export-ignore
.github/          export-ignore
contributing.rst  export-ignore
CONTENT;

        $this->assertSame(trim($expectedGitattributesContent), trim($result->output()));

        $this->assertStringEqualsFile(
            $this->temporaryDirectory . DIRECTORY_SEPARATOR . '.gitattributes',
            $gitattributesContent
        );
    }

    #[Test]
    public function alignsOnlyExistingExportIgnoresAndRemovesPrecedingSlashes(): void
    {
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

        TestCommand::for($this->getCommandInstance())
            ->addArgument($this->temporaryDirectory)
            ->execute()
            ->assertSuccessful()
            ->assertOutputContains('The export-ignore directives in ' . (\realpath($this->temporaryDirectory) ?: $this->temporaryDirectory) . ' have been reformatted.');

        $expectedGitattributesContent = <<<CONTENT
# This file was reformatted by the lean package validator (http://git.io/lean-package-validator).

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
        $gitattributesContent = <<<CONTENT
short export-ignore
much-longer export-ignore
CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $result = TestCommand::for($this->getCommandInstance())
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

    #[Test]
    public function groupsNonExportIgnoreDirectivesInSeparateSection(): void
    {
        $gitattributesContent = <<<CONTENT
* text=auto eol=lf

.editorconfig export-ignore
composer.lock diff=json
.gitattributes export-ignore
CHANGELOG.md merge=union
.gitignore export-ignore
CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $result = TestCommand::for($this->getCommandInstance())
            ->addArgument($this->temporaryDirectory)
            ->addOption('dry-run')
            ->addOption('group')
            ->execute()
            ->assertSuccessful();

        $expectedGitattributesContent = <<<CONTENT
* text=auto eol=lf

composer.lock diff=json
CHANGELOG.md merge=union

.editorconfig  export-ignore
.gitattributes export-ignore
.gitignore     export-ignore
CONTENT;

        $this->assertSame(\trim($expectedGitattributesContent), \trim($result->output()));

        $this->assertStringEqualsFile(
            $this->temporaryDirectory . DIRECTORY_SEPARATOR . '.gitattributes',
            $gitattributesContent
        );
    }

    #[Test]
    public function groupKeepsStickyCommentsWithExportIgnores(): void
    {
        $gitattributesContent = <<<CONTENT
* text=auto eol=lf

# exclude from dist archives
.editorconfig export-ignore
.gitattributes export-ignore
.gitignore export-ignore
CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $result = TestCommand::for($this->getCommandInstance())
            ->addArgument($this->temporaryDirectory)
            ->addOption('dry-run')
            ->addOption('group')
            ->execute()
            ->assertSuccessful();

        $expectedGitattributesContent = <<<CONTENT
* text=auto eol=lf

# exclude from dist archives
.editorconfig  export-ignore
.gitattributes export-ignore
.gitignore     export-ignore
CONTENT;

        $this->assertSame(\trim($expectedGitattributesContent), \trim($result->output()));
    }

    #[Test]
    public function groupMovesUnrelatedCommentsToNonExportIgnoreSection(): void
    {
        $gitattributesContent = <<<CONTENT
# text encoding
* text=auto eol=lf

# blank line separates this comment from the export-ignore entries below

.editorconfig export-ignore
.gitattributes export-ignore
.gitignore export-ignore
CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $result = TestCommand::for($this->getCommandInstance())
            ->addArgument($this->temporaryDirectory)
            ->addOption('dry-run')
            ->addOption('group')
            ->execute()
            ->assertSuccessful();

        $output = $result->output();

        $this->assertStringContainsString('# text encoding', $output);
        $this->assertStringContainsString('# blank line separates this comment', $output);

        $textEncodingPosition = \strpos($output, '# text encoding');
        $separatesPosition = \strpos($output, '# blank line separates');
        $exportIgnorePosition = \strpos($output, 'export-ignore');

        $this->assertLessThan($exportIgnorePosition, $textEncodingPosition);
        $this->assertLessThan($exportIgnorePosition, $separatesPosition);
    }

    #[Test]
    public function groupsNonExportIgnoreDirectivesAndWritesFile(): void
    {
        $gitattributesContent = <<<CONTENT
* text=auto eol=lf

.editorconfig export-ignore
composer.lock diff=json
.gitattributes export-ignore
CHANGELOG.md merge=union
.gitignore export-ignore

*.phar    binary
CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        TestCommand::for($this->getCommandInstance())
            ->addArgument($this->temporaryDirectory)
            ->addOption('group')
            ->execute()
            ->assertSuccessful()
            ->assertOutputContains('The export-ignore directives in ' . (\realpath($this->temporaryDirectory) ?: $this->temporaryDirectory) . ' have been reformatted and grouped.');

        $expectedGitattributesContent = <<<CONTENT
# This file was reformatted by the lean package validator (http://git.io/lean-package-validator).

* text=auto eol=lf
*.phar    binary

composer.lock diff=json
CHANGELOG.md merge=union

.editorconfig  export-ignore
.gitattributes export-ignore
.gitignore     export-ignore
CONTENT;

        $this->assertStringEqualsFile(
            $this->temporaryDirectory . DIRECTORY_SEPARATOR . '.gitattributes',
            $expectedGitattributesContent
        );
    }
}
