<?php

namespace Stolt\LeanPackage\Tests\Commands;

use Mockery;
use Mockery\MockInterface;
use phpmock\MockBuilder;
use Stolt\LeanPackage\Analyser;
use Stolt\LeanPackage\Archive;
use Stolt\LeanPackage\Archive\Validator;
use Stolt\LeanPackage\Commands\ValidateCommand;
use Stolt\LeanPackage\Exceptions\GitattributesCreationFailed;
use Stolt\LeanPackage\Exceptions\NoLicenseFilePresent;
use Stolt\LeanPackage\Tests\CommandTester;
use Stolt\LeanPackage\Tests\TestCase;
use Symfony\Component\Console\Application;

class ValidateCommandTest extends TestCase
{
    /**
     * @var \Symfony\Component\Console\Application
     */
    private $application;

    /**
     * Set up test environment.
     */
    protected function setUp(): void
    {
        $this->setUpTemporaryDirectory();
        if (!defined('WORKING_DIRECTORY')) {
            define('WORKING_DIRECTORY', $this->temporaryDirectory);
        }
        $this->application = $this->getApplication();
    }

    /**
     * Tear down test environment.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        if (is_dir($this->temporaryDirectory)) {
            $this->removeDirectory($this->temporaryDirectory);
        }
    }

    /**
     * @test
     */
    public function validateOnNonExistentGitattributesFilesSuggestsCreation()
    {
        $artifactFilenames = [
            'CONDUCT.md',
            '.travis.yml',
            '.buildignore',
            'phpspec.yml.dist',
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs']
        );

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
        ]);

        $expectedDisplay = <<<CONTENT
Warning: There is no .gitattributes file present in {$this->temporaryDirectory}.

Would expect the following .gitattributes file content:
* text=auto eol=lf

.buildignore export-ignore
.gitattributes export-ignore
.travis.yml export-ignore
CONDUCT.md export-ignore
phpspec.yml.dist export-ignore
specs/ export-ignore

Use the --create|-c option to create a .gitattributes file with the shown content.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > 0);
    }

    /**
     * @test
     */
    public function validateOnNonExistentGitattributesFilesSuggestsCreationWithAlignment()
    {
        $artifactFilenames = [
            'CONDUCT.md',
            '.travis.yml',
            '.buildignore',
            'phpspec.yml.dist',
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs']
        );

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
            '--align-export-ignores' => true,
        ]);

        $expectedDisplay = <<<CONTENT
Warning: There is no .gitattributes file present in {$this->temporaryDirectory}.

Would expect the following .gitattributes file content:
* text=auto eol=lf

.buildignore     export-ignore
.gitattributes   export-ignore
.travis.yml      export-ignore
CONDUCT.md       export-ignore
phpspec.yml.dist export-ignore
specs/           export-ignore

Use the --create|-c option to create a .gitattributes file with the shown content.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > 0);
    }

    /**
     * @test
     * @ticket 16 (https://github.com/raphaelstolt/lean-package-validator/issues/16)
     */
    public function gitattributesFileWithNonExportIgnoreContentShowsExpectedContent()
    {
        $artifactFilenames = [
            '.gitattributes',
            '.gitignore',
            '.scrutinizer.yml',
            '.travis.yml',
            'CHANGELOG.md',
            'README.md',
            'composer.json',
            'phpunit.xml',
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['tests']
        );

        $gitattributesContent = <<<CONTENT
# Auto detect text files and perform LF normalization
*     text=auto

# Force text mode
*.php text diff=php
*.xml text

/tests/bugs/*.php diff=php eol=lf
CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
            '--enforce-strict-order' => true,
        ]);

        $expectedDisplay = <<<CONTENT
The present .gitattributes file is considered invalid.

Would expect the following .gitattributes file content:
# Auto detect text files and perform LF normalization
*     text=auto

# Force text mode
*.php text diff=php
*.xml text

/tests/bugs/*.php diff=php eol=lf

.gitattributes export-ignore
.gitignore export-ignore
.scrutinizer.yml export-ignore
.travis.yml export-ignore
CHANGELOG.md export-ignore
phpunit.xml export-ignore
README.md export-ignore
tests/ export-ignore

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > 0);
    }

    /**
     * @test
     * @ticket 13 (https://github.com/raphaelstolt/lean-package-validator/issues/13)
     */
    public function gitattributesIsInSuggestedFileContent()
    {
        $artifactFilenames = [
            'CONDUCT.md',
            'phpspec.yml.dist',
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs']
        );

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
        ]);

        $expectedDisplay = <<<CONTENT
