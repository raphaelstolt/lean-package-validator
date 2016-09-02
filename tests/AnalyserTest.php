<?php

namespace Stolt\LeanPackage\Tests;

use Mockery;
use Stolt\LeanPackage\Tests\TestCase;
use Stolt\LeanPackage\Analyser;

class AnalyserTest extends TestCase
{
    /**
     * Set up test environment.
     */
    protected function setUp()
    {
        $this->setUpTemporaryDirectory();
    }

    /**
     * Tear down test environment.
     *
     * @return void
     */
    protected function tearDown()
    {
        if (is_dir($this->temporaryDirectory)) {
            $this->removeDirectory($this->temporaryDirectory);
        }
    }

    /**
     * @test
     */
    public function hasCompleteExportIgnoresFailsOnEmptyExportIgnores()
    {
        $analyser = (new Analyser())->setDirectory($this->temporaryDirectory);
        $this->assertFalse(
            $analyser->hasCompleteExportIgnores()
        );
    }

    /**
     * @test
     */
    public function hasCompleteExportIgnoresFailsOnNonExistingGitattributesFile()
    {
        $mock = Mockery::mock(
            'Stolt\LeanPackage\Analyser[hasGitattributesFile]'
        );

        $mock->shouldReceive('hasGitattributesFile')
            ->once()
            ->withNoArgs()
            ->andReturn(false);

        $mock->setDirectory($this->temporaryDirectory);

        $this->assertFalse(
            $mock->hasCompleteExportIgnores()
        );
    }

    /**
     * @test
     */
    public function returnsTrueWhenDirectoryHasCompleteExportIgnores()
    {
        $gitattributesContent = <<<CONTENT
phpspec.yml.dist export-ignore
.buildignore export-ignore
specs/ export-ignore
.travis.yml export-ignore
CONDUCT.md export-ignore
.gitattributes export-ignore
CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $artifactFilenames = [
            'CONDUCT.md',
            '.travis.yml',
            '.buildignore',
            'phpspec.yml.dist',
            '.DS_Store'
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs']
        );

        $analyser = (new Analyser())->setDirectory($this->temporaryDirectory);

        $this->assertTrue(
            $analyser->hasCompleteExportIgnores()
        );
    }

    /**
     * @test
     */
    public function analyseOnNonExistingDirectoryThrowsExpectedException()
    {
        $nonExistingDirectory = '/tmp/non-existing_directory';
        $this->expectException(\RunTimeException::class);
        $this->expectExceptionMessage(
            "Directory {$nonExistingDirectory} doesn't exist."
        );
        $analyser = (new Analyser())->setDirectory($nonExistingDirectory);
    }

    /**
     * @test
     */
    public function gitattributesFileHasAnAccessor()
    {
        $analyser = (new Analyser())->setDirectory($this->temporaryDirectory);
        $expectedGitattributesFilePath = $this->temporaryDirectory
            . DIRECTORY_SEPARATOR
            . '.gitattributes';

        $this->assertEquals(
            $expectedGitattributesFilePath,
            $analyser->getGitattributesFilePath()
        );
    }

    /**
     * @test
     */
    public function directoryHasAnAccessor()
    {
        $analyser = (new Analyser())->setDirectory($this->temporaryDirectory);
        $this->assertEquals($this->temporaryDirectory, $analyser->getDirectory());
    }

    /**
     * @test
     */
    public function returnsExpectedGitattributesContent()
    {
        $artifactsWithoutExportIgnore = [
            'README.md',
            'Makefile',
            '.travis.yml',
            '.buildignore',
            'documentation/'
        ];

        $expectedGitattributesContent = <<<CONTENT
* text=auto eol=lf

.buildignore export-ignore
.travis.yml export-ignore
documentation/ export-ignore
Makefile export-ignore
README.md export-ignore

CONTENT;

        $analyser = (new Analyser())->setDirectory($this->temporaryDirectory);

        $actualGitattributesContent = $analyser->getExpectedGitattributesContent(
            $artifactsWithoutExportIgnore
        );

        $this->assertEquals(
            $expectedGitattributesContent,
            $actualGitattributesContent
        );
    }

