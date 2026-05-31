<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Tests\Commands;

use Mockery;
use Mockery\MockInterface;
use phpmock\functions\FixedValueFunction;
use phpmock\MockBuilder;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Ticket;
use Stolt\LeanPackage\Analyser;
use Stolt\LeanPackage\Analysers\ClassicExportIgnoreAnalyser;
use Stolt\LeanPackage\Archive;
use Stolt\LeanPackage\Archive\Validator;
use Stolt\LeanPackage\Commands\ValidateCommand;
use Stolt\LeanPackage\Exceptions\InvalidGlobPattern;
use Stolt\LeanPackage\Exceptions\NoLicenseFilePresent;
use Stolt\LeanPackage\Gitattributes\FileRepository as GitattributesFileRepository;
use Stolt\LeanPackage\Helpers\Str as OsHelper;
use Stolt\LeanPackage\Presets\Finder;
use Stolt\LeanPackage\Presets\PhpPreset;
use Stolt\LeanPackage\Tests\CommandTester;
use Stolt\LeanPackage\Tests\Helpers\FakeInputReader;
use Stolt\LeanPackage\Tests\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Zenstruck\Console\Test\InteractsWithConsole;
use Zenstruck\Console\Test\TestCommand;

class ValidateCommandTest extends TestCase
{
    use InteractsWithConsole;
    use PHPMock;

    /**
     * Set up a test environment.
     */
    protected function setUp(): void
    {
        $this->setUpTemporaryDirectory();

        $analyser = new Analyser(
            new ClassicExportIgnoreAnalyser(new Finder(new PhpPreset()), new GitattributesFileRepository($this->temporaryDirectory))
        );

        $validateCommand = new ValidateCommand(
            $analyser,
            new Validator(new Archive($this->temporaryDirectory)),
            new FakeInputReader(),
            new GitattributesFileRepository($this->temporaryDirectory),
        );

        $this->application = $this->getApplication($validateCommand);
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
    public function validateOnNonExistentGitattributesFilesSuggestsCreation(): void
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
            'directory' => $this->temporaryDirectory,
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

Use the create command to create a .gitattributes file with the shown content.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > Command::SUCCESS);
    }

    #[Test]
    public function validateOnNonExistentGitattributesFilesSuggestsCreateCommand(): void
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
            'directory' => $this->temporaryDirectory,
            '--enforce-alignment' => true,
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

Use the create command to create a .gitattributes file with the shown content.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > Command::SUCCESS);
    }

    #[Test]
    #[Ticket('https://github.com/raphaelstolt/lean-package-validator/issues/39')]
    public function showsDifferenceBetweenActualAndExpectedGitattributesContent(): void
    {
        if ((new OsHelper())->isWindows()) {
            $this->markTestSkipped('Skipping test on Windows systems');
        }

        $artifactFilenames = [
            '.gitattributes',
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['.github']
        );

        $gitattributesContent = <<<CONTENT
.gitattributes export-ignore
CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => $this->temporaryDirectory,
            '--diff' => true,
        ]);

        $actualDisplayRows = \explode(PHP_EOL, $commandTester->getDisplay());
        $expectedDiffRows = ['--- Original', '+++ Expected', '@@ -1 +1,2 @@'];

        foreach ($expectedDiffRows as $expectedDiffRow) {
            $this->assertContains($expectedDiffRow, $actualDisplayRows);
        }

        $this->assertTrue($commandTester->getStatusCode() > Command::SUCCESS);
    }

    #[Test]
    public function filesInGlobalGitignoreAreExportIgnored(): void
    {
        $globPattern = '{' . \implode(',', (new PhpPreset())->getPresetGlob()) . '}*';

        $mockedClassicExportIgnoreAnalyser = Mockery::mock(
            ClassicExportIgnoreAnalyser::class,
            [new Finder(new PhpPreset()), new GitattributesFileRepository($this->temporaryDirectory),]
        )->makePartial();
        $mockedAnalyser = Mockery::mock(Analyser::class, [$mockedClassicExportIgnoreAnalyser])->makePartial();

        $mockedAnalyser->getActualExportIgnoreAnalyser()->setGlobPattern($globPattern);

        $application = $this->getApplicationWithMockedAnalyser($mockedAnalyser);

        $artifactFilenames = [
            '.gitattributes',
            '.gitignore',
            'captainhook.json',
            'CODE_OF_CONDUCT.md',
            'CONTRIBUTING.md',
            'infection.json5',
            'LICENSE.txt',
            'phpstan.neon',
            'phpunit.xml',
            'README.md',
            'sonar-project.properties',
            'package.json',
            'package-lock.json'
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['tests', '.github', 'docs']
        );

        $command = $application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => $this->temporaryDirectory,
            '--enforce-strict-order' => true,
            '--enforce-alignment' => true,
        ]);

        $expectedDisplay = <<<CONTENT