Warning: There is no .gitattributes file present in {$this->temporaryDirectory}.

Would expect the following .gitattributes file content:
* text=auto eol=lf

.gitattributes export-ignore
CONDUCT.md export-ignore
phpspec.yml.dist export-ignore
specs/ export-ignore

Use the --create|-c option to create a .gitattributes file with the shown content.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > 0);
    }

    /**
     * @test
     * @ticket 15 (https://github.com/raphaelstolt/lean-package-validator/issues/15)
     */
    public function licenseIsInSuggestedFileContentPerDefault()
    {
        $artifactFilenames = [
            'CONDUCT.md',
            'phpspec.yml.dist',
            'License.rst',
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs']
        );

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
        ]);

        $expectedDisplay = <<<CONTENT
Warning: There is no .gitattributes file present in {$this->temporaryDirectory}.

Would expect the following .gitattributes file content:
* text=auto eol=lf

.gitattributes export-ignore
CONDUCT.md export-ignore
License.rst export-ignore
phpspec.yml.dist export-ignore
specs/ export-ignore

Use the --create|-c option to create a .gitattributes file with the shown content.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > 0);
    }

    /**
     * @test
     * @ticket 15 (https://github.com/raphaelstolt/lean-package-validator/issues/15)
     */
    public function licenseIsNotInSuggestedFileContent()
    {
        $artifactFilenames = [
            'CONDUCT.md',
            'phpspec.yml.dist',
            'License.rst',
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs']
        );

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
            '--keep-license' => true,
        ]);

        $expectedDisplay = <<<CONTENT
Warning: There is no .gitattributes file present in {$this->temporaryDirectory}.

Would expect the following .gitattributes file content:
* text=auto eol=lf

.gitattributes export-ignore
CONDUCT.md export-ignore
phpspec.yml.dist export-ignore
specs/ export-ignore

Use the --create|-c option to create a .gitattributes file with the shown content.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > 0);
    }

    /**
     * @test
     * @group glob
     * @ticket 15 (https://github.com/raphaelstolt/lean-package-validator/issues/15)
     */
    public function licenseIsNotInSuggestedFileContentWithCustomGlobPattern()
    {
        $artifactFilenames = [
            'CONDUCT.md',
            'phpspec.yml.dist',
            'License.md',
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs']
        );

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
            '--keep-license' => true,
            '--glob-pattern' => '{.*,*.md,*.dist,LICENSE.md,spec*}*',
        ]);

        $expectedDisplay = <<<CONTENT
Warning: There is no .gitattributes file present in {$this->temporaryDirectory}.

Would expect the following .gitattributes file content:
* text=auto eol=lf

.gitattributes export-ignore
CONDUCT.md export-ignore
phpspec.yml.dist export-ignore
specs/ export-ignore

Use the --create|-c option to create a .gitattributes file with the shown content.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > 0);
    }

    /**
     * @test
     * @ticket 15 (https://github.com/raphaelstolt/lean-package-validator/issues/15)
     */
    public function presentExportIgnoredLicenseWithKeepLicenseOptionInvalidatesResult()
    {
        $artifactFilenames = [
            'CONDUCT.md',
            'phpspec.yml.dist',
            'License.rst',
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs']
        );

        $gitattributesContent = <<<CONTENT
*    text=auto eol=lf

.gitattributes export-ignore
License.rst export-ignore
phpspec.yml.dist export-ignore
specs/ export-ignore
CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
            '--keep-license' => true,
        ]);

        $expectedDisplay = <<<CONTENT
The present .gitattributes file is considered invalid.

Would expect the following .gitattributes file content:
*    text=auto eol=lf

.gitattributes export-ignore
CONDUCT.md export-ignore
phpspec.yml.dist export-ignore
specs/ export-ignore

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > 0);
    }

    /**
     * @test
     * @ticket 15 (https://github.com/raphaelstolt/lean-package-validator/issues/15)
     */
    public function archiveWithoutLicenseFileIsConsideredInvalid()
    {
        $mock = Mockery::mock(
            'Stolt\LeanPackage\Archive\Validator[validate, shouldHaveLicenseFile]',
            [new Archive(WORKING_DIRECTORY, 'foo')]
        );

        $mock->shouldReceive('validate')
            ->once()
            ->withAnyArgs()
            ->andThrow(new NoLicenseFilePresent);

        $mock->shouldReceive('shouldHaveLicenseFile')
            ->once()
            ->withAnyArgs()
            ->andReturn($mock);

        $application = $this->getApplicationWithMockedArchiveValidator($mock);

        $gitattributesContent = <<<CONTENT
phpspec.yml.dist export-ignore
CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $command = $application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
            '--validate-git-archive' => true,
            '--keep-license' => true,
        ]);

        $expectedDisplay = <<<CONTENT
