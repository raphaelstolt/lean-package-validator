<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Tests\Commands;

use PHPUnit\Framework\Attributes\Test;
use Stolt\LeanPackage\Archive;
use Stolt\LeanPackage\Commands\TreeCommand;
use Stolt\LeanPackage\Tests\CommandTester;
use Stolt\LeanPackage\Tests\TestCase;
use Stolt\LeanPackage\Tree;
use Symfony\Component\Console\Application;

class TreeCommandTest extends TestCase
{
    /**
     * @var Application
     */
    private $application;

    /**
     * Set up test environment.
     */
    protected function setUp(): void
    {
        $this->setUpTemporaryDirectory();
        if (!\defined('WORKING_DIRECTORY')) {
            \define('WORKING_DIRECTORY', $this->temporaryDirectory);
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
        if (\is_dir($this->temporaryDirectory)) {
            $this->removeDirectory($this->temporaryDirectory);
        }
    }

    #[Test]
    public function displayExpectedSrcTree(): void
    {
        $command = $this->application->find('tree');
        $commandTester = new CommandTester($command);

        $artifactFilenames = [
            '.gitattributes',
            'composer.json',
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['src', 'tests', '.github', 'docs', 'bin']
        );

        file_put_contents(WORKING_DIRECTORY . '/composer.json', \json_encode(['name' => 'test-src/package']));

        $expectedDisplay = <<<CONTENT
Package: test-src/package
.
├── bin
├── docs
├── .github
├── src
├── tests
├── composer.json
└── .gitattributes

5 directories, 2 files

CONTENT;

        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
            '--src' => true
        ]);

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $commandTester->assertCommandIsSuccessful();
    }

    #[Test]
    public function displayExpectedDistPackageTree(): void
    {
        $command = $this->application->find('tree');
        $commandTester = new CommandTester($command);

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
            'package-lock.json',
            'composer.json',
        ];

        $this->createTemporaryFiles(
            $artifactFilenames,
            ['src', 'tests', '.github', 'docs', 'bin']
        );

        file_put_contents(WORKING_DIRECTORY . '/composer.json', \json_encode(['name' => 'test-dist/package']));

        $gitattributesContent = <<<CONTENT
.gitattributes export-ignore
.gitignore export-ignore
captainhook.json export-ignore
CODE_OF_CONDUCT.md export-ignore
CONTRIBUTING.md export-ignore
infection.json5 export-ignore
LICENSE.txt export-ignore
phpstan.neon export-ignore
phpunit.xml export-ignore
README.md export-ignore
sonar-project.properties export-ignore
package.json export-ignore
package-lock.json export-ignore
composer.json export-ignore
tests/ export-ignore
.github/ export-ignore
docs/ export-ignore
CONTENT;

        $this->createTemporaryGitattributesFile($gitattributesContent);


        $expectedDisplay = <<<CONTENT
Package: test-dist/package
.
├── bin
├── composer.json
└── src

2 directories, 1 file

CONTENT;

        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
        ]);

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $commandTester->assertCommandIsSuccessful();
    }

    /**
     * @return Application
     */
    protected function getApplication(): Application
    {
        $application = new Application();
        $application->add(new TreeCommand(new Tree(new Archive(WORKING_DIRECTORY))));

        return $application;
    }
}
