<?php

namespace Stolt\LeanPackage\Archive;

use Stolt\LeanPackage\Archive;
use Stolt\LeanPackage\Exceptions\GitArchiveNotValidatedYet;

class Validator
{
    /**
     * @var \Stolt\LeanPackage\Archive
     */
    private $archive;

    /**
     * @var boolean
     */
    private $ranValidate = false;

    /**
     * Initialise.
     *
     * @param \Stolt\LeanPackage\Archive $archive The archive to validate.
     */
    public function __construct(Archive $archive)
    {
        $this->archive = $archive;
    }

    /**
     * Set if license file presence should be validated.
     *
     * @return \Stolt\LeanPackage\Archive\Validator
     */
    public function shouldHaveLicenseFile()
    {
        $this->archive->shouldHaveLicenseFile();

        return $this;
    }

    /**
     * Accessor for injected archive instance.
     *
     * @return \Stolt\LeanPackage\Archive
     */
    public function getArchive()
    {
        return $this->archive;
    }

    /**
     * Validate archive against unexpected artifacts.
     *
     * @param  array $unexpectedArtifacts Artifacts not expected in archive.
     *
     * @return boolean
     */
    public function validate(array $unexpectedArtifacts)
    {
        $foundUnexpectedArtifacts = $this->archive->getUnexpectedArchiveArtifacts(
            $unexpectedArtifacts
        );
        $this->ranValidate = true;

        if ($foundUnexpectedArtifacts !== []) {
            return false;
        }

        return true;
    }

    /**
     * Accessor for found unexpected archive artifacts.
     *
     * @throws \Stolt\LeanPackage\Exceptions\GitArchiveNotValidatedYet
     *
     * @return array
     */
    public function getFoundUnexpectedArchiveArtifacts()
    {
        if ($this->ranValidate === false) {
            $message = 'Git archive ' . $this->archive->getFilename()
                . ' not validated. Run validate first.';
            throw new GitArchiveNotValidatedYet($message);
        }

        return $this->archive->getFoundUnexpectedArtifacts();
    }
}