The archive file of the current HEAD is considered invalid due to a missing license file.


CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > 0);
    }

    /**
     * @test
     * @ticket 15 (https://github.com/raphaelstolt/lean-package-validator/issues/15)
     */
    public function archiveWithLicenseFileIsConsideredValid()
    {
        $mock = Mockery::mock(
            'Stolt\LeanPackage\Archive\Validator[validate, shouldHaveLicenseFile]',
            [new Archive(WORKING_DIRECTORY, 'foo')]
        );

        $mock->shouldReceive('validate')
            ->once()
            ->withAnyArgs()
            ->andReturn(true);

        $mock->shouldReceive('shouldHaveLicenseFile')
            ->once()
            ->withAnyArgs()
            ->andReturn($mock);

        $application = $this->getApplicationWithMockedArchiveValidator($mock);

        $gitattributesContent = <<<CONTENT
phpspec.yml.dist export-ignore
CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $command = $application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
            '--validate-git-archive' => true,
            '--keep-license' => true,
        ]);

        $expectedDisplay = <<<CONTENT
The archive file of the current HEAD is considered lean.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() == 0);
    }

    /**
     * @test
     */
    public function failingGitattributesFilesCreationReturnsExpectedStatusCode()
    {
        $artifactFilenames = ['CONDUCT.md'];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs']
        );

        $builder = new MockBuilder();
        $builder->setNamespace('Stolt\LeanPackage\Commands')
            ->setName('file_put_contents')
            ->setFunction(
                function () {
                    return false;
                }
            );

        $mock = $builder->build();
        $mock->enable();

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
            '--create' => true,
        ]);

        $expectedDisplay = <<<CONTENT
Warning: There is no .gitattributes file present in {$this->temporaryDirectory}.

Creation of .gitattributes file failed.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > 0);

        $mock->disable();
    }

    /**
     * @test
     */
    public function validateOnNonExistentGitattributesFilesWithCreationOptionCreatesOneWithoutHeader()
    {
        $artifactFilenames = ['CONDUCT.md'];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs']
        );

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
            '--create' => true,
            '--omit-header' => true
        ]);

        $expectedDisplay = <<<CONTENT
Warning: There is no .gitattributes file present in /tmp/lpv.

Created a .gitattributes file with the shown content:
* text=auto eol=lf

.gitattributes export-ignore
CONDUCT.md export-ignore
specs/ export-ignore


CONTENT;

        $this->assertEquals($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() == 0);
        $this->assertFileExists(
            WORKING_DIRECTORY . DIRECTORY_SEPARATOR . '.gitattributes'
        );
    }

    /**
     * @test
     */
    public function validateOnNonExistentGitattributesFilesWithCreationOptionCreatesOne()
    {
        $artifactFilenames = ['CONDUCT.md'];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs']
        );

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
            '--create' => true,
        ]);

        $expectedDisplay = <<<CONTENT
Warning: There is no .gitattributes file present in /tmp/lpv.

Created a .gitattributes file with the shown content:
This file was generated by the lean package validator (http://git.io/lean-package-validator).

* text=auto eol=lf

.gitattributes export-ignore
CONDUCT.md export-ignore
specs/ export-ignore


CONTENT;

        $this->assertEquals($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() == 0);
        $this->assertFileExists(
            WORKING_DIRECTORY . DIRECTORY_SEPARATOR . '.gitattributes'
        );
    }

    /**
     * @test
     */
    public function validateOnNonExistentGitattributesFilesWithCreationOptionCreatesOneWithAlignment()
    {
        $artifactFilenames = ['CONDUCT.md'];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs']
        );

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
            '--create' => true,
            '--align-export-ignores' => true,
        ]);

        $expectedDisplay = <<<CONTENT
Warning: There is no .gitattributes file present in {$this->temporaryDirectory}.