The present .gitattributes file is considered invalid.

Would expect the following .gitattributes file content:


.gitattributes           export-ignore
.github/                 export-ignore
.gitignore               export-ignore
captainhook.json         export-ignore
CODE_OF_CONDUCT.md       export-ignore
CONTRIBUTING.md          export-ignore
docs/                    export-ignore
infection.json5          export-ignore
LICENSE.txt              export-ignore
package-lock.json        export-ignore
package.json             export-ignore
phpstan.neon             export-ignore
phpunit.xml              export-ignore
README.md                export-ignore
sonar-project.properties export-ignore
tests/                   export-ignore

CONTENT;

        $this->assertStringEqualsStringIgnoringLineEndings($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > Command::SUCCESS);
    }

    #[Test]
    #[Ticket('https://github.com/raphaelstolt/lean-package-validator/issues/16')]
    public function gitattributesFileWithNoExportIgnoresContentShowsExpectedContent(): void
    {
        $mockedClassicExportIgnoreAnalyser = Mockery::mock(
            ClassicExportIgnoreAnalyser::class,
            [new Finder(new PhpPreset()), new GitattributesFileRepository($this->temporaryDirectory)]
        )->makePartial();
        $mockedAnalyser = Mockery::mock(Analyser::class, [$mockedClassicExportIgnoreAnalyser])->makePartial();

        $globPattern = '{' . \implode(',', (new PhpPreset())->getPresetGlob()) . '}*';

        $mockedAnalyser->getActualExportIgnoreAnalyser()->setGlobPattern($globPattern);

        $application = $this->getApplicationWithMockedAnalyser($mockedAnalyser);

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

        $command = $application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => $this->temporaryDirectory,
            '--enforce-strict-order' => true,
            '--enforce-alignment' => true,
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

.gitattributes   export-ignore
.gitignore       export-ignore
.scrutinizer.yml export-ignore
.travis.yml      export-ignore
CHANGELOG.md     export-ignore
phpunit.xml      export-ignore
README.md        export-ignore
tests/           export-ignore

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > Command::SUCCESS);
    }

    #[Test]
    #[Ticket('https://github.com/raphaelstolt/lean-package-validator/issues/13')]
    public function gitattributesIsInSuggestedFileContent(): void
    {
        if ((new OsHelper())->isWindows()) {
            $this->markTestSkipped('Skipping test on Windows systems');
        }

        $artifactFilenames = [
            'CONDUCT.md',
            'phpspec.yml.dist',
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs']
        );

        $command = $this->application->find('validate');

        $expectedDisplay = <<<CONTENT
Warning: There is no .gitattributes file present in {$this->temporaryDirectory}.

Would expect the following .gitattributes file content:
* text=auto eol=lf

.gitattributes export-ignore
CONDUCT.md export-ignore
phpspec.yml.dist export-ignore
specs/ export-ignore

Use the create command to create a .gitattributes file with the shown content.

CONTENT;

        TestCommand::for($command)
            ->addArgument($this->temporaryDirectory)
            ->execute()
            ->assertOutputContains($expectedDisplay)
            ->assertFaulty();
    }

    #[Test]
    #[Ticket('https://github.com/raphaelstolt/lean-package-validator/issues/15')]
    public function licenseIsInSuggestedFileContentPerDefault(): void
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
            'directory' => $this->temporaryDirectory,
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

Use the create command to create a .gitattributes file with the shown content.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > Command::SUCCESS);
    }

    #[Test]
    #[Ticket('https://github.com/raphaelstolt/lean-package-validator/issues/15')]
    public function licenseIsNotInSuggestedFileContent(): void
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
            'directory' => $this->temporaryDirectory,
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

Use the create command to create a .gitattributes file with the shown content.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > Command::SUCCESS);
    }

    #[Test]
    #[Ticket('https://github.com/raphaelstolt/lean-package-validator/issues/47')]
    public function readmeIsNotInSuggestedFileContent(): void
    {
        $artifactFilenames = [
            'CONDUCT.md',
            'phpspec.yml.dist',
            'License.md',
            'README.md'
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs']
        );

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => $this->temporaryDirectory,
            '--keep-readme' => true,
        ]);

        $expectedDisplay = <<<CONTENT
