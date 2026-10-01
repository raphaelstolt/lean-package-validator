<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Tests\Commands;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Stolt\LeanPackage\Analyser;
use Stolt\LeanPackage\Analysers\ClassicExportIgnoreAnalyser;
use Stolt\LeanPackage\Commands\ValidateRemoteCommand;
use Stolt\LeanPackage\Exceptions\GitNotAvailable;
use Stolt\LeanPackage\Exceptions\RepositoryCloneFailed;
use Stolt\LeanPackage\Gitattributes\FileRepository as GitattributesFileRepository;
use Stolt\LeanPackage\Git\RepositoryCloner;
use Stolt\LeanPackage\Presets\Finder;
use Stolt\LeanPackage\Presets\PhpPreset;
use Stolt\LeanPackage\Tests\TestCase;
use Zenstruck\Console\Test\TestCommand;

final class ValidateRemoteCommandTest extends TestCase
{
    private const REPOSITORY_URL = 'https://github.com/acme/package';

    private const VALID_GITATTRIBUTES_CONTENT = <<<CONTENT
* text=auto eol=lf

.gitattributes export-ignore
.gitignore export-ignore
phpunit.xml.dist export-ignore
README.md export-ignore
tests/ export-ignore
CONTENT;

    private Analyser $analyser;

    private GitattributesFileRepository $gitattributesFileRepository;

    private string $cloneDirectory;

    /**
     * @var string[]
     */
    private array $clonedTargetDirectories = [];

    protected function setUp(): void
    {
        $this->setUpTemporaryDirectory();
        if (!\defined('WORKING_DIRECTORY')) {
            \define('WORKING_DIRECTORY', $this->temporaryDirectory);
        }

        $this->cloneDirectory = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'clone';
        $this->gitattributesFileRepository = new GitattributesFileRepository($this->temporaryDirectory);
        $this->analyser = new Analyser(new ClassicExportIgnoreAnalyser(
            new Finder(new PhpPreset()),
            $this->gitattributesFileRepository,
            $this->temporaryDirectory
        ));
        $this->analyser->getActualExportIgnoreAnalyser()->setDirectory($this->temporaryDirectory);
    }

    protected function tearDown(): void
    {
        foreach ($this->clonedTargetDirectories as $clonedTargetDirectory) {
            if (\is_dir($clonedTargetDirectory)) {
                $this->removeDirectory($clonedTargetDirectory);
            }
        }

        if (\is_dir($this->temporaryDirectory)) {
            $this->removeDirectory($this->temporaryDirectory);
        }
        $this->clearAgenticEnvironment();
    }

    /**
     * @param array<string, string> $files Relative file paths with their content.
     */
    private function getCommandInstance(array $files = []): ValidateRemoteCommand
    {
        return new ValidateRemoteCommand(
            $this->analyser,
            $this->gitattributesFileRepository,
            $this->getFakeCloner($files)
        );
    }

    /**
     * Fake a clone by populating the target directory with the given files.
     *
     * @param array<string, string> $files Relative file paths with their content.
     */
    private function getFakeCloner(array $files): RepositoryCloner
    {
        $clonedTargetDirectories = &$this->clonedTargetDirectories;

        return new class ($files, $clonedTargetDirectories) implements RepositoryCloner {
            /**
             * @param array<string, string> $files
             * @param string[] $clonedTargetDirectories
             */
            public function __construct(private readonly array $files, private array &$clonedTargetDirectories)
            {
            }

            public function clone(string $repositoryUrl, string $targetDirectory): void
            {
                $this->clonedTargetDirectories[] = $targetDirectory;

                if (!\is_dir($targetDirectory)) {
                    \mkdir($targetDirectory, permissions: 0o777, recursive: true);
                }

                foreach ($this->files as $file => $content) {
                    $path = $targetDirectory . DIRECTORY_SEPARATOR . $file;
                    if (!\is_dir(\dirname($path))) {
                        \mkdir(\dirname($path), permissions: 0o777, recursive: true);
                    }
                    \file_put_contents($path, $content);
                }
            }
        };
    }

