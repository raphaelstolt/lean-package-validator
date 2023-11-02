<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Commands;

use Stolt\LeanPackage\Analyser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MissingPipeCommand extends Command
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
    protected function configure(): void
    {
        $this->setName('missing');
        $description = 'Validates the given content of a .gitattributes file and displays the missing artifacts';
        $this->setDescription($description);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $standardIn = fopen('php://stdin','r');
        $standardInput = stream_get_contents($standardIn);

        fclose($standardIn);

        $output->write($standardInput);
        return 0;
    }
}
