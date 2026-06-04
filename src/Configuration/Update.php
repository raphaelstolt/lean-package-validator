<?php declare(strict_types=1);

namespace Stolt\LeanPackage\Configuration;

final readonly class Update
{
    public function __construct(
        public string $directory,
        public bool $reformatExportIgnores,
        public bool $migrateToNegatedExportIgnores,
        public bool $group,
        public bool $dryRun,
        public bool $agenticRun,
    ) {
    }
}
