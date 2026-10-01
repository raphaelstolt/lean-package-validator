<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Tests\Git;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Stolt\LeanPackage\Exceptions\RepositoryCloneFailed;
use Stolt\LeanPackage\Git\GitRepositoryCloner;
use Stolt\LeanPackage\Tests\TestCase;

final class GitRepositoryClonerTest extends TestCase
{
    private string $originDirectory;

    private string $cloneDirectory;

    protected function setUp(): void
    {
        $this->setUpTemporaryDirectory();

        $this->originDirectory = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'origin';
        $this->cloneDirectory = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'clone';
    }

    protected function tearDown(): void
    {
        if (\is_dir($this->temporaryDirectory)) {
            \exec('chmod -R u+w ' . \escapeshellarg($this->temporaryDirectory) . ' 2>&1');
            $this->removeDirectory($this->temporaryDirectory);
        }
    }

    private function createOriginRepository(): void
    {
        \mkdir($this->originDirectory);
        \file_put_contents($this->originDirectory . DIRECTORY_SEPARATOR . 'README.md', data: '# Origin');

        $commands = [
            'git init --quiet',
            'git add README.md',
            'git -c user.name=lpv -c user.email=lpv@example.org -c commit.gpgsign=false commit --quiet -m "Initial commit"',
        ];

        foreach ($commands as $command) {
            \exec('cd ' . \escapeshellarg($this->originDirectory) . ' && ' . $command . ' 2>&1', $output, $returnValue);
            $this->assertSame(0, $returnValue, \implode(PHP_EOL, $output));
        }
    }

    #[Test]
    #[Group('integration')]
    public function clonesARepository(): void
    {
        $this->createOriginRepository();

        (new GitRepositoryCloner())->clone('file://' . $this->originDirectory, $this->cloneDirectory);

        $this->assertDirectoryExists($this->cloneDirectory . DIRECTORY_SEPARATOR . '.git');
        $this->assertStringEqualsFile($this->cloneDirectory . DIRECTORY_SEPARATOR . 'README.md', '# Origin');
    }

    #[Test]
    #[Group('integration')]
    public function throwsExpectedExceptionWhenCloningFails(): void
    {
        $this->expectException(RepositoryCloneFailed::class);

        (new GitRepositoryCloner())->clone(
            'file://' . $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'non-existent',
            $this->cloneDirectory
        );
    }
}