    /**
     * @return array<string, string>
     */
    private function getRepositoryFiles(): array
    {
        return [
            'README.md' => '',
            '.gitignore' => '',
            'composer.json' => '{}',
            'phpunit.xml.dist' => '',
            'src/Package.php' => '',
            'tests/PackageTest.php' => '',
        ];
    }

    private function getClonedGitattributesContent(): string
    {
        return (string) \file_get_contents($this->cloneDirectory . DIRECTORY_SEPARATOR . '.gitattributes');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidRepositoryUrls(): array
    {
        return [
            'GitLab URL' => ['https://gitlab.com/acme/package'],
            'Missing repository' => ['https://github.com/acme'],
            'Plain HTTP' => ['http://github.com/acme/package'],
            'Local path' => ['/tmp/acme/package'],
            'Nested path' => ['https://github.com/acme/package/tree/main'],
        ];
    }

    #[Test]
    #[DataProvider('invalidRepositoryUrls')]
    public function failsForInvalidGitHubRepositoryUrls(string $repositoryUrl): void
    {
        TestCommand::for($this->getCommandInstance())
            ->addArgument($repositoryUrl)
            ->addOption('clone-directory', $this->cloneDirectory)
            ->execute()
            ->assertFaulty()
            ->assertOutputContains("The provided repository URL '{$repositoryUrl}' is not a valid GitHub repository URL.");

        $this->assertDirectoryDoesNotExist($this->cloneDirectory);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function validRepositoryUrls(): array
    {
        return [
            'HTTPS URL' => ['https://github.com/acme/package'],
            'HTTPS URL with .git suffix' => ['https://github.com/acme/package.git'],
            'HTTPS URL with trailing slash' => ['https://github.com/acme/package/'],
            'SSH URL' => ['git@github.com:acme/package.git'],
            'Dotted repository name' => ['https://github.com/acme/package.js'],
        ];
    }

    #[Test]
    #[DataProvider('validRepositoryUrls')]
    public function acceptsGitHubRepositoryUrls(string $repositoryUrl): void
    {
        TestCommand::for($this->getCommandInstance($this->getRepositoryFiles()))
            ->addArgument($repositoryUrl)
            ->addOption('clone-directory', $this->cloneDirectory)
            ->execute()
            ->assertSuccessful();
    }

    #[Test]
    public function failsWhenCloningFails(): void
    {
        $failingCloner = new class () implements RepositoryCloner {
            public function clone(string $repositoryUrl, string $targetDirectory): void
            {
                throw new RepositoryCloneFailed('fatal: repository not found');
            }
        };

        TestCommand::for(new ValidateRemoteCommand($this->analyser, $this->gitattributesFileRepository, $failingCloner))
            ->addArgument(self::REPOSITORY_URL)
            ->addOption('clone-directory', $this->cloneDirectory)
            ->execute()
            ->assertFaulty()
            ->assertOutputContains('Cloning the repository ' . self::REPOSITORY_URL . ' failed.');
    }

    #[Test]
    public function failsWhenGitIsNotAvailable(): void
    {
        $gitlessCloner = new class () implements RepositoryCloner {
            public function clone(string $repositoryUrl, string $targetDirectory): void
            {
                throw new GitNotAvailable('The Git command is not available.');
            }
        };

        TestCommand::for(new ValidateRemoteCommand($this->analyser, $this->gitattributesFileRepository, $gitlessCloner))
            ->addArgument(self::REPOSITORY_URL)
            ->addOption('clone-directory', $this->cloneDirectory)
            ->execute()
            ->assertFaulty()
            ->assertOutputContains('The Git command is not available.');
    }

    #[Test]
    public function failsForNonEmptyCloneDirectory(): void
    {
        \mkdir($this->cloneDirectory);
        \touch($this->cloneDirectory . DIRECTORY_SEPARATOR . 'present-file');

        TestCommand::for($this->getCommandInstance($this->getRepositoryFiles()))
            ->addArgument(self::REPOSITORY_URL)
            ->addOption('clone-directory', $this->cloneDirectory)
            ->execute()
            ->assertFaulty()
            ->assertOutputContains("The clone directory {$this->cloneDirectory} already exists and is not empty.");

        $this->assertFileDoesNotExist($this->cloneDirectory . DIRECTORY_SEPARATOR . '.gitattributes');
    }

    #[Test]
    public function clonesIntoAnEmptyCloneDirectory(): void
    {
        \mkdir($this->cloneDirectory);

        TestCommand::for($this->getCommandInstance($this->getRepositoryFiles()))
            ->addArgument(self::REPOSITORY_URL)
            ->addOption('clone-directory', $this->cloneDirectory)
            ->execute()
            ->assertSuccessful();

        $this->assertFileExists($this->cloneDirectory . DIRECTORY_SEPARATOR . '.gitattributes');
    }

    #[Test]
    public function reportsAValidGitattributesFile(): void
    {
        $files = $this->getRepositoryFiles();
        $files['.gitattributes'] = self::VALID_GITATTRIBUTES_CONTENT;

        TestCommand::for($this->getCommandInstance($files))
            ->addArgument(self::REPOSITORY_URL)
            ->addOption('clone-directory', $this->cloneDirectory)
            ->execute()
            ->assertSuccessful()
            ->assertOutputContains('The .gitattributes file of ' . self::REPOSITORY_URL . ' is considered valid.');

        $this->assertSame(self::VALID_GITATTRIBUTES_CONTENT, $this->getClonedGitattributesContent());
    }

    #[Test]
    public function createsAGitattributesFileWhenNoneIsPresent(): void
    {
        TestCommand::for($this->getCommandInstance($this->getRepositoryFiles()))
            ->addArgument(self::REPOSITORY_URL)
            ->addOption('clone-directory', $this->cloneDirectory)
            ->execute()
            ->assertSuccessful()
            ->assertOutputContains('There is no .gitattributes file present in ' . self::REPOSITORY_URL . '.')
            ->assertOutputContains('Created a .gitattributes file in ' . \realpath($this->cloneDirectory) . '.');

        $expectedGitattributesContent = GitattributesFileRepository::GENERATED_HEADER
            . PHP_EOL . PHP_EOL . self::VALID_GITATTRIBUTES_CONTENT;

        $this->assertStringContainsStringIgnoringLineEndings(
            $expectedGitattributesContent,
            $this->getClonedGitattributesContent()
        );
    }

    #[Test]
    public function updatesAnInvalidGitattributesFile(): void
    {
        $files = $this->getRepositoryFiles();
        $files['.gitattributes'] = <<<CONTENT
* text=auto eol=lf

.gitattributes export-ignore
tests/ export-ignore
CONTENT;

        TestCommand::for($this->getCommandInstance($files))
            ->addArgument(self::REPOSITORY_URL)
            ->addOption('clone-directory', $this->cloneDirectory)
            ->execute()
            ->assertSuccessful()
            ->assertOutputContains('The .gitattributes file of ' . self::REPOSITORY_URL . ' is considered invalid.')
            ->assertOutputContains('Updated the .gitattributes file in ' . \realpath($this->cloneDirectory) . '.');

        $content = $this->getClonedGitattributesContent();

        $this->assertStringContainsString(GitattributesFileRepository::MODIFIED_HEADER, $content);
        $this->assertStringContainsStringIgnoringLineEndings(self::VALID_GITATTRIBUTES_CONTENT, $content);
    }

    #[Test]
    public function updatesAnInvalidGitattributesFileWithNegatedExportIgnores(): void
    {
        $files = $this->getRepositoryFiles();
        $files['.gitattributes'] = <<<CONTENT
* text=auto eol=lf

* export-ignore

src/ -export-ignore
CONTENT;

        TestCommand::for($this->getCommandInstance($files))
            ->addArgument(self::REPOSITORY_URL)
            ->addOption('clone-directory', $this->cloneDirectory)
            ->execute()
            ->assertSuccessful()
            ->assertOutputContains('The .gitattributes file of ' . self::REPOSITORY_URL . ' is considered invalid.');

        $content = $this->getClonedGitattributesContent();

        $this->assertStringContainsString('* export-ignore', $content);
        $this->assertStringContainsString('composer.json -export-ignore', $content);
        $this->assertStringContainsString('src/** -export-ignore', $content);
    }

    #[Test]
    public function doesNotWriteAGitattributesFileOnDryRun(): void
    {
        $result = TestCommand::for($this->getCommandInstance($this->getRepositoryFiles()))
            ->addArgument(self::REPOSITORY_URL)
            ->addOption('clone-directory', $this->cloneDirectory)
            ->addOption('dry-run')
            ->execute()
            ->assertSuccessful();

        $this->assertStringContainsStringIgnoringLineEndings(self::VALID_GITATTRIBUTES_CONTENT, $result->output());
        $this->assertFileDoesNotExist($this->cloneDirectory . DIRECTORY_SEPARATOR . '.gitattributes');
    }

    #[Test]
    public function appliesGenerationOptions(): void
    {
        TestCommand::for($this->getCommandInstance($this->getRepositoryFiles()))
            ->addArgument(self::REPOSITORY_URL)
            ->addOption('clone-directory', $this->cloneDirectory)
            ->addOption('keep-readme')
            ->execute()
            ->assertSuccessful();

        $this->assertStringNotContainsString('README.md', $this->getClonedGitattributesContent());
    }

    #[Test]
    public function usesTheLpvFileOfTheClonedRepository(): void
    {
        $files = $this->getRepositoryFiles();
        $files['.lpv'] = 'phpunit*' . PHP_EOL . '.gitignore' . PHP_EOL;

        TestCommand::for($this->getCommandInstance($files))
            ->addArgument(self::REPOSITORY_URL)
            ->addOption('clone-directory', $this->cloneDirectory)
            ->execute()
            ->assertSuccessful();

        $content = $this->getClonedGitattributesContent();

        $this->assertStringContainsString('phpunit.xml.dist export-ignore', $content);
        $this->assertStringNotContainsString('README.md', $content);
        $this->assertStringNotContainsString('tests/', $content);
    }

    #[Test]
    public function ignoresTheLpvFileOfTheWorkingDirectory(): void
    {
        \file_put_contents(WORKING_DIRECTORY . DIRECTORY_SEPARATOR . '.lpv', 'phpunit*' . PHP_EOL . '.gitignore' . PHP_EOL);

        TestCommand::for($this->getCommandInstance($this->getRepositoryFiles()))
            ->addArgument(self::REPOSITORY_URL)
            ->addOption('clone-directory', $this->cloneDirectory)
            ->execute()
            ->assertSuccessful();

        $this->assertStringContainsStringIgnoringLineEndings(
            self::VALID_GITATTRIBUTES_CONTENT,
            $this->getClonedGitattributesContent()
        );
    }

    #[Test]
    public function usesAnExplicitlyProvidedWorkingDirectoryLpvFile(): void
    {
        $workingDirectoryLpvFile = WORKING_DIRECTORY . DIRECTORY_SEPARATOR . '.lpv';
        \file_put_contents($workingDirectoryLpvFile, 'phpunit*' . PHP_EOL . '.gitignore' . PHP_EOL);

        $files = $this->getRepositoryFiles();
        $files['.lpv'] = 'README*' . PHP_EOL . '.gitignore' . PHP_EOL;

        TestCommand::for($this->getCommandInstance($files))
            ->addArgument(self::REPOSITORY_URL)
            ->addOption('clone-directory', $this->cloneDirectory)
            ->addOption('glob-pattern-file', $workingDirectoryLpvFile)
            ->execute()
            ->assertSuccessful();

        $content = $this->getClonedGitattributesContent();

        $this->assertStringContainsString('phpunit.xml.dist export-ignore', $content);
        $this->assertStringNotContainsString('README.md', $content);
    }

    #[Test]
    public function removesTheTemporaryCloneOfAValidRepository(): void
    {
        $files = $this->getRepositoryFiles();
        $files['.gitattributes'] = self::VALID_GITATTRIBUTES_CONTENT;

        TestCommand::for($this->getCommandInstance($files))
            ->addArgument(self::REPOSITORY_URL)
            ->execute()
            ->assertSuccessful();

        $this->assertCount(1, $this->clonedTargetDirectories);
        $this->assertStringStartsWith(
            \sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lpv-remote-acme-package-',
            $this->clonedTargetDirectories[0]
        );
        $this->assertDirectoryDoesNotExist($this->clonedTargetDirectories[0]);
    }

    #[Test]
    public function keepsTheTemporaryCloneWhenAGitattributesFileHasBeenWritten(): void
    {
        TestCommand::for($this->getCommandInstance($this->getRepositoryFiles()))
            ->addArgument(self::REPOSITORY_URL)
            ->execute()
            ->assertSuccessful();

        $this->assertCount(1, $this->clonedTargetDirectories);
        $this->assertFileExists($this->clonedTargetDirectories[0] . DIRECTORY_SEPARATOR . '.gitattributes');
    }

    #[Test]
    public function outputsJsonOnCreationInAnAgenticRun(): void
    {
        \putenv('COPILOT_MODEL=1');

        $result = TestCommand::for($this->getCommandInstance($this->getRepositoryFiles()))
            ->addArgument(self::REPOSITORY_URL)
            ->addOption('clone-directory', $this->cloneDirectory)
            ->execute()
            ->assertSuccessful();

        $json = \json_decode(\trim($result->output()), associative: true);

        $this->assertIsArray($json);
        $this->assertSame('validate-remote', $json['command']);
        $this->assertSame('success', $json['status']);
        $this->assertFalse($json['valid']);
        $this->assertSame(ValidateRemoteCommand::ACTION_CREATED, $json['action']);
        $this->assertSame(\realpath($this->cloneDirectory), $json['clone_directory']);
        $this->assertStringContainsString('tests/ export-ignore', $json['gitattributes_content']);
    }

    #[Test]
    public function outputsJsonForAValidRepositoryInAnAgenticRun(): void
    {
        $files = $this->getRepositoryFiles();
        $files['.gitattributes'] = self::VALID_GITATTRIBUTES_CONTENT;

        \putenv('COPILOT_MODEL=1');

        $result = TestCommand::for($this->getCommandInstance($files))
            ->addArgument(self::REPOSITORY_URL)
            ->addOption('clone-directory', $this->cloneDirectory)
            ->execute()
            ->assertSuccessful();

        $json = \json_decode(\trim($result->output()), associative: true);

        $this->assertIsArray($json);
        $this->assertTrue($json['valid']);
        $this->assertSame(ValidateRemoteCommand::ACTION_NONE, $json['action']);
    }

    #[Test]
    public function outputsJsonOnFailureInAnAgenticRun(): void
    {
        \putenv('COPILOT_MODEL=1');

        $result = TestCommand::for($this->getCommandInstance())
            ->addArgument('https://gitlab.com/acme/package')
            ->execute()
            ->assertFaulty();

        $json = \json_decode(\trim($result->output()), associative: true);

        $this->assertIsArray($json);
        $this->assertSame('validate-remote', $json['command']);
        $this->assertSame('failure', $json['status']);
    }
}
