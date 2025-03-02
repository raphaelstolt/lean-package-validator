<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Tests\Helpers;

use Stolt\LeanPackage\Helpers\InputReader;

class FakeInputReader implements InputReader
{
    private string $input = '';

    public function set(string $input): void
    {
        $this->input = $input;
    }
    public function get(): string|false
    {
        return $this->input;
    }
}
