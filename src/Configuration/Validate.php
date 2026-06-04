<?php declare(strict_types=1);

namespace Stolt\LeanPackage\Configuration;

final readonly class Validate
{
    public function __construct(
        public string $directory,
        public bool $dryRun,
        public bool $agenticRun,
    ) {
    }
}
