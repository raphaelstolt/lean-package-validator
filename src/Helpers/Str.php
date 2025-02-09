<?php

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
     * Check if the operating system is macish.
     *
     * @param string $os
     *
     * @return boolean
     */
    public function isMacOs(string $os = PHP_OS): bool
    {
        if (\strtoupper(\substr($os, 0, 3)) !== 'DAR') {
            return false;
        }

        return true;
    }
}
