<?php

namespace Stolt\LeanPackage;

use Stolt\LeanPackage\Exceptions\GitNotAvailable;
use Stolt\LeanPackage\Exceptions\TreeNotAvailable;
use Stolt\LeanPackage\Helpers\Str as OsHelper;

final class Tree
{
    private Archive $archive;

    /**
     * @throws TreeNotAvailable
     * @throws GitNotAvailable
     */
    public function __construct(Archive $archive)
    {
        if (!$this->detectTreeCommand()) {
            throw new TreeNotAvailable();
        }

        $this->archive = $archive;

        if (!$this->archive->isGitCommandAvailable()) {
            throw new GitNotAvailable();
        }
    }

    public function getTreeForSrc(string $directory): string
    {
        \exec(
            'tree -aL 1 --dirsfirst ' . \escapeshellarg($directory) . '  2>&1',
            $output
        );

        $output[0] = '.';

        return \implode(PHP_EOL, $output) . PHP_EOL;
    }

    public function getTreeForDistPackage(string $directory): string
    {
        $this->archive->createArchive();

        \exec(
            'tar --list --exclude="*/*" --file ' . \escapeshellarg($this->archive->getFilename()) . ' | tree -aL 1 --dirsfirst --fromfile . 2>&1',
            $output
        );

        $this->archive->removeArchive();

        return \implode(PHP_EOL, $output) . PHP_EOL;
    }

    protected function detectTreeCommand(string $command = 'tree'): bool
    {
        \exec('where ' . $command . ' 2>&1', $output, $returnValue);
        if ((new OsHelper())->isWindows() === false) {
            \exec('which ' . $command . ' 2>&1', $output, $returnValue);
        }

        return $returnValue === 0;
    }
}
