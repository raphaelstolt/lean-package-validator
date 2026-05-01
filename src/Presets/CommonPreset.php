<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Presets;

class CommonPreset
{
    private array $aiGlob = [
        'AGENT.md',
        'AGENTS.md',
        'CLAUDE.md',
        'GEMINI.md',
        'AI.md',
        'AIDER.md',
        'CURSOR.md',
        'COPILOT.md',
        'CODEX.md',
        'QWEN.md',
        'WINDSURF.md',
        '.aiassistant',
        '.aider*',
        '.cursor',
        '.cursor/**',
        '.github/copilot-instructions.md',
        '.windsurf',
        '.windsurf/**',
        '.claude',
        '.claude/**',
        '.gemini',
        '.gemini/**',
        '.codex',
        '.codex/**',
        'llms.txt',
        'llms-full.txt',
    ];

    protected function getCommonGlob(): array
    {
        return array_merge($this->aiGlob, [
            '.*',
            '*.txt',
            '*.{md,MD}',
            '*.rst',
            '*.toml',
            '*.xml',
            '*.yml',
            '*.dist.*',
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
        ]);
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
