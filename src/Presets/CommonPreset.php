<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Presets;

class CommonPreset
{
    protected function getCommonGlob(): array
    {
        return [
            '.*[!ai]',
            '*.txt',
            '*.{md,MD}',
            '*.rst',
            '*.toml',
            '*.xml',
            '*.yml',
            '*.dist.*',
            'llms.*',
            '.githooks',
            '*.dist',
            '{B,b}uild*',
            '{D,d}ist',
            '{D,d}oc*',
            '{A,a}rt*',
            '{A,a}sset*',
            '{T,t}ool*',
            '{T,t}est*',
            '{S,s}pec*',
            '{E,e}xample*',
            'LICENSE',
            '{M,m}ake',
            '*.{png,gif,jpeg,jpg,webp}',
        ];
    }
}
