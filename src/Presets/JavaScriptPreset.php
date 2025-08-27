<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Presets;

use Stolt\LeanPackage\Preset;

final class JavaScriptPreset extends CommonPreset implements Preset
{
    public function getPresetGlob(): array
    {
        return \array_unique(\array_merge($this->getCommonGlob(), [
            'package.json',
            'package-lock.json',
            'yarn.lock',
            'pnpm-lock.yaml',
            'bun.lockb',
            '__tests__/**',
            '*.{test,spec}.{js,ts,jsx,tsx}',
            'tsconfig.json',
            'tsconfig.*.json',
            '.eslintrc*',
            '.prettierrc*',
            '.babelrc*',
            'vite.config.*',
            'webpack.config.*',
            'rollup.config.*',
            'jest.config.*',
        ]));
    }
}
