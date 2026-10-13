<?php

declare(strict_types=1);

namespace Stolt\LeanPackage;

use Stolt\LeanPackage\Exceptions\GitHeadNotAvailable;
use Stolt\LeanPackage\Exceptions\GitNotAvailable;

class ImpactReport
{
    /**
     * Estimated number of downloads per month for a typical package.
     */
    private const ESTIMATED_DOWNLOADS = 100_000;

    /**
     * Average grams of CO₂ per MB of data transferred (based on industry averages).
     * Reference: ~0.2-0.3g CO₂ per MB for internet data transfer.
     */
    private const CO2_PER_MB_GRAMS = 0.25;

    /**
     * @var string
     */
    private string $directory;

    /**
     * @var string
     */
    private string $originalArchiveFilename;

    /**
     * @var string
     */
    private string $optimizedArchiveFilename;

    /**
     * Path to the .gitattributes file.
     *
     * @var string
     */
    private string $gitattributesPath;

    /**
     * Initialize.
     *
     * @param string $directory The directory of the repository to archive.
     */
    public function __construct(string $directory)
    {
        $this->directory = $directory;
        $this->originalArchiveFilename = $directory . DIRECTORY_SEPARATOR . 'lpv-original.tar.gz';
        $this->optimizedArchiveFilename = $directory . DIRECTORY_SEPARATOR . 'lpv-optimized.tar.gz';
        $this->gitattributesPath = $directory . DIRECTORY_SEPARATOR . '.gitattributes';
    }

    /**
     * Create the original archive (without .gitattributes filtering).
     * Creates a temporary Git tree without .gitattributes and archives it.
     *
     * @throws GitNotAvailable
     * @throws GitHeadNotAvailable
     * @return bool
     */
    public function createOriginalArchive(): bool
    {
        if (!$this->isGitCommandAvailable()) {
            throw new GitNotAvailable('The Git command is not available.');
        }

        if (!$this->hasHead()) {
            throw new GitHeadNotAvailable('No Git HEAD present to create an archive from.');
        }

        // Save the current index state so we can restore it later
        $indexFile = $this->directory . DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR . 'index';
        $indexBackup = $this->directory . DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR . 'index.lpv-backup';

        try {
            // Copy the current index to a backup
            if (\file_exists($indexFile)) {
                \copy($indexFile, $indexBackup);
            }

            // Read HEAD into the index, then remove .gitattributes to create a tree without it
            $readTreeCommand = \sprintf('cd %s && git read-tree HEAD 2>&1', \escapeshellarg($this->directory));
            \exec($readTreeCommand, $output, $returnValue);

            // Remove .gitattributes from the index (cached only, doesn't touch working directory)
            $rmCachedCommand = \sprintf('cd %s && git rm --cached .gitattributes 2>&1', \escapeshellarg($this->directory));
            \exec($rmCachedCommand, $output, $returnValue);

            // Write a new tree object without .gitattributes
            $writeTreeCommand = \sprintf('cd %s && git write-tree 2>&1', \escapeshellarg($this->directory));
            $treeHash = \trim(\shell_exec($writeTreeCommand));

            if ($treeHash === '' || $treeHash === false) {
                return false;
            }

            // Archive the tree without .gitattributes
            $archiveCommand = \sprintf(
                'cd %s && git archive -o %s %s 2>&1',
                \escapeshellarg($this->directory),
                \escapeshellarg($this->originalArchiveFilename),
                \escapeshellarg($treeHash)
            );
            \exec($archiveCommand, $output, $returnValue);

            return $returnValue === 0;
        } finally {
            // Restore the original index state
            if (\file_exists($indexBackup)) {
                \copy($indexBackup, $indexFile);
                \unlink($indexBackup);
            }
        }
    }

    /**
     * Create the optimized archive (respecting .gitattributes export-ignore).
     *
     * @throws GitNotAvailable
     * @throws GitHeadNotAvailable
     * @return bool
     */
    public function createOptimizedArchive(): bool
    {
        if (!$this->isGitCommandAvailable()) {
            throw new GitNotAvailable('The Git command is not available.');
        }

        if (!$this->hasHead()) {
            throw new GitHeadNotAvailable('No Git HEAD present to create an archive from.');
        }

        // Git archive respects .gitattributes export-ignore by default
        $command = 'git archive -o ' . $this->optimizedArchiveFilename . ' HEAD 2>&1';
        \exec($command, $output, $returnValue);

        return $returnValue === 0;
    }

    /**
     * Get the size of the original archive in bytes.
     *
     * @return int
     */
    public function getOriginalArchiveSize(): int
    {
        if (\file_exists($this->originalArchiveFilename)) {
            return (int) \filesize($this->originalArchiveFilename);
        }

        return 0;
    }

    /**
     * Get the size of the optimized archive in bytes.
     *
     * @return int
     */
    public function getOptimizedArchiveSize(): int
    {
        if (\file_exists($this->optimizedArchiveFilename)) {
            return (int) \filesize($this->optimizedArchiveFilename);
        }

        return 0;
    }

