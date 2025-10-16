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

final class InitCommand extends Command
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
        $this->setName('init');
        $description = 'Create a default .lpv file in a given '
            . 'project/micro-package repository';
        $this->setDescription($description);

        $availablePresets = (new CommonPreset())->formatAvailablePresetDefinitionsForDescription(
            $this->finder->getAvailablePresets()
        );

        $directoryDescription = 'The directory of a project/micro-package repository';
        $overwriteDescription = 'Overwrite existing default .lpv file file';
        $presetDescription = 'The preset to use for the .lpv file. Available ones are ' . $availablePresets . '.';

        $this->addArgument(
            'directory',
            InputArgument::OPTIONAL,
            $directoryDescription,
            $this->analyser->getDirectory()
        );
        $this->addOption('overwrite', 'o', InputOption::VALUE_NONE, $overwriteDescription);
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
        $overwriteDefaultLpvFile = $input->getOption('overwrite');
        $chosenPreset = (string) $input->getOption('preset');
        $globPatternFromPreset = false;
        $isAgenticRun = $this->isAgenticRun($input);

        if ($directory !== WORKING_DIRECTORY) {
            try {
                $this->analyser->setDirectory($directory);
            } catch (\RuntimeException $e) {
                $warning = "Warning: The provided directory "
                    . "'{$directory}' does not exist or is not a directory.";
                if ($isAgenticRun) {
                    $this->writeAgenticOutput($output, 'init', false, $warning);
                } else {
                    $output->writeln('<error>' . $warning . '</error>');
                }
                $output->writeln($e->getMessage(), OutputInterface::VERBOSITY_DEBUG);

                return Command::FAILURE;
            }
        }

        $defaultLpvFile = WORKING_DIRECTORY . DIRECTORY_SEPARATOR . '.lpv';

        $verboseOutput = "+ Checking .lpv file existence in " . WORKING_DIRECTORY . ".";
        $output->writeln($verboseOutput, OutputInterface::VERBOSITY_VERBOSE);

        if (\file_exists($defaultLpvFile) && $overwriteDefaultLpvFile === false) {
            $warning = 'Warning: A default .lpv file already exists.';
            if ($isAgenticRun) {
                $this->writeAgenticOutput($output, 'init', false, $warning);
            } else {
                $output->writeln('<error>' . $warning . '</error>');
            }
            return Command::FAILURE;
        }

        if ($chosenPreset && \in_array(\strtolower($chosenPreset), \array_map('strtolower', $this->finder->getAvailablePresets()), strict: true)) {
            $verboseOutput = '+ Loading preset ' . $chosenPreset . '.';
            $output->writeln($verboseOutput, OutputInterface::VERBOSITY_VERBOSE);
            $globPatternFromPreset = true;
            $defaultGlobPattern = $this->finder->getPresetGlobByLanguageName($chosenPreset);
        } else {
            $warning = 'Warning: Chosen preset ' . $chosenPreset . ' is not available. Maybe contribute it?.';
            if ($isAgenticRun) {
                $this->writeAgenticOutput($output, 'init', false, $warning);
            } else {
                $output->writeln('<error>' . $warning . '</error>');
            }
            return Command::FAILURE;
        }

        $lpvFileContent = \implode(PHP_EOL, $defaultGlobPattern);

        if ($this->isDryRun($input)) {
            $output->writeln($lpvFileContent);

            return self::SUCCESS;
        }

        $bytesWritten = file_put_contents(
            $defaultLpvFile,
            $lpvFileContent
        );

        $verboseOutput = '+ Writing default glob pattern to .lpv file in ' . WORKING_DIRECTORY . '.';

        if ($globPatternFromPreset === true) {
            $verboseOutput = '+ Writing glob pattern for preset ' . $chosenPreset . ' to .lpv file in ' . WORKING_DIRECTORY . '.';
        }

        $output->writeln($verboseOutput, OutputInterface::VERBOSITY_VERBOSE);

        if ($bytesWritten === false) {
            $warning = 'Warning: The creation of the default .lpv file failed.';
            if ($isAgenticRun) {
                $this->writeAgenticOutput($output, 'init', false, $warning);
            } else {
                $output->writeln('<error>' . $warning . '</error>');
            }

            return Command::FAILURE;
        }

        $message = "Created default '{$defaultLpvFile}' file.";

        if ($isAgenticRun) {
            $this->writeAgenticOutput($output, 'init', true, $message, ['lpv_file_path' => $defaultLpvFile]);
        } else {
            $output->writeln("<info>{$message}</info>");
        }

        return Command::SUCCESS;
    }
}
