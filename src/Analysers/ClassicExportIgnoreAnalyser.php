<?php declare(strict_types=1);

namespace Stolt\LeanPackage\Analysers;

use Stolt\LeanPackage\Glob;

final class ClassicExportIgnoreAnalyser extends AbstractExportIgnoreAnalyser
{
    public const EXPORT_IGNORE_CLASSIC = 'classic';
    public const EXPORT_IGNORES_PLACEMENT_PLACEHOLDER = '{{ export_ignores_placement }}';

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
            if (\in_array($filename, $ignoredGlobMatches, strict: true)) {
                continue;
            }

            if (\is_dir($filename)) {
                $expectedExportIgnores[] = $filename . '/';
                continue;
            }
            $expectedExportIgnores[] = $filename;
        }

        \chdir($initialWorkingDirectory);

        if ($this->hasGitattributesFile()) {
            $expectedExportIgnores = \array_merge(
                $expectedExportIgnores,
                $this->getPresentExportIgnoresToPreserve($expectedExportIgnores)
            );
        }

        if (!$this->isStrictOrderComparisonEnabled()) {
            \sort($expectedExportIgnores, SORT_STRING | SORT_FLAG_CASE);
        }

        if ($this->isKeepLicenseEnabled()) {
            $licenseLessExpectedExportIgnores = [];
            \array_filter($expectedExportIgnores, static function ($exportIgnore) use (
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
            \array_filter($expectedExportIgnores, static function ($exportIgnore) use (
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
     * Return export ignores in a .gitattributes file to preserve.
     *
     * @param  array $globPatternMatchingExportIgnores Export ignores matching glob pattern.
     *
     * @return array
     */
    public function getPresentExportIgnoresToPreserve(array $globPatternMatchingExportIgnores): array
    {
        $gitattributesContent = (string) \file_get_contents($this->gitattributesFile);

        if (\preg_match("/(\*\h*)(text\h*)(=\h*auto)/", $gitattributesContent)) {
            $this->textAutoconfiguration();
        }

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
            if (\strstr($line, 'export-ignore') && !\str_contains($line, '-export-ignore') && \strpos($line, '#') === false) {
                list($pattern, $void) = \explode('export-ignore', $line);
                if (\substr($pattern, 0, 1) === '/') {
                    $pattern = \substr($pattern, 1);
                    $this->hasPrecedingSlashesInExportIgnorePattern = true;
                }
                $patternMatches = $this->patternHasMatch($pattern);
                $pattern = \trim($pattern);

                if ($patternMatches
                    && !\in_array($pattern, $globPatternMatchingExportIgnores, strict: true)
                    && !\in_array($pattern, $basenamedGlobPatternMatchingExportIgnores, strict: true)
                ) {
                    if (\file_exists($this->directory . DIRECTORY_SEPARATOR . $pattern)) {
                        return $exportIgnoresToPreserve[] = \trim($pattern);
                    }
                }
            }
        });

        return $exportIgnoresToPreserve;
    }

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
                if (false !== \file_exists($unfilteredExportIgnore)) {
                    continue;
                }
                $staleExportIgnores[] = $unfilteredExportIgnore;
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
     * Get the present export-ignore entries of the .gitattributes file.
     *
     * @param bool $applyGlob
     * @param string $gitattributesContent
     * @param bool $strictOrderComparisonEnabled
     * @return array
     */
    public function getPresentExportIgnores(bool $applyGlob = true, string $gitattributesContent = '', bool $strictOrderComparisonEnabled = false): array
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
        \array_filter($gitattributesLines, function (string $line) use (&$exportIgnores, &$applyGlob) {
            $before = \strstr($line, 'export-ignore', true);
            if ($before !== false && $before !== '' && !\str_ends_with(\rtrim((string) $before), '-')) {
                list($line, $void) = \explode('export-ignore', $line);
                if ($applyGlob) {
                    if ($this->patternHasMatch(\trim($line))) {
                        if (str_starts_with($line, '/')) {
                            $line = \substr($line, 1);
                        }

                        return $exportIgnores[] = \trim($line);
                    }
                } else {
                    if ($this->patternHasMatch(\trim($line))) {
                        if (str_starts_with($line, '/')) {
                            $line = \substr($line, 1);
                        }

                        return $exportIgnores[] = \trim($line);
                    } else {
                        return $exportIgnores[] = \trim($line);
                    }
                }
            }
        });

        if ($strictOrderComparisonEnabled === false) {
            \sort($exportIgnores, SORT_STRING | SORT_FLAG_CASE);
        }

        return \array_unique($exportIgnores);
    }

    public function getGitattributesContentToBe(array $postfixLessExportIgnores = []): string
    {
        $collectExpectedExportIgnores = $this->collectExpectedExportIgnores();

        if (count($postfixLessExportIgnores) === 1 && $postfixLessExportIgnores[0] === '.gitattributes' && count($collectExpectedExportIgnores) === 0) {
            $postfixLessExportIgnores = [];
        }

        $postfixLessExportIgnores = array_unique(array_merge($collectExpectedExportIgnores, $postfixLessExportIgnores));

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

            if ($this->isKeepLicenseEnabled()) {
                $licenseLessExpectedExportIgnores = [];
                \array_filter($postfixLessExportIgnores, static function ($exportIgnore) use (
                    &$licenseLessExpectedExportIgnores
                ) {
                    if (!\preg_match('/(License.*)/i', $exportIgnore)) {
                        $licenseLessExpectedExportIgnores[] = $exportIgnore;
                    }
                });

                $postfixLessExportIgnores = $licenseLessExpectedExportIgnores;
            }

            if ($this->isKeepReadmeEnabled()) {
                $readmeLessExpectedExportIgnores = [];
                \array_filter($postfixLessExportIgnores, static function ($exportIgnore) use (
                    &$readmeLessExpectedExportIgnores
                ) {
                    if (!\preg_match('/(Readme.*)/i', $exportIgnore)) {
                        $readmeLessExpectedExportIgnores[] = $exportIgnore;
                    }
                });

                $postfixLessExportIgnores = $readmeLessExpectedExportIgnores;
            }

            if ($this->isKeepGlobPatternSet()) {
                $excludes = Glob::globArray($this->keepGlobPattern, $postfixLessExportIgnores);
                $postfixLessExportIgnores = \array_diff($postfixLessExportIgnores, $excludes);
            }

            $content = \implode(" export-ignore" . $this->preferredEol, $postfixLessExportIgnores)
                . " export-ignore" . $this->preferredEol;

            if ($this->hasGitattributesFile()) {
                $exportIgnoreContent = \rtrim($content);
                $content = $this->getPresentNonExportIgnoresContent();

                if (\strstr($content, ClassicExportIgnoreAnalyser::EXPORT_IGNORES_PLACEMENT_PLACEHOLDER)) {
                    $content = \str_replace(
                        ClassicExportIgnoreAnalyser::EXPORT_IGNORES_PLACEMENT_PLACEHOLDER,
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
}
