<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Tests\Presets;

use Stolt\LeanPackage\Exceptions\PresetNotAvailable;
use Stolt\LeanPackage\Presets\Finder;
use Stolt\LeanPackage\Presets\PhpPreset;
use Stolt\LeanPackage\Tests\TestCase;

class FinderTest extends TestCase
{
    /**
     * @test
     */
    public function findsExpectedPresets(): void
    {
        $finder = new Finder(new PhpPreset());
        $this->assertSame(['Go', 'Php', 'Python'], $finder->getAvailablePresets());
    }

    /**
     * @test
     * @dataProvider languageProvider
     */
    public function findsExpectedPresetGlobByLanguageNames($languageName): void
    {
        $finder = new Finder(new PhpPreset());
        $presetGlob = $finder->getPresetGlobByLanguageName($languageName);
        $this->assertTrue(\is_array($presetGlob));
    }

    /**
     * @test
     */
    public function forNonAvailableLanguagePresetItThrowsExpectedException(): void
    {
        $this->expectException(PresetNotAvailable::class);
        $expectedExceptionMessage ='Preset for Kotlin not available. Maybe contribute it?.';
        $this->expectExceptionMessage($expectedExceptionMessage);

        $finder = new Finder(new PhpPreset());
        $finder->getPresetGlobByLanguageName('Kotlin');
    }

    /**
     * @return array
     */
    public static function languageProvider(): array
    {
        return [
            ['php'],
            ['Python'],
            ['Go']
        ];
    }

}
