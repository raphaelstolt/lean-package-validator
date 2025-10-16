<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Helpers;

use Stolt\LeanPackage\Helpers\InputReaderInterface;

class PhpInputReader implements InputReaderInterface
{
    public function get(): string|false
    {
        return \file_get_contents('php://stdin');
    }
}
