<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Tests\Presets;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Stolt\LeanPackage\Exceptions\PresetNotAvailable;
use Stolt\LeanPackage\Presets\Finder;
use Stolt\LeanPackage\Presets\PhpPreset;
use Stolt\LeanPackage\Tests\TestCase;

class FinderTest extends TestCase
{
    #[Test]
    public function findsExpectedPresets(): void
    {
        $finder = new Finder(new PhpPreset());

        $actualPresets = $finder->getAvailablePresets();
        $expectedPresets = ['Php', 'Go', 'Python'];

        \sort($actualPresets);
        \sort($expectedPresets);

        $this->assertSame($expectedPresets, $actualPresets);
    }

    #[Test]
    #[DataProvider('languageProvider')]
    public function findsExpectedPresetGlobByLanguageNames(string $languageName): void
    {
        $finder = new Finder(new PhpPreset());
        $presetGlob = $finder->getPresetGlobByLanguageName($languageName);
        $this->assertTrue(\is_array($presetGlob));
    }

    #[Test]
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
