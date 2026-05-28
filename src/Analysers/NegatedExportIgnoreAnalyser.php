<?php declare(strict_types=1);

namespace Stolt\LeanPackage\Analysers;

use Stolt\LeanPackage\Gitattributes\ValueObject as GitattributesValueObject;
use Stolt\LeanPackage\Glob;

final class NegatedExportIgnoreAnalyser extends AbstractExportIgnoreAnalyser
{
    public const EXPORT_IGNORE_NEGATED = 'negated';

    public function collectExpectedExportIgnores(): array
    {
        $expectedNegatedExportIgnores = [];

        if (!\is_dir($this->getDirectory())) {
            throw new \RuntimeException("Directory {$this->getDirectory()} doesn't exist.");
        }

        \chdir($this->getDirectory());

        $globMatches = Glob::glob($this->globPattern, Glob::GLOB_BRACE);

        if (!\is_array($globMatches)) {
            return $expectedNegatedExportIgnores;
        }

        $globMatches = \array_values(
            \array_filter($globMatches, function (string $fileToIgnore): bool {
                if ($this->isKeepLicenseEnabled() && \preg_match('/(License.*)/i', $fileToIgnore)) {
                    return false;
                }

                if ($this->isKeepReadmeEnabled() && \preg_match('/(Readme.*)/i', $fileToIgnore)) {
                    return false;
                }

                return true;
            })
        );

        $allFiles = Glob::glob('{*}', Glob::GLOB_BRACE);

        if (!\is_array($allFiles) || \count($allFiles) === 0) {
            return $expectedNegatedExportIgnores;
        }

        return \array_diff($allFiles, $globMatches);
    }

    public function getGitattributesContentToBe(array $postfixLessExportIgnores = []): GitattributesValueObject
    {
        $exportIgnoresToNegate = $this->buildExportIgnoresToNegate();

        if ($exportIgnoresToNegate === []) {
            return GitattributesValueObject::fromString('');
        }

        $exportIgnoresToNegate = $this->sortAndFormatExportIgnores(
            $exportIgnoresToNegate
        );

        $content = $this->buildExportIgnoreContent($exportIgnoresToNegate);

        if ($this->hasGitattributesFile()) {
            return GitattributesValueObject::fromString($this->mergeWithExistingGitattributes($content));
        }

        $contentToBe = "* text=auto eol=lf"
            . \str_repeat($this->preferredEol, 2)
            . $content;

        return GitattributesValueObject::fromString($contentToBe);
    }

    private function buildExportIgnoresToNegate(): array
    {
        $entries = $this->collectExpectedExportIgnores();

        \sort($entries, SORT_STRING | SORT_FLAG_CASE);

        $entries = \array_map(
            fn (string $entry): string => $this->appendDirectorySeparator($entry),
            $entries
        );

        $globedEntries = $this->buildGlobedEntries($entries);

        $entries = \array_unique(\array_merge($entries, $globedEntries));

        $entries = \array_unique(\array_merge(
            $entries,
            $this->expandBinaryDirectories($entries)
        ));

        \sort($entries, SORT_STRING | SORT_FLAG_CASE);

        return $entries;
    }

    private function appendDirectorySeparator(string $entry): string
    {
        if (\is_dir($this->directory . DIRECTORY_SEPARATOR . $entry)) {
            return $entry . DIRECTORY_SEPARATOR;
        }

        return $entry;
    }

    private function buildGlobedEntries(array $entries): array
    {
        $directories = \array_filter(
            $entries,
            fn (string $entry): bool => $this->shouldBeGlobed($entry)
        );

        return \array_map(
            static function (string $entry): string {
                return $entry . '**';
            },
            $directories
        );
    }

    private function shouldBeGlobed(string $entry): bool
    {
        if (!\str_ends_with($entry, DIRECTORY_SEPARATOR)) {
            return false;
        }

        $iterator = new \FilesystemIterator(
            $this->directory . DIRECTORY_SEPARATOR . $entry
        );

        return \iterator_count($iterator) >= 2;
    }

    private function expandBinaryDirectories(array $entries): array
    {
        $expanded = [];

        foreach ($entries as $entry) {
            $expanded[] = $entry;

            if (!\str_ends_with($entry, 'bin' . DIRECTORY_SEPARATOR)) {
                continue;
            }

            if (\trim($this->getDirectory()) === '') {
                continue;
            }

            $binaryDirectory = $this->getDirectory() . DIRECTORY_SEPARATOR . $entry;

            $iterator = new \FilesystemIterator($binaryDirectory);

            foreach ($iterator as $file) {
                if ($file instanceof \SplFileInfo && $file->isDir()) {
                    continue;
                }
                if ($file instanceof \SplFileInfo) {
                    $expanded[] = $entry . $file->getFilename();
                }
            }
        }

        return $expanded;
    }

