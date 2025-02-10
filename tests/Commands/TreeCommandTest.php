<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Tests\Commands;

use PHPUnit\Framework\Attributes\Test;
use Stolt\LeanPackage\Archive;
use Stolt\LeanPackage\Commands\TreeCommand;
use Stolt\LeanPackage\Exceptions\GitNotAvailable;
use Stolt\LeanPackage\Tests\CommandTester;
use Stolt\LeanPackage\Tests\TestCase;
use Stolt\LeanPackage\Tree;
use Symfony\Component\Console\Application;

class TreeCommandTest extends TestCase
{
    /**
     * @var Application
     */
    private Application $application;

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
    public function displaysExpectedSrcTree(): void
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

        file_put_contents($this->temporaryDirectory . '/composer.json', \json_encode(['name' => 'test-src/package']));

        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => $this->temporaryDirectory,
            '--src' => true
        ]);

        $this->assertStringContainsString('5 directories, 2 files', $commandTester->getDisplay());
        $commandTester->assertCommandIsSuccessful();
    }

    /**
     * @throws GitNotAvailable
     * @return Application
     */
    protected function getApplication(): Application
    {
        $application = new Application();
        $application->add(new TreeCommand(new Tree(new Archive(WORKING_DIRECTORY))));

        return $application;
    }
}
