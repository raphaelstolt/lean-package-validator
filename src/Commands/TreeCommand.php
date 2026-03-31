<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Commands;

use Stolt\LeanPackage\Commands\Concerns\OutputOptions;
use Stolt\LeanPackage\Exceptions\GitHeadNotAvailable;
use Stolt\LeanPackage\Tree;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class TreeCommand extends Command
{
    use OutputOptions;

    private Tree $tree;

    private const UNKNOWN_PACKAGE_NAME = 'unknown/unknown';

    private string $directoryToOperateOn;

    public function __construct(Tree $tree)
    {
        $this->tree = $tree;
        parent::__construct();
    }

    /**
     * Command configuration.
     *
     * @return void
     */
    protected function configure(): void
    {
        $this->directoryToOperateOn = WORKING_DIRECTORY;
        $this->setName('tree');
        $description = 'Display the source structure of a given '
            . "project/micro-package repository or it's dist package";
        $this->setDescription($description);

        $directoryDescription = 'The directory of a project/micro-package repository';

        $this->addArgument(
            'directory',
            InputArgument::OPTIONAL,
            $directoryDescription,
            $this->directoryToOperateOn
        );

        $srcDescription = 'Show the flat src structure of the project/micro-package repository';
        $distPackageDescription = 'Show the flat dist package structure of the project/micro-package';

        $this->addOption('src', null, InputOption::VALUE_NONE, $srcDescription);
        $this->addOption('dist-package', null, InputOption::VALUE_NONE, $distPackageDescription);
        $this->addAgenticOutputOption(function (...$args) {
            $this->getDefinition()->addOption(new InputOption(...$args));
        });
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->directoryToOperateOn = (string) $input->getArgument('directory');
        $isAgenticRun = $this->isAgenticRun($input);

        if (!\is_dir($this->directoryToOperateOn)) {
            $warning = "Warning: The provided directory "
                . "'$this->directoryToOperateOn' does not exist or is not a directory.";
            if ($isAgenticRun) {
                $this->writeAgenticOutput($output, 'tree', false, $warning);
            } else {
                $output->writeln('<error>' . $warning . '</error>');
            }
            return Command::FAILURE;
        }

        $showSrcTree = $input->getOption('src');

        if ($showSrcTree) {
            $verboseOutput = '+ Showing flat structure of package source.';
            $output->writeln($verboseOutput, OutputInterface::VERBOSITY_VERBOSE);

            $packageName = $this->getPackageName();
            $treeOutput = $this->tree->getTreeForSrc($this->directoryToOperateOn);

            if ($isAgenticRun) {
                $treeLines = \array_values(\array_filter(\explode(PHP_EOL, \rtrim($treeOutput)), static fn (string $l): bool => \trim($l) !== ''));
                $this->writeAgenticOutput($output, 'tree', true, "Package: {$packageName}", ['package' => $packageName, 'tree' => $treeLines]);
            } else {
                $output->writeln('Package: <info>' . $packageName . '</info>');
                $output->write($treeOutput);
            }
            return Command::SUCCESS;
        }

        $verboseOutput = '+ Showing flat structure of dist package.';
        $output->writeln($verboseOutput, OutputInterface::VERBOSITY_VERBOSE);

        try {
            $treeToDisplay = $this->tree->getTreeForDistPackage($this->directoryToOperateOn);
            $packageName = $this->getPackageName();

            if ($isAgenticRun) {
                $treeLines = \array_values(\array_filter(\explode(PHP_EOL, \rtrim($treeToDisplay)), static fn (string $l): bool => \trim($l) !== ''));
                $this->writeAgenticOutput($output, 'tree', true, "Package: {$packageName}", ['package' => $packageName, 'tree' => $treeLines]);
            } else {
                $output->writeln('Package: <info>' . $packageName . '</info>');
                $output->write($treeToDisplay);
            }
        } catch (GitHeadNotAvailable $e) {
            $message = 'Directory ' . $this->directoryToOperateOn . ' has no Git Head.';
            if ($isAgenticRun) {
                $this->writeAgenticOutput($output, 'tree', false, $message);
            } else {
                $output->writeln('Directory <info>' . $this->directoryToOperateOn . '</info> has no Git Head.');
            }
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    protected function getPackageName(): string
    {
        if (!\file_exists($this->directoryToOperateOn . DIRECTORY_SEPARATOR . 'composer.json')) {
            return self::UNKNOWN_PACKAGE_NAME;
        }

        $composerContentAsJson = \json_decode(
            \file_get_contents($this->directoryToOperateOn . DIRECTORY_SEPARATOR . 'composer.json'),
            true
        );

        if (!isset($composerContentAsJson['name'])) {
            return self::UNKNOWN_PACKAGE_NAME;
        }

        return \trim($composerContentAsJson['name']);
    }
}
