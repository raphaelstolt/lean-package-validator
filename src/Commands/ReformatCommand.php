<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Commands;

use Stolt\LeanPackage\Analyser;
use Stolt\LeanPackage\Analysers\AbstractExportIgnoreAnalyser;
use Stolt\LeanPackage\Commands\Concerns\OutputOptions;
use Stolt\LeanPackage\Gitattributes\FileRepository as GitattributesFileRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class ReformatCommand extends Command
{
    use OutputOptions;

    protected AbstractExportIgnoreAnalyser $exportIgnoreAnalyser;

    /**
     * @param Analyser $analyser
     * @param GitattributesFileRepository $repository
     */
    public function __construct(private readonly Analyser $analyser,
                                private readonly GitattributesFileRepository $repository)
    {
        $this->exportIgnoreAnalyser = $analyser->getActualExportIgnoreAnalyser();

        parent::__construct();
    }

    /**
     * Command configuration.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this->exportIgnoreAnalyser->setDirectory(\getcwd());

        $this
            ->setName('reformat')
            ->setDescription('Reformat a present .gitattributes file');

        $directoryDescription = 'The directory of a project/micro-package repository';
        $sortAlphabeticallyDescription = 'Sort the export-ignore directives in the .gitattributes file alphabetically';
        $sortFromDirectoriesToFilesDescription = 'Sort the export-ignore directives in the .gitattributes file from directories to files';
        $groupDescription = 'Group non export-ignore directives in a separate section';

        $this->addArgument(
            'directory',
            InputArgument::OPTIONAL,
            $directoryDescription,
            $this->exportIgnoreAnalyser->getDirectory()
        );

        $this->addOption(
            'sort-alphabetically',
            null,
            InputOption::VALUE_NONE,
            $sortAlphabeticallyDescription
        );

        $this->addOption(
            'sort-from-directories-to-files',
            null,
            InputOption::VALUE_NONE,
            $sortFromDirectoriesToFilesDescription
        );

        $this->addOption(
            'group',
            null,
            InputOption::VALUE_NONE,
            $groupDescription
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
     * @return integer
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $directory = (string) $input->getArgument('directory') ?: \getcwd();

        $this->exportIgnoreAnalyser->setDirectory($directory);

        return $this->reformatPresentExportIgnores($input, $output, $directory);
    }

    private function reformatPresentExportIgnores(
        InputInterface  $input,
        OutputInterface $output,
        string          $directory
    ): int
    {
        $gitattributesPath = $this->exportIgnoreAnalyser->getGitattributesFilePath();

        $isAgenticRun = $this->isAgenticRun($input);

        if (!\file_exists($gitattributesPath) && $this->isDryRun($input) !== true) {
            if ($isAgenticRun) {
                $this->writeAgenticOutput($output, $this->getName(), false, 'No .gitattributes file found. Use the create command to create one first.');
            } else {
                $output->writeln('No .gitattributes file found. Use the <info>create</info> command to create one first.');
            }
            return self::FAILURE;
        }

        if ($input->getOption('sort-alphabetically')) {
            $this->exportIgnoreAnalyser->sortAlphabetically();
        }

        if ($input->getOption('sort-from-directories-to-files')) {
            $this->exportIgnoreAnalyser->sortFromDirectoriesToFiles();
        }

        if ($input->getOption('group')) {
            $this->exportIgnoreAnalyser->setGroupNonExportIgnores(true);
        }

        $reformatted = $this->analyser->getReformattedGitattributesContent();

        if ($reformatted === '') {
            $message = 'Unable to determine present .gitattributes content for the given directory.';
            if ($isAgenticRun) {
                $this->writeAgenticOutput($output, $this->getName(), false, $message);
            } else {
                $output->writeln($message);
            }
            return self::FAILURE;
        }

        if ($this->isDryRun($input)) {
            $output->writeln($reformatted);

            return self::SUCCESS;
        }

        try {
            $this->repository->overwriteGitattributesFileFormatted($reformatted);
        } catch (Throwable $e) {
            $message = 'Update of .gitattributes file failed.';
            if ($isAgenticRun) {
                $this->writeAgenticOutput($output, $this->getName(), false, $message);
            } else {
                $output->writeln($message);
            }
            return self::FAILURE;
        }

        $directory = \realpath($directory);
        $suffix =  $input->getOption('group') ? ' and grouped' : '';
        $message = "The export-ignore directives in {$directory} have been reformatted{$suffix}.";
        if ($isAgenticRun) {
            $this->writeAgenticOutput($output, $this->getName(), true, $message, ['gitattributes_file_path' => $gitattributesPath]);
        } else {
            $output->writeln($message);
        }

        return self::SUCCESS;
    }
}
