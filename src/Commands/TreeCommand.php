<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Commands;

use Stolt\LeanPackage\Tree;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class TreeCommand extends Command
{
    private Tree $tree;

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
        $description = 'Displays the source structure of a given '
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
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->directoryToOperateOn = (string) $input->getArgument('directory');

        if (!\is_dir($this->directoryToOperateOn)) {
            $warning = "Warning: The provided directory "
                . "'$this->directoryToOperateOn' does not exist or is not a directory.";
            $outputContent = '<error>' . $warning . '</error>';
            $output->writeln($outputContent);

            return Command::FAILURE;
        }

        $showSrcTree = $input->getOption('src');

        if ($showSrcTree) {
            $verboseOutput = '+ Showing flat structure of package source.';
            $output->writeln($verboseOutput, OutputInterface::VERBOSITY_VERBOSE);

            $output->writeln('Package: <info>' . $this->getPackageName() . '</info>');
            $output->write($this->tree->getTreeForSrc($this->directoryToOperateOn));

            return Command::SUCCESS;
        }

        $verboseOutput = '+ Showing flat structure of dist package.';
        $output->writeln($verboseOutput, OutputInterface::VERBOSITY_VERBOSE);

        $output->writeln('Package: <info>' . $this->getPackageName() . '</info>');
        $output->write($this->tree->getTreeForDistPackage());

        return Command::SUCCESS;
    }

    protected function getPackageName(): string
    {
        $composerContentAsJson = \json_decode(
            \file_get_contents($this->directoryToOperateOn . DIRECTORY_SEPARATOR . 'composer.json'),
            true
        );
        return \trim($composerContentAsJson['name']);
    }
}
