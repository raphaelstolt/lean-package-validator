<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Presets;

use Stolt\LeanPackage\Preset;

final class RustPreset extends CommonPreset implements Preset
{
    public function getPresetGlob(): array
    {
        return \array_unique(\array_merge($this->getCommonGlob(), [
            'benches/**',
            '.rustfmt.toml',
            '.clippy.toml',
        ]));
    }
}
