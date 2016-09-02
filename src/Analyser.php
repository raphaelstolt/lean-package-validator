<?php

namespace Stolt\LeanPackage;

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
            '{M,m}akefile',
            'RMT'
        ];

        $this->globPattern = '{' . implode(',', $globPatterns) . '}*';
    }
//TODO: Add glob validation
    /**
     * Overwrite the default glob pattern.
     *
     * @param string $pattern The glob pattern to use to detect expected
     *                        export-ignores files.
     *
     * @return Stolt\LeanPackag\Analyser
     */
    public function setGlobPattern($pattern)
    {
        $this->globPattern = trim($pattern);

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
            $content = implode(" export-ignore\n", $postfixlessExportIgnores)
                . " export-ignore\n";

            if ($this->hasGitattributesFile()) {
                $exportIgnoreContent = rtrim($content);
                $content = $this->getPresentNonExportIgnoresContent();
                $content .= "\n" . $exportIgnoreContent;
            } else {
                $content = "* text=auto eol=lf\n\n" . $content;
            }

            return $content;
        }

        return '';
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
     * TODO: Keep newline as is.
     *
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

        return implode("\n", $nonExportIgnoreLines);
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
