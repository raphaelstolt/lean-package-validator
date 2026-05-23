<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Gitattributes;

final class ValueObject
{
    private string $content;

    private bool $usesNegatedExportIgnores = false;

    private bool $usesClassicExportIgnores = false;

    public function __construct(string $content)
    {
        $this->content = $content;
        $this->usesNegatedExportIgnores = $this->usesNegatedExportIgnores();
        $this->usesClassicExportIgnores = $this->usesClassicExportIgnores();
    }

    public static function fromString(string $content): self
    {
        return new self($content);
    }

    public static function fromFile(string $path): self
    {
        return new self(\file_get_contents($path));
    }

    private function usesNegatedExportIgnores(): bool
    {
        $lines = \preg_split('/\\r\\n|\\r|\\n/', $this->content) ?: [];

        foreach ($lines as $line) {
            if (\trim($line) === '* export-ignore') {
                return true;
            }
        }

        return false;
    }

    private function usesClassicExportIgnores(): bool
    {
        $lines = \preg_split('/\\r\\n|\\r|\\n/', $this->content) ?: [];

        foreach ($lines as $line) {
            $trimmedLine = \trim($line);

            if (\str_starts_with($trimmedLine, '#')) {
                continue;
            }

            if (
                \str_contains($trimmedLine, 'export-ignore')
                && !\str_contains($trimmedLine, '-export-ignore')
                && $trimmedLine !== '* export-ignore'
            ) {
                return true;
            }
        }

        return false;
    }

    public function hasClassicExportIgnores(): bool
    {
        return $this->usesClassicExportIgnores;
    }

    public function hasNegatedExportIgnores(): bool
    {
        return $this->usesNegatedExportIgnores;
    }

    public function getContent(): string
    {
        return $this->content;
    }
}
