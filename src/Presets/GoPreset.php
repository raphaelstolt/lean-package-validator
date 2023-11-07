<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Presets;

use Stolt\LeanPackage\Preset;

final class GoPreset implements Preset
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
            '*.dist.*',
            '*.dist',
            '{B,b}uild*',
            '{D,d}oc*',
            '{T,t}ool*',
            '{T,t}est*',
            '{S,s}pec*',
            '{E,e}xample*',
            'LICENSE',
            '{{M,m}ake,{V,v}agrant'
        ];
    }
}
