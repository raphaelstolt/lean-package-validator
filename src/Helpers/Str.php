<?php

declare(strict_types=1);


namespace Stolt\LeanPackage\Helpers;

class Str
{
    /**
     * Check if the operating system is windowsish.
     *
     * @param string $os
     *
     * @return boolean
     */
    public function isWindows(string $os = PHP_OS): bool
    {
        if (\strtoupper(\substr($os, 0, 3)) !== 'WIN') {
            return false;
        }

        return true;
    }

    /**
     * Detect the most frequently used end-of-line sequence.
     *
     * @param string $content The content to detect the eol in.
     * @param string $preferredEol
     * @return string
     */
    public static function detectEol($content, string $preferredEol = PHP_EOL): string
    {
        $maxCount = 0;
        $eols = ["\n", "\r", "\n\r", "\r\n"];

        foreach ($eols as $eol) {
            if (($count = \substr_count($content, $eol)) < $maxCount) {
                continue;
            }

            $maxCount = $count;
            $preferredEol = $eol;
        }

        return $preferredEol;
    }
}