Warning: There is no .gitattributes file present in {$this->temporaryDirectory}.

Would expect the following .gitattributes file content:
* text=auto eol=lf

.gitattributes export-ignore
CONDUCT.md export-ignore
License.md export-ignore
phpspec.yml.dist export-ignore
specs/ export-ignore

Use the create command to create a .gitattributes file with the shown content.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > Command::SUCCESS);
    }

    #[Test]
    public function licenseAndReadmeAreNotInSuggestedFileContent(): void
    {
        $artifactFilenames = [
            'CONDUCT.md',
            'phpspec.yml.dist',
            'License.md',
            'README.md'
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs', 'docs']
        );

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => $this->temporaryDirectory,
            '--keep-readme' => true,
            '--keep-license' => true,
        ]);

        $expectedDisplay = <<<CONTENT
Warning: There is no .gitattributes file present in {$this->temporaryDirectory}.

Would expect the following .gitattributes file content:
* text=auto eol=lf

.gitattributes export-ignore
CONDUCT.md export-ignore
docs/ export-ignore
phpspec.yml.dist export-ignore
specs/ export-ignore

Use the create command to create a .gitattributes file with the shown content.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > Command::SUCCESS);
    }

    #[Test]
    public function keepGlobPatternMatchesAreNotInSuggestedFileContent(): void
    {
        $artifactFilenames = [
            'CONDUCT.md',
            'phpspec.yml.dist',
            'License.md',
            'README.md'
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['specs', 'docs']
        );

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => $this->temporaryDirectory,
            '--keep-glob-pattern' => '{README.*,License.*,docs*}',
        ]);

        $expectedDisplay = <<<CONTENT
Warning: There is no .gitattributes file present in {$this->temporaryDirectory}.

Would expect the following .gitattributes file content:
* text=auto eol=lf

.gitattributes export-ignore
CONDUCT.md export-ignore
phpspec.yml.dist export-ignore
specs/ export-ignore

Use the create command to create a .gitattributes file with the shown content.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > Command::SUCCESS);
    }

    #[Test]
    #[Group('glob')]
    #[Ticket('https://github.com/raphaelstolt/lean-package-validator/issues/13')]
    public function licenseIsNotInSuggestedFileContentWithCustomGlobPattern(): void
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
            'directory' => $this->temporaryDirectory,
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

Use the create command to create a .gitattributes file with the shown content.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > Command::SUCCESS);
    }

    #[Test]
    #[Ticket('https://github.com/raphaelstolt/lean-package-validator/issues/15')]
    public function presentExportIgnoredLicenseWithKeepLicenseOptionInvalidatesResult(): void
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
            'directory' => $this->temporaryDirectory,
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
        $this->assertTrue($commandTester->getStatusCode() > Command::SUCCESS);
    }

    #[Test]
    #[Ticket('https://github.com/raphaelstolt/lean-package-validator/issues/15')]
    public function archiveWithoutLicenseFileIsConsideredInvalid(): void
    {
        $mock = Mockery::mock(
            'Stolt\LeanPackage\Archive\Validator[validate, shouldHaveLicenseFile]',
            [new Archive($this->temporaryDirectory, 'foo')]
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
            'directory' => $this->temporaryDirectory,
            '--validate-git-archive' => true,
            '--keep-license' => true,
        ]);

        $expectedDisplay = <<<CONTENT
The archive file of the current HEAD is considered invalid due to a missing license file.


CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > Command::SUCCESS);
    }

    #[Test]
    #[Ticket('https://github.com/raphaelstolt/lean-package-validator/issues/15')]
    public function archiveWithLicenseFileIsConsideredValid(): void
    {
        $mock = Mockery::mock(
            'Stolt\LeanPackage\Archive\Validator[validate, shouldHaveLicenseFile]',
            [new Archive($this->temporaryDirectory, 'foo')]
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
            'directory' => $this->temporaryDirectory,
            '--validate-git-archive' => true,
            '--keep-license' => true,
        ]);

        $expectedDisplay = <<<CONTENT
The archive file of the current HEAD is considered lean.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $commandTester->assertCommandIsSuccessful();
    }

    #[Test]
    public function validGitattributesReturnsExpectedStatusCode(): void
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
            'directory' => $this->temporaryDirectory,
        ]);

        $expectedDisplay = <<<CONTENT
The present .gitattributes file is considered valid.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $commandTester->assertCommandIsSuccessful();
    }

    #[Test]
    public function invalidGitattributesReturnsExpectedStatusCode(): void
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
            'directory' => $this->temporaryDirectory,
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
        $this->assertTrue($commandTester->getStatusCode() > Command::SUCCESS);
    }

    #[Test]
    #[Group('glob')]
    public function optionalGlobPatternIsApplied(): void
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
            'directory' => $this->temporaryDirectory,
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

