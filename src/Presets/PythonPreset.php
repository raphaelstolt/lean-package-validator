<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Presets;

use Stolt\LeanPackage\Preset;

final class PythonPreset extends CommonPreset implements Preset
{
    public function getPresetGlob(): array
    {
        return \array_unique(\array_merge($this->getCommonGlob(), [
            '*.py[cod]',
            'setup.*',
            'requirements*.txt',
            'Pipfile',
            'Pipfile.lock',
        ]));
    }
}
