<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Commands;

use Stolt\LeanPackage\Analyser;
use Stolt\LeanPackage\Commands\Concerns\GeneratesGitattributesOptions;
use Stolt\LeanPackage\GitattributesFileRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class UpdateCommand extends Command
{
    use GeneratesGitattributesOptions;

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
            )->setName(self::$defaultName)->setDescription(self::$defaultDescription);

        // Add common generation options
        $this->addGenerationOptions(function (...$args) {
            $this->getDefinition()->addOption(new InputOption(...$args));
        });

        // Add dry-run option
        $this->getDefinition()->addOption(new InputOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Do not write any files. Output the expected .gitattributes content'
        ));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $directory = (string) $input->getArgument('directory') ?: \getcwd();
        $this->analyser->setDirectory($directory);

        // Apply options that influence generation
        if (!$this->applyGenerationOptions($input, $output, $this->analyser)) {
            return self::FAILURE;
        }

        $gitattributesPath = $this->analyser->getGitattributesFilePath();

        if (!\file_exists($gitattributesPath) && $input->getOption('dry-run') !== true) {
            $output->writeln('No .gitattributes file found. Use the <info>create</info> command to create one first.');

            return self::FAILURE;
        }

        $expected = $this->analyser->getExpectedGitattributesContent();

        if ($expected === '') {
            $output->writeln('Unable to determine expected .gitattributes content for the given directory.');

            return self::FAILURE;
        }

        // Support dry-run: print expected content and exit successfully without writing.
        if ($input->getOption('dry-run') === true) {
            $output->writeln($expected);

            return self::SUCCESS;
        }

        try {
            $this->repository->overwriteGitattributesFile($expected);
        } catch (\Throwable $e) {
            $output->writeln('Update of .gitattributes file failed.');

            return self::FAILURE;
        }

        $directory = \realpath($directory);
        $output->writeln("The .gitattributes file in {$directory} has been updated.");

        return self::SUCCESS;
    }
}
