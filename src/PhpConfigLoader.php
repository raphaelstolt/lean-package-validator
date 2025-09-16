<?php

declare(strict_types=1);

namespace Stolt\LeanPackage;

final class PhpConfigLoader
{
    /**
     * Names searched in the current working directory, first match wins.
     *
     * @var string[]
     */
    private const DEFAULT_FILENAMES = [
        '.lpv.php',
        '.lpv.php.dist',
    ];

    /**
     * Discover a configuration file in the current working directory.
     *
     * @return string|null
     */
    public static function discover(): ?string
    {
        $cwd = \getcwd() ?: '.';

        foreach (self::DEFAULT_FILENAMES as $file) {
            $path = $cwd . DIRECTORY_SEPARATOR . $file;

            if (\is_file($path) && \is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Load a configuration file and validate its structure.
     *
     * Allowed keys (subset relevant to validate command):
     * - directory: string
     * - preset: string
     * - glob-pattern: string
     * - glob-pattern-file: string
     * - stdin-input: bool
     * - diff: bool
     * - report-stale-export-ignores: bool
     * - enforce-strict-order: bool
     * - enforce-alignment: bool
     * - sort-from-directories-to-files: bool
     * - keep-license: bool
     * - keep-readme: bool
     * - keep-glob-pattern: string
     * - align-export-ignores: bool
     *
     * @param string $path
     * @return array<string, mixed>
     */
    public static function load(string $path): array
    {
        /** @var mixed $config */
        $config = (static function (string $__path) {
            /** @noinspection PhpIncludeInspection */
            return require $__path;
        })($path);

        if (!\is_array($config)) {
            throw new \UnexpectedValueException('The configuration file must return an array.');
        }

        $allowed = [
            'directory',
            'preset',
            'glob-pattern',
            'glob-pattern-file',
            'stdin-input',
            'diff',
            'report-stale-export-ignores',
            'enforce-strict-order',
            'enforce-alignment',
            'sort-from-directories-to-files',
            'keep-license',
            'keep-readme',
            'keep-glob-pattern',
            'align-export-ignores',
        ];

        $unknown = \array_diff(\array_keys($config), $allowed);
        if ($unknown !== []) {
            throw new \UnexpectedValueException('Unknown configuration keys: ' . \implode(', ', $unknown));
        }

        // Basic type validation
        $stringKeys = ['directory', 'preset', 'glob-pattern', 'glob-pattern-file', 'keep-glob-pattern'];
        foreach ($stringKeys as $key) {
            if (isset($config[$key]) && !\is_string($config[$key])) {
                throw new \UnexpectedValueException(\sprintf('Configuration "%s" must be a string.', $key));
            }
        }

        $boolKeys = [
            'stdin-input',
            'diff',
            'report-stale-export-ignores',
            'enforce-strict-order',
            'enforce-alignment',
            'sort-from-directories-to-files',
            'keep-license',
            'keep-readme',
            'align-export-ignores',
        ];
        foreach ($boolKeys as $key) {
            if (isset($config[$key]) && !\is_bool($config[$key])) {
                throw new \UnexpectedValueException(\sprintf('Configuration "%s" must be a boolean.', $key));
            }
        }

        return $config;
    }
}
