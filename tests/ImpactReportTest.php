<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Tests;

use PHPUnit\Framework\Attributes\Test;
use Stolt\LeanPackage\ImpactReport;

class ImpactReportTest extends TestCase
{
    #[Test]
    public function formatBytesReturnsBytesForSmallValues(): void
    {
        $this->assertSame('500 B', ImpactReport::formatBytes(500));
    }

    #[Test]
    public function formatBytesReturnsKbForMediumValues(): void
    {
        $this->assertSame('1.5 KB', ImpactReport::formatBytes(1536));
    }

    #[Test]
    public function formatBytesReturnsMbForLargeValues(): void
    {
        $this->assertSame('2.4 MB', ImpactReport::formatBytes(2_516_582));
    }

    #[Test]
    public function formatBytesReturnsGbForVeryLargeValues(): void
    {
        $this->assertSame('1.5 GB', ImpactReport::formatBytes(1_610_612_736));
    }

    #[Test]
    public function formatBytesToMbFormatsCorrectly(): void
    {
        $this->assertSame('2.40 MB', ImpactReport::formatBytesToMb(2_516_582));
    }

    #[Test]
    public function formatBytesToGbFormatsGbForLargeValues(): void
    {
        $this->assertSame('80 GB', ImpactReport::formatBytesToGb(85_899_345_920));
    }

    #[Test]
    public function formatBytesToGbFormatsMbForSmallValues(): void
    {
        $this->assertSame('8.00 MB', ImpactReport::formatBytesToGb(8_388_608));
    }

    #[Test]
    public function formatCo2ReturnsGramsForSmallValues(): void
    {
        $this->assertSame('200.00 g CO₂', ImpactReport::formatCo2(200.0));
    }

    #[Test]
    public function formatCo2ReturnsKgForLargeValues(): void
    {
        $this->assertSame('1.50 kg CO₂', ImpactReport::formatCo2(1500.0));
    }

    #[Test]
    public function sizeReductionPercentageIsZeroWhenNoArchives(): void
    {
        $this->setUpTemporaryDirectory();
        $report = new ImpactReport($this->temporaryDirectory);

        $this->assertSame(0.0, $report->getSizeReductionPercentage());
    }

    #[Test]
    public function dataSavedBytesIsZeroWhenNoArchives(): void
    {
        $this->setUpTemporaryDirectory();
        $report = new ImpactReport($this->temporaryDirectory);

        $this->assertSame(0, $report->getDataSavedBytes());
    }

    #[Test]
    public function estimatedDataSavedBytesScalesByDownloads(): void
    {
        $this->setUpTemporaryDirectory();
        $report = new ImpactReport($this->temporaryDirectory);

        // With no archives, data saved is 0, so estimated is also 0
        $this->assertSame(0, $report->getEstimatedDataSavedBytes());
    }

    #[Test]
    public function estimatedCo2AvoidedIsZeroWhenNoArchives(): void
    {
        $this->setUpTemporaryDirectory();
        $report = new ImpactReport($this->temporaryDirectory);

        $this->assertSame(0.0, $report->getEstimatedCo2Avoided());
    }

    #[Test]
    public function generateReportReturnsNonEmptyString(): void
    {
        $this->setUpTemporaryDirectory();
        $report = new ImpactReport($this->temporaryDirectory);

        $result = $report->generateReport();

        $this->assertStringContainsString('Distribution impact', $result);
        $this->assertStringContainsString('Original archive:', $result);
        $this->assertStringContainsString('Optimized archive:', $result);
        $this->assertStringContainsString('Size reduction:', $result);
        $this->assertStringContainsString('Data saved/download:', $result);
        $this->assertStringContainsString('Estimated data saved:', $result);
        $this->assertStringContainsString('Estimated CO₂ emission avoided:', $result);
    }

    protected function tearDown(): void
    {
        if (isset($this->temporaryDirectory) && \is_dir($this->temporaryDirectory)) {
            $this->removeDirectory($this->temporaryDirectory);
        }
    }
}
