<?php declare(strict_types=1);

namespace Stolt\LeanPackage\Configuration;

final readonly class Reformat
{
    public function __construct(
        public string $directory,
        public bool $sortAlphabetically,
        public bool $sortFromDirectoriesToFiles,
        public bool $groupContent,
        public bool $dryRun,
        public bool $agenticRun,
    ) {
    }
}
