<?php declare(strict_types=1);

namespace Stolt\LeanPackage\Analysers;

use RuntimeException;
use Stolt\LeanPackage\Analyser;
use Stolt\LeanPackage\Exceptions\InvalidGlobPattern;
use Stolt\LeanPackage\Exceptions\InvalidGlobPatternFile;
use Stolt\LeanPackage\Exceptions\NonExistentGlobPatternFile;
use Stolt\LeanPackage\Exceptions\PresetNotAvailable;
use Stolt\LeanPackage\Gitattributes\ValueObject as GitattributesValueObject;
use Stolt\LeanPackage\Glob;
use Stolt\LeanPackage\Helpers\Str;
use Stolt\LeanPackage\Presets\Finder;

abstract class AbstractExportIgnoreAnalyser
{
    protected ExportIgnoreConfiguration $configuration;

    /**
     * The directory to analyse
     *
     * @var string
     */
    public string $directory;

    /**
     * The .gitattributes file to analyse
     *
     * @var string
     */
    public string $gitattributesFile;


    /**
     * Files to ignore in glob matches.
     *
     * @var array
     */
    protected array $ignoredGlobMatches = ['.', '..', '.git', '.DS_Store'];

    /**
     * The default glob pattern.
     *
     * @var array
     */
    protected array $defaultGlobPattern = [];

    /**
     * The .gitattributes glob pattern
     *
     * @var string
     */
    protected string $globPattern;

    /**
     * The preferred end-of-line sequence
     *
     * @var string
     */
    protected string $preferredEol = PHP_EOL;

    /**
     * Whether to do a strict comparison of the export-ignores
     * in the .gitattributes files against the expected ones
     * or not.
     *
     * @var boolean
     */
    protected bool $strictOrderComparison = false;

    /**
     * Whether to do a strict comparison for stale export-ignores
     * in the .gitattributes files against the expected ones
     * or not.
     *
     * @var boolean
     */
    protected bool $staleExportIgnoresComparison = false;

    /**
     * Whether to do a strict alignment comparison of the export-ignores
     * in the .gitattributes files against the expected ones
     * or not.
     *
     * @var boolean
     */
    protected bool $strictAlignmentComparison = false;

    /**
     * Whether to sort the export-ignores
     * in the .gitattributes from directories to files or not.
     *
     * @var boolean
     */
    public bool $sortFromDirectoriesToFiles = false;

    /**
     * Whether to sort the export-ignores alphabetically on reformat or not.
     *
     * @var boolean
     */
    public bool $sortAlphabetically = false;

    /**
     * Whether at least one export-ignore pattern has
     * a preceding slash or not.
     *
     * @var boolean
     */
    protected bool $hasPrecedingSlashesInExportIgnorePattern = false;

    /**
     * Whether a text autoconfiguration is present or not.
     *
     * @var boolean
     */
    protected bool $hasTextAutoconfiguration = false;

    /**
     * Whether to exclude a license file from the export-ignores
     * or not.
     *
     * @var boolean
     */
    protected bool $keepLicense = false;

    /**
     * Whether to exclude a README file from the export-ignores
     * or not.
     *
     * @var boolean
     */
    protected bool $keepReadme = false;

    /**
     * Pattern to exclude from the export-ignores.
     *
     * @var string
     */
    protected string $keepGlobPattern = '';

    /**
     * Whether to align the export-ignores on creation or overwrite
     * or not.
     *
     * @var boolean
     */
    protected bool $alignExportIgnores = false;

    public bool $groupNonExportIgnores = false;

    private Finder $finder;

    /**
     * Initialize.
     */
    public function __construct(Finder $finder, string $directory = '', ?ExportIgnoreConfiguration $configuration = null)
    {
        $this->finder = $finder;
        $this->defaultGlobPattern = $finder->getDefaultPreset();

        $configuration ??= new ExportIgnoreConfiguration(
            directory: $directory,
            globPattern: '{' . implode(',', $this->defaultGlobPattern) . '}*',
        );

        $this->configuration = $configuration;

        $this->directory = $configuration->directory;
        $this->gitattributesFile = $this->directory . DIRECTORY_SEPARATOR . '.gitattributes';

        $this->globPattern = $configuration->globPattern;

        $this->sortFromDirectoriesToFiles = $configuration->sortFromDirectoriesToFiles;
        $this->sortAlphabetically = $configuration->sortAlphabetically;
        $this->keepLicense = $configuration->keepLicense;
        $this->keepReadme = $configuration->keepReadme;
        $this->keepGlobPattern = $configuration->keepGlobPattern;
        $this->alignExportIgnores = $configuration->alignExportIgnores;
    }

