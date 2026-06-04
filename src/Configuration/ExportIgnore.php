<?php declare(strict_types=1);

namespace Stolt\LeanPackage\Configuration;

final readonly class ExportIgnore
{
    public function __construct(
        public string $flavour,
    ) {
    }
}
