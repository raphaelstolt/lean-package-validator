<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Tests;

use Mockery;
use Stolt\LeanPackage\Analyser;
use Stolt\LeanPackage\Exceptions\InvalidGlobPattern;
use Stolt\LeanPackage\Exceptions\InvalidGlobPatternFile;
use Stolt\LeanPackage\Exceptions\NonExistentGlobPatternFile;
use Stolt\LeanPackage\Presets\Finder;
use Stolt\LeanPackage\Presets\PhpPreset;

class AnalyserTest extends TestCase
{
    /**
     * Set up test environment.
     */
    protected function setUp(): void
    {
        $this->setUpTemporaryDirectory();
    }

    /**
     * Tear down test environment.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        if (\is_dir($this->temporaryDirectory)) {
            $this->removeDirectory($this->temporaryDirectory);
        }
    }

    /**
     * @test
     */
    public function hasCompleteExportIgnoresFailsOnEmptyExportIgnores(): void
    {
        $analyser = (new Analyser(new Finder(new PhpPreset())))->setDirectory($this->temporaryDirectory);
        $this->assertFalse(
            $analyser->hasCompleteExportIgnores()
        );
    }

    /**
     * @test
     * @throws InvalidGlobPattern
     */
    public function hasCompleteExportIgnoresFailsOnNonExistingGitattributesFile(): void
    {
        $mock = Mockery::mock(Analyser::class)->makePartial();

        $globPattern = '{' . \implode(',', (new PhpPreset())->getPresetGlob()) . '}*';
        $mock->setGlobPattern($globPattern);

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
    public function returnsTrueWhenDirectoryHasCompleteExportIgnores(): void
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

        $analyser = (new Analyser(new Finder(new PhpPreset())))->setDirectory($this->temporaryDirectory);

        $this->assertTrue(
            $analyser->hasCompleteExportIgnores()
        );
    }

