<?php declare(strict_types=1);

namespace Stolt\LeanPackage;

use Stolt\LeanPackage\Analysers\AbstractExportIgnoreAnalyser;
use Stolt\LeanPackage\Helpers\Str;

final class Reformatter
{
    public function reformat(AbstractExportIgnoreAnalyser $analyser): string
    {
        $gitattributesContent = $analyser->getPresentGitAttributesContent();

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
            if ($analyser->isAlignableExportIgnoreLine($line) === false || $line === '') {
                continue;
            }

            [$pattern] = \explode('export-ignore', $line, 2);

            if ($analyser->isNegatedExportIgnoreLine($line)) {
                [$pattern] = \explode('-export-ignore', $line, 2);
            }

            $exportIgnorePatterns[] = \rtrim($pattern);
        }

        if ($exportIgnorePatterns === []) {
            return $gitattributesContent;
        }

        $longestPattern = \max(\array_map('strlen', $exportIgnorePatterns));

        $alignedLines = \array_map(function (string $line) use ($analyser, $longestPattern): string {
            if ($analyser->isAlignableExportIgnoreLine($line) === false) {
                return $line;
            }

            $exportIgnorePattern = 'export-ignore';

            if ($analyser->isNegatedExportIgnoreLine($line)) {
                $exportIgnorePattern = '-export-ignore';
            }

            [$pattern, $suffix] = \explode($exportIgnorePattern, $line, 2);

            $pattern = \trim($pattern);

            if (\str_starts_with($pattern, '/')) {
                $pattern = \ltrim($pattern, '/');
            }

            if (\is_dir($analyser->getDirectory() . DIRECTORY_SEPARATOR . $pattern) && \str_ends_with($pattern, '/') === false) {
                $pattern .= DIRECTORY_SEPARATOR;
            }

            return $pattern . \str_repeat(' ', $longestPattern - \strlen($pattern) + 1) . $exportIgnorePattern . $suffix;
        }, $gitattributesLines);

        if ($analyser->sortAlphabetically === true) {
            \sort($alignedLines, SORT_STRING);
        }

        if ($analyser->sortFromDirectoriesToFiles === true) {
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

        if ($analyser->groupNonExportIgnores) {
            return $this->applyGrouping($analyser, $alignedLines, $eol);
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
    private function applyGrouping(AbstractExportIgnoreAnalyser $analyser, array $lines, string $eol): string
    {
        $count = \count($lines);
        $isExportIgnoresGroup = \array_fill(0, $count, false);
        $nextIsAnExportIgnore = false;

        for ($i = $count - 1; $i >= 0; $i--) {
            $line = $lines[$i];
            if ($analyser->isAlignableExportIgnoreLine($line)) {
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

        $nonExportIgnoreLines = $analyser->collapseAndTrimBlankLines($nonExportIgnoreLines);
        $exportIgnoreLines = $analyser->collapseAndTrimBlankLines($exportIgnoreLines);

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
}
