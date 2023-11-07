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
    public function isWindows($os = PHP_OS): bool
    {
        if (\strtoupper(\substr($os, 0, 3)) !== 'WIN') {
            return false;
        }

        return true;
    }
}
