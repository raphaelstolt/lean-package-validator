<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Presets;

class CommonPreset
{
    protected function getCommonGlob(): array
    {
        return [
            '.*',
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

    /**
     * @param array $presets
     * @return string
     */
    public function formatAvailablePresetDefinitionsForDescription(array $presets): string
    {
        $presets = \array_map(static function ($preset) {
            return '<comment>' . $preset . '</comment>';
        }, $presets);

        if (\count($presets)  > 2) {
            $lastPreset = \array_pop($presets);
            return \implode(', ', $presets) . ', and ' . $lastPreset;
        }

        return $presets[0] . ' and ' . $presets[1];
    }
}