    /**
     * @test
     */
    public function nonExportIgnoresContentIsEmptyForNonexistentGitattributesFile()
    {
        $analyser = (new Analyser())->setDirectory($this->temporaryDirectory);

        $this->assertEquals('', $analyser->getPresentNonExportIgnoresContent());
    }

    /**
     * @test
     */
    public function nonExportIgnoresContentOfGitattributesFileIsReturned()
    {
        $gitattributesContent = <<<CONTENT
# Auto-detect text files, ensure they use LF.
* text=auto eol=lf

*.php text eol=lf whitespace=blank-at-eol,blank-at-eof,space-before-tab,tab-in-indent,tabwidth=4 diff=php

# Don't include in archives
phpspec.yml.dist export-ignore
.buildignore export-ignore
specs/ export-ignore
CONDUCT.md export-ignore
.gitattributes export-ignore

CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $analyser = (new Analyser())->setDirectory($this->temporaryDirectory);

        $actualGitattributesContent = $analyser->getPresentNonExportIgnoresContent();

        $expectedGitattributesContent = <<<CONTENT
# Auto-detect text files, ensure they use LF.
* text=auto eol=lf

*.php text eol=lf whitespace=blank-at-eol,blank-at-eof,space-before-tab,tab-in-indent,tabwidth=4 diff=php

# Don't include in archives

CONTENT;

        $this->assertEquals(
            $expectedGitattributesContent,
            $actualGitattributesContent
        );
    }

    /**
     * @test
     */
    public function getExpectedGitattributesContentKeepsNonExportIgnoreEntries()
    {
        $artifactsWithoutExportIgnore = [
            'README.md',
            '.travis.yml',
            'documentation/',
            'phpspec.yml.dist',
            '.buildignore',
            'specs/',
            'CONDUCT.md',
            '.gitattributes',
        ];

        $gitattributesContent = <<<CONTENT
# Auto-detect text files, ensure they use LF.
* text=auto eol=lf

*.php text eol=lf whitespace=blank-at-eol,blank-at-eof,space-before-tab,tab-in-indent,tabwidth=4 diff=php

# Don't include in archives
phpspec.yml.dist export-ignore
.buildignore export-ignore
specs/ export-ignore
CONDUCT.md export-ignore
.gitattributes export-ignore
CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $expectedGitattributesContent = <<<CONTENT
# Auto-detect text files, ensure they use LF.
* text=auto eol=lf

*.php text eol=lf whitespace=blank-at-eol,blank-at-eof,space-before-tab,tab-in-indent,tabwidth=4 diff=php

# Don't include in archives
.buildignore export-ignore
.gitattributes export-ignore
.travis.yml export-ignore
CONDUCT.md export-ignore
documentation/ export-ignore
phpspec.yml.dist export-ignore
README.md export-ignore
specs/ export-ignore
CONTENT;

        $analyser = (new Analyser())->setDirectory($this->temporaryDirectory);

        $actualGitattributesContent = $analyser->getExpectedGitattributesContent(
            $artifactsWithoutExportIgnore
        );

        $this->assertEquals(
            $expectedGitattributesContent,
            $actualGitattributesContent
        );
    }

    /**
     * @test
     */
    public function addsAutoEolToGitattributesContentWhenNoGitattributesFilePresent()
    {
        $artifactFilenames = [
            'README.md',
            '.travis.yml'
        ];
        $analyser = (new Analyser())->setDirectory($this->temporaryDirectory);
        $actualGitattributesContent = $analyser->getExpectedGitattributesContent(
            $artifactFilenames
        );

        $expectedGitattributesContent = <<<CONTENT
* text=auto eol=lf

.travis.yml export-ignore
README.md export-ignore

CONTENT;

        $this->assertEquals(
            $expectedGitattributesContent,
            $actualGitattributesContent
        );
    }

    /**
     * @test
     */
    public function returnsEmptyExpectedGitattributesContent()
    {
        $artifactFilenames = [
            'NOPE',
            'ZOPE'
        ];

        $this->createTemporaryFiles(
            $artifactFilenames
        );

        $analyser = (new Analyser())->setDirectory($this->temporaryDirectory);
        $actualGitattributesContent = $analyser->getExpectedGitattributesContent();

        $this->assertEquals(
            '',
            $actualGitattributesContent
        );
    }

