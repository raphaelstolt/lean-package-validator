<?php declare(strict_types=1);

namespace Stolt\LeanPackage;

use Stolt\LeanPackage\Exceptions\InvalidGlobPattern;
use Stolt\LeanPackage\Exceptions\InvalidGlobPatternFile;
use Stolt\LeanPackage\Exceptions\NonExistentGlobPatternFile;
use Stolt\LeanPackage\Exceptions\PresetNotAvailable;
use Stolt\LeanPackage\Presets\Finder;

class Analyser
{
    const EXPORT_IGNORES_PLACEMENT_PLACEHOLDER = '{{ export_ignores_placement }}';
    /**
     * The directory to analyse
     *
     * @var string
     */
    private string $directory;

    /**
     * The .gitattributes file to analyse
     *
     * @var string
     */
    private string $gitattributesFile;

    /**
     * Files to ignore in glob matches.
     *
     * @var array
     */
    private array $ignoredGlobMatches = ['.', '..', '.git', '.DS_Store'];

    /**
     * The default glob pattern.
     *
     * @var array
     */
    private array $defaultGlobPattern = [];

    /**
     * The .gitattributes glob pattern
     *
     * @var string
     */
    private string $globPattern;

    /**
     * The preferred end-of-line sequence
     *
     * @var string
     */
    private string $preferredEol = "\n";

    /**
     * Whether to do a strict comparison of the export-ignores
     * in the .gitattributes files against the expected ones
     * or not.
     *
     * @var boolean
     */
    private bool $strictOrderComparison = false;

    /**
     * Whether to do a strict comparison for stale export-ignores
     * in the .gitattributes files against the expected ones
     * or not.
     *
     * @var boolean
     */
    private bool $staleExportIgnoresComparison = false;

    /**
     * Whether to do a strict alignment comparison of the export-ignores
     * in the .gitattributes files against the expected ones
     * or not.
     *
     * @var boolean
     */
    private bool $strictAlignmentComparison = false;

    /**
     * Whether to sort the export-ignores
     * in the .gitattributes from directories to files or not.
     *
     * @var boolean
     */
    private bool $sortFromDirectoriesToFiles = false;

    /**
     * Whether at least one export-ignore pattern has
     * a preceding slash or not.
     *
     * @var boolean
     */
    private bool $hasPrecedingSlashesInExportIgnorePattern = false;

    /**
     * Whether a text autoconfiguration is present or not.
     *
     * @var boolean
     */
    private bool $hasTextAutoConfiguration = false;

    /**
     * Whether to exclude a license file from the export-ignores
     * or not.
     *
     * @var boolean
     */
    private bool $keepLicense = false;

    /**
     * Whether to exclude a README file from the export-ignores
     * or not.
     *
     * @var boolean
     */
    private bool $keepReadme = false;


    /**
     * Pattern to exclude from the export-ignores.
     *
     * @var string
     */
    private string $keepGlobPattern = '';

    /**
     * Whether to align the export-ignores on creation or overwrite
     * or not.
     *
     * @var boolean
     */
    private bool $alignExportIgnores = false;

    private Finder $finder;