    public function getConfiguration(): ExportIgnoreConfiguration
    {
        return $this->configuration;
    }

    /**
     * Accessor for the set directory.
     *
     * @return string
     */
    public function getDirectory(): string
    {
        return $this->directory;
    }

    /**
     * Set the directory to analyse.
     *
     * @param string $directory The directory to analyse.
     * @return AbstractExportIgnoreAnalyser
     * @return Analyser
     *
     * @throws RuntimeException
     */
    public function setDirectory(string $directory = __DIR__): AbstractExportIgnoreAnalyser
    {
        if (!\is_dir($directory)) {
            $message = "Directory {$directory} doesn't exist.";
            throw new \RuntimeException($message);
        }
        $this->directory = $directory;
        $this->gitattributesFile = $directory
            . DIRECTORY_SEPARATOR
            . '.gitattributes';

        return $this;
    }

    /**
     * Enable strict order comparison.
     *
     * @return AbstractExportIgnoreAnalyser
     */
    public function enableStrictOrderComparison(): self
    {
        $this->strictOrderComparison = true;
        $this->configuration = $this->configuration->enforceStrictOrderComparison(true);

        return $this;
    }

    public function sortFromDirectoriesToFiles(): self
    {
        $this->sortFromDirectoriesToFiles = true;

        return $this;
    }

    public function sortAlphabetically(): self
    {
        $this->sortAlphabetically = true;

        return $this;
    }

    /**
     * Guard for strict order comparison.
     *
     * @return boolean
     */
    public function isStrictOrderComparisonEnabled(): bool
    {
        return $this->strictOrderComparison === true;
    }

    /**
     * Enable stale export ignores comparison.
     *
     * @return AbstractExportIgnoreAnalyser
     */
    public function enableStaleExportIgnoresComparison(): self
    {
        $this->staleExportIgnoresComparison = true;

        return $this;
    }

    /**
     * Guard for stale export ignores comparison.
     *
     * @return boolean
     */
    public function isStaleExportIgnoresComparisonEnabled(): bool
    {
        return $this->staleExportIgnoresComparison === true;
    }

    /**
     * Enable strict alignment comparison.
     *
     * @return AbstractExportIgnoreAnalyser
     */
    public function enableStrictAlignmentComparison(): self
    {
        $this->strictAlignmentComparison = true;

        return $this;
    }

    /**
     * Guard for strict alignment comparison.
     *
     * @return boolean
     */
    public function isStrictAlignmentComparisonEnabled(): bool
    {
        return $this->strictAlignmentComparison === true;
    }

    /**
     * Keep a license file in releases.
     *
     * @return AbstractExportIgnoreAnalyser
     */
    public function keepLicense(): self
    {
        $this->keepLicense = true;
        $this->configuration = $this->configuration->keepLicense(true);

        return $this;
    }

    /**
     * Guard for not export-ignoring a license file.
     *
     * @return boolean
     */
    public function isKeepLicenseEnabled(): bool
    {
        return $this->keepLicense === true;
    }

    /**
     * Keep README file in releases.
     *
     * @return AbstractExportIgnoreAnalyser
     */
    public function keepReadme(): self
    {
        $this->keepReadme = true;
        $this->configuration = $this->configuration->keepReadme(true);

        return $this;
    }

    /**
     * Guard for not export-ignoring README file.
     *
     * @return boolean
     */
    public function isKeepReadmeEnabled(): bool
    {
        return $this->keepReadme === true;
    }

    /**
     * Sets the glob pattern for not export-ignoring license files.
     *
     * @param string $globPattern
     * @return AbstractExportIgnoreAnalyser
     * @throws InvalidGlobPattern
     */
    public function setKeepGlobPattern(string $globPattern): AbstractExportIgnoreAnalyser
    {
        $this->guardGlobPattern($globPattern);
        $this->keepGlobPattern = $globPattern;

        return $this;
    }

    /**
     * @return boolean
     */
    public function isKeepGlobPatternSet(): bool
    {
        return $this->keepGlobPattern !== '';
    }

