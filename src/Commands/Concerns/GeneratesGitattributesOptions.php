<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Commands\Concerns;

use SplFileInfo;
use Stolt\LeanPackage\Analyser;
use Stolt\LeanPackage\Exceptions\InvalidGlobPattern;
use Stolt\LeanPackage\Exceptions\InvalidGlobPatternFile;
use Stolt\LeanPackage\Exceptions\NonExistentGlobPatternFile;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

trait GeneratesGitattributesOptions
{
    protected string $defaultPreset = 'Php';

    protected function getDefaultLpvFile(): string
    {
        $base = \defined('WORKING_DIRECTORY') ? WORKING_DIRECTORY : (\getcwd() ?: '.');

        return $base . DIRECTORY_SEPARATOR . '.lpv';
    }

    protected function addGenerationOptions(callable $addOption): void
    {
        // Glob/preset related
        $addOption('glob-pattern', null, InputOption::VALUE_REQUIRED, 'Use this glob pattern to match artifacts that should be export-ignored');
        $addOption('glob-pattern-file', null, InputOption::VALUE_OPTIONAL, 'Use this file with glob patterns to match export-ignored artifacts', $this->getDefaultLpvFile());
        $addOption('preset', null, InputOption::VALUE_OPTIONAL, 'Use the glob pattern of the given language preset', $this->defaultPreset);

        // Keep rules
        $addOption('keep-license', null, InputOption::VALUE_NONE, 'Do not export-ignore the license file');
        $addOption('keep-readme', null, InputOption::VALUE_NONE, 'Do not export-ignore the README file');
        $addOption('keep-glob-pattern', null, InputOption::VALUE_REQUIRED, 'Do not export-ignore matching glob pattern e.g. {LICENSE.*,README.*,docs*}');

        // Layout/ordering
        $addOption('align-export-ignores', 'a', InputOption::VALUE_NONE, 'Align export-ignores on create or overwrite');
        $addOption('sort-from-directories-to-files', 's', InputOption::VALUE_NONE, 'Sort export-ignores from directories to files');
        $addOption('enforce-strict-order', null, InputOption::VALUE_NONE, 'Enforce strict order comparison (useful for consistency)');
    }

