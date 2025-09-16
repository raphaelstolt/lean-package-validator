<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Tests;

use PHPUnit\Framework\TestCase as PHPUnit;
use Stolt\LeanPackage\Analyser;
use Stolt\LeanPackage\Commands\InitCommand;
use Stolt\LeanPackage\Helpers\Str as OsHelper;
use Stolt\LeanPackage\Presets\Finder;
use Stolt\LeanPackage\Presets\PhpPreset;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;

class TestCase extends PHPUnit
{
    /**
     * @var Application
     */
    protected Application $application;

    protected string $temporaryDirectory;

    /**
     * Set up temporary directory.
     *
     * @return void
     */
    protected function setUpTemporaryDirectory()
    {
        $this->temporaryDirectory = \sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'lpv';

        if ((new OsHelper())->isWindows() === false) {
            \ini_set('sys_temp_dir', '/tmp/lpv');
            $this->temporaryDirectory = '/tmp/lpv';
        }

        if (!\file_exists($this->temporaryDirectory)) {
            \mkdir($this->temporaryDirectory);
        }
    }

    /**
     * Remove directory and files in it.
     *
     * @return void
     */
    protected function removeDirectory(string $directory): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
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
            \touch($artifactFile);
        }

        if (\count($directories) > 0) {
            foreach ($directories as $directory) {
                $artifactDirectory = $this->temporaryDirectory
                    . DIRECTORY_SEPARATOR
                    . $directory;
                \mkdir($artifactDirectory);
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
    protected function createTemporaryGitattributesFile($content): bool
    {
        $temporaryGitattributesFile = $this->temporaryDirectory
            . DIRECTORY_SEPARATOR
            . '.gitattributes';


        $bytesWritten = file_put_contents($temporaryGitattributesFile, $content);

        return $bytesWritten > 0;
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

        return file_put_contents($temporaryGitignoreFile, $content) > 0;
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

        return file_put_contents($temporaryLpvFile, $content) > 0;
    }

    /**
     * @param Command $command
     * @return Application
     */
    protected function getApplication(Command $command): Application
    {
        $application = new Application();
        $application->add($command);

        return $application;
    }
}
