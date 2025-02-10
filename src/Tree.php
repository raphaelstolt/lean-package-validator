<?php

namespace Stolt\LeanPackage;

use Stolt\LeanPackage\Exceptions\GitHeadNotAvailable;
use Stolt\LeanPackage\Exceptions\GitNotAvailable;
use Stolt\LeanPackage\Exceptions\TreeNotAvailable;
use Stolt\LeanPackage\Helpers\Str as OsHelper;

final class Tree
{
    private Archive $archive;

    /**
     * @throws GitNotAvailable
     */
    public function __construct(Archive $archive)
    {
        $this->archive = $archive;

        if (!$this->archive->isGitCommandAvailable()) {
            throw new GitNotAvailable();
        }
    }

    /**
     * @throws TreeNotAvailable
     */
    public function getTreeForSrc(string $directory): string
    {
        if (!$this->detectTreeCommand()) {
            throw new TreeNotAvailable('Unix tree command is not available.');
        }

        if (!$this->detectTreeCommandVersion()) {
            throw new TreeNotAvailable('Required tree command version >=2.0 is not available.');
        }

        $command = 'tree -aL 1 --dirsfirst ' . \escapeshellarg($directory) . ' --gitignore -I .git  2>&1';

        \exec($command, $output);

        $output[0] = '.';

        return \implode(PHP_EOL, $output) . PHP_EOL;
    }

    /**
     * @throws TreeNotAvailable
     * @throws GitHeadNotAvailable
     * @throws GitNotAvailable
     */
    public function getTreeForDistPackage(): string
    {
        if (!$this->detectTreeCommand()) {
            throw new TreeNotAvailable('Unix tree command is not available.');
        }

        $this->archive->createArchive();

        $command = 'tar --list --exclude="*/*" --file ' . \escapeshellarg($this->archive->getFilename()) . ' | tree -aL 1 --dirsfirst --fromfile . 2>&1';

        if ((new OsHelper())->isMacOs()) {
            $command = 'tar --list --file ' . \escapeshellarg($this->archive->getFilename()) . ' | tree -aL 1 --dirsfirst --fromfile . 2>&1';
        }

        \exec($command, $output);

        $this->archive->removeArchive();

        return \implode(PHP_EOL, $output) . PHP_EOL;
    }

    protected function detectTreeCommand(): bool
    {
        $command = 'where tree 2>&1';

        if ((new OsHelper())->isWindows() === false) {
            $command = 'which tree 2>&1';
        }

        \exec($command, $output, $returnValue);

        return $returnValue === 0;
    }

    protected function detectTreeCommandVersion(): bool
    {
        \exec('tree --version 2>&1', $output);

        if (\strpos($output[0], 'v2')) {
            return true;
        }

        return false;
    }
}
