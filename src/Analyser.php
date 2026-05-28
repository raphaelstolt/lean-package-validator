<?php declare(strict_types=1);

namespace Stolt\LeanPackage;

use Stolt\LeanPackage\Analysers\AbstractExportIgnoreAnalyser;
use Stolt\LeanPackage\Analysers\ClassicExportIgnoreAnalyser;
use Stolt\LeanPackage\Analysers\NegatedExportIgnoreAnalyser;
use Stolt\LeanPackage\Gitattributes\FileRepository as GitattributesFileRepository;
use Stolt\LeanPackage\Helpers\Str;

class Analyser
{
    private AbstractExportIgnoreAnalyser $exportIgnoreAnalyser;

    public function __construct(
        AbstractExportIgnoreAnalyser $actualExportIgnoreAnalyser
    ) {
        $this->exportIgnoreAnalyser = $actualExportIgnoreAnalyser;
    }

    public function getActualExportIgnoreAnalyser(): AbstractExportIgnoreAnalyser
    {
        return $this->exportIgnoreAnalyser;
    }

    public function getActualGitattributesContent(): string
    {
        return $this->exportIgnoreAnalyser->getPresentGitAttributesContent();
    }

    /**
     * Return the expected .gitattributes content.
     *
     * @param array $postfixLessExportIgnores Expected patterns without an export-ignore postfix.
     * @param string $flavour The flavour of the .gitattributes file content. Possible values are classic and negated.
     * @return string
     */
    public function getExpectedGitattributesContent(array $postfixLessExportIgnores = [], string $flavour = ClassicExportIgnoreAnalyser::EXPORT_IGNORE_CLASSIC): string
    {
        if ($flavour !== ClassicExportIgnoreAnalyser::EXPORT_IGNORE_CLASSIC
            && $flavour !== NegatedExportIgnoreAnalyser::EXPORT_IGNORE_NEGATED
        ) {
            throw new \InvalidArgumentException("Invalid flavour provided. Expected 'classic' or 'negated'.");
        }

        if (!$this->getActualExportIgnoreAnalyser()->hasGitattributesFile() && $flavour === ClassicExportIgnoreAnalyser::EXPORT_IGNORE_CLASSIC) {
            $postfixLessExportIgnores[] = '.gitattributes';
        }

        \sort($postfixLessExportIgnores, SORT_STRING | SORT_FLAG_CASE);

        if ($flavour === NegatedExportIgnoreAnalyser::EXPORT_IGNORE_NEGATED) {
            if ($this->exportIgnoreAnalyser instanceof ClassicExportIgnoreAnalyser) {
                $formerExportIgnoreAnalyserConfiguration = $this->exportIgnoreAnalyser->getConfiguration();
                $this->exportIgnoreAnalyser = new NegatedExportIgnoreAnalyser(
                    $this->exportIgnoreAnalyser->getFinder(),
                    $this->exportIgnoreAnalyser->getGitattributesFileRepository(),
                    $this->exportIgnoreAnalyser->getDirectory(),
                    $formerExportIgnoreAnalyserConfiguration
                );

                return $this->exportIgnoreAnalyser->getGitattributesContentToBe()->getContent();
            }
            return $this->exportIgnoreAnalyser->getGitattributesContentToBe($postfixLessExportIgnores)->getContent();
        }

        return $this->exportIgnoreAnalyser->getGitattributesContentToBe($postfixLessExportIgnores)->getContent();
    }

