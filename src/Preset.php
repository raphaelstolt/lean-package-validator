<?php

declare(strict_types=1);

namespace Stolt\LeanPackage;

interface Preset
{
    public function getPresetGlob(): array;
}