    /**
     * Accessor for the default glob patterns.
     *
     * @return array
     */
    public function getDefaultGlobPattern(): array
    {
        return $this->defaultGlobPattern;
    }

    /**
     * Is a .gitattributes file present?
     *
     * @return boolean
     */
    public function hasGitattributesFile(): bool
    {
        return \file_exists($this->gitattributesFile) &&
            \is_readable($this->gitattributesFile);
    }

    /**
     * Accessor for aligning export-ignores.
     *
     * @return boolean
     */
    public function isAlignExportIgnoresEnabled(): bool
    {
        return $this->alignExportIgnores === true;
    }

    /**
     * Accessor for the set .gitattributes file path.
     *
     * @return string
     */
    public function getGitattributesFilePath(): string
    {
        return $this->gitattributesFile;
    }

    /**
     * Align export-ignores.
     *
     * @return AbstractExportIgnoreAnalyser
     */
    public function alignExportIgnores(): self
    {
        $this->alignExportIgnores = true;

        return $this;
    }

    /**
     * Accessor for preceding slashes in an export-ignore pattern.
     *
     * @return boolean
     */
    public function hasPrecedingSlashesInExportIgnorePattern(): bool
    {
        return $this->hasPrecedingSlashesInExportIgnorePattern;
    }

    /**
     * Accessor for text autoconfiguration.
     *
     * @return boolean
     */
    public function hasTextAutoconfiguration(): bool
    {
        return $this->hasTextAutoconfiguration;
    }

    public function textAutoconfiguration(): self
    {
        $this->hasTextAutoconfiguration = true;

        return $this;
    }

    public function setGroupNonExportIgnores(bool $group = true): self
    {
        $this->groupNonExportIgnores = $group;

        return $this;
    }

    /**
     * @throws PresetNotAvailable
     */
    public function setGlobPatternFromPreset(string $preset): void
    {
        $this->globPattern = '{' . \implode(',', $this->finder->getPresetGlobByLanguageName($preset)) . '}*';
    }

    /**
     * Collapse consecutive blank lines to a single blank line and trim
     * leading and trailing blank lines from the given array of lines.
     *
     * @param array<int, string> $lines
     * @return array<int, string>
     */
    public function collapseAndTrimBlankLines(array $lines): array
    {
        $collapsed = [];
        $prevWasBlank = false;

        foreach ($lines as $line) {
            if ($line === '') {
                if ($prevWasBlank === false) {
                    $collapsed[] = $line;
                }
                $prevWasBlank = true;
            } else {
                $collapsed[] = $line;
                $prevWasBlank = false;
            }
        }

        while ($collapsed !== [] && $collapsed[0] === '') {
            \array_shift($collapsed);
        }

        while ($collapsed !== [] && \end($collapsed) === '') {
            \array_pop($collapsed);
        }

        return $collapsed;
    }

    /**
     * @return string
     */
    public function getPresentGitAttributesContent(): string
    {
        if ($this->hasGitattributesFile() === false) {
            return '';
        }

        return (string) \file_get_contents($this->gitattributesFile);
    }

    /**
     * Accessor for the injected finder.
     *
     * @return Finder
     */
    public function getFinder(): Finder
    {
        return $this->finder;
    }

    /**
     * Set the glob pattern file.
     *
     * @param string $file
     * @return AbstractExportIgnoreAnalyser
     * @throws InvalidGlobPatternFile
     * @throws NonExistentGlobPatternFile
     */
    public function setGlobPatternFromFile(string $file): AbstractExportIgnoreAnalyser
    {
        if (!\is_file($file)) {
            $message = "Glob pattern file {$file} doesn't exist.";
            throw new NonExistentGlobPatternFile($message);
        }

        $globPatternContent = (string) \file_get_contents($file);

        $globPatternLines = \preg_split(
            '/\\r\\n|\\r|\\n/',
            $globPatternContent
        );

        $globPatterns = [];
        \array_filter($globPatternLines, static function (string $line) use (&$globPatterns) {
            if (\trim($line) !== '') {
                $globPatterns[] = \trim($line);
            }
        });

        $globPattern = '{' . \implode(',', $globPatterns) . '}*';

        try {
            $this->setGlobPattern($globPattern);

            return $this;
        } catch (InvalidGlobPattern $e) {
            $message = "Glob pattern file '{$file}' is invalid.";
            throw new InvalidGlobPatternFile($message);
        }
    }