    /**
     * Return the reformatted .gitattributes content with aligned export-ignore
     * entries, respecting the sort-alphabetically, sort-from-directories-to-files,
     * and group-non-export-ignores settings.
     *
     * @return string
     */
    public function getReformattedGitattributesContent(): string
    {
        $gitattributesContent = $this->exportIgnoreAnalyser->getPresentGitAttributesContent();

        if ($gitattributesContent === '') {
            return '';
        }

        $eol = Str::detectEol($gitattributesContent);

        $gitattributesLines = \preg_split('/\\r\\n|\\r|\\n/', $gitattributesContent);

        if ($gitattributesLines === false) {
            return $gitattributesContent;
        }

        $exportIgnorePatterns = [];

        foreach ($gitattributesLines as $line) {
            if ($this->exportIgnoreAnalyser->isAlignableExportIgnoreLine($line) === false || $line === '') {
                continue;
            }


            [$pattern] = \explode('export-ignore', $line, 2);

            if ($this->exportIgnoreAnalyser->isNegatedExportIgnoreLine($line)) {
                [$pattern] = \explode('-export-ignore', $line, 2);
            }

            $exportIgnorePatterns[] = \rtrim($pattern);
        }

        if ($exportIgnorePatterns === []) {
            return $gitattributesContent;
        }

        $longestPattern = \max(\array_map('strlen', $exportIgnorePatterns));

        $alignedLines = \array_map(function (string $line) use ($longestPattern): string {
            if ($this->exportIgnoreAnalyser->isAlignableExportIgnoreLine($line) === false) {
                return $line;
            }
            $exportIgnorePattern = 'export-ignore';

            if ($this->exportIgnoreAnalyser->isNegatedExportIgnoreLine($line)) {
                $exportIgnorePattern = '-export-ignore';
            }

            [$pattern, $suffix] = \explode($exportIgnorePattern, $line, 2);

            $pattern = \trim($pattern);

            if (\str_starts_with($pattern, '/')) {
                $pattern = \ltrim($pattern, '/');
            }

            if (\is_dir($this->exportIgnoreAnalyser->directory . DIRECTORY_SEPARATOR . $pattern) && \str_ends_with($pattern, '/') === false) {
                $pattern .= DIRECTORY_SEPARATOR;
            }

            if (\strlen($pattern) > $longestPattern) {
                $longestPattern = \strlen($pattern);
            }

            return $pattern . \str_repeat(' ', $longestPattern - \strlen($pattern) + 1) . $exportIgnorePattern . $suffix;
        }, $gitattributesLines);

        if ($this->exportIgnoreAnalyser->sortAlphabetically === true) {
            \sort($alignedLines, SORT_STRING);
        }

        if ($this->exportIgnoreAnalyser->sortFromDirectoriesToFiles === true) {
            $directories = \array_filter($alignedLines, static function (string $line): bool {
                return \dirname($line) !== '.';
            });

            $files = \array_filter($alignedLines, static function (string $line): bool {
                return \dirname($line) === '.';
            });

            \sort($directories, SORT_NATURAL);
            \usort($files, static function (string $a, string $b): int {
                return \strnatcasecmp(\basename($a), \basename($b));
            });

            $alignedLines = \array_merge($directories, $files);
        }

        if ($this->exportIgnoreAnalyser->groupNonExportIgnores) {
            return $this->applyGrouping($alignedLines, $eol);
        }

        return \implode($eol, $alignedLines);
    }

    /**
     * Reorganise lines into a non-export-ignore section followed by an
     * export-ignore section. A comment immediately preceding an export-ignore
     * line (no blank line between them) is treated as "sticky" and kept in the
     * export-ignore section alongside the entries it describes.
     *
     * @param array<int, string> $lines
     */
    private function applyGrouping(array $lines, string $eol): string
    {
        $count = \count($lines);
        $isExportIgnoresGroup = \array_fill(0, $count, false);
        $nextIsAnExportIgnore = false;

        for ($i = $count - 1; $i >= 0; $i--) {
            $line = $lines[$i];
            if ($this->exportIgnoreAnalyser->isAlignableExportIgnoreLine($line)) {
                $isExportIgnoresGroup[$i] = true;
                $nextIsAnExportIgnore = true;
            } elseif (\trim($line) === '') {
                $isExportIgnoresGroup[$i] = false;
                $nextIsAnExportIgnore = false;
            } elseif (\str_starts_with(\ltrim($line), '#')) {
                $isExportIgnoresGroup[$i] = $nextIsAnExportIgnore;
            } else {
                $isExportIgnoresGroup[$i] = false;
                $nextIsAnExportIgnore = false;
            }
        }

        $nonExportIgnoreLines = [];
        $exportIgnoreLines = [];

        foreach ($lines as $i => $line) {
            if ($isExportIgnoresGroup[$i]) {
                $exportIgnoreLines[] = $line;
            } else {
                $nonExportIgnoreLines[] = $line;
            }
        }

        $nonExportIgnoreLines = $this->exportIgnoreAnalyser->collapseAndTrimBlankLines($nonExportIgnoreLines);
        $exportIgnoreLines = $this->exportIgnoreAnalyser->collapseAndTrimBlankLines($exportIgnoreLines);

        $nonExportIgnoreLines = $this->subGroupNonExportIgnoreLines($nonExportIgnoreLines);

        if ($nonExportIgnoreLines === [] && $exportIgnoreLines === []) {
            return '';
        }

        if ($nonExportIgnoreLines === []) {
            return \implode($eol, $exportIgnoreLines);
        }

        if ($exportIgnoreLines === []) {
            return \implode($eol, $nonExportIgnoreLines);
        }

        return \implode($eol, $nonExportIgnoreLines) . $eol . $eol . \implode($eol, $exportIgnoreLines);
    }