Use the create command to create a .gitattributes file with the shown content.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > Command::SUCCESS);
    }

    #[Test]
    #[Group('glob')]
    public function usageOfInvalidGlobFailsValidation(): void
    {
        $failingGlobPattern = '{single-pattern*}';
        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => $this->temporaryDirectory,
            '--glob-pattern' => $failingGlobPattern,
        ]);

        $expectedDisplay = <<<CONTENT
Warning: The provided glob pattern '{$failingGlobPattern}' is considered invalid.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > Command::SUCCESS);
    }

    #[Test]
    #[Group('glob')]
    #[Ticket('https://github.com/raphaelstolt/lean-package-validator/issues/38')]
    public function missingGlobPatternProducesUserFriendlyErrorMessage(): void
    {
        $missingGlobPattern = '';
        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => $this->temporaryDirectory,
            '--glob-pattern' => $missingGlobPattern,
        ]);

        $expectedDisplay = <<<CONTENT
Warning: The provided glob pattern '{$missingGlobPattern}' is considered invalid.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > Command::SUCCESS);
    }

    #[Test]
    public function leanArchiveIsConsideredLean(): void
    {
        $mock = Mockery::mock(
            'Stolt\LeanPackage\Archive\Validator[validate]',
            [new Archive($this->temporaryDirectory, 'foo')]
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
            'directory' => $this->temporaryDirectory,
            '--validate-git-archive' => true,
        ]);

        $expectedDisplay = <<<CONTENT
The archive file of the current HEAD is considered lean.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $commandTester->assertCommandIsSuccessful();
    }

    #[Test]
    public function nonLeanArchiveIsNotConsideredLeanPlural(): void
    {
        $mock = Mockery::mock(
            'Stolt\LeanPackage\Archive\Validator[validate, getFoundUnexpectedArchiveArtifacts]',
            [new Archive($this->temporaryDirectory, 'foo')]
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
            'directory' => $this->temporaryDirectory,
            '--validate-git-archive' => true,
        ]);

        $expectedDisplay = <<<CONTENT
The archive file of the current HEAD is not considered lean.

Seems like the following artifacts slipped in:
.gitignore
.travis.yml


CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > Command::SUCCESS);
    }

    #[Test]
    public function nonLeanArchiveIsNotConsideredLeanSingular(): void
    {
        $mock = Mockery::mock(
            'Stolt\LeanPackage\Archive\Validator[validate, getFoundUnexpectedArchiveArtifacts]',
            [new Archive($this->temporaryDirectory, 'foo')]
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
            'directory' => $this->temporaryDirectory,
            '--validate-git-archive' => true,
        ]);

        $expectedDisplay = <<<CONTENT
The archive file of the current HEAD is not considered lean.

Seems like the following artifact slipped in:
.gitignore


CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > Command::SUCCESS);
    }

    /**
     * @throws InvalidGlobPattern
     */
    #[Test]
    public function impossibilityToResolveExpectedGitattributesFileContentIsInfoed(): void
    {
        $mockedClassicExportIgnoreAnalyser = Mockery::mock(
            ClassicExportIgnoreAnalyser::class,
            [new Finder(new PhpPreset()), new GitattributesFileRepository($this->temporaryDirectory),]
        )->makePartial();
        $mockedAnalyser = Mockery::mock(Analyser::class, [$mockedClassicExportIgnoreAnalyser])->makePartial();

        $globPattern = '{' . \implode(',', (new PhpPreset())->getPresetGlob()) . '}*';
        $mockedClassicExportIgnoreAnalyser->setGlobPattern($globPattern);

        $mockedAnalyser->shouldReceive('getExpectedGitattributesContent')
            ->once()
            ->withAnyArgs()
            ->andReturn('');

        $application = $this->getApplicationWithMockedAnalyser($mockedAnalyser);

        $command = $application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => $this->temporaryDirectory,
        ]);

        $expectedDisplay = <<<CONTENT
Warning: There is no .gitattributes file present in {$this->temporaryDirectory}.

