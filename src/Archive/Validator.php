<?php

namespace Stolt\LeanPackage\Archive;

use Stolt\LeanPackage\Archive;
use Stolt\LeanPackage\Exceptions\GitArchiveNotValidatedYet;
use Stolt\LeanPackage\Exceptions\GitHeadNotAvailable;
use Stolt\LeanPackage\Exceptions\GitNotAvailable;
use Stolt\LeanPackage\Exceptions\NoLicenseFilePresent;

class Validator
{
    /**
     * @var Archive
     */
    private Archive $archive;

    /**
     * @var boolean
     */
    private bool $ranValidate = false;

    /**
     * Initialise.
     *
     * @param Archive $archive The archive to validate.
     */
    public function __construct(Archive $archive)
    {
        $this->archive = $archive;
    }

    /**
     * Set if license file presence should be validated.
     *
     * @return Validator
     */
    public function shouldHaveLicenseFile(): Validator
    {
        $this->archive->shouldHaveLicenseFile();

        return $this;
    }

    /**
     * Accessor for injected archive instance.
     *
     * @return Archive
     */
    public function getArchive(): Archive
    {
        return $this->archive;
    }

    /**
     * Validate archive against unexpected artifacts.
     *
     * @param array $unexpectedArtifacts Artifacts not expected in archive.
     *
     * @throws GitNotAvailable
     * @throws GitHeadNotAvailable
     * @throws GitNotAvailable|NoLicenseFilePresent
     * @throws GitHeadNotAvailable
     * @return boolean
     */
    public function validate(array $unexpectedArtifacts): bool
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
     * @throws GitArchiveNotValidatedYet
     *
     * @return array
     */
    public function getFoundUnexpectedArchiveArtifacts(): array
    {
        if ($this->ranValidate === false) {
            $message = 'Git archive ' . $this->archive->getFilename()
                . ' not validated. Run validate first.';
            throw new GitArchiveNotValidatedYet($message);
        }

        return $this->archive->getFoundUnexpectedArtifacts();
    }
}