Created a .gitattributes file with the shown content:
This file was generated by the lean package validator (http://git.io/lean-package-validator).

* text=auto eol=lf

.gitattributes export-ignore
CONDUCT.md     export-ignore
specs/         export-ignore


CONTENT;

        $expectedGitattributesContent = <<<CONTENT
This file was generated by the lean package validator (http://git.io/lean-package-validator).

* text=auto eol=lf

.gitattributes export-ignore
CONDUCT.md     export-ignore
specs/         export-ignore

CONTENT;

        $this->assertEquals($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() == 0);
        $this->assertStringEqualsFile(
            WORKING_DIRECTORY . DIRECTORY_SEPARATOR . '.gitattributes',
            $expectedGitattributesContent
        );
    }

    /**
     * @test
     */
    public function validGitattributesReturnsExpectedStatusCode()
    {
        $artifactFilenames = [
            '.buildignore',
            'phpspec.yml.dist',
            'foo.txt',
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs']
        );

        $gitattributesContent = <<<CONTENT
*    text=auto eol=lf

.gitattributes export-ignore
.buildignore export-ignore
foo.txt export-ignore
phpspec.yml.dist export-ignore
specs/ export-ignore
CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
        ]);

        $expectedDisplay = <<<CONTENT
The present .gitattributes file is considered valid.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() == 0);
    }

    /**
     * @test
     */
    public function invalidGitattributesReturnsExpectedStatusCode()
    {
        $artifactFilenames = [
            '.buildignore',
            'phpspec.yml.dist',
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs']
        );

        $gitattributesContent = <<<CONTENT
specs/ export-ignore

CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
        ]);

        $expectedDisplay = <<<CONTENT
The present .gitattributes file is considered invalid.

Would expect the following .gitattributes file content:
.buildignore export-ignore
.gitattributes export-ignore
phpspec.yml.dist export-ignore
specs/ export-ignore


CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > 0);
    }

    /**
     * @test
     * @group glob
     */
    public function optionalGlobPatternIsApplied()
    {
        $artifactFilenames = [
            'CONDUCT.rst',
            '.travis.yml',
            '.buildignore',
            'mock.pyc',
            'testrunner.py',
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['dist']
        );

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
            '--glob-pattern' => '{.*,*.rst,*.py[cod],testrunner.py,dist}*',
        ]);

        $expectedDisplay = <<<CONTENT
Warning: There is no .gitattributes file present in {$this->temporaryDirectory}.

Would expect the following .gitattributes file content:
* text=auto eol=lf

.buildignore export-ignore
.gitattributes export-ignore
.travis.yml export-ignore
CONDUCT.rst export-ignore
dist/ export-ignore
mock.pyc export-ignore
testrunner.py export-ignore

Use the --create|-c option to create a .gitattributes file with the shown content.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > 0);
    }

    /**
     * @test
     * @group glob
     */
    public function usageOfInvalidGlobFailsValidation()
    {
        $failingGlobPattern = '{single-pattern*}';
        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
            '--glob-pattern' => $failingGlobPattern,
        ]);

        $expectedDisplay = <<<CONTENT
Warning: The provided glob pattern '{$failingGlobPattern}' is considered invalid.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > 0);
    }

    /**
     * @test
     */
    public function overwriteOptionOnNonExistentGitattributesFileImplicatesCreate()
    {
        $artifactFilenames = ['CONDUCT.md'];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs']
        );

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
            '--overwrite' => true,
        ]);

        $expectedDisplay = <<<CONTENT
Warning: There is no .gitattributes file present in {$this->temporaryDirectory}.

Created a .gitattributes file with the shown content:
This file was partly modified by the lean package validator (http://git.io/lean-package-validator).

* text=auto eol=lf

.gitattributes export-ignore
CONDUCT.md export-ignore
specs/ export-ignore


CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() == 0);
        $this->assertFileExists(
            WORKING_DIRECTORY . DIRECTORY_SEPARATOR . '.gitattributes'
        );
    }

    /**
     * @test
     */
    public function leanArchiveIsConsideredLean()
    {
        $mock = Mockery::mock(
            'Stolt\LeanPackage\Archive\Validator[validate]',
            [new Archive(WORKING_DIRECTORY, 'foo')]
        );
        $mock->shouldReceive('validate')
            ->once()
            ->withAnyArgs()
            ->andReturn(true);

        $application = $this->getApplicationWithMockedArchiveValidator($mock);

        $gitattributesContent = <<<CONTENT
phpspec.yml.dist export-ignore
CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $command = $application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
            '--validate-git-archive' => true,
        ]);

        $expectedDisplay = <<<CONTENT
