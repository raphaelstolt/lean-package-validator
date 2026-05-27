<?php

namespace Stolt\LeanPackage\Tests;

use PHPUnit\Framework\Attributes\Test;
use Stolt\LeanPackage\Analyser;
use Stolt\LeanPackage\Analysers\ClassicExportIgnoreAnalyser;
use Stolt\LeanPackage\Gitattributes\FileRepository as GitattributesFileRepository;
use Stolt\LeanPackage\Presets\Finder;
use Stolt\LeanPackage\Presets\PhpPreset;

final class GitattributesFileRepositoryTest extends TestCase
{
    public function setUp(): void
    {
        $this->setUpTemporaryDirectory();
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
    }

    #[Test]
    public function addsExpectedFileHeaders(): void
    {
        $analyser = new Analyser(new ClassicExportIgnoreAnalyser(
            new Finder(new PhpPreset()),
            new GitattributesFileRepository($this->temporaryDirectory)
        ));

        $analyser->getActualExportIgnoreAnalyser()->setDirectory($this->temporaryDirectory);

        $repository = new GitattributesFileRepository($this->temporaryDirectory);

        $fakeGitattributesContent = <<<CONTENT

.gitattributes export-ignore
phpspec.yml.dist export-ignore
specs/ export-ignore
version-increase-command export-ignore
CONTENT;
        $contentWithFileHeader = $repository->applyOverwriteHeaderPolicy($fakeGitattributesContent);

        $this->assertStringContainsString(GitattributesFileRepository::GENERATED_HEADER, $contentWithFileHeader);

        $generatedHeader = GitattributesFileRepository::GENERATED_HEADER . PHP_EOL . PHP_EOL;
        $fakeGitattributesContent = <<<CONTENT
{$generatedHeader}

.gitattributes export-ignore
phpspec.yml.dist export-ignore
specs/ export-ignore
version-increase-command export-ignore
CONTENT;

        $this->createTemporaryFilesInDirectory($this->temporaryDirectory, ['.gitattributes']);

        $contentWithFileHeader = $repository->applyOverwriteHeaderPolicy($fakeGitattributesContent);

        $this->assertStringContainsString(GitattributesFileRepository::MODIFIED_HEADER, $contentWithFileHeader);

        $modifiedHeader = GitattributesFileRepository::MODIFIED_HEADER . PHP_EOL . PHP_EOL;
        $fakeGitattributesContent = <<<CONTENT
{$modifiedHeader}

.gitattributes export-ignore
phpspec.yml.dist export-ignore
specs/ export-ignore
version-increase-command export-ignore
CONTENT;
        $contentWithFileHeader = $repository->applyOverwriteHeaderPolicy($fakeGitattributesContent);

        $this->assertStringContainsString(GitattributesFileRepository::MODIFIED_HEADER, $contentWithFileHeader);

        $fakeGitattributesContent = <<<CONTENT

.gitattributes export-ignore
phpspec.yml.dist export-ignore
specs/ export-ignore
version-increase-command export-ignore
CONTENT;

        $contentWithFileHeader = $repository->applyOverwriteHeaderPolicy($fakeGitattributesContent);

        $this->assertStringContainsString(GitattributesFileRepository::MODIFIED_HEADER, $contentWithFileHeader);
    }
}
