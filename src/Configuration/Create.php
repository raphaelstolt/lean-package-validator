<?php declare(strict_types=1);

namespace Stolt\LeanPackage\Configuration;

final readonly class Create
{
    public function __construct(
        public string $directory,
        public bool $forceOverwrite,
        public string $flavour ,
        public bool $isDryRun,
        public bool $isAgenticRun,
    ) {
    }
}