Unable to resolve expected .gitattributes content.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > Command::SUCCESS);
    }

    #[Test]
    public function invalidDirectoryArgumentReturnsExpectedStatusCode(): void
    {
        $nonExistentDirectoryOrFile = $this->temporaryDirectory
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
        $this->assertTrue($commandTester->getStatusCode() > Command::SUCCESS);
    }

    #[Test]
    #[Ticket('https://github.com/raphaelstolt/lean-package-validator/issues/41')]
    public function staleExportIgnoresAreConsideredAsInvalid(): void
    {
        $gitattributesContent = <<<CONTENT
* text=auto eol=lf

.editorconfig export-ignore
.gitattributes export-ignore
.github/ export-ignore
CHANGELOG.md export-ignore
LICENSE.md export-ignore
tests/ export-ignore
stale-non-existent-dir/ export-ignore

CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $artifactFilenames = ['.editorconfig', '.gitattributes', 'CHANGELOG.md', 'LICENSE.md'];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['.github', 'tests']
        );

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => $this->temporaryDirectory,
            '--diff' => true,
            '--report-stale-export-ignores' => true
        ]);

        $expectedDisplay = <<<CONTENT
The present .gitattributes file is considered invalid.

Would expect the following .gitattributes file content:
--- Original
+++ Expected
@@ -6,4 +6,3 @@
 CHANGELOG.md export-ignore
 LICENSE.md export-ignore
 tests/ export-ignore
-stale-non-existent-dir/ export-ignore


CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > Command::SUCCESS);
    }

    #[Test]
    #[Ticket('https://github.com/raphaelstolt/lean-package-validator/issues/22')]
    public function nonExistentArtifactsWhichAreExportIgnoredAreIgnoredOnComparison(): void
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
            'directory' => $this->temporaryDirectory,
        ]);

        $expectedDisplay = <<<CONTENT
The present .gitattributes file is considered valid.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $commandTester->assertCommandIsSuccessful();
    }

    #[Test]
    public function strictAlignmentOfExportIgnoresCanBeEnforced(): void
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
            'directory' => $this->temporaryDirectory,
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
        $this->assertTrue($commandTester->getStatusCode() > Command::SUCCESS);
    }

    #[Test]
    public function strictAlignmentAndOrderOfExportIgnoresCanBeEnforced(): void
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
            'directory' => $this->temporaryDirectory,
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
        $this->assertTrue($commandTester->getStatusCode() > Command::SUCCESS);
    }

    #[Test]
    #[Ticket('https://github.com/raphaelstolt/lean-package-validator/issues/6')]
    public function strictOrderOfExportIgnoresCanBeEnforced(): void
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
            'directory' => $this->temporaryDirectory,
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
        $this->assertTrue($commandTester->getStatusCode() > Command::SUCCESS);
    }

    #[Test]
    #[Group('glob')]
    #[Ticket('https://github.com/raphaelstolt/lean-package-validator/issues/9')]
    public function givenGlobPatternTakesPrecedenceOverDefaultGlobPatternFile(): void
    {
        $artifactFilenames = [
            '.buildignore',
            'phpspec.yml.dist',
            'Phulpfile',
            '.lpv'
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
            'directory' => $this->temporaryDirectory,
            '--glob-pattern' => '{.*,*file,*.dist,specs}*',
        ]);

        $expectedDisplay = <<<CONTENT
The present .gitattributes file is considered valid.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $commandTester->assertCommandIsSuccessful();
    }

    #[Test]
    #[Group('glob')]
    #[Ticket('https://github.com/raphaelstolt/lean-package-validator/issues/9')]
    public function presentGlobPatternFileTakesPrecedenceOverDefaultGlobPattern(): void
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
            'directory' => $this->temporaryDirectory,
            '--glob-pattern-file' => $temporaryLpvFile,
        ]);

        $expectedDisplay = <<<CONTENT
The present .gitattributes file is considered valid.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $commandTester->assertCommandIsSuccessful();
    }

    #[Test]
    #[Group('glob')]
    #[Ticket('https://github.com/raphaelstolt/lean-package-validator/issues/35')]
    public function presentLpvPatternFileIsUsed(): void
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

.gitattributes export-ignore
.lpv export-ignore
a.txt export-ignore
b.rst export-ignore
example/ export-ignore
Vagrantfile export-ignore

CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $lpvContent = <<<CONTENT
*.txt
*file
example

CONTENT;

        $this->createTemporaryGlobPatternFile($lpvContent);

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => $this->temporaryDirectory,
        ]);

        $expectedDisplay = <<<CONTENT
The present .gitattributes file is considered valid.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $commandTester->assertCommandIsSuccessful();
    }

    #[Test]
    #[Group('glob')]
    #[Ticket('https://github.com/raphaelstolt/lean-package-validator/issues/9')]
    public function providedNonExistentGlobPatternFileFailsValidation(): void
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
            'directory' => $this->temporaryDirectory,
            '--glob-pattern-file' => $temporaryLpvFile,
        ]);

        $expectedDisplay = <<<CONTENT
