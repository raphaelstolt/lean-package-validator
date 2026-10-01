<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Git;

use Stolt\LeanPackage\Exceptions\GitNotAvailable;
use Stolt\LeanPackage\Exceptions\RepositoryCloneFailed;
use Stolt\LeanPackage\Helpers\Str as OsHelper;

final class GitRepositoryCloner implements RepositoryCloner
{
    /**
     * Clone the repository behind the given URL into the target directory
     * via a shallow Git clone.
     *
     * @param string $repositoryUrl The URL of the repository to clone.
     * @param string $targetDirectory The directory to clone the repository into.
     * @throws GitNotAvailable|RepositoryCloneFailed
     * @return void
     */
    public function clone(string $repositoryUrl, string $targetDirectory): void
    {
        if (!$this->isGitCommandAvailable()) {
            throw new GitNotAvailable('The Git command is not available.');
        }

        $command = \sprintf(
            'git clone --depth 1 --quiet %s %s 2>&1',
            \escapeshellarg($repositoryUrl),
            \escapeshellarg($targetDirectory)
        );

        \exec($command, $output, $returnValue);

        if ($returnValue !== 0) {
            throw new RepositoryCloneFailed(\trim(\implode(PHP_EOL, $output)));
        }
    }

    private function isGitCommandAvailable(): bool
    {
        $lookupCommand = (new OsHelper())->isWindows() ? 'where git 2>&1' : 'which git 2>&1';
        \exec($lookupCommand, $output, $returnValue);

        return $returnValue === 0;
    }
}
