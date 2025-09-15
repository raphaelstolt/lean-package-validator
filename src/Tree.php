<?php declare(strict_types=1);

namespace Stolt\LeanPackage;

use FilesystemIterator;
use Stolt\LeanPackage\Exceptions\GitHeadNotAvailable;
use Stolt\LeanPackage\Exceptions\GitNotAvailable;
use Symfony\Component\Finder\Finder;

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

    public function getTreeForSrc(string $directory): string
    {
        return $this->getTree($directory);
    }

    /**
     * @throws GitHeadNotAvailable
     * @throws GitNotAvailable
     */
    public function getTreeForDistPackage(string $directory): string
    {
        \chdir($directory);

        $this->archive->createArchive();
        $temporaryDirectory = \sys_get_temp_dir() . '/dist-release';

        if (!\file_exists($temporaryDirectory)) {
            \mkdir($temporaryDirectory);
        }

        $command = 'tar -xf ' . \escapeshellarg($this->archive->getFilename()) . ' --directory ' . $temporaryDirectory . ' 2>&1';

        \exec($command);

        $distReleaseTree = $this->getTree($temporaryDirectory);


        $this->archive->removeArchive();
        $this->removeDirectory($temporaryDirectory);

        return $distReleaseTree;
    }

    private function getTree(string $directory): string
    {
        $finder = new Finder();
        $finder->in($directory)->ignoreVCSIgnored(true)
            ->ignoreDotFiles(false)->depth(0)->sortByName()->sortByType();

        $tree[] = '.';

        $index = 0;
        $directoryCount = 0;
        $fileCount = 0;

        foreach ($finder as $file) {
            $index++;
            $filename = $file->getFilename();
            if ($file->isDir()) {
                $filename = $file->getFilename() . '/';
                $directoryCount++;
            }

            if ($file->isFile()) {
                $fileCount++;
            }

            if ($index < $finder->count()) {
                $tree[] = '├── ' . $filename;
            } else {
                $tree[] = '└── ' . $filename;
            }
        }

        $tree[] = PHP_EOL;
        $tree[] = \sprintf(
            '%d %s, %d %s',
            $directoryCount,
            $directoryCount > 1 ? 'directories' : 'directory',
            $fileCount,
            $fileCount > 1 ? 'files': 'file'
        );
        $tree[] = PHP_EOL;

        return \implode(PHP_EOL, $tree);
    }

    protected function removeDirectory(string $directory): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        /** @var \SplFileInfo $fileinfo */
        foreach ($files as $fileinfo) {
            if ($fileinfo->isDir()) {
                @\rmdir($fileinfo->getRealPath());
                continue;
            }
            @\unlink($fileinfo->getRealPath());
        }

        @\rmdir($directory);
    }
}
