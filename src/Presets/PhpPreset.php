<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Presets;

use Stolt\LeanPackage\Preset;

final class PhpPreset implements Preset
{
    public function getPresetGlob(): array
    {
        return [
            '.*',
            '*.lock',
            '*.txt',
            '*.rst',
            '*.{md,MD}',
            '*.xml',
            '*.yml',
            'phpunit*',
            'appveyor.yml',
            'box.json',
            'captainhook.json',
            'infection*',
            'phpstan*',
            'sonar*',
            'rector*',
            'pint.json',
            'ecs*',
            '*.dist.*',
            '*.dist',
            '{B,b}uild*',
            '{D,d}oc*',
            '{T,t}ool*',
            '{T,t}est*',
            '{S,s}pec*',
            '{E,e}xample*',
            'LICENSE',
            '{{M,m}ake,{B,b}ox,{V,v}agrant,{P,p}hulp}file',
            'RMT'
        ];
    }
}
