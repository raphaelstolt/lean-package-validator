<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Commands;

use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\UnifiedDiffOutputBuilder;
use SplFileInfo;
use Stolt\LeanPackage\Analyser;
use Stolt\LeanPackage\Archive\Validator;
use Stolt\LeanPackage\Commands\Concerns\GeneratesGitattributesOptions;
use Stolt\LeanPackage\Exceptions\GitArchiveNotValidatedYet;
use Stolt\LeanPackage\Exceptions\GitattributesCreationFailed;
use Stolt\LeanPackage\Exceptions\GitHeadNotAvailable;
use Stolt\LeanPackage\Exceptions\GitNotAvailable;
use Stolt\LeanPackage\Exceptions\InvalidGlobPattern;
use Stolt\LeanPackage\Exceptions\InvalidGlobPatternFile;
use Stolt\LeanPackage\Exceptions\NoLicenseFilePresent;
use Stolt\LeanPackage\Exceptions\NonExistentGlobPatternFile;
use Stolt\LeanPackage\Exceptions\PresetNotAvailable;
use Stolt\LeanPackage\GitattributesFileRepository;
use Stolt\LeanPackage\Helpers\InputReader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ValidateCommand extends Command
{
    use GeneratesGitattributesOptions;

    /**
     * Default glob pattern file.
     *
     * @var string
     */
    protected string $defaultLpvFile = WORKING_DIRECTORY . DIRECTORY_SEPARATOR . '.lpv';

    protected string $defaultPreset = 'Php';

    /**
     * Package analyser.
     *
     * @var Analyser
     */
    protected Analyser $analyser;

    /**
     * Archive validator.
     *
     * @var Validator
     */
    protected Validator $archiveValidator;

    protected GitattributesFileRepository $gitattributesFileRepository;

    /**
     * Input reader.
     *
     * @var InputReader
     */
    protected InputReader $inputReader;

    /**
     * @param Analyser $analyser
     * @param Validator $archiveValidator
     * @param InputReader $inputReader
     */
    public function __construct(Analyser $analyser, Validator $archiveValidator, InputReader $inputReader)
    {
        $this->analyser = $analyser;
        $this->archiveValidator = $archiveValidator;
        $this->gitattributesFileRepository = new GitattributesFileRepository($this->analyser);
        $this->inputReader = $inputReader;

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
        $this->setName('validate');
        $description = 'Validates the .gitattributes file of a given '
            . 'project/micro-package repository';
        $this->setDescription($description);

        $directoryDescription = 'The directory of a project/micro-package repository';

        $this->addArgument(
            'directory',
            InputArgument::OPTIONAL,
            $directoryDescription,
            $this->analyser->getDirectory()
        );

        $createDescription = 'Create a .gitattributes file if not present';
        $enforceStrictOrderDescription = 'Enforce a strict order comparison of '
            . 'export-ignores in the .gitattributes file';
        $enforceExportIgnoreAlignmentDescription = 'Enforce a strict alignment of '
            . 'export-ignores in the .gitattributes file';
        $overwriteDescription = 'Overwrite existing .gitattributes file '
            . 'with missing export-ignores';
        $validateArchiveDescription = 'Validate Git archive against current HEAD';
        $omitHeaderDescription = 'Omit adding a header to created or modified .gitattributes file';
        $diffDescription = 'Show difference between expected and actual .gitattributes content';
        $reportStaleExportIgnoresDescription = 'Filter stale export-ignores referencing non existent artifacts. Requires --diff option to be set';

        $exampleGlobPattern = '{.*,*.md}';
        $globPatternDescription = 'Use this glob pattern e.g. <comment>'
            . $exampleGlobPattern . '</comment> to match artifacts which should be '
            . 'export-ignored';
        $globPatternFileDescription = 'Use this file with glob patterns '
            . 'to match artifacts which should be export-ignored';
        $presetDescription = 'Use the glob pattern of the given language preset';

        $keepLicenseDescription = 'Do not export-ignore the license file';
        $keepReadmeDescription = 'Do not export-ignore the README file';
        $keepGlobPatternDescription = 'Do not export-ignore matching glob pattern e.g. <comment>{LICENSE.*,README.*,docs*}</comment>';
        $sortDescription = 'Sort from directories to files';

        $alignExportIgnoresDescription = 'Align export-ignores on create or overwrite';
        $stdinInputDescription = "Read .gitattributes content from standard input";

        $this->addOption('stdin-input', null, InputOption::VALUE_NONE, $stdinInputDescription);
        $this->addOption('create', 'c', InputOption::VALUE_NONE, $createDescription);
        $this->addOption(
            'enforce-strict-order',
            null,
            InputOption::VALUE_NONE,
            $enforceStrictOrderDescription
        );
        $this->addOption(
            'enforce-alignment',
            null,
            InputOption::VALUE_NONE,
            $enforceExportIgnoreAlignmentDescription
        );

        $this->addOption('overwrite', 'o', InputOption::VALUE_NONE, $overwriteDescription);
        $this->addOption(
            'validate-git-archive',
            null,
            InputOption::VALUE_NONE,
            $validateArchiveDescription
        );
        $this->addOption(
            'glob-pattern',
            null,
            InputOption::VALUE_REQUIRED,
            $globPatternDescription
        );
        $this->addOption(
            'glob-pattern-file',
            null,
            InputOption::VALUE_OPTIONAL,
            $globPatternFileDescription,
            $this->defaultLpvFile
        );
        $this->addOption(
            'preset',
            null,
            InputOption::VALUE_OPTIONAL,
            $presetDescription,
            $this->defaultPreset
        );
        $this->addOption(
            'keep-license',
            null,
            InputOption::VALUE_NONE,
            $keepLicenseDescription
        );
        $this->addOption(
            'keep-readme',
            null,
            InputOption::VALUE_NONE,
            $keepReadmeDescription
        );
        $this->addOption(
            'keep-glob-pattern',
            null,
            InputOption::VALUE_NONE,
            $keepGlobPatternDescription
        );
        $this->addOption(
            'align-export-ignores',
            'a',
            InputOption::VALUE_NONE,
            $alignExportIgnoresDescription
        );
        $this->addOption(
            'sort-from-directories-to-files',
            's',
            InputOption::VALUE_NONE,
            $sortDescription
        );
        $this->addOption(
            'omit-header',
            null,
            InputOption::VALUE_NONE,
            $omitHeaderDescription
        );
        $this->addOption(
            'diff',
            null,
            InputOption::VALUE_NONE,
            $diffDescription
        );
        $this->addOption(
            'report-stale-export-ignores',
            null,
            InputOption::VALUE_NONE,
            $reportStaleExportIgnoresDescription
        );
    }

    /**
     * Execute command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @throws GitArchiveNotValidatedYet
     * @throws GitHeadNotAvailable
     * @throws GitNotAvailable
     * @return integer
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $directory = (string) $input->getArgument('directory');
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

                return Command::FAILURE;
            }
        }

        $stdinInput = $input->getOption('stdin-input');

        if ($stdinInput !== false) {
            $stdinInputContents = $this->inputReader->get();

            if (!\strpos($stdinInputContents, 'export-ignore')) {
                $warning = "Warning: The provided input stream seems to be no .gitattributes content.";
                $outputContent = '<error>' . $warning . '</error>';
                $output->writeln($outputContent);

                return Command::FAILURE;
            }

            $verboseOutput = '+ Validating .gitattributes content from STDIN.';
            $output->writeln($verboseOutput, OutputInterface::VERBOSITY_VERBOSE);

            if ($this->analyser->hasCompleteExportIgnoresFromString($stdinInputContents)) {
                $info = 'The provided .gitattributes content is considered <info>valid</info>.';
                $output->writeln($info);

                return Command::SUCCESS;
            }

            $outputContent = 'The provided .gitattributes file is considered <error>invalid</error>.';
            $output->writeln($outputContent);

            return Command::FAILURE;
        }

        $verboseOutput = '+ Scanning directory ' . $directory . '.';
        $output->writeln($verboseOutput, OutputInterface::VERBOSITY_VERBOSE);

        // Print deprecation notices for legacy options but do NOT change exit code.
        if ($input->hasOption('create') && (bool) $input->getOption('create')) {
            $output->writeln('<comment>The --create option is deprecated. Please use the dedicated <info>create</info> command.</comment>');
        }
        if ($input->hasOption('overwrite') && (bool) $input->getOption('overwrite')) {
            $output->writeln('<comment>The --overwrite option is deprecated. Please use the dedicated <info>update</info> command.</comment>');
        }

        // Apply shared generation-related options via the trait (glob/preset/keep/alignment/order)
        if (!$this->applyGenerationOptions($input, $output, $this->analyser)) {
            return Command::FAILURE;
        }

        $createGitattributesFile = $input->getOption('create');
        $overwriteGitattributesFile = $input->getOption('overwrite');
        $validateArchive = $input->getOption('validate-git-archive');
        $globPattern = $input->getOption('glob-pattern');
        $globPatternFile = (string) $input->getOption('glob-pattern-file');
        $omitHeader = $input->getOption('omit-header');
        $showDifference = $input->getOption('diff');
        $reportStaleExportIgnores = $input->getOption('report-stale-export-ignores');

        $enforceStrictOrderComparison = $input->getOption('enforce-strict-order');
        $sortFromDirectoriesToFiles = $input->getOption('sort-from-directories-to-files');

        if ($enforceStrictOrderComparison && $sortFromDirectoriesToFiles === false) {
            $verboseOutput = '+ Enforcing strict order comparison.';
            $output->writeln($verboseOutput, OutputInterface::VERBOSITY_VERBOSE);

            $this->analyser->enableStrictOrderComparison();
        }

        if ($sortFromDirectoriesToFiles) {
            $verboseOutput = '+ Sorting from files to directories.';
            $output->writeln($verboseOutput, OutputInterface::VERBOSITY_VERBOSE);

            $this->analyser->sortFromDirectoriesToFiles();
        }

        if ($reportStaleExportIgnores) {
            $verboseOutput = '+ Enforcing stale export ignores comparison.';
            $output->writeln($verboseOutput, OutputInterface::VERBOSITY_VERBOSE);

            $this->analyser->enableStaleExportIgnoresComparison();
        }

        $enforceExportIgnoresAlignment = $input->getOption('enforce-alignment');

        if ($enforceExportIgnoresAlignment) {
            $verboseOutput = '+ Enforcing alignment comparison.';
            $output->writeln($verboseOutput, OutputInterface::VERBOSITY_VERBOSE);

            $this->analyser->enableStrictAlignmentComparison();
        }

        $keepLicense = (boolean) $input->getOption('keep-license');

        if ($keepLicense) {
            $verboseOutput = '+ Keeping the license file.';
            $output->writeln($verboseOutput, OutputInterface::VERBOSITY_VERBOSE);

            $this->analyser->keepLicense();
        }

        $keepReadme = (boolean) $input->getOption('keep-readme');

        if ($keepReadme) {
            $verboseOutput = '+ Keeping the README file.';
            $output->writeln($verboseOutput, OutputInterface::VERBOSITY_VERBOSE);

            $this->analyser->keepReadme();
        }

        $keepGlobPattern = (string) $input->getOption('keep-glob-pattern');

        if ($keepGlobPattern !== '') {
            $verboseOutput = \sprintf('+ Keeping files matching the glob pattern <info>%s</info>.', $keepGlobPattern);
            $output->writeln($verboseOutput, OutputInterface::VERBOSITY_VERBOSE);
            try {
                $this->analyser->setKeepGlobPattern($keepGlobPattern);
            } catch (InvalidGlobPattern $e) {
                $warning = "Warning: The provided glob pattern "
                    . "'$keepGlobPattern' is considered invalid.";
                $outputContent = '<error>' . $warning . '</error>';
                $output->writeln($outputContent);

                $output->writeln($e->getMessage(), OutputInterface::VERBOSITY_DEBUG);

                return Command::FAILURE;
            }
        }

        $alignExportIgnores = $input->getOption('align-export-ignores');

        if ($alignExportIgnores) {
            $verboseOutput = '+ Aligning the export-ignores.';
            $output->writeln($verboseOutput, OutputInterface::VERBOSITY_VERBOSE);

            $this->analyser->alignExportIgnores();
        }

        if ($globPattern || $globPattern === '') {
            try {
                $verboseOutput = "+ Using glob pattern <info>$globPattern</info>.";
                $output->writeln($verboseOutput, OutputInterface::VERBOSITY_VERBOSE);

                $this->analyser->setGlobPattern((string) $globPattern);
            } catch (InvalidGlobPattern $e) {
                $warning = "Warning: The provided glob pattern "
                    . "'$globPattern' is considered invalid.";
                $outputContent = '<error>' . $warning . '</error>';
                $output->writeln($outputContent);

                $output->writeln($e->getMessage(), OutputInterface::VERBOSITY_DEBUG);

                return Command::FAILURE;
            }
        } elseif ($this->isGlobPatternFileSettable($globPatternFile)) {
            try {
                if ($this->isDefaultGlobPatternFilePresent()) {
                    $this->analyser->setGlobPatternFromFile($globPatternFile);
                }
                if ($globPatternFile) {
                    $globPatternFileInfo = new SplFileInfo($globPatternFile);
                    $verboseOutput = '+ Using ' . $globPatternFileInfo->getBasename() . ' file as glob pattern input.';
                    $output->writeln($verboseOutput, OutputInterface::VERBOSITY_VERBOSE);

                    $this->analyser->setGlobPatternFromFile($globPatternFile);
                }

            } catch (NonExistentGlobPatternFile $e) {
                $warning = "Warning: The provided glob pattern file "
                    . "'$globPatternFile' doesn't exist.";
                $outputContent = '<error>' . $warning . '</error>';
                $output->writeln($outputContent);

                $output->writeln($e->getMessage(), OutputInterface::VERBOSITY_DEBUG);

                return Command::FAILURE;
            } catch (InvalidGlobPatternFile $e) {
                $warning = "Warning: The provided glob pattern file "
                    . "'$globPatternFile' is considered invalid.";
                $outputContent = '<error>' . $warning . '</error>';
                $output->writeln($outputContent);

                $output->writeln($e->getMessage(), OutputInterface::VERBOSITY_DEBUG);

                return Command::FAILURE;
            }
        } elseif ($chosenPreset) {
            try {

                $verboseOutput = '+ Using the ' . $chosenPreset . ' language preset.';
                $output->writeln($verboseOutput, OutputInterface::VERBOSITY_VERBOSE);

                $this->analyser->setGlobPatternFromPreset($chosenPreset);
            } catch (PresetNotAvailable $e) {
                $warning = 'Warning: The chosen language preset ' . $chosenPreset . ' is not available. Maybe contribute it?.';
                $outputContent = '<error>' . $warning . '</error>';
                $output->writeln($outputContent);

                return Command::FAILURE;
            }
        }

        $verboseOutput = '+ Checking .gitattribute file existence in ' . $directory . '.';
        $output->writeln($verboseOutput, OutputInterface::VERBOSITY_VERBOSE);

        if (!$this->analyser->hasGitattributesFile()) {
            $warning = 'Warning: There is no .gitattributes file present in '
                . $this->analyser->getDirectory() . '.';
            $outputContent = '<error>' . $warning . '</error>';

            $expectedGitattributesFileContent = $this->analyser
                ->getExpectedGitattributesContent();

            $verboseOutput = '+ Getting expected .gitattribute file content.';
            $output->writeln($verboseOutput, OutputInterface::VERBOSITY_VERBOSE);

            if ($expectedGitattributesFileContent !== '') {
                if ($createGitattributesFile || $overwriteGitattributesFile) {
                    try {
                        $outputContent .= $this->gitattributesFileRepository->createGitattributesFile(
                            $expectedGitattributesFileContent,
                            $omitHeader === false
                        );

                        $output->writeln($outputContent);

                        return Command::SUCCESS;
                    } catch (GitattributesCreationFailed $e) {
                        $outputContent .= PHP_EOL . PHP_EOL . $e->getMessage();
                        $output->writeln($outputContent);

                        $output->writeln($e->getMessage(), OutputInterface::VERBOSITY_DEBUG);

                        return Command::FAILURE;
                    }
                } else {
                    $verboseOutput = '+ Suggesting .gitattribute file content.';
                    $output->writeln($verboseOutput, OutputInterface::VERBOSITY_VERBOSE);

                    $outputContent .= $this->getSuggestGitattributesFileCreationOptionOutput(
                        $expectedGitattributesFileContent
                    );

                    $output->writeln($outputContent);

                    return Command::FAILURE;
                }
            }

            $outputContent .= PHP_EOL . PHP_EOL . '<info>Unable to resolve expected .gitattributes '
                . 'content.</info>';
            $output->writeln($outputContent);

            return Command::FAILURE;
        } elseif ($validateArchive) {
            try {
                $verboseOutput = '+ Validating Git archive.';
                $output->writeln($verboseOutput, OutputInterface::VERBOSITY_VERBOSE);

                if ($this->isValidArchive($keepLicense)) {
                    $info = '<info>The archive file of the current HEAD is considered lean.</info>';
                    $output->writeln($info);

                    return Command::SUCCESS;
                }
                $foundUnexpectedArchiveArtifacts = $this->archiveValidator
                    ->getFoundUnexpectedArchiveArtifacts();

                $info = '<error>The archive file of the current HEAD is not considered lean.</error>'
                    . PHP_EOL . PHP_EOL . 'Seems like the following artifacts slipped in:<info>' . PHP_EOL
                    . \implode(PHP_EOL, $foundUnexpectedArchiveArtifacts) . '</info>' . PHP_EOL;

                if (\count($this->archiveValidator->getFoundUnexpectedArchiveArtifacts()) === 1) {
                    $info = '<error>The archive file of the current HEAD is not considered lean.</error>'
                        . PHP_EOL . PHP_EOL . 'Seems like the following artifact slipped in:<info>' . PHP_EOL
                        . \implode(PHP_EOL, $foundUnexpectedArchiveArtifacts) . '</info>' . PHP_EOL;
                }
            } catch (NoLicenseFilePresent $e) {
                $errorMessage = 'The archive file of the current HEAD '
                    . 'is considered invalid due to a missing license file.';
                $info = '<error>' . $errorMessage . '</error>' . PHP_EOL;

                $output->writeln($e->getMessage(), OutputInterface::VERBOSITY_DEBUG);

                $this->archiveValidator->getArchive()->removeArchive();
            }

            $output->writeln($info);

            return Command::FAILURE;
        } else {
            $verboseOutput = '+ Analysing the .gitattribute content.';
            $output->writeln($verboseOutput, OutputInterface::VERBOSITY_VERBOSE);

            if ($this->analyser->hasCompleteExportIgnores() === false) {
                $outputContent = 'The present .gitattributes file is considered <error>invalid</error>.';

                $verboseOutput = "+ Gathering expected .gitattribute content.";
                $output->writeln($verboseOutput, OutputInterface::VERBOSITY_VERBOSE);

                $expectedGitattributesFileContent = $this->analyser
                    ->getExpectedGitattributesContent();

                if ($createGitattributesFile || $overwriteGitattributesFile) {
                    try {
                        $verboseOutput = "+ Trying to create expected .gitattribute file.";
                        if ($overwriteGitattributesFile) {
                            $verboseOutput = "+ Trying to overwrite existing .gitattribute file with expected content.";
                        }
                        $output->writeln($verboseOutput, OutputInterface::VERBOSITY_VERBOSE);

                        if ($omitHeader === false) {
                            if (\str_contains($expectedGitattributesFileContent, GitattributesFileRepository::GENERATED_HEADER)) {
                                $expectedGitattributesFileContent = \str_replace(
                                    GitattributesFileRepository::GENERATED_HEADER . PHP_EOL . PHP_EOL,
                                    '',
                                    $expectedGitattributesFileContent
                                );
                            }
                            $expectedGitattributesFileContent = GitattributesFileRepository::MODIFIED_HEADER . PHP_EOL . PHP_EOL . $expectedGitattributesFileContent;
                        }

                        $outputContent .= $this->gitattributesFileRepository->overwriteGitattributesFile(
                            $expectedGitattributesFileContent
                        );

                        $output->writeln($outputContent);

                        return Command::SUCCESS;
                    } catch (GitattributesCreationFailed $e) {
                        $outputContent .= PHP_EOL . PHP_EOL . $e->getMessage();
                        $output->writeln($outputContent);

                        $output->writeln($e->getMessage(), OutputInterface::VERBOSITY_DEBUG);

                        return Command::FAILURE;
                    }
                }

                if ($showDifference) {
                    $actual = $this->analyser->getPresentGitAttributesContent();
                    $builder = new UnifiedDiffOutputBuilder(
                        "--- Original" . PHP_EOL . "+++ Expected" . PHP_EOL,
                        true
                    );
                    $differ = new Differ($builder);
                    $expectedGitattributesFileContent = $differ->diff($actual, $expectedGitattributesFileContent);
                }

                $outputContent .= $this->getExpectedGitattributesFileContentOutput(
                    $expectedGitattributesFileContent
                );

                $output->writeln($outputContent);

                return Command::FAILURE;
            }

            $info = 'The present .gitattributes file is considered <info>valid</info>.';
            $output->writeln($info);

            if ($this->analyser->hasPrecedingSlashesInExportIgnorePattern()) {
                $verboseOutput = '+ Checking for preceding slashes in export-ignore statements.';
                $output->writeln($verboseOutput, OutputInterface::VERBOSITY_VERBOSE);

                $warning = "Warning: At least one export-ignore pattern has a leading '/', "
                    . 'which is considered as a smell.';
                $outputContent = '<error>' . $warning . '</error>';
                $output->writeln($outputContent);
            }

            if ($this->analyser->hasTextAutoConfiguration() === false) {
                $verboseOutput = '+ Checking for text auto configuration.';
                $output->writeln($verboseOutput, OutputInterface::VERBOSITY_VERBOSE);

                $warning = 'Warning: Missing a text auto configuration. '
                    . 'Consider adding one.';
                $outputContent = '<error>' . $warning . '</error>';
                $output->writeln($outputContent);
            }

            return Command::SUCCESS;
        }
    }

    /**
     * Check if a glob pattern file is settable.
     *
     * @param string $file The glob pattern file to check.
     * @return boolean
     */
    protected function isGlobPatternFileSettable(string $file): bool
    {
        if ($this->isGlobPatternFileProvided($file)) {
            return true;
        }

        return $this->isDefaultGlobPatternFilePresent();
    }

    /**
     * Check if a glob pattern file was provided.
     *
     * @param string $file The glob pattern file provided.
     * @return boolean
     */
    protected function isGlobPatternFileProvided(string $file): bool
    {
        return $file !== $this->defaultLpvFile;
    }

    /**
     * Check if a default glob pattern file (.lpv) is present.
     *
     * @return boolean
     */
    protected function isDefaultGlobPatternFilePresent(): bool
    {
        return \file_exists($this->defaultLpvFile);
    }

    /**
     * Validate archive of current Git HEAD.
     *
     * @param boolean $validateLicenseFilePresence Whether the archive should have a license file or not.
     * @throws GitNotAvailable|NoLicenseFilePresent
     * @throws GitHeadNotAvailable
     * @return boolean
     */
    protected function isValidArchive(bool $validateLicenseFilePresence = false): bool
    {
        if ($validateLicenseFilePresence) {
            return $this->archiveValidator->shouldHaveLicenseFile()->validate(
                $this->analyser->collectExpectedExportIgnores()
            );
        }

        return $this->archiveValidator->validate(
            $this->analyser->collectExpectedExportIgnores()
        );
    }

    /**
     * Get expected gitattributes file content output content.
     *
     * @param string $expectedGitattributesFileContent
     *
     * @return string
     */
    protected function getExpectedGitattributesFileContentOutput(
        string $expectedGitattributesFileContent
    ): string {
        $content = 'Would expect the following .gitattributes file content:' . PHP_EOL
            . '<info>' . $expectedGitattributesFileContent . '</info>';

        return \str_repeat(PHP_EOL, 2) . $content;
    }

    /**
     * Get suggest gitattributes file creation output content.
     *
     * @param string $expectedGitattributesFileContent
     *
     * @return string
     */
    protected function getSuggestGitattributesFileCreationOptionOutput(
        string $expectedGitattributesFileContent
    ): string {
        $content = 'Would expect the following .gitattributes file content:' . PHP_EOL
            . '<info>' . $expectedGitattributesFileContent . '</info>' . PHP_EOL
            . 'Use the <info>--create|-c</info> option to create a '
            . '.gitattributes file with the shown content.';

        return PHP_EOL . PHP_EOL . $content;
    }
}