    /**
     * Apply generation-related options to the analyser.
     */
    protected function applyGenerationOptions(InputInterface $input, OutputInterface $output, Analyser $analyser): bool
    {
        $globPattern = $input->getOption('glob-pattern');
        $globPatternFile = (string) $input->getOption('glob-pattern-file');
        $chosenPreset = (string) $input->getOption('preset');

        $keepLicense = (bool) $input->getOption('keep-license');
        $keepReadme = (bool) $input->getOption('keep-readme');
        $keepGlobPattern = (string) ($input->getOption('keep-glob-pattern') ?? '');

        $alignExportIgnores = (bool) $input->getOption('align-export-ignores');
        $sortFromDirectoriesToFiles = (bool) $input->getOption('sort-from-directories-to-files');
        $enforceStrictOrderComparison = (bool) $input->getOption('enforce-strict-order');

        // Order comparison (for consistency in generation/validation flow)
        if ($enforceStrictOrderComparison && $sortFromDirectoriesToFiles === false) {
            $output->writeln('+ Enforcing strict order comparison.', OutputInterface::VERBOSITY_VERBOSE);
            $analyser->enableStrictOrderComparison();
        }

        if ($sortFromDirectoriesToFiles) {
            $output->writeln('+ Sorting from files to directories.', OutputInterface::VERBOSITY_VERBOSE);
            $analyser->sortFromDirectoriesToFiles();
        }

        if ($keepLicense) {
            $output->writeln('+ Keeping the license file.', OutputInterface::VERBOSITY_VERBOSE);
            $analyser->keepLicense();
        }

        if ($keepReadme) {
            $output->writeln('+ Keeping the README file.', OutputInterface::VERBOSITY_VERBOSE);
            $analyser->keepReadme();
        }

        if ($keepGlobPattern !== '') {
            $output->writeln(\sprintf('+ Keeping files matching the glob pattern <info>%s</info>.', $keepGlobPattern), OutputInterface::VERBOSITY_VERBOSE);
            try {
                $analyser->setKeepGlobPattern($keepGlobPattern);
            } catch (InvalidGlobPattern $e) {
                $warning = "Warning: The provided glob pattern '{$keepGlobPattern}' is considered invalid.";
                $output->writeln('<error>' . $warning . '</error>');
                $output->writeln($e->getMessage(), OutputInterface::VERBOSITY_DEBUG);

                return false;
            }
        }

        if ($alignExportIgnores) {
            $output->writeln('+ Aligning the export-ignores.', OutputInterface::VERBOSITY_VERBOSE);
            $analyser->alignExportIgnores();
        }

        // Glob selection/override order: explicit pattern -> glob file -> preset
        if ($globPattern || $globPattern === '') {
            try {
                $output->writeln("+ Using glob pattern <info>{$globPattern}</info>.", OutputInterface::VERBOSITY_VERBOSE);
                $analyser->setGlobPattern((string) $globPattern);
            } catch (InvalidGlobPattern $e) {
                $warning = "Warning: The provided glob pattern '{$globPattern}' is considered invalid.";
                $output->writeln('<error>' . $warning . '</error>');
                $output->writeln($e->getMessage(), OutputInterface::VERBOSITY_DEBUG);

                return false;
            }
        } elseif ($this->isGlobPatternFileSettable($globPatternFile)) {
            try {
                if ($this->isDefaultGlobPatternFilePresent()) {
                    $analyser->setGlobPatternFromFile($globPatternFile);
                }
                if ($globPatternFile) {
                    $globPatternFileInfo = new SplFileInfo($globPatternFile);
                    $output->writeln('+ Using ' . $globPatternFileInfo->getBasename() . ' file as glob pattern input.', OutputInterface::VERBOSITY_VERBOSE);

                    $analyser->setGlobPatternFromFile($globPatternFile);
                }
            } catch (NonExistentGlobPatternFile $e) {
                $warning = "Warning: The provided glob pattern file '{$globPatternFile}' doesn't exist.";
                $output->writeln('<error>' . $warning . '</error>');
                $output->writeln($e->getMessage(), OutputInterface::VERBOSITY_DEBUG);

                return false;
            } catch (InvalidGlobPatternFile $e) {
                $warning = "Warning: The provided glob pattern file '{$globPatternFile}' is considered invalid.";
                $output->writeln('<error>' . $warning . '</error>');
                $output->writeln($e->getMessage(), OutputInterface::VERBOSITY_DEBUG);

                return false;
            }
        } elseif ($chosenPreset) {
            try {
                $output->writeln('+ Using the ' . $chosenPreset . ' language preset.', OutputInterface::VERBOSITY_VERBOSE);
                $analyser->setGlobPatternFromPreset($chosenPreset);
            } catch (\Stolt\LeanPackage\Exceptions\PresetNotAvailable $e) {
                $warning = 'Warning: The chosen language preset ' . $chosenPreset . ' is not available. Maybe contribute it?.';
                $output->writeln('<error>' . $warning . '</error>');

                return false;
            }
        }

        return true;
    }

    // Minimal copies of helper checks used in ValidateCommand
    protected function isGlobPatternFileSettable(string $file): bool
    {
        if ($this->isGlobPatternFileProvided($file)) {
            return true;
        }

        return $this->isDefaultGlobPatternFilePresent();
    }

    protected function isGlobPatternFileProvided(string $file): bool
    {
        return $file !== $this->getDefaultLpvFile();
    }

    protected function isDefaultGlobPatternFilePresent(): bool
    {
        return \file_exists($this->getDefaultLpvFile());
    }
}