The archive file of the current HEAD is considered lean.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() == 0);
    }

    /**
     * @test
     */
    public function notLeanArchiveIsNotConsideredLeanPlural()
    {
        $mock = Mockery::mock(
            'Stolt\LeanPackage\Archive\Validator[validate, getFoundUnexpectedArchiveArtifacts]',
            [new Archive(WORKING_DIRECTORY, 'foo')]
        );
        $mock->shouldReceive('validate')
            ->once()
            ->withAnyArgs()
            ->andReturn(false);

        $mock->shouldReceive('getFoundUnexpectedArchiveArtifacts')
            ->twice()
            ->withAnyArgs()
            ->andReturn(['.gitignore', '.travis.yml']);

        $application = $this->getApplicationWithMockedArchiveValidator($mock);

        $gitattributesContent = <<<CONTENT
phpspec.yml.dist export-ignore
CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $command = $application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
            '--validate-git-archive' => true,
        ]);

        $expectedDisplay = <<<CONTENT
The archive file of the current HEAD is not considered lean.

Seems like the following artifacts slipped in:
.gitignore
.travis.yml


CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > 0);
    }

    /**
     * @test
     */
    public function notLeanArchiveIsNotConsideredLeanSingular()
    {
        $mock = Mockery::mock(
            'Stolt\LeanPackage\Archive\Validator[validate, getFoundUnexpectedArchiveArtifacts]',
            [new Archive(WORKING_DIRECTORY, 'foo')]
        );
        $mock->shouldReceive('validate')
            ->once()
            ->withAnyArgs()
            ->andReturn(false);

        $mock->shouldReceive('getFoundUnexpectedArchiveArtifacts')
            ->twice()
            ->withAnyArgs()
            ->andReturn(['.gitignore']);

        $application = $this->getApplicationWithMockedArchiveValidator($mock);

        $gitattributesContent = <<<CONTENT
phpspec.yml.dist export-ignore
CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $command = $application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
            '--validate-git-archive' => true,
        ]);

        $expectedDisplay = <<<CONTENT
The archive file of the current HEAD is not considered lean.

Seems like the following artifact slipped in:
.gitignore


CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > 0);
    }

    /**
     * @test
     */
    public function impossibilityToResolveExpectedGitattributesFileContentIsInfoed()
    {
        $mock = Mockery::mock(
            'Stolt\LeanPackage\Analyser[getExpectedGitattributesContent]'
        );
        $mock->shouldReceive('getExpectedGitattributesContent')
            ->once()
            ->withAnyArgs()
            ->andReturn('');

        $application = $this->getApplicationWithMockedAnalyser($mock);

        $command = $application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
        ]);

        $expectedDisplay = <<<CONTENT
Warning: There is no .gitattributes file present in {$this->temporaryDirectory}.

Unable to resolve expected .gitattributes content.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > 0);
    }

    /**
     * @test
     */
    public function invalidDirectoryAgumentReturnsExpectedStatusCode()
    {
        $nonExistentDirectoryOrFile = WORKING_DIRECTORY
            . DIRECTORY_SEPARATOR
            . 'non-existent-directory-or-file';

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => $nonExistentDirectoryOrFile,
        ]);

        $expectedDisplay = <<<CONTENT
Warning: The provided directory '{$nonExistentDirectoryOrFile}' does not exist or is not a directory.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > 0);
    }

    /**
     * @test
     */
    public function incompleteGitattributesFileIsOverwrittenWithAlignment()
    {
        $gitattributesContent = <<<CONTENT
# These files are always considered text and should use LF.
# See core.whitespace @ http://git-scm.com/docs/git-config for whitespace flags.

*.php text eol=lf

# Ignore all non production artifacts with an "export-ignore".
/.gitattributes     export-ignore
/.gitignore         export-ignore
/.styleci.yml       export-ignore
/.travis.yml        export-ignore
/.appveyor.yml      export-ignore
/phpunit.xml.dist export-ignore
/tests/ export-ignore

CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $artifactFilenames = [
            '.gitignore',
            '.travis.yml',
            '.appveyor.yml',
            'phpunit.xml.dist',
            '.styleci.yml',
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['tests', 'docs', 'example']
        );

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
            '--overwrite' => true,
            '-a' => true,
        ]);

        $expectedDisplay = <<<CONTENT
The present .gitattributes file is considered invalid.

Overwrote it with the shown content:
# These files are always considered text and should use LF.
# See core.whitespace @ http://git-scm.com/docs/git-config for whitespace flags.

*.php text eol=lf

