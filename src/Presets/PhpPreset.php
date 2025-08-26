<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Presets;

use Stolt\LeanPackage\Preset;

final class PhpPreset extends CommonPreset implements Preset
{
    public function getPresetGlob(): array
    {
        return \array_unique(\array_merge($this->getCommonGlob(), [
            '*.lock',
            'phpunit*',
            'appveyor.yml',
            'box.json',
            'composer-dependency-analyser*',
            'collision-detector*',
            'captainhook.json',
            'peck.json',
            'infection*',
            'phpstan*',
            'sonar*',
            'rector*',
            'phpkg.con*',
            'package*',
            'pint.json',
            'renovate.json',
            '*debugbar.json',
            'ecs*',
            'RMT',
            '{{M,m}ake,{B,b}ox,{V,v}agrant,{P,p}hulp}file'
        ]));
    }
}
