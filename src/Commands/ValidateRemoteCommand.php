<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Commands;

use Stolt\LeanPackage\Analyser;
use Stolt\LeanPackage\Commands\Concerns\GeneratesGitattributesOptions;
use Stolt\LeanPackage\Commands\Concerns\OutputOptions;
use Stolt\LeanPackage\Exceptions\GitNotAvailable;
use Stolt\LeanPackage\Exceptions\InvalidRepositoryUrl;
use Stolt\LeanPackage\Exceptions\RepositoryCloneFailed;
use Stolt\LeanPackage\Gitattributes\FileRepository as GitattributesFileRepository;
use Stolt\LeanPackage\Git\RepositoryCloner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final class ValidateRemoteCommand extends Command
{
    use GeneratesGitattributesOptions {
        getDefaultLpvFile as getWorkingDirectoryLpvFile;
    }
    use OutputOptions;

    public const GITHUB_REPOSITORY_URL_PATTERN = '~^(?:https://github\.com/|git@github\.com:)(?<owner>[A-Za-z0-9_.-]+)/(?<repository>[A-Za-z0-9_.-]+?)(?:\.git)?/?$~';

    public const ACTION_NONE = 'none';
    public const ACTION_CREATED = 'created';
    public const ACTION_UPDATED = 'updated';

    protected static string $defaultName = 'validate-remote';

    protected static string $defaultDescription = 'Validate the .gitattributes file of a remote GitHub repository and create or update it in a local clone';

    /**
     * The directory the remote repository has been cloned into.
     */
    private string $cloneDirectory = '';

    private bool $hasWrittenGitattributesFile = false;

    public function __construct(
        private readonly Analyser $analyser,
        private readonly GitattributesFileRepository $repository,
        private readonly RepositoryCloner $repositoryCloner
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'repository-url',
                InputArgument::REQUIRED,
                'The URL of the GitHub repository e.g. <comment>https://github.com/owner/repository</comment>'
            )->addOption(
                'clone-directory',
                null,
                InputOption::VALUE_REQUIRED,
                'The directory to clone the repository into. Defaults to a unique temporary directory'
            )->setName(self::$defaultName)->setDescription(self::$defaultDescription);

        // Add common generation options
        $this->addGenerationOptions(function (...$args) {
            $this->getDefinition()->addOption(new InputOption(...$args));
        });

        // Add dry-run option
        $this->addDryRunOutputOption(function (...$args) {
            $this->getDefinition()->addOption(new InputOption(...$args));
        }, 'Do not write any .gitattributes file into the clone. Output the expected .gitattributes content');
    }

    /**
     * Resolve the default .lpv file from the cloned repository, as the one
     * of the current working directory is unrelated to the remote repository.
     */
    protected function getDefaultLpvFile(): string
    {
        if ($this->cloneDirectory === '') {
            return $this->getWorkingDirectoryLpvFile();
        }

        return $this->cloneDirectory . DIRECTORY_SEPARATOR . '.lpv';
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $repositoryUrl = \trim((string) $input->getArgument('repository-url'));

        try {
            $repositoryName = $this->getRepositoryName($repositoryUrl);
        } catch (InvalidRepositoryUrl $e) {
            return $this->fail($output, $e->getMessage());
        }

        $cloneDirectory = (string) $input->getOption('clone-directory');
        $isTemporaryClone = $cloneDirectory === '';

        if ($isTemporaryClone) {
            $cloneDirectory = \sys_get_temp_dir() . DIRECTORY_SEPARATOR
                . 'lpv-remote-' . $repositoryName . '-' . \bin2hex(\random_bytes(4));
        }

        if (\file_exists($cloneDirectory) && !$this->isEmptyDirectory($cloneDirectory)) {
            return $this->fail($output, "The clone directory {$cloneDirectory} already exists and is not empty.");
        }

        $output->writeln("+ Cloning {$repositoryUrl} into {$cloneDirectory}.", OutputInterface::VERBOSITY_VERBOSE);

        try {
            $this->repositoryCloner->clone($repositoryUrl, $cloneDirectory);
        } catch (GitNotAvailable $e) {
            return $this->fail($output, 'The Git command is not available.');
        } catch (RepositoryCloneFailed $e) {
            $output->writeln($e->getMessage(), OutputInterface::VERBOSITY_DEBUG);

            return $this->fail($output, "Cloning the repository {$repositoryUrl} failed.");
        }

        if (!\is_dir($cloneDirectory)) {
            return $this->fail($output, "Cloning the repository {$repositoryUrl} failed.");
        }

        $this->cloneDirectory = (string) \realpath($cloneDirectory);

        try {
            return $this->validateClone($input, $output, $repositoryUrl);
        } finally {
            // A temporary clone is only kept when a .gitattributes file has been written into it.
            if ($isTemporaryClone && $this->hasWrittenGitattributesFile === false) {
                $output->writeln("+ Removing the temporary clone {$this->cloneDirectory}.", OutputInterface::VERBOSITY_VERBOSE);
                $this->removeDirectory($this->cloneDirectory);
            }
        }
    }

    private function validateClone(InputInterface $input, OutputInterface $output, string $repositoryUrl): int
    {
        $isAgenticRun = $this->isAgenticRun();

        $this->analyser->getActualExportIgnoreAnalyser()->setDirectory($this->cloneDirectory);
        $this->repository->setWorkingDirectory($this->cloneDirectory);

        // Only use a .lpv file of the clone when no glob pattern file has been explicitly provided.
        if ($input->hasParameterOption('--glob-pattern-file') === false) {
            $input->setOption('glob-pattern-file', $this->getDefaultLpvFile());
        }

        if (!$this->applyGenerationOptions($input, $output, $this->analyser->getActualExportIgnoreAnalyser(), $this->getName())) {
            return self::FAILURE;
        }

        $exportIgnoreAnalyser = $this->analyser->getActualExportIgnoreAnalyser();
        $gitattributesPath = $exportIgnoreAnalyser->getGitattributesFilePath();
        $hasGitattributesFile = $exportIgnoreAnalyser->hasGitattributesFile();

        $output->writeln('+ Analysing the .gitattributes content.', OutputInterface::VERBOSITY_VERBOSE);

        if ($hasGitattributesFile && $this->hasValidGitattributesFile()) {
            $message = "The .gitattributes file of {$repositoryUrl} is considered valid.";
            if ($isAgenticRun) {
                $this->writeAgenticOutput($output, $this->getName(), true, $message, [
                    'valid' => true,
                    'action' => self::ACTION_NONE,
                ]);
            } else {
                $output->writeln("The .gitattributes file of {$repositoryUrl} is considered <info>valid</info>.");
            }

            return self::SUCCESS;
        }

        $expected = $this->analyser->getExpectedGitattributesContent();

        if ($expected === '') {
            return $this->fail($output, 'Unable to determine expected .gitattributes content for the cloned repository.');
        }

        $finding = $hasGitattributesFile
            ? "The .gitattributes file of {$repositoryUrl} is considered invalid."
            : "There is no .gitattributes file present in {$repositoryUrl}.";

        if ($this->isDryRun($input)) {
            if ($isAgenticRun) {
                $this->writeAgenticOutput($output, $this->getName(), true, $finding, [
                    'valid' => false,
                    'action' => self::ACTION_NONE,
                    'expected_gitattributes_content' => $expected,
                ]);
            } else {
                $output->writeln($expected);
            }

            return self::SUCCESS;
        }

        try {
            if ($hasGitattributesFile) {
                $this->repository->overwriteGitattributesFile($expected);
            } else {
                $this->repository->createGitattributesFile($expected);
            }
        } catch (Throwable $e) {
            $output->writeln($e->getMessage(), OutputInterface::VERBOSITY_DEBUG);

            return $this->fail($output, 'Writing the .gitattributes file into the clone failed.');
        }

        $this->hasWrittenGitattributesFile = true;

        $action = $hasGitattributesFile ? self::ACTION_UPDATED : self::ACTION_CREATED;
        $result = $hasGitattributesFile
            ? "Updated the .gitattributes file in {$this->cloneDirectory}."
            : "Created a .gitattributes file in {$this->cloneDirectory}.";

        if ($isAgenticRun) {
            $this->writeAgenticOutput($output, $this->getName(), true, $finding . ' ' . $result, [
                'valid' => false,
                'action' => $action,
                'clone_directory' => $this->cloneDirectory,
                'gitattributes_file_path' => $gitattributesPath,
                'gitattributes_content' => $this->repository->getGitattributesContent(),
            ]);

            return self::SUCCESS;
        }

        $output->writeln('<error>' . $finding . '</error>');
        $output->writeln($result . ' With the shown content:');
        $output->writeln('<info>' . $this->repository->getGitattributesContent() . '</info>');

        return self::SUCCESS;
    }

    /**
     * Extract a filesystem friendly owner-repository name from a GitHub repository URL.
     *
     * @throws InvalidRepositoryUrl
     */
    private function getRepositoryName(string $repositoryUrl): string
    {
        if (\preg_match(self::GITHUB_REPOSITORY_URL_PATTERN, $repositoryUrl, $matches) !== 1) {
            throw new InvalidRepositoryUrl(
                "The provided repository URL '{$repositoryUrl}' is not a valid GitHub repository URL."
            );
        }

        return $matches['owner'] . '-' . $matches['repository'];
    }

    private function hasValidGitattributesFile(): bool
    {
        if ($this->analyser->hasCompleteExportIgnores()) {
            return true;
        }

        if ($this->analyser->usesNegatedExportIgnoreStrategy()) {
            return false;
        }

        return $this->analyser->getExpectedGitattributesContent() === $this->analyser->getActualGitattributesContent();
    }

    private function isEmptyDirectory(string $directory): bool
    {
        return \is_dir($directory) && \array_diff((array) \scandir($directory), ['.', '..']) === [];
    }

    private function removeDirectory(string $directory): void
    {
        if (!\is_dir($directory)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        /** @var \SplFileInfo $fileInfo */
        foreach ($files as $fileInfo) {
            if ($fileInfo->isDir() && !$fileInfo->isLink()) {
                \rmdir($fileInfo->getPathname());
                continue;
            }
            // Git marks pack files read-only, which prevents their removal on Windows.
            \chmod($fileInfo->getPathname(), permissions: 0o666);
            \unlink($fileInfo->getPathname());
        }

        \rmdir($directory);
    }

    private function fail(OutputInterface $output, string $message): int
    {
        if ($this->isAgenticRun()) {
            $this->writeAgenticOutput($output, $this->getName(), false, $message);
        } else {
            $output->writeln('<error>' . $message . '</error>');
        }

        return self::FAILURE;
    }
}
