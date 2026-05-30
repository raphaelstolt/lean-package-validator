<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Commands\Concerns;

use Laravel\AgentDetector\AgentDetector;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

trait OutputOptions
{
    protected function addDryRunOutputOption(callable $addOption, string $message): void
    {
        $addOption('dry-run', null, InputOption::VALUE_NONE, $message);
    }

    protected function isDryRun(InputInterface $input): bool
    {
        return (bool) $input->getOption('dry-run');
    }

    protected function isAgenticRun(): bool
    {
        return AgentDetector::detect()->isAgent === true;
    }

    /**
     * @param array<string, mixed> $extra
     */
    protected function writeAgenticOutput(
        OutputInterface $output,
        string $command,
        bool $success,
        string $message,
        array $extra = []
    ): void {
        $data = \array_merge(
            ['command' => $command, 'status' => $success ? 'success' : 'failure', 'message' => $message],
            $extra
        );
        $output->writeln((string) \json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
