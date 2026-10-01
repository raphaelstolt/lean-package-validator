<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Git;

use Stolt\LeanPackage\Exceptions\GitNotAvailable;
use Stolt\LeanPackage\Exceptions\RepositoryCloneFailed;

interface RepositoryCloner
{
    /**
     * Clone the repository behind the given URL into the target directory.
     *
     * @param string $repositoryUrl The URL of the repository to clone.
     * @param string $targetDirectory The directory to clone the repository into.
     * @throws GitNotAvailable|RepositoryCloneFailed
     * @return void
     */
    public function clone(string $repositoryUrl, string $targetDirectory): void;
}
