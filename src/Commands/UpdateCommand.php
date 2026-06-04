<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Commands;

use Stolt\LeanPackage\Analyser;
use Stolt\LeanPackage\Analysers\AbstractExportIgnoreAnalyser;
use Stolt\LeanPackage\Commands\Concerns\GeneratesGitattributesOptions;
use Stolt\LeanPackage\Commands\Concerns\OutputOptions;
use Stolt\LeanPackage\Gitattributes\FileRepository as GitattributesFileRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class UpdateCommand extends Command
{
    use GeneratesGitattributesOptions;
    use OutputOptions;

    private AbstractExportIgnoreAnalyser $exportIgnoreAnalyser;

    /**
     * @var string $defaultName
     */
    protected static $defaultName = 'update';
    /**
     * @var string $defaultDescription
     */
    protected static $defaultDescription = 'Update an existing .gitattributes file for a project/micro-package repository';

    public function __construct(
        private readonly Analyser $analyser,
        private readonly GitattributesFileRepository $repository
    ) {
        $this->exportIgnoreAnalyser = $analyser->getActualExportIgnoreAnalyser();

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'directory',
                InputArgument::OPTIONAL,
                'The package directory whose .gitattributes file should be updated',
                \defined('WORKING_DIRECTORY') ? WORKING_DIRECTORY : \getcwd()
            )->addOption(
                'reformat-export-ignores',
                'r',
                InputOption::VALUE_NONE,
                'Only reformat the export-ignores directives in the .gitattributes file'
            )->addOption(
                'migrate-to-negated-export-ignores',
                'm',
                InputOption::VALUE_NONE,
                'Migrate from classic to negated-export-ignores'
            )->addOption(
                'group',
                null,
                InputOption::VALUE_NONE,
                'Group non export-ignore directives in a separate section'
            )->setName(self::$defaultName)->setDescription(self::$defaultDescription);

        // Add common generation options
        $this->addGenerationOptions(function (...$args) {
            $this->getDefinition()->addOption(new InputOption(...$args));
        });

        // Add dry-run option
        $this->addDryRunOutputOption(function (...$args) {
            $this->getDefinition()->addOption(new InputOption(...$args));
        }, 'Do not write any files. Output the expected .gitattributes content');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $directory = (string) $input->getArgument('directory') ?: \getcwd();
        $this->exportIgnoreAnalyser->setDirectory($directory);
        $isAgenticRun = $this->isAgenticRun();

        if ($input->getOption('group')) {
            $this->exportIgnoreAnalyser->setGroupNonExportIgnores(true);
        }

        // Apply options that influence generation
        if (!$this->applyGenerationOptions($input, $output, $this->exportIgnoreAnalyser, $this->getName())) {
            return self::FAILURE;
        }

        $gitattributesPath = $this->exportIgnoreAnalyser->getGitattributesFilePath();

        if (!\file_exists($gitattributesPath) && $this->isDryRun($input) !== true) {
            if ($isAgenticRun) {
                $this->writeAgenticOutput($output, $this->getName(), false, 'No .gitattributes file found. Use the create command to create one first.');
            } else {
                $output->writeln('No .gitattributes file found. Use the <info>create</info> command to create one first.');
            }
            return self::FAILURE;
        }

        $expected = $this->analyser->getExpectedGitattributesContent();

        if ($expected === '') {
            $message = 'Unable to determine expected .gitattributes content for the given directory.';
            if ($isAgenticRun) {
                $this->writeAgenticOutput($output, $this->getName(), false, $message);
            } else {
                $output->writeln($message);
            }
            return self::FAILURE;
        }

        // Support dry-run: print expected content and exit successfully without writing.
        if ($this->isDryRun($input)) {
            $output->writeln($expected);

            return self::SUCCESS;
        }

        try {
            $this->repository->overwriteGitattributesFile($expected);
        } catch (\Throwable $e) {
            $message = 'Update of .gitattributes file failed.';
            if ($isAgenticRun) {
                $this->writeAgenticOutput($output, $this->getName(), false, $message);
            } else {
                $output->writeln($message);
            }
            return self::FAILURE;
        }

        $directory = \realpath($directory);
        $message = "The .gitattributes file in {$directory} has been updated.";
        if ($isAgenticRun) {
            $this->writeAgenticOutput($output, $this->getName(), true, $message, ['gitattributes_file_path' => $gitattributesPath]);
        } else {
            $output->writeln($message);
        }
        return self::SUCCESS;
    }
}
