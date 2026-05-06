<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Commands;

use Stolt\LeanPackage\Analyser;
use Stolt\LeanPackage\Commands\Concerns\OutputOptions;
use Stolt\LeanPackage\GitattributesFileRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ReformatCommand extends Command
{
    use OutputOptions;

    /**
     * @param Analyser $analyser
     * @param GitattributesFileRepository $repository
     */
    public function __construct(private readonly Analyser $analyser,
                                private readonly GitattributesFileRepository $repository)
    {
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
            ->setName('reformat')
            ->setDescription('Reformat a present .gitattributes file');

        $directoryDescription = 'The directory of a project/micro-package repository';

        $this->addArgument(
            'directory',
            InputArgument::OPTIONAL,
            $directoryDescription,
            $this->analyser->getDirectory()
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
        $directory = (string)$input->getArgument('directory') ?: WORKING_DIRECTORY;
        $this->analyser->setDirectory($directory);
        $isAgenticRun = $this->isAgenticRun($input);

        return $this->reformatPresentExportIgnores($input, $output, $directory, $isAgenticRun);
    }




    private function reformatPresentExportIgnores(
        InputInterface  $input,
        OutputInterface $output,
        string          $directory,
        bool            $isAgenticRun
    ): int
    {
        $gitattributesPath = $this->analyser->getGitattributesFilePath();

        if (!\file_exists($gitattributesPath) && $this->isDryRun($input) !== true) {
            if ($isAgenticRun) {
                $this->writeAgenticOutput($output, $this->getName(), false, 'No .gitattributes file found. Use the create command to create one first.');
            } else {
                $output->writeln('No .gitattributes file found. Use the <info>create</info> command to create one first.');
            }
            return self::FAILURE;
        }

        $aligned = $this->getPresentGitattributesContentWithAlignedExportIgnores();

        if ($aligned === '') {
            $message = 'Unable to determine present .gitattributes content for the given directory.';
            if ($isAgenticRun) {
                $this->writeAgenticOutput($output, $this->getName(), false, $message);
            } else {
                $output->writeln($message);
            }
            return self::FAILURE;
        }

        if ($this->isDryRun($input)) {
            $output->writeln($aligned);

            return self::SUCCESS;
        }

        try {
            $this->repository->overwriteGitattributesFileFormatted($aligned);
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
        $message = "The export-ignore directives in {$directory} have been reformatted.";
        if ($isAgenticRun) {
            $this->writeAgenticOutput($output, $this->getName(), true, $message, ['gitattributes_file_path' => $gitattributesPath]);
        } else {
            $output->writeln($message);
        }

        return self::SUCCESS;
    }

    private function getPresentGitattributesContentWithAlignedExportIgnores(): string
    {
        $gitattributesContent = $this->analyser->getPresentGitAttributesContent();

        if ($gitattributesContent === '') {
            return '';
        }

        $eol = $this->detectEol($gitattributesContent);
        $gitattributesLines = \preg_split('/\\r\\n|\\r|\\n/', $gitattributesContent);

        if ($gitattributesLines === false) {
            return $gitattributesContent;
        }

        $exportIgnorePatterns = [];

        foreach ($gitattributesLines as $line) {
            if ($this->isAlignableExportIgnoreLine($line) === false) {
                continue;
            }

            [$pattern] = \explode('export-ignore', $line, 2);
            $exportIgnorePatterns[] = \rtrim($pattern);
        }

        if ($exportIgnorePatterns === []) {
            return $gitattributesContent;
        }

        $longestPattern = \max(\array_map('strlen', $exportIgnorePatterns));

        $alignedLines = \array_map(function (string $line) use ($longestPattern): string {
            if ($this->isAlignableExportIgnoreLine($line) === false) {
                return $line;
            }

            [$pattern, $suffix] = \explode('export-ignore', $line, 2);
            $pattern = \rtrim($pattern);

            if (str_starts_with($pattern, '/') && str_ends_with($pattern, '/') === false) {
                $pattern = str_replace('/', '', $pattern);
            }

            return $pattern . \str_repeat(' ', $longestPattern - \strlen($pattern) + 1) . 'export-ignore' . $suffix;
        }, $gitattributesLines);

        return \implode($eol, $alignedLines);
    }

    private function isAlignableExportIgnoreLine(string $line): bool
    {
        return \str_contains($line, 'export-ignore')
            && \str_starts_with(\ltrim($line), '#') === false;
    }

    private function detectEol(string $content): string
    {
        if (\str_contains($content, "\r\n")) {
            return "\r\n";
        }

        if (\str_contains($content, "\r")) {
            return "\r";
        }

        return "\n";
    }
}
