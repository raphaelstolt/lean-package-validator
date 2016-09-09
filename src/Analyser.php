<?php

namespace Stolt\LeanPackage;

use Stolt\LeanPackage\Exceptions\InvalidGlobPattern;

class Analyser
{
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
            '*.dist.*',
            '{B,b}uild*',
            '{D,d}oc*',
            '{T,t}ool*',
            '{T,t}est*',
            '{S,s}pec*',
            '{E,e}xample*',
            'LICENSE',
            '{{M,m}ake,{B,b}ox,{V,v}agrant}file',
            'RMT'
        ];

        $this->globPattern = '{' . implode(',', $globPatterns) . '}*';
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
            && (substr($this->globPattern, -1) !== '}' && substr($this->globPattern, -2) !== '}*'))
        {
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
                $content = $this->getPresentNonExportIgnoresContent();
                if ($this->lastContentLineHasEol($content) === false) {
                    $content.= $this->preferredEol;
                }
                $content .= $exportIgnoreContent;
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
     * Check if last line of content has an end of line sequence.
     *
     * @param  string $content The content to detect the eol in.
     *
     * @return boolean
     */
    private function lastContentLineHasEol($content)
    {
        if (substr($content, -1) !== $this->preferredEol) {
            return false;
        }

        return true;
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
        array_filter($gitattributesLines, function ($line) use (&$nonExportIgnoreLines) {
            if (strstr($line, 'export-ignore') === false) {
                return $nonExportIgnoreLines[] = trim($line);
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
                return $exportIgnores[] = trim($line);
            }
        });

        sort($exportIgnores, SORT_STRING | SORT_FLAG_CASE);

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

        return ($expectedExportIgnores === $actualExportIgnores);
    }
}