    /**
     * Overwrite the default glob pattern.
     *
     * @param string $pattern The glob pattern to use to detect expected export-ignores files.
     *
     * @return AbstractExportIgnoreAnalyser
     * @return Analyser
     *
     * @throws InvalidGlobPattern
     */
    public function setGlobPattern($pattern): AbstractExportIgnoreAnalyser
    {
        $this->globPattern = \trim($pattern);
        $this->guardGlobPattern($this->globPattern);

        return $this;
    }

    /**
     * Guard the given glob pattern.
     *
     * @param string $pattern
     * @throws InvalidGlobPattern
     * @return void
     */
    protected function guardGlobPattern(string $pattern): void
    {
        $invalidGlobPattern = false;

        if (\substr($pattern, 0) !== '{'
            && (!str_ends_with($pattern, '}') && !str_ends_with($pattern, '}*'))) {
            $invalidGlobPattern = true;
        }

        $bracesContent = \trim(\substr($pattern, 1, -1));

        if ($bracesContent === '') {
            $invalidGlobPattern = true;
        }

        $globPatterns = \explode(',', $bracesContent);

        if (\count($globPatterns) === 1) {
            $invalidGlobPattern = true;
        }

        if ($invalidGlobPattern === true) {
            throw new InvalidGlobPattern;
        }
    }

    /**
     * @param string $gitignoreFile
     * @return array
     */
    protected function getGitignorePatterns(string $gitignoreFile): array
    {
        if (!\file_exists($gitignoreFile)) {
            return [];
        }

        $gitignoreContent = (string) \file_get_contents($gitignoreFile);
        $gitignoreLines = \preg_split(
            '/\\r\\n|\\r|\\n/',
            $gitignoreContent
        );

        $gitignoredPatterns = [];

        \array_filter($gitignoreLines, static function ($line) use (&$gitignoredPatterns) {
            $line = \trim($line);
            if ($line !== '' && !str_contains($line, '#')) {
                if (str_starts_with($line, "/")) {
                    $gitignoredPatterns[] = \substr($line, 1);
                }
                if (str_ends_with($line, "/")) {
                    $gitignoredPatterns[] = \substr($line, 0, -1);
                }
                $gitignoredPatterns[] = $line;
            }
        });

        return $gitignoredPatterns;
    }

    /**
     * Return patterns in .gitignore file.
     *
     * @return array
     */
    public function getGitignoredPatterns(): array
    {
        $gitignoreFile = $this->getDirectory() . DIRECTORY_SEPARATOR . '.gitignore';

        return $this->getGitignorePatterns($gitignoreFile);
    }

    /**
     * Check if a given pattern produces a match against the repository directory.
     *
     * @param string $globPattern
     * @return boolean
     */
    protected function patternHasMatch(string $globPattern): bool
    {
        if (str_starts_with(\trim($globPattern), '/')) {
            $globPattern = \trim(\substr($globPattern, 1));
        } elseif (str_ends_with(\trim($globPattern), '/')) {
            $globPattern = \trim(\substr($globPattern, 0, -1));
        } else {
            $globPattern = '{' . \trim($globPattern) . '}*';
        }

        $initialWorkingDirectory = (string) \getcwd();
        \chdir($this->directory);

        $matches = Glob::glob($globPattern, Glob::GLOB_BRACE);

        \chdir($initialWorkingDirectory);

        return \is_array($matches) && \count($matches) > 0;
    }

    protected function mergeWithExistingGitattributes(string $content): string
    {
        $exportIgnoreContent = \rtrim($content);

        $existingContent = $this->getPresentNonExportIgnoresContent();

        if (\str_contains(
            $existingContent,
            ClassicExportIgnoreAnalyser::EXPORT_IGNORES_PLACEMENT_PLACEHOLDER
        )) {
            return \str_replace(
                ClassicExportIgnoreAnalyser::EXPORT_IGNORES_PLACEMENT_PLACEHOLDER,
                $exportIgnoreContent,
                $existingContent
            );
        }

        return $existingContent
            . \str_repeat($this->preferredEol, 2)
            . $exportIgnoreContent;
    }

