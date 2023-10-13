<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Commands;

use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\UnifiedDiffOutputBuilder;
use SplFileInfo;
use Stolt\LeanPackage\Analyser;
use Stolt\LeanPackage\Archive\Validator;
use Stolt\LeanPackage\Exceptions\GitattributesCreationFailed;
use Stolt\LeanPackage\Exceptions\InvalidGlobPattern;
use Stolt\LeanPackage\Exceptions\InvalidGlobPatternFile;
use Stolt\LeanPackage\Exceptions\NoLicenseFilePresent;
use Stolt\LeanPackage\Exceptions\NonExistentGlobPatternFile;
use Stolt\LeanPackage\Generator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ValidateCommand extends Command
{
    /**
     * Default glob pattern file.
     *
     * @var string
     */
    protected $defaultLpvFile = WORKING_DIRECTORY . DIRECTORY_SEPARATOR . '.lpv';

    /**
     * Package analyser.
     *
     * @var \Stolt\LeanPackage\Analyser
     */
    protected $analyser;

    /**
     * Archive validator.
     *
     * @var \Stolt\LeanPackage\Archive\Validator
     */
    protected $archiveValidator;

    /**
     * @param Analyser  $analyser
     * @param Validator $archiveValidator
     */
    public function __construct(Analyser $analyser, Validator $archiveValidator)
    {
        $this->analyser = $analyser;
        $this->archiveValidator = $archiveValidator;

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
        $enforceExportIgnoreAligmentDescription = 'Enforce a strict alignment of '
            . 'export-ignores in the .gitattributes file';
        $overwriteDescription = 'Overwrite existing .gitattributes file '
            . 'with missing export-ignores';
        $validateArchiveDescription = 'Validate Git archive against current HEAD';
        $omitHeaderDescription = 'Omit adding a header to created or modified .gitattributes file';
        $diffDescription = 'Show difference between expected and actual .gitattributes content';

        $exampleGlobPattern = '{.*,*.md}';
        $globPatternDescription = 'Use this glob pattern e.g. <comment>'
            . $exampleGlobPattern . '</comment> to match artifacts which should be '
            . 'export-ignored';
        $globPatternFileDescription = 'Use this file with glob patterns '
            . 'to match artifacts which should be export-ignored';

        $keepLicenseDescription = 'Do not export-ignore license file';

        $alignExportIgnoresDescription = 'Align export-ignores on create or overwrite';

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
            $enforceExportIgnoreAligmentDescription
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
            'keep-license',
            null,
            InputOption::VALUE_NONE,
            $keepLicenseDescription
        );
        $this->addOption(
            'align-export-ignores',
            'a',
            InputOption::VALUE_NONE,
            $alignExportIgnoresDescription
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

        $verboseOutput = '+ Scanning directory ' . $directory . '.';
        $output->writeln($verboseOutput, OutputInterface::VERBOSITY_VERBOSE);

        $createGitattributesFile = $input->getOption('create');
        $overwriteGitattributesFile = $input->getOption('overwrite');
        $validateArchive = $input->getOption('validate-git-archive');
        $globPattern = $input->getOption('glob-pattern');
        $globPatternFile = (string) $input->getOption('glob-pattern-file');
        $omitHeader = $input->getOption('omit-header');
        $showDifference = $input->getOption('diff');

        $enforceStrictOrderComparison = $input->getOption('enforce-strict-order');

        if ($enforceStrictOrderComparison) {
            $verboseOutput = '+ Enforcing strict order comparison.';
            $output->writeln($verboseOutput, OutputInterface::VERBOSITY_VERBOSE);

            $this->analyser->enableStrictOrderCamparison();
        }

        $enforceExportIgnoresAlignment = $input->getOption('enforce-alignment');

        if ($enforceExportIgnoresAlignment) {
            $verboseOutput = '+ Enforcing alignment comparison.';
            $output->writeln($verboseOutput, OutputInterface::VERBOSITY_VERBOSE);

            $this->analyser->enableStrictAlignmentCamparison();
        }

        $keepLicense = (boolean) $input->getOption('keep-license');

        if ($keepLicense) {
            $verboseOutput = '+ Keeping the license file.';
            $output->writeln($verboseOutput, OutputInterface::VERBOSITY_VERBOSE);

            $this->analyser->keepLicense();
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

                return 1;
            }
        } elseif($this->isGlobPatternFileSetable($globPatternFile)) {
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

                return 1;
            } catch (InvalidGlobPatternFile $e) {
                $warning = "Warning: The provided glob pattern file "
                    . "'$globPatternFile' is considered invalid.";
                $outputContent = '<error>' . $warning . '</error>';
                $output->writeln($outputContent);

                $output->writeln($e->getMessage(), OutputInterface::VERBOSITY_DEBUG);

                return 1;
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
                        if ($omitHeader === false) {
                            $headerContent = 'This file was partly modified by the lean package validator (http://git.io/lean-package-validator).' . PHP_EOL;

                            if ($createGitattributesFile) {
                                $headerContent = 'This file was generated by the lean package validator (http://git.io/lean-package-validator).' . PHP_EOL;
                            }
                            $expectedGitattributesFileContent = $headerContent . PHP_EOL . $expectedGitattributesFileContent;
                        }

                        $expectedGitattributesFileContent = $expectedGitattributesFileContent;
                        $outputContent .= $this->createGitattributesFile(
                            $expectedGitattributesFileContent
                        );

                        $output->writeln($outputContent);

                        return 0;
                    } catch (GitattributesCreationFailed $e) {
                        $outputContent .= PHP_EOL . PHP_EOL . $e->getMessage();
                        $output->writeln($outputContent);

                        $output->writeln($e->getMessage(), OutputInterface::VERBOSITY_DEBUG);

                        return 1;
                    }
                } else {
                    $verboseOutput = '+ Suggesting .gitattribute file content.';
                    $output->writeln($verboseOutput, OutputInterface::VERBOSITY_VERBOSE);

                    $outputContent .= $this->getSuggestGitattributesFileCreationOptionOutput(
                        $expectedGitattributesFileContent
                    );

                    $output->writeln($outputContent);

                    return 1;
                }
            }

            $outputContent .= PHP_EOL . PHP_EOL . '<info>Unable to resolve expected .gitattributes '
                . 'content.</info>';
            $output->writeln($outputContent);

            return 1;
        } elseif ($validateArchive) {
            try {
                $verboseOutput = '+ Validating Git archive.';
                $output->writeln($verboseOutput, OutputInterface::VERBOSITY_VERBOSE);

                if ($this->isValidArchive($keepLicense)) {
                    $info = '<info>The archive file of the current HEAD is considered lean.</info>';
                    $output->writeln($info);

                    return 0;
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

            return 1;
        } else {
            $verboseOutput = '+ Analysing the .gitattribute content.';
            $output->writeln($verboseOutput, OutputInterface::VERBOSITY_VERBOSE);

            if ($this->analyser->hasCompleteExportIgnores() === false) {
                $outputContent = '<error>The present .gitattributes file is considered invalid.</error>';

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

                        $outputContent .= $this->overwriteGitattributesFile(
                            $expectedGitattributesFileContent
                        );

                        $output->writeln($outputContent);

                        return 0;
                    } catch (GitattributesCreationFailed $e) {
                        $outputContent .= PHP_EOL . PHP_EOL . $e->getMessage();
                        $output->writeln($outputContent);

                        $output->writeln($e->getMessage(), OutputInterface::VERBOSITY_DEBUG);

                        return 1;
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

                return 1;
            }

            $info = '<info>The present .gitattributes file is considered valid.</info>';
            $output->writeln($info);

            if ($this->analyser->hasPrecedingSlashesInExportIgnorePattern()) {
                $verboseOutput = '+ Checking for preceding slashes in export-ignore statements.';
                $output->writeln($verboseOutput, OutputInterface::VERBOSITY_VERBOSE);

                $warning = "Warning: At least one export-ignore pattern has a leading '/', "
                    . $warning = 'which is considered as a smell.';
                $outputContent = '<error>' . $warning . '</error>';
                $output->writeln($outputContent);
            }

            if ($this->analyser->hasTextAutoConfiguration() === false) {
                $verboseOutput = '+ Checking for text auto configuration.';

                $warning = 'Warning: Missing a text auto configuration. '
                    . 'Consider adding one.';
                $outputContent = '<error>' . $warning . '</error>';
                $output->writeln($outputContent);
            }

            return 0;
        }
    }

    /**
     * Check if a glob pattern file is setable.
     *
     * @param string $file The glob pattern file to check.
     * @return boolean
     */
    protected function isGlobPatternFileSetable($file)
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
    protected function isGlobPatternFileProvided($file)
    {
        return $file !== $this->defaultLpvFile;
    }

    /**
     * Check if a default glob pattern file (.lpv) is present.
     *
     * @return boolean
     */
    protected function isDefaultGlobPatternFilePresent()
    {
        return \file_exists($this->defaultLpvFile);
    }

    /**
     * Validate archive of current Git HEAD.
     *
     * @param  boolean $validateLicenseFilePresence Whether the archive should have a license file or not.
     * @throws \Stolt\LeanPackage\Exceptions\NoLicenseFilePresent
     * @return boolean
     */
    protected function isValidArchive($validateLicenseFilePresence = false)
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
     * @param  string $expectedGitattributesFileContent
     *
     * @return string
     */
    protected function getExpectedGitattributesFileContentOutput(
        $expectedGitattributesFileContent
    ) {
        $content = 'Would expect the following .gitattributes file content:' . PHP_EOL
            . '<info>' . $expectedGitattributesFileContent . '</info>';

        return \str_repeat(PHP_EOL, 2) . $content;
    }

    /**
     * Get suggest gitattributes file creation output content.
     *
     * @param  string $expectedGitattributesFileContent
     *
     * @return string
     */
    protected function getSuggestGitattributesFileCreationOptionOutput(
        $expectedGitattributesFileContent
    ) {
        $content = 'Would expect the following .gitattributes file content:' . PHP_EOL
            . '<info>' . $expectedGitattributesFileContent . '</info>' . PHP_EOL
            . 'Use the <info>--create|-c</info> option to create a '
            . '.gitattributes file with the shown content.';

        return PHP_EOL . PHP_EOL . $content;
    }

    /**
     * Create the gitattributes file.
     *
     * @param  string  $content The content of the gitattributes file
     * @throws \Stolt\LeanPackage\Exceptions\GitattributesCreationFailed
     *
     * @return string
     */
    protected function createGitattributesFile($content)
    {
        $bytesWritten = file_put_contents(
            $this->analyser->getGitattributesFilePath(),
            $content
        );

        if ($bytesWritten) {
            $content = 'Created a .gitattributes file with the shown content:'
                . PHP_EOL . '<info>' . $content . '</info>';

            return PHP_EOL . PHP_EOL . $content;
        }

        $message = 'Creation of .gitattributes file failed.';
        throw new GitattributesCreationFailed($message);
    }

    /**
     * Overwrite an existing gitattributes file.
     *
     * @param  string  $content The content of the gitattributes file
     * @throws \Stolt\LeanPackage\Exceptions\GitattributesCreationFailed
     *
     * @return string
     */
    protected function overwriteGitattributesFile($content)
    {
        $bytesWritten = file_put_contents(
            $this->analyser->getGitattributesFilePath(),
            $content
        );

        if ($bytesWritten) {
            $content = 'Overwrote it with the shown content:'
                . PHP_EOL . '<info>' . $content . '</info>';

            return PHP_EOL . PHP_EOL . $content;
        }

        $message = 'Overwrite of .gitattributes file failed.';
        throw new GitattributesCreationFailed($message);
    }
}
