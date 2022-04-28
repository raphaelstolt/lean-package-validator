<?php

namespace Stolt\LeanPackage;

use Laminas\Stdlib\Glob;
use Stolt\LeanPackage\Exceptions\InvalidGlobPattern;
use Stolt\LeanPackage\Exceptions\InvalidGlobPatternFile;
use Stolt\LeanPackage\Exceptions\NonExistentGlobPatternFile;

class Analyser
{
    const EXPORT_IGNORES_PLACEMENT_PLACEHOLDER = '{{ export_ignores_placement }}';
    /**
     * The directory to analyse
     *
     * @var string
     */
    private $directory;

    /**
     * The .gitattributes file to analyse
     *
     * @var string
     */
    private $gitattributesFile;

    /**
     * Files to ignore in glob matches.
     *
     * @var array
     */
    private $ignoredGlobMatches = ['.', '..', '.git', '.DS_Store'];

    /**
     * The default glob patterns.
     *
     * @var array
     */
    private $defaultGlobPatterns = [];

    /**
     * The .gitattributes glob pattern
     *
     * @var string
     */
    private $globPattern;

    /**
     * The preferred end of line sequence
     *
     * @var string
     */
    private $preferredEol = "\n";

    /**
     * Whether to do a strict comparsion of the export-ignores
     * in the .gitattributes files against the expected ones
     * or not.
     *
     * @var boolean
     */
    private $strictOrderComparison = false;

    /**
     * Whether to do a strict alignment comparsion of the export-ignores
     * in the .gitattributes files against the expected ones
     * or not.
     *
     * @var boolean
     */
    private $strictAlignmentComparison = false;

    /**
     * Whether at least one export-ignore pattern has
     * a preceding slash or not.
     *
     * @var boolean
     */
    private $hasPrecedingSlashesInExportIgnorePattern = false;

    /**
     * Whether a text auto configuration is present or not.
     *
     * @var boolean
     */
    private $hasTextAutoConfiguration = false;

    /**
     * Whether to exclude a license file from the export-ignores
     * or not.
     *
     * @var boolean
     */
    private $keepLicense = false;

    /**
     * Whether to align the export-ignores on create or overwrite
     * or not.
     *
     * @var boolean
     */
    private $alignExportIgnores = false;

    /**
     * Initialize.
     */
    public function __construct()
    {
        $this->defaultGlobPatterns = [
            '.*',
            '*.lock',
            '*.txt',
            '*.rst',
            '*.{md,MD}',
            '*.xml',
            '*.yml',
            'appveyor.yml',
            'box.json',
            'captainhook.json',
            '*.dist.*',
            '*.dist',
            '{B,b}uild*',
            '{D,d}oc*',
            '{T,t}ool*',
            '{T,t}est*',
            '{S,s}pec*',
            '{E,e}xample*',
            'LICENSE',
            '{{M,m}ake,{B,b}ox,{V,v}agrant,{P,p}hulp}file',
            'RMT'
        ];

        $this->globPattern = '{' . implode(',', $this->defaultGlobPatterns) . '}*';
    }

    /**
     * Accessor for the default glob patterns.
     *
     * @return array
     */
    public function getDefaultGlobPatterns()
    {
        return $this->defaultGlobPatterns;
    }

    /**
     * Accessor for preceding slashes in export-ignore pattern.
     *
     * @return boolean
     */
    public function hasPrecedingSlashesInExportIgnorePattern()
    {
        return $this->hasPrecedingSlashesInExportIgnorePattern;
    }

    /**
     * Accessor for text auto configuration.
     *
     * @return boolean
     */
    public function hasTextAutoConfiguration()
    {
        return $this->hasTextAutoConfiguration;
    }

    /**
     * Set the glob pattern file.
     *
     * @param  string $file
     * @throws \Stolt\LeanPackage\Exceptions\NonExistentGlobPatternFile
     * @throws \Stolt\LeanPackage\Exceptions\InvalidGlobPatternFile
     * @return \Stolt\LeanPackage\Analyser
     */
    public function setGlobPatternFromFile($file)
    {
        if (!is_file($file)) {
            $message = "Glob pattern file {$file} doesn't exist.";
            throw new NonExistentGlobPatternFile($message);
        }

        $globPatternContent = (string) file_get_contents($file);

        $globPatternLines = preg_split(
            '/\\r\\n|\\r|\\n/',
            $globPatternContent
        );

        $globPatterns = [];
        array_filter($globPatternLines, function ($line) use (&$globPatterns) {
            if (trim($line) !== '') {
                $globPatterns[] = trim($line);
            }
        });

        $globPattern = '{' . implode(',', $globPatterns) . '}*';

        try {
            $this->setGlobPattern($globPattern);

            return $this;
        } catch (InvalidGlobPattern $e) {
            $message = "Glob pattern file '{$file}' is invalid.";
            throw new InvalidGlobPatternFile($message);
        }
    }

