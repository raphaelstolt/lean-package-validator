<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Helpers;

interface InputReaderInterface
{
    public function get(): string|false;
}
