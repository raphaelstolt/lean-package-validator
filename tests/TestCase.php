<?php

namespace Stolt\LeanPackage\Tests;

use PHPUnit\Framework\TestCase as PHPUnit;
use Stolt\LeanPackage\Helpers\Str as OsHelper;

class TestCase extends PHPUnit
{
    protected $temporaryDirectory;

    /**
     * Set up temporary directory.
     *
     * @return void
     */
    protected function setUpTemporaryDirectory()
    {
        if ((new OsHelper())->isWindows() === false) {
            ini_set('sys_temp_dir', '/tmp/lpv');
            $this->temporaryDirectory = '/tmp/lpv';
        } else {
            $this->temporaryDirectory = sys_get_temp_dir()
                . DIRECTORY_SEPARATOR
                . 'lpv';
        }

        if (!file_exists($this->temporaryDirectory)) {
            mkdir($this->temporaryDirectory);
        }
    }

    /**
     * Remove directory and files in it.
     *
     * @return void
     */
    protected function removeDirectory($directory)
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            if ($fileinfo->isDir()) {
                @rmdir($fileinfo->getRealPath());
                continue;
            }
            @unlink($fileinfo->getRealPath());
        }

        @rmdir($directory);
    }

    /**
     * Create temporary files (and directories) to collect
     * expected gitattributes from.
     *
     * @param  array $files       Files to create.
     * @param  array $directories Directories to create.
     *
     * @return void
     */
    protected function createTemporaryFiles(array $files, array $directories = [])
    {
        foreach ($files as $file) {
            $artifactFile = $this->temporaryDirectory
                . DIRECTORY_SEPARATOR
                . $file;
            touch($artifactFile);
        }

        if (is_array($directories) && count($directories) > 0) {
            foreach ($directories as $directory) {
                $artifactDirectory = $this->temporaryDirectory
                    . DIRECTORY_SEPARATOR
                    . $directory;
                mkdir($artifactDirectory);
            }
        }
    }

    /**
     * Create temporary gitattributes file.
     *
     * @param  string $content Content of file.
     *
     * @return boolean
     */
    protected function createTemporaryGitattributesFile($content)
    {
        $temporaryGitattributesFile = $this->temporaryDirectory
            . DIRECTORY_SEPARATOR
            . '.gitattributes';

        return file_put_contents($temporaryGitattributesFile, $content) >= 0;
    }

    /**
     * Create temporary gitignore file.
     *
     * @param  string $content Content of file.
     *
     * @return boolean
     */
    protected function createTemporaryGitignoreFile($content)
    {
        $temporaryGitignoreFile = $this->temporaryDirectory
            . DIRECTORY_SEPARATOR
            . '.gitignore';

        return file_put_contents($temporaryGitignoreFile, $content) >= 0;
    }

    /**
     * Create temporary glob pattern (.lpv) file.
     *
     * @param  string $content Content of file.
     *
     * @return boolean
     */
    protected function createTemporaryGlobPatternFile($content)
    {
        $temporaryLpvFile = $this->temporaryDirectory
            . DIRECTORY_SEPARATOR
            . '.lpv';

        return file_put_contents($temporaryLpvFile, $content) >= 0;
    }
}
