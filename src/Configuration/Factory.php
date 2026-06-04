<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Configuration;

use Laravel\AgentDetector\AgentDetector;
use Symfony\Component\Console\Input\InputInterface;

final class Factory
{
    public function createValidateConfig(
        InputInterface $input
    ): Validate {
        return new Validate(
            (string) $input->getArgument('directory'),
            (bool) $input->getOption('dry-run'),
            AgentDetector::detect()->isAgent === true,
        );
    }

    public function createCreateConfig(
        InputInterface $input
    ): Create {
        return new Create(
            (string) $input->getArgument('directory') ?: WORKING_DIRECTORY,
            (bool) $input->getOption('force'),
            (string) $input->getOption('flavour'),
            (bool) $input->getOption('dry-run'),
            AgentDetector::detect()->isAgent === true,
        );
    }

    public function createReformatConfig(
        InputInterface $input
    ): Reformat {
        return new Reformat(
            (string) $input->getArgument('directory'),
            (bool) $input->getOption('sort-alphabetically'),
            (bool) $input->getOption('sort-from-directories-to-files'),
            (bool) $input->getOption('group'),
            (bool) $input->getOption('dry-run'),
            AgentDetector::detect()->isAgent === true,
        );
    }

    public function createUpdateConfig(
        InputInterface $input
    ): Update {
        return new Update(
            (string) $input->getArgument('directory'),
            (bool) $input->getOption('reformat-export-ignores'),
            (bool) $input->getOption('migrate-to-negated-export-ignores'),
            (bool) $input->getOption('group'),
            (bool) $input->getOption('dry-run'),
            AgentDetector::detect()->isAgent === true,
        );
    }

    public function createExportIgnoreConfig(
        InputInterface $input
    ): ExportIgnore {
        return new ExportIgnore(
            (string) $input->getOption('flavour'),
        );
    }
}
