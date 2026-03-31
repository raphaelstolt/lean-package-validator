<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Commands;

use Stolt\LeanPackage\Analyser;
use Stolt\LeanPackage\Commands\Concerns\GeneratesGitattributesOptions;
use Stolt\LeanPackage\Commands\Concerns\OutputOptions;
use Stolt\LeanPackage\GitattributesFileRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class CreateCommand extends Command
{
    use GeneratesGitattributesOptions;
    use OutputOptions;

    /**
     * @var string $defaultName
     */
    protected static $defaultName = 'create';
    /**
     * @var string $defaultDescription
     */
    protected static $defaultDescription = 'Create a new .gitattributes file for a project/micro-package repository';

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
                'The package directory to create the .gitattributes file in',
                \defined('WORKING_DIRECTORY') ? WORKING_DIRECTORY : \getcwd()
            )->setName(self::$defaultName)->setDescription(self::$defaultDescription);

        // Add common generation options
        $this->addGenerationOptions(function (...$args) {
            $this->getDefinition()->addOption(new InputOption(...$args));
        });
        $this->getDefinition()->addOption(new InputOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Do not write any files. Output the expected .gitattributes content'
        ));
        $this->addAgenticOutputOption(function (...$args) {
            $this->getDefinition()->addOption(new InputOption(...$args));
        });
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $directory = (string) $input->getArgument('directory') ?: \getcwd();
        $this->analyser->setDirectory($directory);
        $isAgenticRun = $this->isAgenticRun($input);

        // Apply options that influence generation
        if (!$this->applyGenerationOptions($input, $output, $this->analyser)) {
            return self::FAILURE;
        }

        $gitattributesPath = $this->analyser->getGitattributesFilePath();

        if (\file_exists($gitattributesPath) && $input->getOption('dry-run') !== true) {
            $message = 'A .gitattributes file already exists. Use the update command to modify it.';
            if ($isAgenticRun) {
                $this->writeAgenticOutput($output, 'create', false, $message);
            } else {
                $output->writeln($message);
            }
            return self::FAILURE;
        }

        $expected = $this->analyser->getExpectedGitattributesContent();

        if ($expected === '') {
            $message = 'Unable to determine expected .gitattributes content for the given directory.';
            if ($isAgenticRun) {
                $this->writeAgenticOutput($output, 'create', false, $message);
            } else {
                $output->writeln($message);
            }
            return self::FAILURE;
        }

        // Support dry-run: print expected content and exit successfully without writing.
        if ($input->getOption('dry-run') === true) {
            $output->writeln($expected);

            return self::SUCCESS;
        }

        try {
            $this->repository->createGitattributesFile($expected);
        } catch (\Throwable $e) {
            $message = 'Creation of .gitattributes file failed.';
            if ($isAgenticRun) {
                $this->writeAgenticOutput($output, 'create', false, $message);
            } else {
                $output->writeln($message);
            }
            return self::FAILURE;
        }

        $directory = \realpath($directory);
        $message = "A .gitattributes file has been created in {$directory}.";
        if ($isAgenticRun) {
            $this->writeAgenticOutput($output, 'create', true, $message, ['gitattributes_file_path' => $gitattributesPath]);
        } else {
            $output->writeln($message);
        }
        return self::SUCCESS;
    }
}