    /**
     * Initialize.
     */
    public function __construct(Finder $finder)
    {
        $this->finder = $finder;
        $this->defaultGlobPattern = $finder->getDefaultPreset();

        $this->globPattern = '{' . \implode(',', $this->defaultGlobPattern) . '}*';
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
     * Accessor for the default glob patterns.
     *
     * @return array
     */
    public function getDefaultGlobPattern(): array
    {
        return $this->defaultGlobPattern;
    }

    /**
     * Accessor for preceding slashes in export-ignore pattern.
     *
     * @return boolean
     */
    public function hasPrecedingSlashesInExportIgnorePattern(): bool
    {
        return $this->hasPrecedingSlashesInExportIgnorePattern;
    }

    /**
     * Accessor for text auto configuration.
     *
     * @return boolean
     */
    public function hasTextAutoConfiguration(): bool
    {
        return $this->hasTextAutoConfiguration;
    }

    /**
     * Set the glob pattern file.
     *
     * @param string $file
     * @throws \Stolt\LeanPackage\Exceptions\NonExistentGlobPatternFile
     * @throws \Stolt\LeanPackage\Exceptions\InvalidGlobPatternFile
     * @return Analyser
     */
    public function setGlobPatternFromFile(string $file): Analyser
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
        \array_filter($globPatternLines, function ($line) use (&$globPatterns) {
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
     * Guard the given glob pattern.
     *
     * @param string $pattern
     * @throws InvalidGlobPattern
     * @return void
     */
    private function guardGlobPattern(string $pattern): void
    {
        $invalidGlobPattern = false;

        if (\substr($pattern, 0) !== '{'
            && (\substr($pattern, -1) !== '}' && \substr($pattern, -2) !== '}*')) {
            $invalidGlobPattern = true;
        }

        $bracesContent = \trim(\substr($pattern, 1, -1));

        if (empty($bracesContent)) {
            $invalidGlobPattern = true;
        }

        $globPatterns = \explode(',', $bracesContent);

        if (\count($globPatterns) == 1) {
            $invalidGlobPattern = true;
        }

        if ($invalidGlobPattern === true) {
            throw new InvalidGlobPattern;
        }
    }

    /**
     * Overwrite the default glob pattern.
     *
     * @param string $pattern The glob pattern to use to detect expected
     *                        export-ignores files.
     *
     * @throws \Stolt\LeanPackage\Exceptions\InvalidGlobPattern
     * @return Analyser
     * @return Analyser
     *
     */
    public function setGlobPattern($pattern): Analyser
    {
        $this->globPattern = \trim($pattern);
        $this->guardGlobPattern($this->globPattern);

        return $this;
    }

    /**
     * Set the directory to analyse.
     *
     * @param  string $directory The directory to analyse.
     * @throws \RuntimeException
     * @return Analyser
     * @return Analyser
     *
     */
    public function setDirectory($directory = __DIR__): Analyser
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
     * Accessor for the set directory.
     *
     * @return string
     */
    public function getDirectory(): string
    {
        return $this->directory;
    }

    /**
     * Enable strict order comparison.
     *
     * @return Analyser
     */
    public function enableStrictOrderComparison(): Analyser
    {
        $this->strictOrderComparison = true;

        return $this;
    }

    public function sortFromDirectoriesToFiles(): Analyser
    {
        $this->sortFromDirectoriesToFiles = true;

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
     * @return Analyser
     */
    public function enableStaleExportIgnoresComparison(): Analyser
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
     * @return Analyser
     */
    public function enableStrictAlignmentComparison(): Analyser
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
     * Keep license file in releases.
     *
     * @return Analyser
     */
    public function keepLicense(): Analyser
    {
        $this->keepLicense = true;

        return $this;
    }

    /**
     * Guard for not export-ignoring license file.
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
     * @return Analyser
     */
    public function keepReadme(): Analyser
    {
        $this->keepReadme = true;

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
     * @throws InvalidGlobPattern
     * @return Analyser
     */
    public function setKeepGlobPattern(string $globPattern): Analyser
    {
        $this->guardGlobPattern($globPattern);
        $this->keepGlobPattern = $globPattern;

        return $this;
    }

    /**
     * Guard for not export-ignoring glob pattern.
     *
     * @return boolean
     */
    public function isKeepGlobPatternSet(): bool
    {
        return $this->keepGlobPattern !== '';
    }

    /**
     * Align export-ignores.
     *
     * @return Analyser
     */
    public function alignExportIgnores(): Analyser
    {
        $this->alignExportIgnores = true;

        return $this;
    }

    /**
     * Guard for aligning export-ignores.
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
     * @param string $gitignoreFile
     * @return array
     */
    private function getGitignorePatterns(string $gitignoreFile): array
    {
        if (!\file_exists($gitignoreFile)) {
            return [];
        }

        $gitignoreContent = (string) \file_get_contents($gitignoreFile);
        $eol = $this->detectEol($gitignoreContent);

        $gitignoreLines = \preg_split(
            '/\\r\\n|\\r|\\n/',
            $gitignoreContent
        );

        $gitignoredPatterns = [];

        \array_filter($gitignoreLines, function ($line) use (&$gitignoredPatterns) {
            $line = \trim($line);
            if ($line !== '' && \strpos($line, '#') === false) {
                if (\substr($line, 0, 1) === "/") {
                    $gitignoredPatterns[] = \substr($line, 1);
                }
                if (\substr($line, -1, 1) === "/") {
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
     * Return the expected .gitattributes content.
     *
     * @param array $postfixLessExportIgnores Expected patterns without an export-ignore postfix.
     * @return string
     */
    public function getExpectedGitattributesContent(array $postfixLessExportIgnores = []): string
    {
        if ($postfixLessExportIgnores === []) {
            $postfixLessExportIgnores = $this->collectExpectedExportIgnores();
        }

        if (!$this->hasGitattributesFile() && \count($postfixLessExportIgnores) > 0) {
            $postfixLessExportIgnores[] = '.gitattributes';
        }

        \sort($postfixLessExportIgnores, SORT_STRING | SORT_FLAG_CASE);

        if (\count($postfixLessExportIgnores) > 0) {
            if ($this->sortFromDirectoriesToFiles === false && ($this->isAlignExportIgnoresEnabled() || $this->isStrictAlignmentComparisonEnabled())) {
                $postfixLessExportIgnores = $this->getAlignedExportIgnoreArtifacts(
                    $postfixLessExportIgnores
                );
            }

            if ($this->sortFromDirectoriesToFiles) {
                $postfixLessExportIgnores = $this->getByDirectoriesToFilesExportIgnoreArtifacts(
                    $postfixLessExportIgnores
                );
            }

            $content = \implode(" export-ignore" . $this->preferredEol, $postfixLessExportIgnores)
                . " export-ignore" . $this->preferredEol;

            if ($this->hasGitattributesFile()) {
                $exportIgnoreContent = \rtrim($content);
                $content = $this->getPresentNonExportIgnoresContent();

                if (\strstr($content, self::EXPORT_IGNORES_PLACEMENT_PLACEHOLDER)) {
                    $content = \str_replace(
                        self::EXPORT_IGNORES_PLACEMENT_PLACEHOLDER,
                        $exportIgnoreContent,
                        $content
                    );
                } else {
                    $content = $content
                        . \str_repeat($this->preferredEol, 2)
                        . $exportIgnoreContent;
                }
            } else {
                $content = "* text=auto eol=lf" . \str_repeat($this->preferredEol, 2) . $content;
            }

            return $content;
        }

        return '';
    }

    /**
     * Return export ignores in .gitattributes file to preserve.
     *
     * @param  array $globPatternMatchingExportIgnores Export ignores matching glob pattern.
     *
     * @return array
     */
    public function getPresentExportIgnoresToPreserve(array $globPatternMatchingExportIgnores): array
    {
        $gitattributesContent = (string) \file_get_contents($this->gitattributesFile);

        if (\preg_match("/(\*\h*)(text\h*)(=\h*auto)/", $gitattributesContent)) {
            $this->hasTextAutoConfiguration = true;
        }

        $eol = $this->detectEol($gitattributesContent);

        $gitattributesLines = \preg_split(
            '/\\r\\n|\\r|\\n/',
            $gitattributesContent
        );

        $basenamedGlobPatternMatchingExportIgnores = \array_map(
            'basename',
            $globPatternMatchingExportIgnores
        );

        $exportIgnoresToPreserve = [];

        \array_filter($gitattributesLines, function ($line) use (
            &$exportIgnoresToPreserve,
            &$globPatternMatchingExportIgnores,
            &$basenamedGlobPatternMatchingExportIgnores
        ) {
            if (\strstr($line, 'export-ignore') && \strpos($line, '#') === false) {
                list($pattern, $void) = \explode('export-ignore', $line);
                if (\substr($pattern, 0, 1) === '/') {
                    $pattern = \substr($pattern, 1);
                    $this->hasPrecedingSlashesInExportIgnorePattern = true;
                }
                $patternMatches = $this->patternHasMatch($pattern);
                $pattern = \trim($pattern);

                if ($patternMatches
                    && !\in_array($pattern, $globPatternMatchingExportIgnores)
                    && !\in_array($pattern, $basenamedGlobPatternMatchingExportIgnores)
                ) {
                    return $exportIgnoresToPreserve[] = \trim($pattern);
                }
            }
        });

        return $exportIgnoresToPreserve;
    }

    /**
     * Collect the expected export-ignored files.
     *
     * @return array
     */
    public function collectExpectedExportIgnores(): array
    {
        $expectedExportIgnores = [];

        $initialWorkingDirectory = (string) \getcwd();

        \chdir($this->directory);

        $ignoredGlobMatches = \array_merge(
            $this->ignoredGlobMatches,
            $this->getGitignoredPatterns()
        );

        $globMatches = Glob::glob($this->globPattern, Glob::GLOB_BRACE);

        if (!\is_array($globMatches)) {
            return $expectedExportIgnores;
        }

        foreach ($globMatches as $filename) {
            if (!\in_array($filename, $ignoredGlobMatches)) {
                if (\is_dir($filename)) {
                    $expectedExportIgnores[] = $filename . '/';
                    continue;
                }
                $expectedExportIgnores[] = $filename;
            }
        }

        \chdir($initialWorkingDirectory);

        if ($this->hasGitattributesFile()) {
            $expectedExportIgnores = \array_merge(
                $expectedExportIgnores,
                $this->getPresentExportIgnoresToPreserve($expectedExportIgnores)
            );
        }

        \sort($expectedExportIgnores, SORT_STRING | SORT_FLAG_CASE);

        if ($this->isKeepLicenseEnabled()) {
            $licenseLessExpectedExportIgnores = [];
            \array_filter($expectedExportIgnores, function ($exportIgnore) use (
                &$licenseLessExpectedExportIgnores
            ) {
                if (!\preg_match('/(License.*)/i', $exportIgnore)) {
                    $licenseLessExpectedExportIgnores[] = $exportIgnore;
                }
            });

            $expectedExportIgnores = $licenseLessExpectedExportIgnores;
        }

        if ($this->isKeepReadmeEnabled()) {
            $readmeLessExpectedExportIgnores = [];
            \array_filter($expectedExportIgnores, function ($exportIgnore) use (
                &$readmeLessExpectedExportIgnores
            ) {
                if (!\preg_match('/(Readme.*)/i', $exportIgnore)) {
                    $readmeLessExpectedExportIgnores[] = $exportIgnore;
                }
            });

            $expectedExportIgnores = $readmeLessExpectedExportIgnores;
        }

        if ($this->isKeepGlobPatternSet()) {
            $excludes = Glob::globArray($this->keepGlobPattern, $expectedExportIgnores);
            $expectedExportIgnores = \array_diff($expectedExportIgnores, $excludes);
        }

        return \array_unique($expectedExportIgnores);
    }

    /**
     * Detect most frequently used end of line sequence.
     *
     * @param  string $content The content to detect the eol in.
     *
     * @return string
     */
    private function detectEol($content): string
    {
        $maxCount = 0;
        $preferredEol = $this->preferredEol;
        $eols = ["\n", "\r", "\n\r", "\r\n"];

        foreach ($eols as $eol) {
            if (($count = \substr_count($content, $eol)) >= $maxCount) {
                $maxCount = $count;
                $preferredEol = $eol;
            }
        }

        $this->preferredEol = $preferredEol;

        return $preferredEol;
    }

    /**
     * Check if a given pattern produces a match
     * against the repository directory.
     *
     * @param  string  $globPattern
     * @return boolean
     */
    private function patternHasMatch($globPattern): bool
    {
        if (\substr(\trim($globPattern), 0, 1) === '/') {
            $globPattern = \trim(\substr($globPattern, 1));
        } elseif (\substr(\trim($globPattern), -1) === '/') {
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
     * Get the present non export-ignore entries of
     * the .gitattributes file.
     *
     * @return string
     */
    public function getPresentNonExportIgnoresContent(): string
    {
        if ($this->hasGitattributesFile() === false) {
            return '';
        }

        $gitattributesContent = (string) \file_get_contents($this->gitattributesFile);
        $eol = $this->detectEol($gitattributesContent);

        $gitattributesLines = \preg_split(
            '/\\r\\n|\\r|\\n/',
            $gitattributesContent
        );

        $nonExportIgnoreLines = [];
        $exportIgnoresPlacementPlaceholderSet = false;
        $exportIgnoresPlacementPlaceholder = self::EXPORT_IGNORES_PLACEMENT_PLACEHOLDER;

        \array_filter($gitattributesLines, function ($line) use (
            &$nonExportIgnoreLines,
            &$exportIgnoresPlacementPlaceholderSet,
            &$exportIgnoresPlacementPlaceholder
        ) {
            if (\strstr($line, 'export-ignore') === false || \strstr($line, '#')) {
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

    /**
     * Get the present export-ignore entries of
     * the .gitattributes file.
     *
     * @param bool $applyGlob
     * @param string $gitattributesContent
     * @return array
     */
    public function getPresentExportIgnores(bool $applyGlob = true, string $gitattributesContent = ''): array
    {
        if ($this->hasGitattributesFile() === false && $gitattributesContent === '') {
            return [];
        }

        if ($gitattributesContent === '') {
            $gitattributesContent = (string) \file_get_contents($this->gitattributesFile);
        }

        $gitattributesLines = \preg_split(
            '/\\r\\n|\\r|\\n/',
            $gitattributesContent
        );

        $exportIgnores = [];
        \array_filter($gitattributesLines, function ($line) use (&$exportIgnores, &$applyGlob) {
            if (\strstr($line, 'export-ignore', true)) {
                list($line, $void) = \explode('export-ignore', $line);
                if ($applyGlob) {
                    if ($this->patternHasMatch(\trim($line))) {
                        if (\substr($line, 0, 1) === '/') {
                            $line = \substr($line, 1);
                        }

                        return $exportIgnores[] = \trim($line);
                    }
                } else {
                    if ($this->patternHasMatch(\trim($line))) {
                        if (\substr($line, 0, 1) === '/') {
                            $line = \substr($line, 1);
                        }

                        return $exportIgnores[] = \trim($line);
                    } else {
                        return $exportIgnores[] = \trim($line);
                    }
                }
            }
        });

        if ($this->isStrictOrderComparisonEnabled() === false) {
            \sort($exportIgnores, SORT_STRING | SORT_FLAG_CASE);
        }

        return \array_unique($exportIgnores);
    }

    /**
     * @param  array  $artifacts The export-ignore artifacts to align.
     * @return array
     */
    private function getAlignedExportIgnoreArtifacts(array $artifacts): array
    {
        $longestArtifact = \max(\array_map('strlen', $artifacts));

        return \array_map(function ($artifact) use (&$longestArtifact) {
            if (\strlen($artifact) < $longestArtifact) {
                return $artifact . \str_repeat(
                    ' ',
                    $longestArtifact - \strlen($artifact)
                );
            }
            return $artifact;
        }, $artifacts);
    }

    private function getByDirectoriesToFilesExportIgnoreArtifacts(array $artifacts): array
    {
        $directories = \array_filter($artifacts, function ($artifact) {
            if (\strpos($artifact, '/')) {
                return $artifact;
            }
        });
        $files = \array_filter($artifacts, function ($artifact) {
            if (\strpos($artifact, '/') === false) {
                return $artifact;
            }
        });

        return \array_merge($directories, $files);
    }

    public function hasCompleteExportIgnoresFromString(string $gitattributesContent): bool
    {
        $expectedExportIgnores = $this->collectExpectedExportIgnores();
        $presentExportIgnores = $this->getPresentExportIgnores(true, $gitattributesContent);

        return \array_values($expectedExportIgnores) === \array_values($presentExportIgnores);
    }

    /**
     * Is existing .gitattributes file having all export-ignore(s).
     *
     */
    public function hasCompleteExportIgnores(): bool
    {
        $expectedExportIgnores = $this->collectExpectedExportIgnores();

        if ($expectedExportIgnores === [] || $this->hasGitattributesFile() === false) {
            return false;
        }

        $actualExportIgnores = $this->getPresentExportIgnores();

        $staleExportIgnores = [];

        if ($this->isStaleExportIgnoresComparisonEnabled()) {
            $unfilteredExportIgnores = $this->getPresentExportIgnores(false);
            foreach ($unfilteredExportIgnores as $unfilteredExportIgnore) {
                if (false === \file_exists($unfilteredExportIgnore)) {
                    $staleExportIgnores[] = $unfilteredExportIgnore;
                }
            }
        }

        if ($this->isStrictAlignmentComparisonEnabled()) {
            $expectedExportIgnores = $this->getAlignedExportIgnoreArtifacts(
                $expectedExportIgnores
            );
        }

        if ($this->isStaleExportIgnoresComparisonEnabled()) {
            $actualExportIgnores = \array_merge($actualExportIgnores, $staleExportIgnores);
        }

        return \array_values($expectedExportIgnores) === \array_values($actualExportIgnores);
    }

    /**
     * @throws PresetNotAvailable
     */
    public function setGlobPatternFromPreset(string $preset): void
    {
        $this->globPattern = '{' . \implode(',', $this->finder->getPresetGlobByLanguageName($preset)) . '}*';
    }
}
