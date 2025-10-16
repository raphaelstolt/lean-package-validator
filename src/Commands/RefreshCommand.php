<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Commands;

use Stolt\LeanPackage\Analyser;
use Stolt\LeanPackage\Commands\Concerns\OutputOptions;
use Stolt\LeanPackage\Exceptions\PresetNotAvailable;
use Stolt\LeanPackage\Presets\CommonPreset;
use Stolt\LeanPackage\Presets\Finder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class RefreshCommand extends Command
{
    use OutputOptions;

    private const DEFAULT_PRESET = 'PHP';

    /**
     * Package analyser.
     *
     * @var Analyser
     */
    protected Analyser $analyser;

    /**
     * @var Finder
     */
    private Finder $finder;

    /**
     * @param Analyser  $analyser
     */
    public function __construct(Analyser $analyser)
    {
        $this->analyser = $analyser;
        $this->finder = $analyser->getFinder();

        parent::__construct();
    }

    /**
     * Command configuration.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this->analyser->setDirectory(WORKING_DIRECTORY);

        $this
            ->setName('refresh')
            ->setDescription('Refresh a present .lpv file');

        $availablePresets = (new CommonPreset())->formatAvailablePresetDefinitionsForDescription(
            $this->finder->getAvailablePresets()
        );

        $directoryDescription = 'The directory of a project/micro-package repository';
        $presetDescription = 'The preset to use for the .lpv file. Available ones are ' . $availablePresets . '.';

        $this->addArgument(
            'directory',
            InputArgument::OPTIONAL,
            $directoryDescription,
            $this->analyser->getDirectory()
        );

        $this->addOption(
            'preset',
            null,
            InputOption::VALUE_REQUIRED,
            $presetDescription,
            self::DEFAULT_PRESET
        );
        $this->addDryRunOutputOption(function (...$args) {
            $this->getDefinition()->addOption(new InputOption(...$args));
        }, 'Do not write any files. Output the content that would be written');
        $this->addAgenticOutputOption(function (...$args) {
            $this->getDefinition()->addOption(new InputOption(...$args));
        });
    }

    /**
     * Execute command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @throws PresetNotAvailable
     * @return integer
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $directory = (string) $input->getArgument('directory');
        $chosenPreset = (string) $input->getOption('preset');
        $isAgenticRun = $this->isAgenticRun($input);

        if ($directory !== '' && $directory !== WORKING_DIRECTORY) {
            try {
                $this->analyser->setDirectory($directory);
            } catch (\RuntimeException $e) {
                $warning = "Warning: The provided directory "
                    . "'{$directory}' does not exist or is not a directory.";
                if ($isAgenticRun) {
                    $this->writeAgenticOutput($output, 'refresh', false, $warning);
                } else {
                    $output->writeln('<error>' . $warning . '</error>');
                }
                $output->writeln($e->getMessage(), OutputInterface::VERBOSITY_DEBUG);

                return Command::FAILURE;
            }
        }

        $defaultLpvFile = WORKING_DIRECTORY . DIRECTORY_SEPARATOR . '.lpv';

        if (!\file_exists($defaultLpvFile) && $this->isDryRun($input) !== true) {
            $warning = 'Warning: No default .lpv file exists to refresh.';
            if ($isAgenticRun) {
                $this->writeAgenticOutput($output, 'refresh', false, $warning);
            } else {
                $output->writeln('<error>' . $warning . '</error>');
            }
            return Command::FAILURE;
        }

        if ($chosenPreset === '' || !\in_array(\strtolower($chosenPreset), \array_map('strtolower', $this->finder->getAvailablePresets()), true)) {
            $warning = 'Warning: Chosen preset ' . $chosenPreset . ' is not available. Maybe contribute it?.';
            if ($isAgenticRun) {
                $this->writeAgenticOutput($output, 'refresh', false, $warning);
            } else {
                $output->writeln('<error>' . $warning . '</error>');
            }
            return Command::FAILURE;
        }

        $expectedGlobPattern = $this->finder->getPresetGlobByLanguageName($chosenPreset);
        $expectedContent = \implode("\n", $expectedGlobPattern);

        $existingContent = '';
        if (\file_exists($defaultLpvFile)) {
            $existingContent = (string) \file_get_contents($defaultLpvFile);
        }

        $existingLines = \array_values(\array_filter(
            \preg_split('/\r\n|\r|\n/', $existingContent) ?: [],
            static fn (string $line): bool => \trim($line) !== ''
        ));

        $mergedLines = $existingLines;
        foreach (\preg_split('/\r\n|\r|\n/', $expectedContent) ?: [] as $line) {
            $line = \trim((string) $line);
            if ($line === '') {
                continue;
            }

            if (!\in_array($line, $mergedLines, true)) {
                $mergedLines[] = $line;
            }
        }

        $refreshedContent = \implode("\n", $mergedLines);

        if ($this->isDryRun($input)) {
            $output->writeln($refreshedContent);

            return Command::SUCCESS;
        }

        $bytesWritten = file_put_contents($defaultLpvFile, $refreshedContent);

        if ($bytesWritten === false) {
            $warning = 'Warning: The refresh of the default .lpv file failed.';
            if ($isAgenticRun) {
                $this->writeAgenticOutput($output, 'refresh', false, $warning);
            } else {
                $output->writeln('<error>' . $warning . '</error>');
            }
            return Command::FAILURE;
        }

        $message = "Refreshed default '{$defaultLpvFile}' file.";
        if ($isAgenticRun) {
            $this->writeAgenticOutput($output, 'refresh', true, $message, ['lpv_file_path' => $defaultLpvFile]);
        } else {
            $output->writeln($message);
        }
        return Command::SUCCESS;
    }
}