Warning: The provided glob pattern file '{$temporaryLpvFile}' doesn't exist.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > Command::SUCCESS);
    }

    #[Test]
    #[Group('glob')]
    #[Ticket('https://github.com/raphaelstolt/lean-package-validator/issues/9')]
    public function providedInvalidGlobPatternFileFailsValidation(): void
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
            'directory' => $this->temporaryDirectory,
            '--glob-pattern-file' => $temporaryLpvFile,
        ]);

        $expectedDisplay = <<<CONTENT
Warning: The provided glob pattern file '{$temporaryLpvFile}' is considered invalid.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() > Command::SUCCESS);
    }

    #[Test]
    #[Ticket('https://github.com/raphaelstolt/lean-package-validator/issues/4')]
    public function precedingSlashesInExportIgnorePatternsRaiseAWarning(): void
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
            'directory' => $this->temporaryDirectory,
        ]);

        $expectedDisplay = <<<CONTENT
The present .gitattributes file is considered valid.
Warning: At least one export-ignore pattern has a leading '/', which is considered as a smell.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $commandTester->assertCommandIsSuccessful();
    }

    #[Test]
    #[Ticket('https://github.com/raphaelstolt/lean-package-validator/issues/12')]
    public function missingTextAutoConfigurationRaisesAWarning(): void
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
            'directory' => $this->temporaryDirectory,
        ]);

        $expectedDisplay = <<<CONTENT
The present .gitattributes file is considered valid.
Warning: Missing a text auto configuration. Consider adding one.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $commandTester->assertCommandIsSuccessful();
    }

    #[Test]
    #[Ticket('https://github.com/raphaelstolt/lean-package-validator/issues/17')]
    public function gitignoredFilesAreExcludedFromValidation(): void
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
            'directory' => $this->temporaryDirectory,
        ]);

        $expectedDisplay = <<<CONTENT
The present .gitattributes file is considered valid.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $commandTester->assertCommandIsSuccessful();
    }


    #[Test]
    #[RunInSeparateProcess]
    public function detectsNonGitattributeContentInStdinInput(): void
    {
        $application = new Application();
        $fakeInputReader = new FakeInputReader();
        $fakeInputReader->set('Some non .gitattributes content.');

        $analyserCommand = new ValidateCommand(
            new Analyser(new ClassicExportIgnoreAnalyser(new Finder(new PhpPreset()), new GitattributesFileRepository($this->temporaryDirectory))),
            new Validator(new Archive(\getcwd())),
            $fakeInputReader,
            new GitattributesFileRepository($this->temporaryDirectory)
        );

        $application->addCommand($analyserCommand);
        $command = $application->find('validate');

        $expectedDisplay = <<<CONTENT
Warning: The provided input stream seems to be no .gitattributes content.
CONTENT;

        TestCommand::for($command)
            ->addOption('stdin-input')
            ->execute()
            ->assertOutputContains($expectedDisplay)
            ->assertFaulty();
    }

    #[Test]
    #[RunInSeparateProcess]
    public function detectsValidGitattributesContentInStdinInput(): void
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
.php_cs.cache export-ignore
composer.lock export-ignore
.gitignore export-ignore
.gitattributes export-ignore

CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $application = new Application();
        $fakeInputReader = new FakeInputReader();
        $fakeInputReader->set($gitattributesContent);

        $analyserCommand = new ValidateCommand(
            new Analyser(new ClassicExportIgnoreAnalyser(new Finder(new PhpPreset()), new GitattributesFileRepository($this->temporaryDirectory))),
            new Validator(new Archive($this->temporaryDirectory)),
            $fakeInputReader,
            new GitattributesFileRepository($this->temporaryDirectory)
        );

        $application->addCommand($analyserCommand);
        $command = $application->find('validate');

        $expectedDisplay = <<<CONTENT