    /**
     * Subgroup non-export-ignore lines: glob/wildcard patterns first,
     * then specific-file attribute directives, comments before both.
     *
     * @param array<int, string> $lines
     * @return array<int, string>
     */
    private function subGroupNonExportIgnoreLines(array $lines): array
    {
        $commentLines = [];
        $globPatternLines = [];
        $specificFileLines = [];

        foreach ($lines as $line) {
            if (\str_starts_with(\ltrim($line), '#')) {
                $commentLines[] = $line;
            } elseif ($line !== '' && \str_starts_with(\ltrim($line), '*')) {
                $globPatternLines[] = $line;
            } elseif ($line !== '') {
                $specificFileLines[] = $line;
            }
        }

        $result = [];

        if ($commentLines !== []) {
            $result = \array_merge($result, $commentLines);
            if ($globPatternLines !== [] || $specificFileLines !== []) {
                $result[] = '';
            }
        }

        if ($globPatternLines !== []) {
            $result = \array_merge($result, $globPatternLines);
            if ($specificFileLines !== []) {
                $result[] = '';
            }
        }

        if ($specificFileLines !== []) {
            $result = \array_merge($result, $specificFileLines);
        }

        return $result;
    }

    public function usesNegatedExportIgnoreStrategy(string $gitattributesContent = ''): bool
    {
        if ($gitattributesContent === '') {
            if ($this->exportIgnoreAnalyser->hasGitattributesFile() === false) {
                return false;
            }
            $gitattributesContent = (string) \file_get_contents($this->exportIgnoreAnalyser->gitattributesFile);
        }

        $lines = \preg_split('/\\r\\n|\\r|\\n/', $gitattributesContent) ?: [];

        foreach ($lines as $line) {
            if (\trim($line) === '* export-ignore') {
                return true;
            }
        }

        return false;
    }

    public function hasCompleteExportIgnoresFromString(string $gitattributesContent): bool
    {
        if ($this->usesNegatedExportIgnoreStrategy($gitattributesContent)) {
            return $this->buildNegatedAnalyser()->getPresentExportIgnores(true, $gitattributesContent) !== [];
        }

        $expectedExportIgnores = $this->exportIgnoreAnalyser->collectExpectedExportIgnores();
        $presentExportIgnores = $this->exportIgnoreAnalyser->getPresentExportIgnores(
            true,
            $gitattributesContent,
            $this->getActualExportIgnoreAnalyser()->getConfiguration()->enforceStrictOrderComparison
        );

        \sort($expectedExportIgnores, SORT_STRING | SORT_FLAG_CASE);

        if ($this->getActualExportIgnoreAnalyser()->getConfiguration()->enforceStrictOrderComparison === true) {
            return $expectedExportIgnores === $presentExportIgnores;
        }

        \sort($presentExportIgnores, SORT_STRING | SORT_FLAG_CASE);

        return $expectedExportIgnores === $presentExportIgnores;
    }

    /**
     * Is existing .gitattributes file having all export-ignore(s).
     */
    public function hasCompleteExportIgnores(): bool
    {
        if ($this->usesNegatedExportIgnoreStrategy()) {
            return $this->buildNegatedAnalyser()->hasCompleteExportIgnores();
        }

        return $this->exportIgnoreAnalyser->hasCompleteExportIgnores();
    }

    private function buildNegatedAnalyser(): NegatedExportIgnoreAnalyser
    {
        if ($this->exportIgnoreAnalyser instanceof NegatedExportIgnoreAnalyser) {
            return $this->exportIgnoreAnalyser;
        }

        $directory = $this->exportIgnoreAnalyser->getDirectory();

        $analyser = new NegatedExportIgnoreAnalyser(
            $this->exportIgnoreAnalyser->getFinder(),
            new GitattributesFileRepository($this->exportIgnoreAnalyser->getDirectory()),
            $directory,
            $this->exportIgnoreAnalyser->getConfiguration(),
        );

        $analyser->setDirectory($directory);

        if ($this->exportIgnoreAnalyser->isStaleExportIgnoresComparisonEnabled()) {
            $analyser->enableStaleExportIgnoresComparison();
        }

        return $analyser;
    }
}