    /**
     * Get the present non-export-ignore entries of the .gitattributes file.
     *
     * @return string
     */
    public function getPresentNonExportIgnoresContent(): string
    {
        if ($this->hasGitattributesFile() === false) {
            return '';
        }

        $gitattributesContent = (string) \file_get_contents($this->gitattributesFile);
        $eol = Str::detectEol($gitattributesContent);
        $this->preferredEol = $eol;

        $gitattributesLines = \preg_split(
            '/\\r\\n|\\r|\\n/',
            $gitattributesContent
        );

        if ($this->groupNonExportIgnores) {
            $nonExportIgnoreLines = [];

            \array_filter($gitattributesLines, static function (string $line) use (
                &$nonExportIgnoreLines
            ) {
                if (!str_contains($line, 'export-ignore') || \strstr($line, '#')) {
                    $nonExportIgnoreLines[] = \trim($line);
                }
            });

            $collapsed = $this->collapseAndTrimBlankLines($nonExportIgnoreLines);

            if ($collapsed === []) {
                return ClassicExportIgnoreAnalyser::EXPORT_IGNORES_PLACEMENT_PLACEHOLDER;
            }

            return \implode($eol, $collapsed);
        }

        $nonExportIgnoreLines = [];
        $exportIgnoresPlacementPlaceholderSet = false;
        $exportIgnoresPlacementPlaceholder = ClassicExportIgnoreAnalyser::EXPORT_IGNORES_PLACEMENT_PLACEHOLDER;

        \array_filter($gitattributesLines, static function (string $line) use (
            &$nonExportIgnoreLines,
            &$exportIgnoresPlacementPlaceholderSet,
            &$exportIgnoresPlacementPlaceholder
        ) {
            if (!str_contains($line, 'export-ignore') || \strstr($line, '#')) {
                return $nonExportIgnoreLines[] = \trim($line);
            } else {
                if ($exportIgnoresPlacementPlaceholderSet === false) {
                    $exportIgnoresPlacementPlaceholderSet = true;
                    return $nonExportIgnoreLines[] = $exportIgnoresPlacementPlaceholder;
                }
            }
        });

        return \implode($eol, $nonExportIgnoreLines);
    }

    public function isAlignableExportIgnoreLine(string $line): bool
    {
        return (\str_contains($line, 'export-ignore') || \str_contains($line, '-export-ignore'))
            && \str_starts_with(\trim($line), '* export-ignore') === false
            && \str_starts_with(\ltrim($line), '#') === false;
    }

    /**
     * @param  array  $artifacts The export-ignore artifacts to align.
     * @return array
     */
    protected function getAlignedExportIgnoreArtifacts(array $artifacts): array
    {
        $longestArtifact = \max(\array_map('strlen', $artifacts));

        return \array_map(static function (string $artifact) use (&$longestArtifact) {
            if (\strlen($artifact) < $longestArtifact) {
                return $artifact . \str_repeat(
                        ' ',
                        $longestArtifact - \strlen($artifact)
                    );
            }
            return $artifact;
        }, $artifacts);
    }

    protected function getByDirectoriesToFilesExportIgnoreArtifacts(array $artifacts): array
    {
        $directories = \array_filter($artifacts, static function (string $artifact) {
            if (\strpos($artifact, '/')) {
                return $artifact;
            }
        });

        $files = \array_filter($artifacts, static function (string $artifact) {
            if (!str_contains($artifact, '/')) {
                return $artifact;
            }
        });

        return \array_merge($directories, $files);
    }

    protected function sortAndFormatExportIgnores(array $entries): array
    {
        if ($this->sortFromDirectoriesToFiles === false &&
            ($this->isAlignExportIgnoresEnabled() || $this->isStrictAlignmentComparisonEnabled())) {
            return $this->getAlignedExportIgnoreArtifacts($entries);
        }

        if ($this->sortFromDirectoriesToFiles) {
            return $this->getByDirectoriesToFilesExportIgnoreArtifacts($entries);
        }

        return $entries;
    }

    public function isNegatedExportIgnoreLine(string $line): bool
    {
        return \str_contains($line, '-export-ignore') && \str_starts_with(\ltrim($line), '#') === false;
    }

    abstract public function getPresentExportIgnores(bool $applyGlob = true, string $gitattributesContent = '', bool $strictOrderComparisonEnabled = false): array;
    abstract public function collectExpectedExportIgnores(): array;
    abstract public function getGitattributesContentToBe(array $postfixLessExportIgnores = []): GitattributesValueObject;
    abstract public function hasCompleteExportIgnores(): bool;
}
