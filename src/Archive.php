<?php

namespace Stolt\LeanPackage;

use PharData;
use Stolt\LeanPackage\Exceptions\GitHeadNotAvailable;
use Stolt\LeanPackage\Exceptions\GitNotAvailable;
use Stolt\LeanPackage\Exceptions\NoLicenseFilePresent;

class Archive
{
    /**
     * @var string
     */
    private $directory;

    /**
     * @var string
     */
    private $name;

    /**
     * @var string
     */
    private $filename;

    /**
     * @var array
     */
    private $foundUnexpectedArtifacts = [];

    /**
     * Whether the archive should have a license file or not.
     *
     * @var boolean
     */
    private $shouldHaveLicenseFile = false;

    /**
     * Initialize.
     *
     * @param string $directory The directory of the repository to archive.
     * @param string $name      The extensionless name of the repository archive.
     */
    public function __construct($directory, $name)
    {
        $this->directory = $directory;
        $this->name = $name;
        $this->filename = $directory
            . DIRECTORY_SEPARATOR
            . $name . '.tar.gz';
    }

    /**
     * Set if license file presence should be validated.
     *
     * @return Stolt\LeanPackage\Archive
     */
    public function shouldHaveLicenseFile()
    {
        $this->shouldHaveLicenseFile = true;

        return $this;
    }

    /**
     * Guard for license file presence validation.
     *
     * @return boolean
     */
    public function validateLicenseFilePresence()
    {
        return $this->shouldHaveLicenseFile === true;
    }

    /**
     * Accessor for the archive filename.
     *
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * Accessor for found unexpected artifacts.
     *
     * @return array
     */
    public function getFoundUnexpectedArtifacts()
    {
        return $this->foundUnexpectedArtifacts;
    }

    /**
     * Has repository a HEAD.
     *
     * @throws \Stolt\LeanPackage\Exceptions\GitNotAvailable
     *
     * @return boolean
     */
    public function hasHead()
    {
        if ($this->isGitCommandAvailable()) {
            exec('git show-ref --head 2>&1', $output, $returnValue);
            return $returnValue === 0;
        }

        throw new GitNotAvailable('The Git command is not available.');
    }

    /**
     * Is the Git command available?
     *
     * @paramk string $command The command to check availabilty of. Defaults to git.
     *
     * @return boolean
     */
    public function isGitCommandAvailable($command = 'git')
    {
        exec('where ' . $command . ' 2>&1', $output, $returnValue);
        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            exec('which ' . $command . ' 2>&1', $output, $returnValue);
        }

        return $returnValue === 0;
    }

    /**
     * Create a Git archive from the current HEAD.
     *
     * @throws \Stolt\LeanPackage\Exceptions\GitHeadNotAvailable
     *
     * @return boolean
     */
    public function createArchive()
    {
        if ($this->hasHead()) {
            $command = 'git archive -o ' . $this->getFilename() . ' HEAD 2>&1';
            exec($command, $output, $returnValue);

            return $returnValue === 0;
        }

        throw new GitHeadNotAvailable('No Git HEAD present to create an archive from.');
    }

    /**
     * Compare archive against unexpected artifacts.
     *
     * @param  array $unexpectedArtifacts The unexpected artifacts.
     * @throws \Stolt\LeanPackage\Exceptions\NoLicenseFilePresent
     * @return array
     */
    public function compareArchive(array $unexpectedArtifacts)
    {
        $foundUnexpectedArtifacts = [];
        $archive = new PharData($this->getFilename());
        $hasLicenseFile = false;

        foreach ($archive as $archiveFile) {
            if ($archiveFile->isDir()) {
                $file = basename($archiveFile) . '/';
                if (in_array($file, $unexpectedArtifacts)) {
                    $foundUnexpectedArtifacts[] = $file;
                }
                continue;
            }

            $file = basename($archiveFile);
            if ($this->validateLicenseFilePresence()) {
                if (preg_match('/(License.*)/i', $file)) {
                    $hasLicenseFile = true;
                }
            }

            if (in_array($file, $unexpectedArtifacts)) {
                $foundUnexpectedArtifacts[] = $file;
            }
        }

        if ($this->validateLicenseFilePresence() && $hasLicenseFile === false) {
            throw new NoLicenseFilePresent('No license file present in archive.');
        }

        sort($foundUnexpectedArtifacts, SORT_STRING | SORT_FLAG_CASE);

        return $foundUnexpectedArtifacts;
    }

    /**
     * Remove temporary Git archive.
     *
     * @return boolean
     */
    public function removeArchive()
    {
        if (file_exists($this->getFilename())) {
            return unlink($this->getFilename());
        }

        return false;
    }

    /**
     * Delegator for temporary archive creation and comparison against
     * a set of unexpected artifacts.
     *
     * @param array $unexpectedArtifacts The unexpected artifacts of the archive.
     *
     * @throws \Stolt\LeanPackage\Exceptions\GitNotAvailable
     * @throws \Stolt\LeanPackage\Exceptions\GitHeadNotAvailable
     *
     * @return array
     */
    public function getUnexpectedArchiveArtifacts(array $unexpectedArtifacts)
    {
        $this->createArchive();
        $this->foundUnexpectedArtifacts = $this->compareArchive($unexpectedArtifacts);
        $this->removeArchive();

        return $this->foundUnexpectedArtifacts;
    }
}
