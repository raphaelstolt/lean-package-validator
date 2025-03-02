<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Helpers;

interface InputReader
{
    public function get(): string|false;
}