    /**
     * @test
     */
    public function analyseOnNonExistingDirectoryThrowsExpectedException(): void
    {
        $nonExistingDirectory = '/tmp/non-existing_directory';
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            "Directory {$nonExistingDirectory} doesn't exist."
        );
        (new Analyser(new Finder(new PhpPreset())))->setDirectory($nonExistingDirectory);
    }

    /**
     * @test
     */
    public function gitattributesFileHasAnAccessor(): void
    {
        $analyser = (new Analyser(new Finder(new PhpPreset())))->setDirectory($this->temporaryDirectory);
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
    public function directoryHasAnAccessor(): void
    {
        $analyser = (new Analyser(new Finder(new PhpPreset())))->setDirectory($this->temporaryDirectory);
        $this->assertEquals($this->temporaryDirectory, $analyser->getDirectory());
    }

    /**
     * @test
     */
    public function returnsExpectedGitattributesContent(): void
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
.gitattributes export-ignore
.travis.yml export-ignore
documentation/ export-ignore
Makefile export-ignore
README.md export-ignore

CONTENT;

        $analyser = (new Analyser(new Finder(new PhpPreset())))->setDirectory($this->temporaryDirectory);

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
    public function expectedFileMatchesAreInExpectedGitattributesContent(): void
    {
        $artifactFilenames = [
            'Vagrantfile',
            'makefile',
            'Boxfile',
        ];

        $this->createTemporaryFiles(
            $artifactFilenames
        );

        $expectedGitattributesContent = <<<CONTENT
* text=auto eol=lf

.gitattributes export-ignore
Boxfile export-ignore
makefile export-ignore
Vagrantfile export-ignore

CONTENT;

        $analyser = (new Analyser(new Finder(new PhpPreset())))->setDirectory($this->temporaryDirectory);

        $actualGitattributesContent = $analyser->getExpectedGitattributesContent();

        $this->assertEquals(
            $expectedGitattributesContent,
            $actualGitattributesContent
        );
    }

    /**
     * @test
     */
    public function nonExportIgnoresContentIsEmptyForNonexistentGitattributesFile(): void
    {
        $analyser = (new Analyser(new Finder(new PhpPreset())))->setDirectory($this->temporaryDirectory);

        $this->assertEquals('', $analyser->getPresentNonExportIgnoresContent());
    }

    /**
     * @test
     */
    public function nonExportIgnoresContentOfGitattributesFileIsReturned(): void
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

        $analyser = (new Analyser(new Finder(new PhpPreset())))->setDirectory($this->temporaryDirectory);

        $actualGitattributesContent = $analyser->getPresentNonExportIgnoresContent();

        $exportIgnoresPlacementPlaceholder = Analyser::EXPORT_IGNORES_PLACEMENT_PLACEHOLDER;
        $expectedGitattributesContent = <<<CONTENT
# Auto-detect text files, ensure they use LF.
* text=auto eol=lf

*.php text eol=lf whitespace=blank-at-eol,blank-at-eof,space-before-tab,tab-in-indent,tabwidth=4 diff=php

# Don't include in archives
{$exportIgnoresPlacementPlaceholder}

CONTENT;

        $this->assertEquals(
            $expectedGitattributesContent,
            $actualGitattributesContent
        );
    }

    /**
     * @test
     */
    public function getExpectedGitattributesContentKeepsNonExportIgnoreEntries(): void
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

        $analyser = (new Analyser(new Finder(new PhpPreset())))->setDirectory($this->temporaryDirectory);

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
    public function addsAutoEolToGitattributesContentWhenNoGitattributesFilePresent(): void
    {
        $artifactFilenames = [
            'README.md',
            '.travis.yml'
        ];
        $analyser = (new Analyser(new Finder(new PhpPreset())))->setDirectory($this->temporaryDirectory);
        $actualGitattributesContent = $analyser->getExpectedGitattributesContent(
            $artifactFilenames
        );

        $expectedGitattributesContent = <<<CONTENT
* text=auto eol=lf

.gitattributes export-ignore
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
    public function returnsEmptyExpectedGitattributesContent(): void
    {
        $artifactFilenames = [
            'NOPE',
            'ZOPE'
        ];

        $this->createTemporaryFiles(
            $artifactFilenames
        );

        $analyser = (new Analyser(new Finder(new PhpPreset())))->setDirectory($this->temporaryDirectory);
        $actualGitattributesContent = $analyser->getExpectedGitattributesContent();

        $this->assertEquals(
            '',
            $actualGitattributesContent
        );
    }

    /**
     * @test
     */
    public function returnsFalseWhenGitattributesFileHasGaps(): void
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

        $analyser = (new Analyser(new Finder(new PhpPreset())))->setDirectory($this->temporaryDirectory);

        $this->assertFalse(
            $analyser->hasCompleteExportIgnores()
        );
    }

    /**
     * @test
     */
    public function hasGitattributesFileOnExistingGitattributesFile(): void
    {
        $temporaryGitattributesFile = $this->temporaryDirectory
            . DIRECTORY_SEPARATOR
            . '.gitattributes';

        \touch($temporaryGitattributesFile);

        $analyser = (new Analyser(new Finder(new PhpPreset())))->setDirectory($this->temporaryDirectory);

        $this->assertTrue(
            $analyser->hasGitattributesFile()
        );
    }

    /**
     * @test
     */
    public function hasGitattributesFileFailsOnNonExistingGitattributesFile(): void
    {
        $temporaryGitattributesFile = $this->temporaryDirectory
            . DIRECTORY_SEPARATOR
            . '.nope';

        \touch($temporaryGitattributesFile);

        $analyser = (new Analyser(new Finder(new PhpPreset())))->setDirectory($this->temporaryDirectory);

        $this->assertFalse(
            $analyser->hasGitattributesFile()
        );
    }

    /**
     * @test
     */
    public function collectExpectedExportIgnoresReturnsExpectedEntries(): void
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

        $analyser = (new Analyser(new Finder(new PhpPreset())))->setDirectory($this->temporaryDirectory);

        $actualExportIgnores = $analyser->collectExpectedExportIgnores();

        $this->assertEquals(
            $expectedExportIgnores,
            $actualExportIgnores
        );
    }

    /**
     * @test
     */
    public function returnsAnEmptyArrayOnNonExistingGitattributesFile(): void
    {
        $analyser = (new Analyser(new Finder(new PhpPreset())))->setDirectory($this->temporaryDirectory);

        $actualExportIgnores = $analyser->getPresentExportIgnores();

        $this->assertEquals(
            [],
            $actualExportIgnores
        );
    }

    /**
     * @test
     */
    public function returnsExpectedPresentExportIgnores(): void
    {
        $gitattributesContent = <<<CONTENT
* text=auto eol=lf

phpspec.yml.dist export-ignore
.buildignore export-ignore
.travis.yml export-ignore
CONDUCT.md export-ignore
specs/ export-ignore
CONTENT;

        $artifactFilenames = [
'phpspec.yml.dist',
'.buildignore',
'.travis.yml',
'CONDUCT.md',
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs']
        );

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $expectedExportIgnores = [
            '.buildignore',
            '.travis.yml',
            'CONDUCT.md',
            'phpspec.yml.dist',
            'specs/'
        ];

        $analyser = (new Analyser(new Finder(new PhpPreset())))->setDirectory($this->temporaryDirectory);

        $actualExportIgnores = $analyser->getPresentExportIgnores();

        $this->assertEquals(
            $expectedExportIgnores,
            $actualExportIgnores
        );
    }

    /**
     * @test
     */
    public function nonExportIgnoresContentHasPlaceholderForExportIgnoresPlacement(): void
    {
        $gitattributesContent = <<<CONTENT
# A head comment
* text=auto eol=lf

# Some content before

.editorconfig export-ignore
.gitattributes export-ignore
.github/ export-ignore

# Some content after

CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $exportIgnoresPlacementPlaceholder = Analyser::EXPORT_IGNORES_PLACEMENT_PLACEHOLDER;

        $expectedNonExportIgnoresContent = <<<CONTENT
# A head comment
* text=auto eol=lf

# Some content before

{$exportIgnoresPlacementPlaceholder}

# Some content after

CONTENT;

        $analyser = (new Analyser(new Finder(new PhpPreset())))->setDirectory($this->temporaryDirectory);

        $actualNonExportIgnoresContentContent = $analyser->getPresentNonExportIgnoresContent();

        $this->assertEquals(
            $expectedNonExportIgnoresContent,
            $actualNonExportIgnoresContentContent
        );
    }

    /**
     * @test
     */
    public function returnsExpectedGitattributesContentWithPreservedLocation(): void
    {
        $artifactFilenames = [
            'README.md',
            'Makefile',
            '.editorconfig',
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['tests', '.github']
        );

        $gitattributesContent = <<<CONTENT
# A head comment
* text=auto eol=lf

# Some content before

.editorconfig export-ignore
.gitattributes export-ignore
.github/ export-ignore

# Some content after

# Some more content

CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $expectedGitattributesContent = <<<CONTENT
# A head comment
* text=auto eol=lf

# Some content before

.editorconfig export-ignore
.gitattributes export-ignore
.github/ export-ignore
Makefile export-ignore
README.md export-ignore
tests/ export-ignore

# Some content after

# Some more content

CONTENT;

        $analyser = (new Analyser(new Finder(new PhpPreset())))->setDirectory($this->temporaryDirectory);
        $actualGitattributesContent = $analyser->getExpectedGitattributesContent();

        $this->assertEquals(
            $expectedGitattributesContent,
            $actualGitattributesContent
        );
    }

    /**
     * @test
     * @ticket 5 (https://github.com/raphaelstolt/lean-package-validator/issues/5)
     */
    public function varyingOrderDoesNotFailCompletenessCheck(): void
    {
        $artifactFilenames = [
            'README.md',
            'phpspec.yml.dist',
            'build.xml.dist',
            'Makefile',
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs']
        );

        $gitattributesContent = <<<CONTENT
* text=auto eol=lf

# Files to exclude from export
specs/ export-ignore
build.xml.dist export-ignore
README.md export-ignore
Makefile export-ignore
phpspec.yml.dist export-ignore
.gitattributes export-ignore

CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $analyser = (new Analyser(new Finder(new PhpPreset())))->setDirectory($this->temporaryDirectory);

        $this->assertTrue($analyser->hasCompleteExportIgnores());
    }

    /**
     * @test
     * @ticket 6 (https://github.com/raphaelstolt/lean-package-validator/issues/6)
     */
    public function varyingOrderDoesFailCompletenessCheckWhenEnforced(): void
    {
        $artifactFilenames = [
            'README.md',
            'phpspec.yml.dist',
            'build.xml.dist',
            'Makefile',
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs']
        );

        $gitattributesContent = <<<CONTENT
* text=auto eol=lf

# Files to exclude from export
specs/ export-ignore
build.xml.dist export-ignore
README.md export-ignore
Makefile export-ignore
phpspec.yml.dist export-ignore
.gitattributes export-ignore

CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $analyser = (new Analyser(new Finder(new PhpPreset())))->setDirectory($this->temporaryDirectory);
        $analyser->enableStrictOrderCamparison();

        $this->assertFalse($analyser->hasCompleteExportIgnores());
    }

    /**
     * @test
     */
    public function notPatternMatchingExportIgnoresArePreservedAssumedFileExists(): void
    {
        $artifactFilenames = [
            'README.md',
            'phpspec.yml.dist',
            'application-version-incrementor',
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs']
        );

        $gitattributesContent = <<<CONTENT
# A head comment
* text=auto eol=lf

# Files to exclude from export
.gitattributes export-ignore
application-version-incrementor export-ignore

# Other patterns

CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $expectedGitattributesContent = <<<CONTENT
# A head comment
* text=auto eol=lf

# Files to exclude from export
.gitattributes export-ignore
application-version-incrementor export-ignore
phpspec.yml.dist export-ignore
README.md export-ignore
specs/ export-ignore

# Other patterns

CONTENT;

        $analyser = (new Analyser(new Finder(new PhpPreset())))->setDirectory($this->temporaryDirectory);
        $actualGitattributesContent = $analyser->getExpectedGitattributesContent();

        $this->assertEquals(
            $expectedGitattributesContent,
            $actualGitattributesContent
        );
    }

    /**
     * @test
     */
    public function nonPatternsMatchingButMatchingExistingFilesArePreservedExportIgnores(): void
    {
        $artifactFilenames = [
            'changelog-generator',
            'README.md',
            'phpspec.yml.dist',
            'application-version-incrementor',
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs']
        );

        $gitattributesContent = <<<CONTENT
* text=auto eol=lf

changelog-generator export-ignore
.gitattributes export-ignore
application-version-incrementor export-ignore

CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $expectedExportIgnoresToPreserve = [
            'changelog-generator',
            'application-version-incrementor',
        ];

        $globPatternMatchingExportIgnores = [
            'README.md',
            'phpspec.yml.dist',
            'specs/',
            '.gitattributes',
        ];

        $analyser = (new Analyser(new Finder(new PhpPreset())))->setDirectory($this->temporaryDirectory);

        $actualExportIgnoresToPreserve = $analyser->getPresentExportIgnoresToPreserve(
            $globPatternMatchingExportIgnores
        );

        $this->assertEquals(
            $expectedExportIgnoresToPreserve,
            $actualExportIgnoresToPreserve
        );
    }

    /**
     * @test
     */
    public function notPatternMatchingExportIgnoresDoNotFailCompletenessCheck(): void
    {
        $artifactFilenames = [
            'changelog-generator',
            'README.md',
            'phpspec.yml.dist',
            'application-version-incrementor',
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs']
        );

        $gitattributesContent = <<<CONTENT
* text=auto eol=lf

.gitattributes export-ignore
application-version-incrementor export-ignore
changelog-generator export-ignore
phpspec.yml.dist export-ignore
README.md export-ignore
specs/ export-ignore
CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $analyser = (new Analyser(new Finder(new PhpPreset())))->setDirectory($this->temporaryDirectory);
        $this->assertTrue($analyser->hasCompleteExportIgnores());
    }

    /**
     * @test
     * @group glob
     * @ticket 14 (https://github.com/raphaelstolt/lean-package-validator/issues/14)
     */
    public function captainHookConfigurationFileIsInDefaultPattern(): void
    {
        $artifactFilenames = [
            'README.md',
            'captainhook.json'
        ];

        $this->createTemporaryFiles(
            $artifactFilenames
        );

        $expectedGitattributesContent = <<<CONTENT
* text=auto eol=lf

.gitattributes export-ignore
captainhook.json export-ignore
README.md export-ignore

CONTENT;

        $analyser = (new Analyser(new Finder(new PhpPreset())))->setDirectory($this->temporaryDirectory);
        $actualGitattributesContent = $analyser->getExpectedGitattributesContent();

        $this->assertEquals(
            $expectedGitattributesContent,
            $actualGitattributesContent
        );
    }

    /**
     * @test
     * @group glob
     * @ticket 9 (https://github.com/raphaelstolt/lean-package-validator/issues/9)
     */
    public function nonExistingGlobPatternFileThrowsExpectedException(): void
    {
        $fixturesDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'fixtures';
        $globPatternFile = $fixturesDirectory . DIRECTORY_SEPARATOR . '.non-existent-lpv';

        $this->expectException(NonExistentGlobPatternFile::class);

        $analyser = (new Analyser(new Finder(new PhpPreset())))
            ->setDirectory($this->temporaryDirectory)
            ->setGlobPatternFromFile($globPatternFile);
    }

    /**
     * @test
     * @group glob
     * @ticket 9 (https://github.com/raphaelstolt/lean-package-validator/issues/9)
     */
    public function emptyGlobPatternFileThrowsExpectedException(): void
    {
        $temporaryLpvFile = $this->temporaryDirectory
            . DIRECTORY_SEPARATOR
            . '.lpv';
        $lpvContent = '';

        $this->createTemporaryGlobPatternFile($lpvContent);
        $this->expectException(InvalidGlobPatternFile::class);

        $analyser = (new Analyser(new Finder(new PhpPreset())))
            ->setDirectory($this->temporaryDirectory)
            ->setGlobPatternFromFile($temporaryLpvFile);
    }

    /**
     * @test
     * @group glob
     * @ticket 9 (https://github.com/raphaelstolt/lean-package-validator/issues/9)
     */
    public function defaultExportIgnoresGlobPatternIsOverwritableFromFile(): void
    {
        $temporaryLpvFile = $this->temporaryDirectory
            . DIRECTORY_SEPARATOR
            . '.lpv';
        $lpvContent = <<<CONTENT
*file
*.dist

CONTENT;

        $this->createTemporaryGlobPatternFile($lpvContent);

        $artifactFilenamesMatchingGlob = [
            'build.xml.dist',
            'Dockerfile',
            'Phulpfile',
            'resultset.xml.dist',
        ];

        $this->createTemporaryFiles(
            $artifactFilenamesMatchingGlob
        );

        $analyser = (new Analyser(new Finder(new PhpPreset())))
            ->setDirectory($this->temporaryDirectory)
            ->setGlobPatternFromFile($temporaryLpvFile);

        $actualExportIgnores = $analyser->collectExpectedExportIgnores();

        \sort($artifactFilenamesMatchingGlob, SORT_STRING | SORT_FLAG_CASE);

        $this->assertEquals(
            $artifactFilenamesMatchingGlob,
            $actualExportIgnores
        );
    }

    /**
     * @test
     * @group glob
     */
    public function defaultExportIgnoresGlobPatternIsOverwritable(): void
    {
        $analyser = (new Analyser(new Finder(new PhpPreset())))
            ->setDirectory($this->temporaryDirectory)
            ->setGlobPattern('{*.txt,*.yml}*');

        $artifactFilenamesMatchingGlob = [
            'Z.txt',
            'A.txt',
            'B.txt',
        ];

        $this->createTemporaryFiles(
            $artifactFilenamesMatchingGlob
        );

        $actualExportIgnores = $analyser->collectExpectedExportIgnores();

        \sort($artifactFilenamesMatchingGlob);

        $this->assertEquals(
            $artifactFilenamesMatchingGlob,
            $actualExportIgnores
        );
    }

    /**
     * @test
     * @group glob
     */
    public function emptyGlobPatternThrowsExpectedException(): void
    {
        $this->expectException(InvalidGlobPattern::class);
        (new Analyser(new Finder(new PhpPreset())))
            ->setDirectory($this->temporaryDirectory)
            ->setGlobPattern('');
    }

    /**
     * @test
     * @group glob
     */
    public function invalidGlobPatternBracesThrowsExpectedException(): void
    {
        $this->expectException(InvalidGlobPattern::class);
        $analyser = (new Analyser(new Finder(new PhpPreset())))
            ->setDirectory($this->temporaryDirectory)
            ->setGlobPattern('[fdofodsppfosdp]');
        // TODO: Fix smelly test
        $this->assertEquals($this->temporaryDirectory, $analyser->getDirectory());
    }

    /**
     * @test
     * @group glob
     */
    public function wildcardAfterBracesIsNotRaisingAnException(): void
    {
        $analyser = (new Analyser(new Finder(new PhpPreset())))
            ->setDirectory($this->temporaryDirectory)
            ->setGlobPattern('{*.ymk, test.php}*');
        // TODO: Fix smelly test
        $this->assertEquals($this->temporaryDirectory, $analyser->getDirectory());
    }

    /**
     * @test
     * @group glob
     */
    public function emptyGlobPatternBracesContentThrowsExpectedException(): void
    {
        $this->expectException(InvalidGlobPattern::class);
        $analyser = (new Analyser(new Finder(new PhpPreset())))
            ->setDirectory($this->temporaryDirectory)
            ->setGlobPattern('{ }');
    }

    /**
     * @test
     * @group glob
     */
    public function singleGlobPatternThrowsExpectedException(): void
    {
        $this->expectException(InvalidGlobPattern::class);
        $analyser = (new Analyser(new Finder(new PhpPreset())))
            ->setDirectory($this->temporaryDirectory)
            ->setGlobPattern('{*.go}');
    }

    /**
     * @test
     * @group glob
     */
    public function globPatternWithEnclosedBracesAreConsideredValid(): void
    {
        $analyser = (new Analyser(new Finder(new PhpPreset())))
            ->setDirectory($this->temporaryDirectory)
            ->setGlobPattern('{{{M,m}ake,{B,b}ox,{V,v}agrant}file,RMT}');
        // TODO: Fix smelly test
        $this->assertEquals($this->temporaryDirectory, $analyser->getDirectory());
    }

    /**
     * @test
     */
    public function withDistEndingFilesAreNotExportIgnored(): void
    {
        $artifactFilenames = [
            'SUPPORT.md',
            'README.md',
            'humbug.json.dist',
        ];

        $this->createTemporaryFiles(
            $artifactFilenames
        );

        $expectedGitattributesContent = <<<CONTENT
* text=auto eol=lf

.gitattributes export-ignore
humbug.json.dist export-ignore
README.md export-ignore
SUPPORT.md export-ignore

CONTENT;

        $analyser = (new Analyser(new Finder(new PhpPreset())))->setDirectory($this->temporaryDirectory);
        $actualGitattributesContent = $analyser->getExpectedGitattributesContent();

        $this->assertEquals(
            $expectedGitattributesContent,
            $actualGitattributesContent
        );
    }

    /**
     * @test
     * @ticket 15 (https://github.com/raphaelstolt/lean-package-validator/issues/15)
     */
    public function licenseFileIsNotExportIgnored(): void
    {
        $artifactFilenames = [
            'LICENSE.txt',
            'README.md',
            'phpspec.yml.dist',
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs']
        );

        $expectedGitattributesContent = <<<CONTENT
* text=auto eol=lf

.gitattributes export-ignore
phpspec.yml.dist export-ignore
README.md export-ignore
specs/ export-ignore

CONTENT;

        $analyser = (new Analyser(new Finder(new PhpPreset())))->setDirectory($this->temporaryDirectory)->keepLicense();
        $actualGitattributesContent = $analyser->getExpectedGitattributesContent();

        $this->assertTrue($analyser->isKeepLicenseEnabled());
        $this->assertEquals(
            $expectedGitattributesContent,
            $actualGitattributesContent
        );
    }

    /**
     * @test
     * @ticket 24 (https://github.com/raphaelstolt/lean-package-validator/issues/24)
     */
    public function directoriesOnlyExportIgnoredOnce(): void
    {
        $artifactFilenames = [
            'LICENSE.md',
            'README.md',
            'phpunit.xml.dist',
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['tests', 'specs', 'docs']
        );


        $gitattributesContent = <<<CONTENT
.gitattributes export-ignore
LICENSE.md export-ignore
phpunit.xml.dist export-ignore
README.md export-ignore
/specs export-ignore
/docs/* export-ignore
/tests export-ignore
CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $expectedGitattributesContent = <<<CONTENT
.gitattributes export-ignore
docs/ export-ignore
LICENSE.md export-ignore
phpunit.xml.dist export-ignore
README.md export-ignore
specs/ export-ignore
tests/ export-ignore
CONTENT;

        $analyser = (new Analyser(new Finder(new PhpPreset())))->setDirectory($this->temporaryDirectory);
        $actualGitattributesContent = $analyser->getExpectedGitattributesContent();

        $this->assertEquals(
            $expectedGitattributesContent,
            $actualGitattributesContent
        );
    }

    /**
     * @test
     */
    public function exportIgnoresAreAligned(): void
    {
        $artifactFilenames = [
            'LICENSE.txt',
            'README.md',
            'phpspec.yml.dist',
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs']
        );

        $expectedGitattributesContent = <<<CONTENT
* text=auto eol=lf

.gitattributes   export-ignore
LICENSE.txt      export-ignore
phpspec.yml.dist export-ignore
README.md        export-ignore
specs/           export-ignore

CONTENT;

        $analyser = (new Analyser(new Finder(new PhpPreset())))->setDirectory($this->temporaryDirectory)->alignExportIgnores();
        $actualGitattributesContent = $analyser->getExpectedGitattributesContent();

        $this->assertTrue($analyser->isAlignExportIgnoresEnabled());
        $this->assertEquals(
            $expectedGitattributesContent,
            $actualGitattributesContent
        );
    }

    /**
     * @test
     * @ticket 4 (https://github.com/raphaelstolt/lean-package-validator/issues/4)
     */
    public function precedingSlashesAreDetected(): void
    {
        $artifactFilenames = [
            'changelog-generator',
            'README.md',
            'phpspec.yml.dist',
            'application-version-incrementor',
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs']
        );

        $gitattributesContent = <<<CONTENT
/.gitattributes export-ignore
/application-version-incrementor export-ignore
/changelog-generator export-ignore
phpspec.yml.dist export-ignore
/README.md export-ignore
specs/ export-ignore
CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $analyser = (new Analyser(new Finder(new PhpPreset())))->setDirectory($this->temporaryDirectory);
        $this->assertFalse($analyser->hasPrecedingSlashesInExportIgnorePattern());
        $this->assertTrue($analyser->hasCompleteExportIgnores());
        $this->assertTrue($analyser->hasPrecedingSlashesInExportIgnorePattern());
    }

    /**
     * @test
     * @ticket 12 (https://github.com/raphaelstolt/lean-package-validator/issues/12)
     */
    public function missingTextAutoConfigurationIsDetected(): void
    {
        $artifactFilenames = [
            'README.md',
            'phpspec.yml.dist',
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs']
        );

        $gitattributesContent = <<<CONTENT
.gitattributes export-ignore
phpspec.yml.dist export-ignore
README.md export-ignore
specs/ export-ignore
CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $analyser = (new Analyser(new Finder(new PhpPreset())))->setDirectory($this->temporaryDirectory);
        $this->assertTrue($analyser->hasCompleteExportIgnores());
        $this->assertFalse($analyser->hasTextAutoConfiguration());
    }

    /**
     * @test
     * @ticket 12 (https://github.com/raphaelstolt/lean-package-validator/issues/12)
     */
    public function presentTextAutoConfigurationIsDetected(): void
    {
        $artifactFilenames = [
            'README.md',
            'phpspec.yml.dist',
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs']
        );

        $gitattributesContent = <<<CONTENT
# some comment
* text=auto eol=lf

.gitattributes export-ignore
phpspec.yml.dist export-ignore
README.md export-ignore
specs/ export-ignore
CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $analyser = (new Analyser(new Finder(new PhpPreset())))->setDirectory($this->temporaryDirectory);
        $this->assertTrue($analyser->hasCompleteExportIgnores());
        $this->assertTrue($analyser->hasTextAutoConfiguration());
    }

    /**
     * @test
     */
    public function returnsEmptyPatternsWhenNoGitignoreFilePresent(): void
    {
        $analyser = (new Analyser(new Finder(new PhpPreset())))->setDirectory($this->temporaryDirectory);
        $this->assertEquals([], $analyser->getGitignoredPatterns());
    }

    /**
     * @test
     */
    public function returnsExpectedGitignoredPatterns(): void
    {
        $gitignoreContent = <<<CONTENT
# Composer files
vendor/*
composer.lock

# Test related files
coverage-reports/

 # Cache files
.php_cs.cache

CONTENT;

        $this->createTemporaryGitignoreFile($gitignoreContent);

        $analyser = (new Analyser(new Finder(new PhpPreset())))->setDirectory($this->temporaryDirectory);

        $expectedGitignorePatterns = [
            'vendor/*',
            'composer.lock',
            'coverage-reports',
            'coverage-reports/',
            '.php_cs.cache',
        ];

        $this->assertEquals(
            $expectedGitignorePatterns,
            $analyser->getGitignoredPatterns()
        );
    }

    /**
     * @test
     * @ticket 17 (https://github.com/raphaelstolt/lean-package-validator/issues/17)
     */
    public function presentGitignoredFileIsExcludedFromValidation(): void
    {
        $artifactFilenames = [
            '.buildignore',
            'phpspec.yml.dist',
            'composer.lock',
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs']
        );

        $gitattributesContent = <<<CONTENT
.buildignore export-ignore
phpspec.yml.dist export-ignore
specs/ export-ignore
.gitattributes export-ignore
.gitignore export-ignore

CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $gitignoreContent = <<<CONTENT
/vendor/*
/coverage-reports
composer.lock
.php_cs.cache

CONTENT;

        $this->createTemporaryGitignoreFile($gitignoreContent);

        $analyser = (new Analyser(new Finder(new PhpPreset())))->setDirectory($this->temporaryDirectory);
        $this->assertTrue($analyser->hasCompleteExportIgnores());
    }

    /**
     * @test
     * @ticket 21 (https://github.com/raphaelstolt/lean-package-validator/issues/21)
     */
    public function presentGitignoredSpecsCoverageDirectoryIsExcludedFromValidation(): void
    {
        $artifactFilenames = [
            '.buildignore',
            'phpspec.yml.dist',
            'composer.lock',
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs', 'specs-coverage', 'some-other-dir', 'bar']
        );

        $gitattributesContent = <<<CONTENT
.buildignore export-ignore
phpspec.yml.dist export-ignore
specs/ export-ignore
.gitattributes export-ignore
.gitignore export-ignore

CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $gitignoreContent = <<<CONTENT
/vendor/*
/specs-coverage
composer.lock
some-other-dir/
bar
.php_cs.cache

CONTENT;

        $this->createTemporaryGitignoreFile($gitignoreContent);

        $analyser = (new Analyser(new Finder(new PhpPreset())))->setDirectory($this->temporaryDirectory);
        $this->assertTrue($analyser->hasCompleteExportIgnores());
    }

    /**
     * @test
     * @group glob
     */
    public function returnsExpectedDefaultGlobPatterns(): void
    {
        $analyser = (new Analyser(new Finder(new PhpPreset())))->setDirectory($this->temporaryDirectory);

        $expectedDefaultGlobPatterns = [
            '.*',
            '*.lock',
            '*.txt',
            '*.rst',
            '*.{md,MD}',
            '*.xml',
            '*.yml',
            'phpunit*',
            'appveyor.yml',
            'box.json',
            'captainhook.json',
            'infection*',
            'phpstan*',
            'sonar*',
            '*.dist.*',
            '*.dist',
            '{B,b}uild*',
            '{D,d}oc*',
            '{T,t}ool*',
            '{T,t}est*',
            '{S,s}pec*',
            '{E,e}xample*',
            'LICENSE',
            '{{M,m}ake,{B,b}ox,{V,v}agrant,{P,p}hulp}file',
            'RMT'
        ];

        $this->assertEquals(
            $expectedDefaultGlobPatterns,
            $analyser->getDefaultGlobPattern()
        );
    }
}