    /**
     * Guard the set glob pattern.
     *
     * @throws \Stolt\LeanPackage\Exceptions\InvalidGlobPattern
     * @return void
     */
    private function guardGlobPattern()
    {
        $invalidGlobPattern = false;

        if (substr($this->globPattern, 0) !== '{'
            && (substr($this->globPattern, -1) !== '}' && substr($this->globPattern, -2) !== '}*')) {
            $invalidGlobPattern = true;
        }

        $bracesContent = trim(substr($this->globPattern, 1, -1));

        if (empty($bracesContent)) {
            $invalidGlobPattern = true;
        }

        $globPatterns = explode(',', $bracesContent);

        if (count($globPatterns) == 1) {
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
     *
     * @return \Stolt\LeanPackage\Analyser
     */
    public function setGlobPattern($pattern)
    {
        $this->globPattern = trim($pattern);
        $this->guardGlobPattern();

        return $this;
    }

    /**
     * Set the directory to analyse.
     *
     * @param  string $directory The directory to analyse.
     * @throws \RuntimeException
     *
     * @return \Stolt\LeanPackage\Analyser
     */
    public function setDirectory($directory = __DIR__)
    {
        if (!is_dir($directory)) {
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
    public function getDirectory()
    {
        return $this->directory;
    }

    /**
     * Enable strict order camparison.
     *
     * @return \Stolt\LeanPackage\Analyser
     */
    public function enableStrictOrderCamparison()
    {
        $this->strictOrderComparison = true;

        return $this;
    }

    /**
     * Guard for strict order camparison.
     *
     * @return boolean
     */
    public function isStrictOrderCamparisonEnabled()
    {
        return $this->strictOrderComparison === true;
    }

    /**
     * Enable strict alignment camparison.
     *
     * @return \Stolt\LeanPackage\Analyser
     */
    public function enableStrictAlignmentCamparison()
    {
        $this->strictAlignmentComparison = true;

        return $this;
    }

    /**
     * Guard for strict alignment camparison.
     *
     * @return boolean
     */
    public function isStrictAlignmentCamparisonEnabled()
    {
        return $this->strictAlignmentComparison === true;
    }

    /**
     * Keep license file in releases.
     *
     * @return \Stolt\LeanPackage\Analyser
     */
    public function keepLicense()
    {
        $this->keepLicense = true;

        return $this;
    }

    /**
     * Guard for not export-ignoring license file.
     *
     * @return boolean
     */
    public function isKeepLicenseEnabled()
    {
        return $this->keepLicense === true;
    }

    /**
     * Align export-ignores.
     *
     * @return \Stolt\LeanPackage\Analyser
     */
    public function alignExportIgnores()
    {
        $this->alignExportIgnores = true;

        return $this;
    }

    /**
     * Guard for aligning export-ignores.
     *
     * @return boolean
     */
    public function isAlignExportIgnoresEnabled()
    {
        return $this->alignExportIgnores === true;
    }

    /**
     * Accessor for the set .gitattributes file path.
     *
     * @return string
     */
    public function getGitattributesFilePath()
    {
        return $this->gitattributesFile;
    }

    /**
     * Is a .gitattributes file present?
     *
     * @return boolean
     */
    public function hasGitattributesFile()
    {
        return file_exists($this->gitattributesFile) &&
            is_readable($this->gitattributesFile);
    }

    /**
     * Return patterns in .gitignore file.
     *
     * @return array
     */
    public function getGitignoredPatterns()
    {
        $gitignoreFile = $this->getDirectory() . DIRECTORY_SEPARATOR . '.gitignore';

        if (!file_exists($gitignoreFile)) {
            return [];
        }

        $gitignoreContent = (string) file_get_contents($gitignoreFile);
        $eol = $this->detectEol($gitignoreContent);

        $gitignoreLines = preg_split(
            '/\\r\\n|\\r|\\n/',
            $gitignoreContent
        );

        $gitignoredPatterns = [];

        array_filter($gitignoreLines, function ($line) use (&$gitignoredPatterns) {
            $line = trim($line);
            if ($line !== '' && strpos($line, '#') === false) {
                if (substr($line, 0, 1) === "/") {
                    $gitignoredPatterns[] = substr($line, 1);
                }
                if (substr($line, -1, 1) === "/") {
                    $gitignoredPatterns[] = substr($line, 0, -1);
                }
                $gitignoredPatterns[] = $line;
            }
        });

        return $gitignoredPatterns;
    }

    /**
     * Return the expected .gitattributes content.
     *
     * @param  array $postfixlessExportIgnores Expected patterns without
     *                                         an export-ignore postfix.
     *
     * @return string
     */
    public function getExpectedGitattributesContent(array $postfixlessExportIgnores = [])
    {
        if ($postfixlessExportIgnores === []) {
            $postfixlessExportIgnores = $this->collectExpectedExportIgnores();
        }

        if (!$this->hasGitattributesFile() && count($postfixlessExportIgnores) > 0) {
            $postfixlessExportIgnores[] = '.gitattributes';
        }

        sort($postfixlessExportIgnores, SORT_STRING | SORT_FLAG_CASE);

        if (count($postfixlessExportIgnores) > 0) {
            if ($this->isAlignExportIgnoresEnabled() || $this->isStrictAlignmentCamparisonEnabled()) {
                $postfixlessExportIgnores = $this->getAlignedExportIgnoreArtifacts(
                    $postfixlessExportIgnores
                );
            }

            $content = implode(" export-ignore" . $this->preferredEol, $postfixlessExportIgnores)
                . " export-ignore" . $this->preferredEol;

            if ($this->hasGitattributesFile()) {
                $exportIgnoreContent = rtrim($content);
                $content = $this->getPresentNonExportIgnoresContent();

                if (strstr($content, self::EXPORT_IGNORES_PLACEMENT_PLACEHOLDER)) {
                    $content = str_replace(
                        self::EXPORT_IGNORES_PLACEMENT_PLACEHOLDER,
                        $exportIgnoreContent,
                        $content
                    );
                } else {
                    $content = $content
                        . str_repeat($this->preferredEol, 2)
                        . $exportIgnoreContent;
                }
            } else {
                $content = "* text=auto eol=lf"
                    . str_repeat($this->preferredEol, 2)
                    . $content;
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
    public function getPresentExportIgnoresToPreserve(array $globPatternMatchingExportIgnores)
    {
        $gitattributesContent = (string) file_get_contents($this->gitattributesFile);

        if (preg_match("/(\*\h*)(text\h*)(=\h*auto)/", $gitattributesContent)) {
            $this->hasTextAutoConfiguration = true;
        }

        $eol = $this->detectEol($gitattributesContent);

        $gitattributesLines = preg_split(
            '/\\r\\n|\\r|\\n/',
            $gitattributesContent
        );

        $basenamedGlobPatternMatchingExportIgnores = array_map(
            'basename',
            $globPatternMatchingExportIgnores
        );

        $exportIgnoresToPreserve = [];

        array_filter($gitattributesLines, function ($line) use (
            &$exportIgnoresToPreserve,
            &$globPatternMatchingExportIgnores,
            &$basenamedGlobPatternMatchingExportIgnores
        ) {
            if (strstr($line, 'export-ignore') && strpos($line, '#') === false) {
                list($pattern, $void) = explode('export-ignore', $line);
                if (substr($pattern, 0, 1) === '/') {
                    $pattern = substr($pattern, 1);
                    $this->hasPrecedingSlashesInExportIgnorePattern = true;
                }
                $patternMatches = $this->patternHasMatch($pattern);
                $pattern = trim($pattern);

                if ($patternMatches
                    && !in_array($pattern, $globPatternMatchingExportIgnores)
                    && !in_array($pattern, $basenamedGlobPatternMatchingExportIgnores)
                ) {
                    return $exportIgnoresToPreserve[] = trim($pattern);
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
    public function collectExpectedExportIgnores()
    {
        $expectedExportIgnores = [];

        $initialWorkingDirectory = (string) getcwd();

        chdir($this->directory);

        $ignoredGlobMatches = array_merge(
            $this->ignoredGlobMatches,
            $this->getGitignoredPatterns()
        );

        $globMatches = Glob::glob($this->globPattern, Glob::GLOB_BRACE);

        if (!is_array($globMatches)) {
            return $expectedExportIgnores;
        }

        foreach ($globMatches as $filename) {
            if (!in_array($filename, $ignoredGlobMatches)) {
                if (is_dir($filename)) {
                    $expectedExportIgnores[] = $filename . '/';
                    continue;
                }
                $expectedExportIgnores[] = $filename;
            }
        }

        chdir($initialWorkingDirectory);

        if ($this->hasGitattributesFile()) {
            $expectedExportIgnores = array_merge(
                $expectedExportIgnores,
                $this->getPresentExportIgnoresToPreserve($expectedExportIgnores)
            );
        }

        sort($expectedExportIgnores, SORT_STRING | SORT_FLAG_CASE);

        if ($this->isKeepLicenseEnabled()) {
            $licenseLessExpectedExportIgnores = [];
            array_filter($expectedExportIgnores, function ($exportIgnore) use (
                &$licenseLessExpectedExportIgnores
            ) {
                if (!preg_match('/(License.*)/i', $exportIgnore)) {
                    $licenseLessExpectedExportIgnores[] = $exportIgnore;
                }
            });

            $expectedExportIgnores = $licenseLessExpectedExportIgnores;
        }

        return array_unique($expectedExportIgnores);
    }

    /**
     * Detect most frequently used end of line sequence.
     *
     * @param  string $content The content to detect the eol in.
     *
     * @return string
     */
    private function detectEol($content)
    {
        $maxCount = 0;
        $preferredEol = $this->preferredEol;
        $eols = ["\n", "\r", "\n\r", "\r\n"];

        foreach ($eols as $eol) {
            if (($count = substr_count($content, $eol)) >= $maxCount) {
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
    private function patternHasMatch($globPattern)
    {
        if (substr(trim($globPattern), 0, 1) === '/') {
            $globPattern = trim(substr($globPattern, 1));
        } elseif (substr(trim($globPattern), -1) === '/') {
            $globPattern = trim(substr($globPattern, 0, -1));
        } else {
            $globPattern = '{' . trim($globPattern) . '}*';
        }

        $initialWorkingDirectory = (string) getcwd();
        chdir($this->directory);

        $matches = Glob::glob($globPattern, Glob::GLOB_BRACE);

        chdir($initialWorkingDirectory);

        return is_array($matches) && count($matches) > 0;
    }

    /**
     * Get the present non export-ignore entries of
     * the .gitattributes file.
     *
     * @return string
     */
    public function getPresentNonExportIgnoresContent()
    {
        if ($this->hasGitattributesFile() === false) {
            return '';
        }

        $gitattributesContent = (string) file_get_contents($this->gitattributesFile);
        $eol = $this->detectEol($gitattributesContent);

        $gitattributesLines = preg_split(
            '/\\r\\n|\\r|\\n/',
            $gitattributesContent
        );

        $nonExportIgnoreLines = [];
        $exportIgnoresPlacementPlaceholderSet = false;
        $exportIgnoresPlacementPlaceholder = self::EXPORT_IGNORES_PLACEMENT_PLACEHOLDER;

        array_filter($gitattributesLines, function ($line) use (
            &$nonExportIgnoreLines,
            &$exportIgnoresPlacementPlaceholderSet,
            &$exportIgnoresPlacementPlaceholder
        ) {
            if (strstr($line, 'export-ignore') === false || strstr($line, '#')) {
                return $nonExportIgnoreLines[] = trim($line);
            } else {
                if ($exportIgnoresPlacementPlaceholderSet === false) {
                    $exportIgnoresPlacementPlaceholderSet = true;
                    return $nonExportIgnoreLines[] = $exportIgnoresPlacementPlaceholder;
                }
            }
        });

        return implode($eol, $nonExportIgnoreLines);
    }

    /**
     * Get the present export-ignore entries of
     * the .gitattributes file.
     *
     * @return array
     */
    public function getPresentExportIgnores()
    {
        if ($this->hasGitattributesFile() === false) {
            return [];
        }

        $gitattributesContent = (string) file_get_contents($this->gitattributesFile);

        $gitattributesLines = preg_split(
            '/\\r\\n|\\r|\\n/',
            $gitattributesContent
        );

        $exportIgnores = [];
        array_filter($gitattributesLines, function ($line) use (&$exportIgnores) {
            if (strstr($line, 'export-ignore', true)) {
                list($line, $void) = explode('export-ignore', $line);
                if ($this->patternHasMatch(trim($line))) {
                    if (substr($line, 0, 1) === '/') {
                        $line = substr($line, 1);
                    }

                    return $exportIgnores[] = trim($line);
                }
            }
        });

        if ($this->isStrictOrderCamparisonEnabled() === false) {
            sort($exportIgnores, SORT_STRING | SORT_FLAG_CASE);
        }

        return array_unique($exportIgnores);
    }

    /**
     * @param  array  $artifacts The export-ignore artifacts to align.
     * @return array
     */
    private function getAlignedExportIgnoreArtifacts(array $artifacts)
    {
        $longestArtifact = max(array_map('strlen', $artifacts));

        return array_map(function ($artifact) use (&$longestArtifact) {
            if (strlen($artifact) < $longestArtifact) {
                return $artifact . str_repeat(
                    ' ',
                    $longestArtifact - strlen($artifact)
                );
            }
            return $artifact;
        }, $artifacts);
    }

    /**
     * Is existing .gitattributes file has all export-ignore(s).
     *
     * @return boolean
     */
    public function hasCompleteExportIgnores()
    {
        $expectedExportIgnores = $this->collectExpectedExportIgnores();

        if ($expectedExportIgnores === [] || $this->hasGitattributesFile() === false) {
            return false;
        }

        $actualExportIgnores = $this->getPresentExportIgnores();

        if ($this->isStrictAlignmentCamparisonEnabled()) {
            $expectedExportIgnores = $this->getAlignedExportIgnoreArtifacts(
                $expectedExportIgnores
            );
        }

        return array_values($expectedExportIgnores) === array_values($actualExportIgnores);
    }
}