    public function hasDoubleStarForDirectories(array $negatedEntries): bool
    {
        $lookup = \array_flip($negatedEntries);

        foreach ($negatedEntries as $entry) {
            if (!(\str_ends_with($entry, '/'))) {
                continue;
            }

            $directory = \rtrim($entry, '/');
            $expected = $directory . '/**';

            $hasDirectFileEntry = false;

            foreach ($negatedEntries as $candidate) {
                if (
                    !(\str_starts_with($candidate, $directory . '/')
                    && !\str_ends_with($candidate, '/')
                    && $candidate !== $expected)
                ) {
                    continue;
                }

                $hasDirectFileEntry = true;

                break;
            }

            if ($hasDirectFileEntry) {
                continue;
            }

            if (!isset($lookup[$expected])) {
                return false;
            }
        }

        return true;
    }

    private function buildExportIgnoreContent(array $entries): string
    {
        $suffix = ' -export-ignore';

        if ($this->isAlignExportIgnoresEnabled()) {
            $maxLength = \max(
                \array_map(
                    static fn (string $entry): int => \strlen($entry),
                    $entries
                )
            );

            $lines = \array_map(
                static function (string $entry) use ($maxLength, $suffix): string {
                    return \str_pad(
                        $entry,
                        $maxLength,
                        ' ',
                        STR_PAD_RIGHT
                    ) . $suffix;
                },
                $entries
            );
        } else {
            $lines = \array_map(
                static function (string $entry) use ($suffix): string {
                    return $entry . $suffix;
                },
                $entries
            );
        }

        return '* export-ignore'
            . $this->preferredEol
            . $this->preferredEol
            . \implode($this->preferredEol, $lines)
            . $this->preferredEol;
    }

    /**
     * @param bool $applyGlob
     * @param string $gitattributesContent
     * @param bool $strictOrderComparisonEnabled
     * @return array<int, string>
     */
    public function getPresentExportIgnores(bool $applyGlob = true, string $gitattributesContent = '', bool $strictOrderComparisonEnabled = false): array
    {
        if ($this->hasGitattributesFile() === false && $gitattributesContent === '') {
            return [];
        }

        if ($gitattributesContent === '') {
            $gitattributesContent = $this->gitattributesFileRepository->getGitattributesContent();
        }

        $lines = \preg_split('/\\r\\n|\\r|\\n/', $gitattributesContent) ?: [];

        $negatedIgnores = [];

        foreach ($lines as $line) {
            if (!\str_contains($line, '-export-ignore') || \str_starts_with(\ltrim($line), '#')) {
                continue;
            }

            [$pattern] = \explode('-export-ignore', $line, 2);
            $pattern = \ltrim(\trim($pattern), '/');

            if ($pattern === '') {
                continue;
            }

            if ($applyGlob) {
                if ($this->patternHasMatch($pattern)) {
                    $negatedIgnores[] = $pattern;
                }
            } else {
                $negatedIgnores[] = $pattern;
            }
        }

        if ($strictOrderComparisonEnabled === false) {
            \sort($negatedIgnores, SORT_STRING | SORT_FLAG_CASE);
        }

        return \array_unique($negatedIgnores);
    }

    public function hasCompleteExportIgnores(): bool
    {
        if ($this->hasGitattributesFile() === false) {
            return false;
        }

        $content = $this->gitattributesFileRepository->getGitattributesContent();

        if (\preg_match("/(\*\h*)(text\h*)(=\h*auto)/", $content)) {
            $this->hasTextAutoconfiguration = true;
        }

        $presentNegatedExportIgnores = $this->getPresentExportIgnores(true);

        if ($presentNegatedExportIgnores === []) {
            return false;
        }

        if ($this->isStaleExportIgnoresComparisonEnabled()) {
            $allNegatedIgnores = $this->getPresentExportIgnores(false);
            $staleNegatedIgnores = \array_diff($allNegatedIgnores, $presentNegatedExportIgnores);

            if ($staleNegatedIgnores !== []) {
                return false;
            }
        }

        return $this->hasDoubleStarForDirectories($presentNegatedExportIgnores);
    }
}
