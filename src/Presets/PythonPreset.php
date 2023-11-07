<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Presets;

use Stolt\LeanPackage\Preset;

final class PythonPreset implements Preset
{
    public function getPresetGlob(): array
    {
        return [
            '.*',
            '*.txt',
            '*.rst',
            '*.py[cod]',
            '*.{md,MD}',
            '*.xml',
            '*.yml',
            '*.dist.*',
            '*.dist',
            '{B,b}uild*',
            '{D,d}oc*',
            '{D,d}ist',
            '{T,t}ool*',
            '{T,t}est*',
            '{S,s}pec*',
            '{E,e}xample*',
            'LICENSE',
            '{{M,m}ake'
        ];
    }
}
