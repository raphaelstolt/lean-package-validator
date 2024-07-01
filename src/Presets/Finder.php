<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Presets;

use Stolt\LeanPackage\Exceptions\PresetNotAvailable;
use Stolt\LeanPackage\Preset;

class Finder
{
    const PRESET_SUFFIX = 'Preset';

    private Preset $defaultPreset;

    public function __construct(Preset $defaultPreset)
    {
        $this->defaultPreset = $defaultPreset;
    }

    /**
     * @return array
     */
    public function getAvailablePresets(): array
    {
        $dir = new \DirectoryIterator(\dirname(__FILE__));
        $availablePresets = [];
        foreach ($dir as $fileinfo) {
            if (!$fileinfo->isDot()) {
                $presetsParts = \explode(self::PRESET_SUFFIX, $fileinfo->getBasename());
                if (\count($presetsParts) == 2) {
                    $availablePresets[] = $presetsParts[0];
                }
            }
        }

        return $availablePresets;
    }

    /**
     * @param string $name
     * @throws PresetNotAvailable
     * @return array
     */
    public function getPresetGlobByLanguageName(string $name): array
    {
        $name = \ucfirst(\strtolower($name));

        if (!\in_array($name, $this->getAvailablePresets())) {
            throw new PresetNotAvailable('Preset for Kotlin not available. Maybe contribute it?.');
        }

        $presetClassName = \sprintf('Stolt\LeanPackage\Presets\%sPreset', $name);

        /** @var Preset $preset **/
        $preset = new $presetClassName();

        return $preset->getPresetGlob();
    }

    /**
     * Returns the default Preset glob array
     *
     * @return array
     */
    public function getDefaultPreset(): array
    {
        return $this->defaultPreset->getPresetGlob();
    }
}
