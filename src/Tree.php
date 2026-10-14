<?php declare(strict_types=1);

namespace Stolt\LeanPackage;

use FilesystemIterator;
use Stolt\LeanPackage\Exceptions\GitHeadNotAvailable;
use Stolt\LeanPackage\Exceptions\GitNotAvailable;

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
        $gitignorePatterns = $this->getGitignorePatterns($directory . DIRECTORY_SEPARATOR . '.gitignore');

        $entries = [];
        $iterator = new FilesystemIterator($directory, FilesystemIterator::SKIP_DOTS);

        foreach ($iterator as $file) {
            $filename = $file->getFilename();

            // Skip entries that match .gitignore patterns
            if ($this->isGitignored($filename, $gitignorePatterns)) {
                continue;
            }

            $entries[] = $file;
        }

        // Sort by type (directories first, then files), then by name
        \usort($entries, static function ($a, $b): int {
            $aIsDir = $a->isDir() ? 0 : 1;
            $bIsDir = $b->isDir() ? 0 : 1;
            if ($aIsDir !== $bIsDir) {
                return $aIsDir - $bIsDir;
            }
            return \strnatcmp($a->getFilename(), $b->getFilename());
        });

        $tree[] = '.';

        $index = 0;
        $directoryCount = 0;
        $fileCount = 0;
        $totalCount = \count($entries);

        foreach ($entries as $file) {
            $index++;
            $filename = $file->getFilename();
            if ($file->isDir()) {
                $filename = $file->getFilename() . '/';
                $directoryCount++;
            }

            if ($file->isFile()) {
                $fileCount++;
            }

            if ($index < $totalCount) {
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

    /**
     * Parse .gitignore file and return list of patterns.
     */
    private function getGitignorePatterns(string $gitignoreFile): array
    {
        if (!\file_exists($gitignoreFile)) {
            return [];
        }

        $gitignoreContent = (string) \file_get_contents($gitignoreFile);
        $gitignoreLines = \preg_split('/\\r\\n|\\r|\\n/', $gitignoreContent);

        $gitignoredPatterns = [];

        \array_filter($gitignoreLines, static function ($line) use (&$gitignoredPatterns) {
            $line = \trim($line);
            if ($line !== '' && !\str_starts_with($line, '#')) {
                if (\str_starts_with($line, '/')) {
                    $gitignoredPatterns[] = \substr($line, 1);
                }
                if (\str_ends_with($line, '/')) {
                    $gitignoredPatterns[] = \substr($line, 0, -1);
                }
                $gitignoredPatterns[] = $line;
            }
        });

        return $gitignoredPatterns;
    }

    /**
     * Check if a filename matches any of the gitignore patterns.
     */
    private function isGitignored(string $filename, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            // Negation patterns (start with !)
            if (\str_starts_with($pattern, '!')) {
                continue;
            }

            // Convert gitignore pattern to glob pattern
            $globPattern = $pattern;

            // Check if the filename matches the pattern
            if ($this->matchGitignorePattern($filename, $globPattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Match a filename against a gitignore pattern.
     */
    private function matchGitignorePattern(string $filename, string $pattern): bool
    {
        // If pattern contains a slash (not trailing), it's path-relative, skip for depth-0 matching
        if (\str_contains(\rtrim($pattern, '/'), '/')) {
            return false;
        }

        // Convert gitignore glob to PHP fnmatch pattern
        // Handle ** as matching any path
        $pattern = \str_replace('**', '*', $pattern);

        // If pattern ends with /, it only matches directories (already handled by getGitignorePatterns)
        // Use fnmatch for glob matching
        return \fnmatch($pattern, $filename, FNM_PATHNAME) !== false;
    }

    private function removeDirectory(string $directory): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        /** @var \SplFileInfo $fileinfo */
        foreach ($files as $fileinfo) {
            if ($fileinfo->isDir()) {
                \rmdir($fileinfo->getRealPath());
                continue;
            }
            \unlink($fileinfo->getRealPath());
        }

        \rmdir($directory);
    }
}