# Ignore all non production artifacts with an "export-ignore".
.appveyor.yml    export-ignore
.gitattributes   export-ignore
.gitignore       export-ignore
.styleci.yml     export-ignore
.travis.yml      export-ignore
docs/            export-ignore
example/         export-ignore
phpunit.xml.dist export-ignore
tests/           export-ignore


CONTENT;

        $expectedGitattributesContent = <<<CONTENT
# These files are always considered text and should use LF.
# See core.whitespace @ http://git-scm.com/docs/git-config for whitespace flags.

*.php text eol=lf

# Ignore all non production artifacts with an "export-ignore".
.appveyor.yml    export-ignore
.gitattributes   export-ignore
.gitignore       export-ignore
.styleci.yml     export-ignore
.travis.yml      export-ignore
docs/            export-ignore
example/         export-ignore
phpunit.xml.dist export-ignore
tests/           export-ignore

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() == 0);
        $this->assertStringEqualsFile(
            WORKING_DIRECTORY . DIRECTORY_SEPARATOR . '.gitattributes',
            $expectedGitattributesContent
        );
    }

    /**
     * @test
     * @ticket 8 (https://github.com/raphaelstolt/lean-package-validator/issues/8)
     * @dataProvider optionProvider
     */
    public function incompleteGitattributesFileIsOverwritten($option)
    {
        $gitattributesContent = <<<CONTENT
* text=auto eol=lf

phpspec.yml.dist export-ignore
specs/ export-ignore
version-increase-command export-ignore
CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $artifactFilenames = ['phpspec.yml.dist', 'version-increase-command'];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs']
        );

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
            $option => true,
        ]);

        $expectedDisplay = <<<CONTENT
The present .gitattributes file is considered invalid.

Overwrote it with the shown content:
* text=auto eol=lf

.gitattributes export-ignore
phpspec.yml.dist export-ignore
specs/ export-ignore
version-increase-command export-ignore

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() == 0);
        $this->assertFileExists(
            WORKING_DIRECTORY . DIRECTORY_SEPARATOR . '.gitattributes'
        );
    }

    /**
     * @test
     * @ticket 8 (https://github.com/raphaelstolt/lean-package-validator/issues/8)
     */
    public function failingGitattributesFilesOverwriteReturnsExpectedStatusCode()
    {
        $gitattributesContent = <<<CONTENT
* text=auto eol=lf

phpspec.yml.dist export-ignore
specs/ export-ignore
CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $artifactFilenames = ['phpspec.yml.dist'];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs']
        );

        $builder = new MockBuilder();
        $builder->setNamespace('Stolt\LeanPackage\Commands')
            ->setName('file_put_contents')
            ->setFunction(
                function () {
                    return false;
                }
            );

        $mock = $builder->build();
        $mock->enable();

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
            '--create' => true,
        ]);

        $expectedDisplay = <<<CONTENT
The present .gitattributes file is considered invalid.

Overwrite of .gitattributes file failed.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > 0);

        $mock->disable();
    }

    /**
     * @test
     * @ticket 22 (https://github.com/raphaelstolt/lean-package-validator/issues/22)
     */
    public function nonExistentArtifactsWhichAreExportIgnoredAreIgnoredOnComparison()
    {
        $artifactFilenames = [
            '.gitattributes',
            'appveyor.yml',
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['tests']
        );

        $gitattributesContent = <<<CONTENT
* text=auto eol=lf

# Do not include the following files when creating repo artifacts, e.g. the zip
tests/ export-ignore
non-existent-directory/ export-ignore # non existent artifact directory
non-existent-file export-ignore # non existent artifact file
.gitattributes export-ignore
appveyor.yml export-ignore

CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
        ]);

        $expectedDisplay = <<<CONTENT
The present .gitattributes file is considered valid.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() === 0);
    }

    /**
     * @test
     */
    public function strictAlignmentOfExportIgnoresCanBeEnforced()
    {
        $artifactFilenames = [
            '.buildignore',
            'phpspec.yml.dist',
            'Phulpfile'
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs']
        );

        $gitattributesContent = <<<CONTENT
.buildignore export-ignore
.gitattributes export-ignore
phpspec.yml.dist export-ignore
Phulpfile export-ignore
specs/ export-ignore

CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
            '--enforce-alignment' => true,
        ]);

        $expectedDisplay = <<<CONTENT
The present .gitattributes file is considered invalid.

Would expect the following .gitattributes file content:
.buildignore     export-ignore
.gitattributes   export-ignore
phpspec.yml.dist export-ignore
Phulpfile        export-ignore
specs/           export-ignore


CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > 0);
    }

    /**
     * @test
     */
    public function strictAlignmentAndOrderOfExportIgnoresCanBeEnforced()
    {
        $artifactFilenames = [
            '.buildignore',
            'phpspec.yml.dist',
            'Phulpfile'
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs']
        );

        $gitattributesContent = <<<CONTENT
.gitattributes export-ignore
.buildignore export-ignore
phpspec.yml.dist export-ignore
Phulpfile export-ignore
specs/ export-ignore

CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
            '--enforce-alignment' => true,
            '--enforce-strict-order' => true,
        ]);

        $expectedDisplay = <<<CONTENT
The present .gitattributes file is considered invalid.

Would expect the following .gitattributes file content:
.buildignore     export-ignore
.gitattributes   export-ignore
phpspec.yml.dist export-ignore
Phulpfile        export-ignore
specs/           export-ignore


CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > 0);
    }

    /**
     * @test
     * @ticket 6 (https://github.com/raphaelstolt/lean-package-validator/issues/6)
     */
    public function strictOrderOfExportIgnoresCanBeEnforced()
    {
        $artifactFilenames = [
            '.buildignore',
            'phpspec.yml.dist',
            'Phulpfile'
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs']
        );

        $gitattributesContent = <<<CONTENT
Phulpfile export-ignore
.buildignore export-ignore
phpspec.yml.dist
specs/ export-ignore
.gitattributes export-ignore

CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
            '--enforce-strict-order' => true,
        ]);

        $expectedDisplay = <<<CONTENT
The present .gitattributes file is considered invalid.

Would expect the following .gitattributes file content:
.buildignore export-ignore
.gitattributes export-ignore
phpspec.yml.dist export-ignore
Phulpfile export-ignore
specs/ export-ignore
phpspec.yml.dist


CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > 0);
    }

    /**
     * @test
     * @group glob
     * @ticket 9 (https://github.com/raphaelstolt/lean-package-validator/issues/9)
     */
    public function givenGlobPatternTakesPrecedenceOverDefaultGlobPatternFile()
    {
        $artifactFilenames = [
            '.buildignore',
            'phpspec.yml.dist',
            'Phulpfile'
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs']
        );

        $gitattributesContent = <<<CONTENT
* text   = auto

.buildignore export-ignore
.gitattributes export-ignore
.lpv export-ignore
phpspec.yml.dist export-ignore
Phulpfile export-ignore
specs/ export-ignore

CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $lpvContent = <<<CONTENT
*.txt
*.php

CONTENT;

        $this->createTemporaryGlobPatternFile($lpvContent);

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
            '--glob-pattern' => '{.*,*file,*.dist,specs}*',
        ]);

        $expectedDisplay = <<<CONTENT
The present .gitattributes file is considered valid.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() === 0);
    }

    /**
     * @test
     * @group glob
     * @ticket 9 (https://github.com/raphaelstolt/lean-package-validator/issues/9)
     */
    public function presentGlobPatternFileTakesPrecedenceOverDefaultGlobPattern()
    {
        $artifactFilenames = [
            'a.txt',
            'b.rst',
            'Vagrantfile'
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['example']
        );

        $gitattributesContent = <<<CONTENT
* text=auto

example/ export-ignore
a.txt export-ignore
b.rst export-ignore
Vagrantfile export-ignore

CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $lpvContent = <<<CONTENT
*.txt
*file
example

CONTENT;

        $this->createTemporaryGlobPatternFile($lpvContent);

        $temporaryLpvFile = $this->temporaryDirectory
            . DIRECTORY_SEPARATOR
            . '.lpv';

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
            '--glob-pattern-file' => $temporaryLpvFile,
        ]);

        $expectedDisplay = <<<CONTENT
The present .gitattributes file is considered valid.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() === 0);
    }

    /**
     * @test
     * @group glob
     * @ticket 9 (https://github.com/raphaelstolt/lean-package-validator/issues/9)
     */
    public function providedNonExistentGlobPatternFileFailsValidation()
    {
        $gitattributesContent = <<<CONTENT
.gitattributes export-ignore

CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $temporaryLpvFile = $this->temporaryDirectory
            . DIRECTORY_SEPARATOR
            . '.lpv-non-existent';

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
            '--glob-pattern-file' => $temporaryLpvFile,
        ]);

        $expectedDisplay = <<<CONTENT