    /**
     * Get the size reduction percentage.
     *
     * @return float
     */
    public function getSizeReductionPercentage(): float
    {
        $original = $this->getOriginalArchiveSize();
        $optimized = $this->getOptimizedArchiveSize();

        if ($original === 0) {
            return 0.0;
        }

        return (float) \number_format((($original - $optimized) / $original) * 100, 1);
    }

    /**
     * Get the data saved per download in bytes.
     *
     * @return int
     */
    public function getDataSavedBytes(): int
    {
        return $this->getOriginalArchiveSize() - $this->getOptimizedArchiveSize();
    }

    /**
     * Get the estimated total data saved per month in bytes.
     *
     * @return int
     */
    public function getEstimatedDataSavedBytes(): int
    {
        return $this->getDataSavedBytes() * self::ESTIMATED_DOWNLOADS;
    }

    /**
     * Get the estimated CO₂ emission avoided per month in grams.
     *
     * @return float
     */
    public function getEstimatedCo2Avoided(): float
    {
        $dataSavedMb = $this->getEstimatedDataSavedMb();
        return \round($dataSavedMb * self::CO2_PER_MB_GRAMS, 2);
    }

    /**
     * Get the estimated total data saved per month in MB.
     *
     * @return float
     */
    public function getEstimatedDataSavedMb(): float
    {
        return $this->getEstimatedDataSavedBytes() / (1024 * 1024);
    }

    /**
     * Format bytes to a human-readable string.
     *
     * @param int $bytes
     * @return string
     */
    public static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1024 * 1024) {
            return \round($bytes / 1024, 2) . ' KB';
        }

        if ($bytes < 1024 * 1024 * 1024) {
            return \round($bytes / (1024 * 1024), 2) . ' MB';
        }

        return \round($bytes / (1024 * 1024 * 1024), 2) . ' GB';
    }

    /**
     * Format bytes to MB with 2 decimal places.
     *
     * @param int $bytes
     * @return string
     */
    public static function formatBytesToMb(int $bytes): string
    {
        return \number_format($bytes / (1024 * 1024), 2) . ' MB';
    }

    /**
     * Format bytes to GB with 0 decimal places for large values.
     *
     * @param int $bytes
     * @return string
     */
    public static function formatBytesToGb(int $bytes): string
    {
        $gb = $bytes / (1024 * 1024 * 1024);
        if ($gb >= 1) {
            return \number_format($gb, 0) . ' GB';
        }

        // If less than 1 GB, show in MB
        return self::formatBytesToMb($bytes);
    }

    /**
     * Format CO₂ value with appropriate unit.
     *
     * @param float $grams
     * @return string
     */
    public static function formatCo2(float $grams): string
    {
        if ($grams >= 1000) {
            return \number_format($grams / 1000, 2) . ' kg CO₂';
        }

        return \number_format($grams, 2) . ' g CO₂';
    }

    /**
     * Generate the formatted impact report string.
     *
     * @return string
     */
    public function generateReport(): string
    {
        $originalSize = self::formatBytesToMb($this->getOriginalArchiveSize());
        $optimizedSize = self::formatBytesToMb($this->getOptimizedArchiveSize());
        $reduction = \number_format($this->getSizeReductionPercentage(), 1) . '%';
        $dataSaved = self::formatBytesToMb($this->getDataSavedBytes());
        $estimatedDataSaved = self::formatBytesToGb($this->getEstimatedDataSavedBytes());
        $co2Avoided = self::formatCo2($this->getEstimatedCo2Avoided());

        // Label column width for alignment
        $labelWidth = 32;

        $report = "Distribution impact" . PHP_EOL;
        $report .= \str_repeat('─', $labelWidth) . PHP_EOL;
        $report .= \str_pad('Original archive:', $labelWidth) . ' ' . $originalSize . PHP_EOL;
        $report .= \str_pad('Optimized archive:', $labelWidth) . ' ' . $optimizedSize . PHP_EOL;
        $report .= \str_pad('Size reduction:', $labelWidth) . ' ' . $reduction . PHP_EOL;
        $report .= \str_pad('Data saved/download:', $labelWidth) . ' ' . $dataSaved . PHP_EOL;
        $report .= \str_pad('Estimated data saved:', $labelWidth) . ' ' . $estimatedDataSaved . PHP_EOL;
        $report .= \str_pad('Estimated CO₂ emission avoided:', $labelWidth) . ' ' . $co2Avoided . PHP_EOL;

        return $report;
    }

    /**
     * Remove temporary archives.
     *
     * @return void
     */
    public function removeArchives(): void
    {
        if (\file_exists($this->originalArchiveFilename)) {
            \unlink($this->originalArchiveFilename);
        }
        if (\file_exists($this->optimizedArchiveFilename)) {
            \unlink($this->optimizedArchiveFilename);
        }
    }

    /**
     * Check if Git is available.
     *
     * @return bool
     */
    private function isGitCommandAvailable(): bool
    {
        \exec('where git 2>&1', $output, $returnValue);
        if ((new Helpers\Str())->isWindows() === false) {
            \exec('which git 2>&1', $output, $returnValue);
        }

        return $returnValue === 0;
    }

    /**
     * Check if the repository has a HEAD.
     *
     * @return bool
     */
    private function hasHead(): bool
    {
        \exec('git show-ref --head 2>&1', $output, $returnValue);
        return $returnValue === 0;
    }
}
