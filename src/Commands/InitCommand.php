<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Commands;

use Stolt\LeanPackage\Analyser;
use Stolt\LeanPackage\Presets\Finder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class InitCommand extends Command
{
    private const DEFAULT_PRESET = 'PHP';

    /**
     * Package analyser.
     *
     * @var \Stolt\LeanPackage\Analyser
     */
    protected $analyser;

    /**
     * @var \Stolt\LeanPackage\Presets\Finder
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
    protected function configure()
    {
        $this->analyser->setDirectory(WORKING_DIRECTORY);
        $this->setName('init');
        $description = 'Creates a default .lpv file in a given '
            . 'project/micro-package repository';
        $this->setDescription($description);

        $availablePresets = $this->formatAvailablePresetDefinitionsForDescription(
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
    }

    /**
     * @param array $presets
     * @return string
     */
    private function formatAvailablePresetDefinitionsForDescription(array $presets): string
    {
        $presets = \array_map(function ($preset) {
            return '<comment>' . $preset . '</comment>';
        }, $presets);

        if (\count($presets)  > 2) {
            $lastPreset = \array_pop($presets);
            return \implode(', ', $presets) . ', and ' . $lastPreset;
        }

        return $presets[0] . ' and ' . $presets[1];
    }

    /**
     * Execute command.
     *
     * @param \Symfony\Component\Console\Input\InputInterface   $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return integer
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $directory = (string) $input->getArgument('directory');
        $overwriteDefaultLpvFile = $input->getOption('overwrite');
        $chosenPreset = (string) $input->getOption('preset');

        if ($directory !== WORKING_DIRECTORY) {
            try {
                $this->analyser->setDirectory($directory);
            } catch (\RuntimeException $e) {
                $warning = "Warning: The provided directory "
                    . "'$directory' does not exist or is not a directory.";
                $outputContent = '<error>' . $warning . '</error>';
                $output->writeln($outputContent);

                $output->writeln($e->getMessage(), OutputInterface::VERBOSITY_DEBUG);

                return 1;
            }
        }

        $defaultLpvFile = WORKING_DIRECTORY . DIRECTORY_SEPARATOR . '.lpv';

        $verboseOutput = "+ Checking .lpv file existence in " . WORKING_DIRECTORY . ".";
        $output->writeln($verboseOutput, OutputInterface::VERBOSITY_VERBOSE);

        if (\file_exists($defaultLpvFile) && $overwriteDefaultLpvFile === false) {
            $warning = 'Warning: A default .lpv file already exists.';
            $outputContent = '<error>' . $warning . '</error>';
            $output->writeln($outputContent);

            return 1;
        }

        $defaultGlobPattern = $this->analyser->getDefaultGlobPattern();
        $globPatternFromPreset = false;

        if ($chosenPreset && \in_array(\strtolower($chosenPreset), \array_map('strtolower', $this->finder->getAvailablePresets()))) {
            $verboseOutput = '+ Loadind preset ' . $chosenPreset . '.';
            $output->writeln($verboseOutput, OutputInterface::VERBOSITY_VERBOSE);
            $globPatternFromPreset = true;
            $defaultGlobPattern = $this->finder->getPresetGlobByLanguageName($chosenPreset);
        } else {
            $warning = 'Warning: Chosen preset ' . $chosenPreset . ' is not available. Maybe contribute it?.';
            $outputContent = '<error>' . $warning . '</error>';
            $output->writeln($outputContent);

            return 1;
        }

        $lpvFileContent = \implode("\n", $defaultGlobPattern);

        $bytesWritten = file_put_contents(
            $defaultLpvFile,
            $lpvFileContent
        );

        $verboseOutput = '+ Writing default glob pattern to .lpv file in ' . WORKING_DIRECTORY . '.';
        if ($globPatternFromPreset) {
            $verboseOutput = '+ Writing glob pattern for preset ' . $chosenPreset . ' to .lpv file in ' . WORKING_DIRECTORY . '.';
        }
        $output->writeln($verboseOutput, OutputInterface::VERBOSITY_VERBOSE);

        if ($bytesWritten === false) {
            $warning = 'Warning: The creation of the default .lpv file failed.';
            $outputContent = '<error>' . $warning . '</error>';
            $output->writeln($outputContent);

            return 1;
        }

        $info = "<info>Created default '$defaultLpvFile' file.</info>";
        $output->writeln($info);

        return 0;
    }
}
