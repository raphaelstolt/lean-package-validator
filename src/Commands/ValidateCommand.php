<?php

namespace Stolt\LeanPackage\Commands;

use Stolt\LeanPackage\Analyser;
use Stolt\LeanPackage\Exceptions\GitattributesCreationFailed;
use Stolt\LeanPackage\Exceptions\InvalidGlobPattern;
use Stolt\LeanPackage\Exceptions\InvalidGlobPatternFile;
use Stolt\LeanPackage\Exceptions\NonExistentGlobPatternFile;
use Stolt\LeanPackage\Generator;
use Stolt\LeanPackage\Archive\Validator;
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
     * @param Analyser $analyser
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
        $overwriteDescription = 'Overwrite existing .gitattributes file '
            . 'with missing export-ignores';
        $validateArchiveDescription = 'Validate Git archive against current HEAD';

        $exampleGlobPattern = '{.*,*.md}';
        $globPatternDescription = 'Use this glob pattern e.g. <comment>'
            . $exampleGlobPattern . '</comment> to match artifacts which should be '
            . 'export-ignored';
        $globPatternFileDescription = 'Use this file with glob patterns '
            . 'to match artifacts which should be export-ignored';

        $this->addOption('create', 'c', InputOption::VALUE_NONE, $createDescription);
        $this->addOption(
            'enforce-strict-order',
            null,
            InputOption::VALUE_NONE,
            $enforceStrictOrderDescription
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
    }

    /**
     * Execute command.
     *
     * @param \Symfony\Component\Console\Input\InputInterface   $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $directory = $input->getArgument('directory');

        if ($directory !== WORKING_DIRECTORY) {
            try {
                $this->analyser->setDirectory($directory);
            } catch (\RuntimeException $e) {
                $warning = "Warning: The provided directory "
                    . "'$directory' does not exist or is not a directory.";
                $outputContent = '<error>' . $warning . '</error>';
                $output->writeln($outputContent);

                return 1;
            }
        }

        $createGitattributesFile = $input->getOption('create');
        $overwriteGitattributesFile = $input->getOption('overwrite');
        $validateArchive = $input->getOption('validate-git-archive');
        $globPattern = $input->getOption('glob-pattern');
        $globPatternFile = $input->getOption('glob-pattern-file');

        $enforceStrictOrderComparison = $input->getOption('enforce-strict-order');

        if ($enforceStrictOrderComparison) {
            $this->analyser->enableStrictOrderCamparison();
        }

        if ($globPattern) {
            try {
                $this->analyser->setGlobPattern($globPattern);
            } catch (InvalidGlobPattern $e) {
                $warning = "Warning: The provided glob pattern "
                    . "'$globPattern' is considered invalid.";
                $outputContent = '<error>' . $warning . '</error>';
                $output->writeln($outputContent);

                return 1;
            }
        } elseif ($this->isGlobPatternFileSetable($globPatternFile)) {
            try {
                $this->analyser->setGlobPatternFromFile($globPatternFile);
            } catch (NonExistentGlobPatternFile $e) {
                $warning = "Warning: The provided glob pattern file "
                    . "'$globPatternFile' doesn't exist.";
                $outputContent = '<error>' . $warning . '</error>';
                $output->writeln($outputContent);

                return 1;
            } catch (InvalidGlobPatternFile $e) {
                $warning = "Warning: The provided glob pattern file "
                    . "'$globPatternFile' is considered invalid.";
                $outputContent = '<error>' . $warning . '</error>';
                $output->writeln($outputContent);

                return 1;
            }
        }

        if (!$this->analyser->hasGitattributesFile()) {
            $warning = 'Warning: There is no .gitattributes file present in '
                . $this->analyser->getDirectory() . '.';
            $outputContent = '<error>' . $warning . '</error>';

            $expectedGitattributesFileContent = $this->analyser
                ->getExpectedGitattributesContent();

            if ($expectedGitattributesFileContent !== '') {
                if ($createGitattributesFile || $overwriteGitattributesFile) {
                    try {
                        $outputContent .= $this->createGitattributesFile(
                            $expectedGitattributesFileContent
                        );

                        $output->writeln($outputContent);

                        return true;
                    } catch (GitattributesCreationFailed $e) {
                        $outputContent .= PHP_EOL . PHP_EOL . $e->getMessage();
                        $output->writeln($outputContent);
                        return 1;
                    }
                } else {
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
            if ($this->isValidArchive()) {
                $info = '<info>The archive file of the current HEAD is considered lean.</info>';
                $output->writeln($info);

                return true;
            }
            $foundUnexpectedArchiveArtifacts = $this->archiveValidator
                ->getFoundUnexpectedArchiveArtifacts();

            $info = '<error>The archive file of the current HEAD is not considered lean.</error>'
                . PHP_EOL . PHP_EOL . 'Seems like the following artifacts slipped in:<info>' . PHP_EOL
                . implode(PHP_EOL, $foundUnexpectedArchiveArtifacts) . '</info>' . PHP_EOL;

            if (count($this->archiveValidator->getFoundUnexpectedArchiveArtifacts()) === 1) {
                $info = '<error>The archive file of the current HEAD is not considered lean.</error>'
                    . PHP_EOL . PHP_EOL . 'Seems like the following artifact slipped in:<info>' . PHP_EOL
                    . implode(PHP_EOL, $foundUnexpectedArchiveArtifacts) . '</info>' . PHP_EOL;
            }

            $output->writeln($info);

            return 1;
        } else {
            if ($this->analyser->hasCompleteExportIgnores() === false) {
                $outputContent = '<error>The present .gitattributes file is considered invalid.</error>';

                if ($this->analyser->hasCompleteExportIgnores() === false) {
                    $expectedGitattributesFileContent = $this->analyser
                        ->getExpectedGitattributesContent();

                    if ($createGitattributesFile || $overwriteGitattributesFile) {
                        try {
                            $outputContent .= $this->overwriteGitattributesFile(
                                $expectedGitattributesFileContent
                            );

                            $output->writeln($outputContent);

                            return true;
                        } catch (GitattributesCreationFailed $e) {
                            $outputContent .= PHP_EOL . PHP_EOL . $e->getMessage();
                            $output->writeln($outputContent);

                            return 1;
                        }
                    }

                    $outputContent .= $this->getExpectedGitattributesFileContentOutput(
                        $expectedGitattributesFileContent
                    );

                    $output->writeln($outputContent);

                    return 1;
                }
            }

            $info = '<info>The present .gitattributes file is considered valid.</info>';
            $output->writeln($info);

            return true;
        }
    }

    /**
     * Check if a glob pattern file is setable.
     *
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
        return file_exists($this->defaultLpvFile);
    }

    /**
     * Validate archive of current Git HEAD.
     *
     * @return boolean
     */
    protected function isValidArchive()
    {
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

        return str_repeat(PHP_EOL, 2) . $content;
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
