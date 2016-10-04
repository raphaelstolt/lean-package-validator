<?php

namespace Stolt\LeanPackage;

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
     * Initialize.
     */
    public function __construct()
    {
        $globPatterns = [
            '.*',
            '*.lock',
            '*.txt',
            '*.rst',
            '*.{md,MD}',
            '*.xml',
            '*.yml',
            'box.json',
            'captainhook.json',
            '*.dist.*',
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

        $this->globPattern = '{' . implode(',', $globPatterns) . '}*';
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
     * @throws Stolt\LeanPackag\Exceptions\NonExistentGlobPatternFile
     * @throws Stolt\LeanPackag\Exceptions\InvalidGlobPatternFile
     * @return Stolt\LeanPackag\Analyser
     */
    public function setGlobPatternFromFile($file)
    {
        if (!is_file($file)) {
            $message = "Glob pattern file {$file} doesn't exist.";
            throw new NonExistentGlobPatternFile($message);
        }

        $globPatternContent = file_get_contents($file);

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
     * @throws Stolt\LeanPackag\Exceptions\InvalidGlobPattern
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
     * @throws Stolt\LeanPackag\Exceptions\InvalidGlobPattern
     *
     * @return Stolt\LeanPackag\Analyser
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
     * @throws \RunTimeException
     *
     * @return Stolt\LeanPackag\Analyser
     */
    public function setDirectory($directory = __DIR__)
    {
        if (!is_dir($directory)) {
            $message = "Directory {$directory} doesn't exist.";
            throw new \RunTimeException($message);
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
     * @return Stolt\LeanPackag\Analyser
     */
    public function enableStrictOrderCamparison()
    {
        $this->strictOrderComparison = true;

        return $this;
    }

    /**
     * Accessor for strict order camparison.
     *
     * @return boolean
     */
    public function isStrictOrderCamparisonEnabled()
    {
        return $this->strictOrderComparison === true;
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
        sort($postfixlessExportIgnores, SORT_STRING | SORT_FLAG_CASE);

        if (count($postfixlessExportIgnores) > 0) {
            $content = implode(" export-ignore" . $this->preferredEol, $postfixlessExportIgnores)
                . " export-ignore" . $this->preferredEol;

            if ($this->hasGitattributesFile()) {
                $exportIgnoreContent = rtrim($content);
                $this->getPresentExportIgnoresToPreserve($postfixlessExportIgnores);

                $content = $this->getPresentNonExportIgnoresContent();
                $content = str_replace(
                    self::EXPORT_IGNORES_PLACEMENT_PLACEHOLDER,
                    $exportIgnoreContent,
                    $content
                );
            } else {
                $content = "* text=auto eol=lf"
                    . str_repeat($this->preferredEol, 2)
                    . '.gitattributes export-ignore' . $this->preferredEol
                    . $content;
            }

            return $content;
        }

        return '';
    }

    /**
     * Return export ignores in .gitattributes file to preserve.
     *
     * @param  array $postfixlessExportIgnores Export ignores matching glob pattern.
     *
     * @return array
     */
    public function getPresentExportIgnoresToPreserve(array $globPatternMatchingExportIgnores)
    {
        $gitattributesContent = file_get_contents($this->gitattributesFile);

        if (preg_match("/(\*\h*)(text\h*)(=\h*auto)/", $gitattributesContent)) {
            $this->hasTextAutoConfiguration = true;
        }

        $eol = $this->detectEol($gitattributesContent);

        $gitattributesLines = preg_split(
            '/\\r\\n|\\r|\\n/',
            $gitattributesContent
        );

        $exportIgnoresToPreserve = [];

        array_filter($gitattributesLines, function ($line) use (
            &$exportIgnoresToPreserve,
            &$globPatternMatchingExportIgnores
        ) {
            if (strstr($line, 'export-ignore')) {
                list($pattern, $void) = explode('export-ignore', $line);
                if (substr($pattern, 0, 1) === '/') {
                    $pattern = substr($pattern, 1);
                    $this->hasPrecedingSlashesInExportIgnorePattern = true;
                }
                $patternMatches = $this->patternHasMatch($pattern);
                if ($patternMatches && !in_array(trim($pattern), $globPatternMatchingExportIgnores)) {
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

        $initialWorkingDirectory = getcwd();

        chdir($this->directory);

        foreach (glob($this->globPattern, GLOB_BRACE) as $filename) {
            if (!in_array($filename, $this->ignoredGlobMatches)) {
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
        $globPattern = '{' . trim($globPattern) . '}*';

        $initialWorkingDirectory = getcwd();
        chdir($this->directory);

        $matches = glob($globPattern, GLOB_BRACE);

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

        $gitattributesContent = file_get_contents($this->gitattributesFile);
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
            if (strstr($line, 'export-ignore') === false) {
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

        $gitattributesContent = file_get_contents($this->gitattributesFile);

        $gitattributesLines = preg_split(
            '/\\r\\n|\\r|\\n/',
            $gitattributesContent
        );

        $exportIgnores = [];
        array_filter($gitattributesLines, function ($line) use (&$exportIgnores) {
            if (strstr($line, 'export-ignore', true)) {
                list($line, $void) = explode('export-ignore', $line);
                if (substr($line, 0, 1) === '/') {
                    $line = substr($line, 1);
                }
                return $exportIgnores[] = trim($line);
            }
        });

        if ($this->isStrictOrderCamparisonEnabled() === false) {
            sort($exportIgnores, SORT_STRING | SORT_FLAG_CASE);
        }

        return $exportIgnores;
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

        return array_values($expectedExportIgnores) === array_values($actualExportIgnores);
    }
}
