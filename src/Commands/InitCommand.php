<?php

namespace Stolt\LeanPackage\Commands;

use Stolt\LeanPackage\Analyser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class InitCommand extends Command
{
    /**
     * Package analyser.
     *
     * @var \Stolt\LeanPackage\Analyser
     */
    protected $analyser;

    /**
     * @param Analyser  $analyser
     */
    public function __construct(Analyser $analyser)
    {
        $this->analyser = $analyser;

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
        $this->setName('init');
        $description = 'Creates a default .lpv file in a given '
            . 'project/micro-package repository';
        $this->setDescription($description);

        $directoryDescription = 'The directory of a project/micro-package repository';
        $overwriteDescription = 'Overwrite existing default .lpv file file';

        $this->addArgument(
            'directory',
            InputArgument::OPTIONAL,
            $directoryDescription,
            $this->analyser->getDirectory()
        );
        $this->addOption('overwrite', 'o', InputOption::VALUE_NONE, $overwriteDescription);
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
        $overwriteDefaultLpvFile = $input->getOption('overwrite');

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

        $defaultLpvFile = WORKING_DIRECTORY . DIRECTORY_SEPARATOR . '.lpv';

        if (\file_exists($defaultLpvFile) && $overwriteDefaultLpvFile === false) {
            $warning = 'Warning: A default .lpv file already exists.';
            $outputContent = '<error>' . $warning . '</error>';
            $output->writeln($outputContent);

            return 1;
        }

        $defaultGlobPatterns = $this->analyser->getDefaultGlobPatterns();
        $lpvFileContent = \implode("\n", $defaultGlobPatterns);

        $bytesWritten = file_put_contents(
            $defaultLpvFile,
            $lpvFileContent
        );

        if ($bytesWritten === false) {
            $warning = 'Warning: The creation of the default .lpv file failed.';
            $outputContent = '<error>' . $warning . '</error>';
            $output->writeln($outputContent);

            return 1;
        }

        $info = "<info>Created default '$defaultLpvFile' file.</info>";
        $output->writeln($info);

        return 0;
    }
}
