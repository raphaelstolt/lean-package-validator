<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Tests\Helpers;

use Stolt\LeanPackage\Helpers\InputReaderInterface;

class FakeInputReader implements InputReaderInterface
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
