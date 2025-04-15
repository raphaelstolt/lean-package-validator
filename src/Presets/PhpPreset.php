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
            '*.{png,gif,jpeg,jpg,webp}',
            '*.xml',
            '*.yml',
            '*.toml',
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
            'package*',
            'pint.json',
            'renovate.json',
            '*debugbar.json',
            'ecs*',
            '*.dist.*',
            '*.dist',
            '{B,b}uild*',
            '{D,d}oc*',
            '{T,t}ool*',
            '{T,t}est*',
            '{S,s}pec*',
            '{A,a}rt*',
            '{A,a}sset*',
            '{E,e}xample*',
            'LICENSE',
            '{{M,m}ake,{B,b}ox,{V,v}agrant,{P,p}hulp}file',
            'RMT'
        ];
    }
}