    /**
     * @test
     */
    public function returnsFalseWhenGitattributesFileHasGaps()
    {
        $gitattributesContent = <<<CONTENT
phpspec.yml.dist export-ignore
CONDUCT.md export-ignore
CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $artifactFilenames = [
            'CONDUCT.md',
            'phpspec.yml.dist'
        ];

        $this->createTemporaryFiles(
            $artifactFilenames
        );

        $analyser = (new Analyser())->setDirectory($this->temporaryDirectory);

        $this->assertFalse(
            $analyser->hasCompleteExportIgnores()
        );
    }

    /**
     * @test
     */
    public function hasGitattributesFileOnExistingGitattributesFile()
    {
        $temporaryGitattributesFile = $this->temporaryDirectory
            . DIRECTORY_SEPARATOR
            . '.gitattributes';

        touch($temporaryGitattributesFile);

        $analyser = (new Analyser())->setDirectory($this->temporaryDirectory);

        $this->assertTrue(
            $analyser->hasGitattributesFile()
        );
    }

    /**
     * @test
     */
    public function hasGitattributesFileFailsOnNonExistingGitattributesFile()
    {
        $temporaryGitattributesFile = $this->temporaryDirectory
            . DIRECTORY_SEPARATOR
            . '.nope';

        touch($temporaryGitattributesFile);

        $analyser = (new Analyser())->setDirectory($this->temporaryDirectory);

        $this->assertFalse(
            $analyser->hasGitattributesFile()
        );
    }

    /**
     * @test
     */
    public function collectExpectedExportIgnoresReturnsExpectedEntries()
    {
        $temporaryGitattributesFile = $this->temporaryDirectory
            . DIRECTORY_SEPARATOR
            . '.gitattributes';

        $artifactFilenames = [
            'README.md',
            'Makefile',
            '.travis.yml',
            '.buildignore',
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['documentation']
        );

        $expectedExportIgnores = [
            '.buildignore',
            '.travis.yml',
            'documentation/',
            'Makefile',
            'README.md',
        ];

        $analyser = (new Analyser())->setDirectory($this->temporaryDirectory);

        $actualExportIgnores = $analyser->collectExpectedExportIgnores();

        $this->assertEquals(
            $expectedExportIgnores,
            $actualExportIgnores
        );
    }

    /**
     * @test
     */
    public function returnsAnEmptyArrayOnNonExistingGitattributesFile()
    {
        $analyser = (new Analyser())->setDirectory($this->temporaryDirectory);

        $actualExportIgnores = $analyser->getPresentExportIgnores();

        $this->assertEquals(
            [],
            $actualExportIgnores
        );
    }

    /**
     * @test
     */
    public function returnsExpectedPresentExportIgnores()
    {
        $gitattributesContent = <<<CONTENT
* text=auto eol=lf

phpspec.yml.dist export-ignore
.buildignore export-ignore
.travis.yml export-ignore
CONDUCT.md export-ignore
specs/ export-ignore
CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $expectedExportIgnores = [
            '.buildignore',
            '.travis.yml',
            'CONDUCT.md',
            'phpspec.yml.dist',
            'specs/'
        ];

        $analyser = (new Analyser())->setDirectory($this->temporaryDirectory);

        $actualExportIgnores = $analyser->getPresentExportIgnores();

        $this->assertEquals(
            $expectedExportIgnores,
            $actualExportIgnores
        );
    }

    /**
     * @test
     */
    public function defaultExportIgnoresGlobPatternIsOverwritable()
    {
        $analyser = (new Analyser())
            ->setDirectory($this->temporaryDirectory)
            ->setGlobPattern('{*.txt}*');

        $artifactFilenamesMatchingGlob = [
            'Z.txt',
            'A.txt',
            'B.txt',
        ];

        $this->createTemporaryFiles(
            $artifactFilenamesMatchingGlob
        );

        $actualExportIgnores = $analyser->collectExpectedExportIgnores();

        sort($artifactFilenamesMatchingGlob);

        $this->assertEquals(
            $artifactFilenamesMatchingGlob,
            $actualExportIgnores
        );
    }
}
