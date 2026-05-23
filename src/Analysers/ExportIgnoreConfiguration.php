<?php declare(strict_types=1);

namespace Stolt\LeanPackage\Analysers;

final readonly class ExportIgnoreConfiguration
{
    public function __construct(
        public string $directory,
        public string $globPattern = '',
        public bool $sortFromDirectoriesToFiles = false,
        public bool $sortAlphabetically = false,
        public bool $keepLicense = false,
        public bool $keepReadme = false,
        public string $keepGlobPattern = '',
        public bool $alignExportIgnores = false,
        public bool $enforceStrictOrderComparison = false,
    ) {}

    public function withDirectory(string $directory): self
    {
        return new self(
            directory: $directory,
            globPattern: $this->globPattern,
            sortFromDirectoriesToFiles: $this->sortFromDirectoriesToFiles,
            sortAlphabetically: $this->sortAlphabetically,
            keepLicense: $this->keepLicense,
            keepReadme: $this->keepReadme,
            keepGlobPattern: $this->keepGlobPattern,
            alignExportIgnores: $this->alignExportIgnores,
            enforceStrictOrderComparison: $this->enforceStrictOrderComparison,
        );
    }

    public function withGlobPattern(string $globPattern): self
    {
        return new self(
            directory: $this->directory,
            globPattern: $globPattern,
            sortFromDirectoriesToFiles: $this->sortFromDirectoriesToFiles,
            sortAlphabetically: $this->sortAlphabetically,
            keepLicense: $this->keepLicense,
            keepReadme: $this->keepReadme,
            keepGlobPattern: $this->keepGlobPattern,
            alignExportIgnores: $this->alignExportIgnores,
            enforceStrictOrderComparison: $this->enforceStrictOrderComparison,
        );
    }

    public function keepLicense(bool $enabled = true): self
    {
        return new self(
            directory: $this->directory,
            globPattern: $this->globPattern,
            sortFromDirectoriesToFiles: $this->sortFromDirectoriesToFiles,
            sortAlphabetically: $this->sortAlphabetically,
            keepLicense: $enabled,
            keepReadme: $this->keepReadme,
            keepGlobPattern: $this->keepGlobPattern,
            alignExportIgnores: $this->alignExportIgnores,
            enforceStrictOrderComparison: $this->enforceStrictOrderComparison,
        );
    }

    public function keepReadme(bool $enabled = true): self
    {
        return new self(
            directory: $this->directory,
            globPattern: $this->globPattern,
            sortFromDirectoriesToFiles: $this->sortFromDirectoriesToFiles,
            sortAlphabetically: $this->sortAlphabetically,
            keepLicense: $this->keepLicense,
            keepReadme: $enabled,
            keepGlobPattern: $this->keepGlobPattern,
            alignExportIgnores: $this->alignExportIgnores,
            enforceStrictOrderComparison: $this->enforceStrictOrderComparison,
        );
    }

    public function alignExportIgnores(bool $enabled = true): self
    {
        return new self(
            directory: $this->directory,
            globPattern: $this->globPattern,
            sortFromDirectoriesToFiles: $this->sortFromDirectoriesToFiles,
            sortAlphabetically: $this->sortAlphabetically,
            keepLicense: $this->keepLicense,
            keepReadme: $this->keepReadme,
            keepGlobPattern: $this->keepGlobPattern,
            alignExportIgnores: $enabled,
            enforceStrictOrderComparison: $this->enforceStrictOrderComparison,
        );
    }

    public function enforceStrictOrderComparison(bool $enabled = true): self
    {
        return new self(
            directory: $this->directory,
            globPattern: $this->globPattern,
            sortFromDirectoriesToFiles: $this->sortFromDirectoriesToFiles,
            sortAlphabetically: $this->sortAlphabetically,
            keepLicense: $this->keepLicense,
            keepReadme: $this->keepReadme,
            keepGlobPattern: $this->keepGlobPattern,
            alignExportIgnores: $this->alignExportIgnores,
            enforceStrictOrderComparison: $enabled,
        );
    }
}
