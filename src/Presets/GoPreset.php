<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Presets;

use Stolt\LeanPackage\Preset;

final class GoPreset extends CommonPreset implements Preset
{
    public function getPresetGlob(): array
    {
        return \array_unique(\array_merge($this->getCommonGlob(), [
            'go.*',
            'cmd/**',
            'pkg/**',
            'internal/**',
            '*.go',
            '*_test.go',
        ]));
    }
}