The provided .gitattributes content is considered valid.
CONTENT;

        TestCommand::for($command)
            ->addArgument($this->temporaryDirectory)
            ->addOption('stdin-input')
            ->execute()
            ->assertOutputContains($expectedDisplay)
            ->assertSuccessful();
    }

    #[Test]
    #[RunInSeparateProcess]
    public function usesTheRustPresetIfRequested(): void
    {
        $artifactFilenames = [
            'CODE_OF_CONDUCT.md',
            '.rustfmt.toml',
            '.clippy.toml',
            'LICENSE.md',
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['src']
        );

        $gitattributesContent = <<<CONTENT
*    text=auto eol=lf

.clippy.toml export-ignore
.gitattributes export-ignore
.rustfmt.toml export-ignore
CODE_OF_CONDUCT.md export-ignore
LICENSE.md export-ignore
CONTENT;

        $expectedDisplay = <<<CONTENT
The present .gitattributes file is considered valid.

CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => $this->temporaryDirectory,
            '--preset' => 'Rust',
        ]);

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $commandTester->assertCommandIsSuccessful();
    }

    /**
     * @param MockInterface $mockedAnalyser
     * @return Application
     */
    protected function getApplicationWithMockedAnalyser(MockInterface $mockedAnalyser): Application
    {
        $application = new Application();

        $archive = new Archive(
            $this->temporaryDirectory
        );

        $analyserCommand = new ValidateCommand(
            $mockedAnalyser,
            new Validator($archive),
            new FakeInputReader(),
            new GitattributesFileRepository($this->temporaryDirectory)
        );

        $application->addCommand($analyserCommand);

        return $application;
    }

    /**
     * @param MockInterface $mockedArchiveValidator
     * @return Application
     */
    protected function getApplicationWithMockedArchiveValidator(MockInterface $mockedArchiveValidator): Application
    {
        $application = new Application();

        $analyserCommand = new ValidateCommand(
            new Analyser(new ClassicExportIgnoreAnalyser(new Finder(new PhpPreset()), new GitattributesFileRepository($this->temporaryDirectory))),
            $mockedArchiveValidator,
            new FakeInputReader(),
            new GitattributesFileRepository($this->temporaryDirectory)
        );

        $application->addCommand($analyserCommand);

        return $application;
    }

    #[Test]
    public function outputsJsonOnSuccessInAnAgenticRun(): void
    {
        $artifactFilenames = ['.buildignore', 'phpspec.yml.dist', 'foo.txt'];
        $this->createTemporaryFiles($artifactFilenames, ['specs']);

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

        \putenv('COPILOT_MODEL=1');

        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => $this->temporaryDirectory,
        ]);

        $commandTester->assertCommandIsSuccessful();

        $json = \json_decode(\trim($commandTester->getDisplay()), true);

        $this->assertIsArray($json);
        $this->assertSame('validate', $json['command']);
        $this->assertSame('success', $json['status']);
        $this->assertTrue($json['valid']);
    }

    #[Test]
    public function outputsJsonOnFailureInAnAgenticRun(): void
    {
        $artifactFilenames = ['.buildignore', 'phpspec.yml.dist'];
        $this->createTemporaryFiles($artifactFilenames, ['specs']);

        $gitattributesContent = <<<CONTENT
*    text=auto eol=lf

.gitattributes export-ignore
CONTENT;
        $this->createTemporaryGitattributesFile($gitattributesContent);

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);

        \putenv('COPILOT_MODEL=1');

        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => $this->temporaryDirectory,
        ]);

        $this->assertTrue($commandTester->getStatusCode() !== Command::SUCCESS);

        $json = \json_decode(\trim($commandTester->getDisplay()), true);

        $this->assertIsArray($json);
        $this->assertSame('validate', $json['command']);
        $this->assertSame('failure', $json['status']);
        $this->assertFalse($json['valid']);
        $this->assertArrayHasKey('expected_gitattributes_content', $json);
    }

    #[Test]
    public function outputsJsonOnMissingGitattributesFileInAnAgenticRun(): void
    {
        $this->createTemporaryFiles(['.buildignore', 'phpspec.yml.dist'], ['specs']);

        $command = $this->application->find('validate');
        $commandTester = new CommandTester($command);

        \putenv('COPILOT_MODEL=1');

        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => $this->temporaryDirectory,
        ]);

        $this->assertTrue($commandTester->getStatusCode() !== Command::SUCCESS);

        $json = \json_decode(\trim($commandTester->getDisplay()), true);

        $this->assertIsArray($json);
        $this->assertSame('validate', $json['command']);
        $this->assertSame('failure', $json['status']);
        $this->assertFalse($json['valid']);
        $this->assertArrayHasKey('expected_gitattributes_content', $json);
    }

    #[Test]
    public function validatesNegatedExportIgnoreDirectivesAsValid(): void
    {
        $this->createTemporaryFiles(['composer.json', 'LICENSE.md'], ['src', 'bin']);

        $this->createTemporaryFilesInDirectory($this->temporaryDirectory . DIRECTORY_SEPARATOR . 'bin', ['test-cli']);
        $this->createTemporaryFilesInDirectory($this->temporaryDirectory . DIRECTORY_SEPARATOR . 'src', ['Foo.php', 'Bar.php']);

        $gitattributesContent = <<<CONTENT
# Line-ending normalization for text files.
* text=auto

# PHP-aware hunk headers in `git diff`.
*.php diff=php

# Binary files keep their bytes.
*.gpg binary
*.phar binary

# Default: nothing is included in the dist archive of the Composer package.
# Re-include only the files that actually belong to the distributed package below.
* export-ignore

# Top-level metadata.
composer.json -export-ignore
LICENSE.md -export-ignore

# Source code and CLI entry point.
/src -export-ignore
/src** -export-ignore
/bin -export-ignore
/bin/test-cli -export-ignore
CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $command = $this->application->find('validate');

        TestCommand::for($command)
            ->addArgument($this->temporaryDirectory)
            ->execute()
            ->assertOutputContains('The present .gitattributes file is considered valid.')
            ->assertSuccessful();
    }

    #[Test]
    public function validatesIncompleteNegatedExportIgnoreDirectivesAsInvalid(): void
    {
        $this->createTemporaryFiles(['composer.json'], ['src']);

        $gitattributesContent = <<<CONTENT
# Default: nothing is included in the dist archive of the Composer package.
* export-ignore
CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $command = $this->application->find('validate');

        TestCommand::for($command)
            ->addArgument($this->temporaryDirectory)
            ->execute()
            ->assertOutputContains('The present .gitattributes file with negated export-ignore directives is considered invalid.')
            ->assertFaulty();
    }

    #[Test]
    #[RunInSeparateProcess]
    public function validatesNegatedExportIgnoreDirectivesFromStdinAsValid(): void
    {
        $this->createTemporaryFiles(['composer.json', 'LICENSE.md'], ['src']);

        $gitattributesContent = <<<CONTENT
* export-ignore

/composer.json -export-ignore
/LICENSE.md    -export-ignore
/src           -export-ignore
CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $application = new Application();
        $fakeInputReader = new FakeInputReader();
        $fakeInputReader->set($gitattributesContent);

        $analyserCommand = new ValidateCommand(
            new Analyser(new ClassicExportIgnoreAnalyser(new Finder(new PhpPreset()), new GitattributesFileRepository($this->temporaryDirectory))),
            new Validator(new Archive($this->temporaryDirectory)),
            $fakeInputReader,
            new GitattributesFileRepository($this->temporaryDirectory)
        );

        $application->addCommand($analyserCommand);
        $command = $application->find('validate');

        TestCommand::for($command)
            ->addOption('stdin-input')
            ->execute()
            ->assertOutputContains('The provided .gitattributes content is considered valid.')
            ->assertSuccessful();
    }

    #[Test]
    public function detectsStaleNegatedExportIgnoreDirectivesAsInvalid(): void
    {
        $this->createTemporaryFiles(['composer.json', 'LICENSE.md'], ['src']);

        $gitattributesContent = <<<CONTENT
* export-ignore

composer.json      -export-ignore
LICENSE.md         -export-ignore
NON_EXISTENT.md    -export-ignore
/src               -export-ignore
/stale-non-existent-dir -export-ignore
CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $command = $this->application->find('validate');

        TestCommand::for($command)
            ->addArgument($this->temporaryDirectory)
            ->addOption('report-stale-export-ignores')
            ->execute()
            ->assertOutputContains('The present .gitattributes file with negated export-ignore directives is considered invalid.')
            ->assertFaulty();
    }

    #[Test]
    public function detectsIncompleteNegatedExportIgnoreDirectivesAsInvalid(): void
    {
        $this->createTemporaryFiles(['composer.json', 'LICENSE.md'], ['src', 'resources']);

        $this->createTemporaryFilesInDirectory($this->temporaryDirectory . DIRECTORY_SEPARATOR . 'src', ['Foo.php', 'Bar.php']);
        $this->createTemporaryFilesInDirectory($this->temporaryDirectory . DIRECTORY_SEPARATOR . 'resources', ['foo', 'bar']);

        $gitattributesContent = <<<CONTENT
* export-ignore

composer.json      -export-ignore
LICENSE.md         -export-ignore
NON_EXISTENT.md    -export-ignore
src/               -export-ignore
resources/         -export-ignore
CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);

        $command = $this->application->find('validate');

        TestCommand::for($command)
            ->addArgument($this->temporaryDirectory)
            ->execute()
            ->assertOutputContains('The present .gitattributes file with negated export-ignore directives is considered invalid.')
            ->assertFaulty();
    }
}
