<?php

namespace Stolt\LeanPackage\Tests;

use PHPUnit\Framework\Attributes\Test;
use Stolt\LeanPackage\Analyser;
use Stolt\LeanPackage\GitattributesFileRepository;
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
        $analyser = (new Analyser(new Finder(new PhpPreset())))->setDirectory($this->temporaryDirectory);

        $repository = new GitattributesFileRepository($analyser);

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

        \touch($this->temporaryDirectory . DIRECTORY_SEPARATOR . '.gitattributes');

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