Warning: The provided glob pattern file '{$temporaryLpvFile}' doesn't exist.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > 0);
    }

    /**
     * @test
     * @group glob
     * @ticket 9 (https://github.com/raphaelstolt/lean-package-validator/issues/9)
     */
    public function providedInvalidGlobPatternFileFailsValidation()
    {
        $gitattributesContent = <<<CONTENT
.gitattributes export-ignore

CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $lpvContent = <<<CONTENT
{foo.*}

CONTENT;

        $this->createTemporaryGlobPatternFile($lpvContent);

        $temporaryLpvFile = $this->temporaryDirectory
            . DIRECTORY_SEPARATOR
            . '.lpv';

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
            '--glob-pattern-file' => $temporaryLpvFile,
        ]);

        $expectedDisplay = <<<CONTENT
Warning: The provided glob pattern file '{$temporaryLpvFile}' is considered invalid.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > 0);
    }

    /**
     * @test
     * @ticket 4 (https://github.com/raphaelstolt/lean-package-validator/issues/4)
     */
    public function precedingSlashesInExportIgnorePatternsRaiseAWarning()
    {
        $artifactFilenames = [
            '.buildignore',
            'phpspec.yml.dist',
            'Phulpfile'
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs']
        );

        $gitattributesContent = <<<CONTENT

/Phulpfile export-ignore
/.buildignore export-ignore
/phpspec.yml.dist export-ignore
/specs/ export-ignore
/.gitattributes export-ignore

*    text=auto

CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
        ]);

        $expectedDisplay = <<<CONTENT
The present .gitattributes file is considered valid.
Warning: At least one export-ignore pattern has a leading '/', which is considered as a smell.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() == 0);
    }

    /**
     * @test
     * @ticket 12 (https://github.com/raphaelstolt/lean-package-validator/issues/12)
     */
    public function missingTextAutoConfigurationRaisesAWarning()
    {
        $artifactFilenames = [
            '.buildignore',
            'phpspec.yml.dist',
            'Phulpfile'
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs']
        );

        $gitattributesContent = <<<CONTENT
Phulpfile export-ignore
.buildignore export-ignore
phpspec.yml.dist export-ignore
specs/ export-ignore
.gitattributes export-ignore

CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
        ]);

        $expectedDisplay = <<<CONTENT
The present .gitattributes file is considered valid.
Warning: Missing a text auto configuration. Consider adding one.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() == 0);
    }

    /**
     * @test
     * @ticket 17 (https://github.com/raphaelstolt/lean-package-validator/issues/17)
     */
    public function gitignoredFilesAreExcludedFromValidation()
    {
        $artifactFilenames = [
            '.buildignore',
            'phpspec.yml.dist',
            '.php_cs.cache',
            'composer.lock',
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs']
        );

        $gitattributesContent = <<<CONTENT
*    text=auto

.buildignore export-ignore
phpspec.yml.dist export-ignore
specs/ export-ignore
.gitignore export-ignore
.gitattributes export-ignore

CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $gitignoreContent = <<<CONTENT
/vendor/*
/coverage-reports
composer.lock
.php_cs.cache

CONTENT;

        $this->createTemporaryGitignoreFile($gitignoreContent);

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
        ]);

        $expectedDisplay = <<<CONTENT
The present .gitattributes file is considered valid.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() == 0);
    }

    /**
     * @return array
     */
    public static function optionProvider()
    {
        return [
            ['--overwrite'],
            ['--create']
        ];
    }

    /**
     * @return \Symfony\Component\Console\Application
     */
    protected function getApplicationWithMockedAnalyser(MockInterface $mockedAnalyser)
    {
        $application = new Application();

        $archive = new Archive(
            $this->temporaryDirectory,
            basename($this->temporaryDirectory)
        );

        $analyserCommand = new ValidateCommand(
            $mockedAnalyser,
            new Validator($archive)
        );

        $application->add($analyserCommand);

        return $application;
    }

    /**
     * @return \Symfony\Component\Console\Application
     */
    protected function getApplicationWithMockedArchiveValidator(MockInterface $mockedArchiveValidator)
    {
        $application = new Application();

        $analyserCommand = new ValidateCommand(
            new Analyser,
            $mockedArchiveValidator
        );

        $application->add($analyserCommand);

        return $application;
    }

    /**
     * @return \Symfony\Component\Console\Application
     */
    protected function getApplication()
    {
        $application = new Application();
        $archive = new Archive(
            $this->temporaryDirectory,
            basename($this->temporaryDirectory)
        );

        $analyserCommand = new ValidateCommand(
            new Analyser,
            new Validator($archive)
        );

        $application->add($analyserCommand);

        return $application;
    }
}
