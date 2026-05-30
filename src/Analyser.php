<?php declare(strict_types=1);

namespace Stolt\LeanPackage;

use Stolt\LeanPackage\Analysers\AbstractExportIgnoreAnalyser;
use Stolt\LeanPackage\Analysers\ClassicExportIgnoreAnalyser;
use Stolt\LeanPackage\Analysers\NegatedExportIgnoreAnalyser;
use Stolt\LeanPackage\Gitattributes\FileRepository as GitattributesFileRepository;

class Analyser
{
    private AbstractExportIgnoreAnalyser $exportIgnoreAnalyser;

    public function __construct(
        AbstractExportIgnoreAnalyser $actualExportIgnoreAnalyser
    ) {
        $this->exportIgnoreAnalyser = $actualExportIgnoreAnalyser;
    }

    public function getActualExportIgnoreAnalyser(): AbstractExportIgnoreAnalyser
    {
        return $this->exportIgnoreAnalyser;
    }

    public function getActualGitattributesContent(): string
    {
        return $this->exportIgnoreAnalyser->getPresentGitAttributesContent();
    }

    /**
     * Return the expected .gitattributes content.
     *
     * @param array $postfixLessExportIgnores Expected patterns without an export-ignore postfix.
     * @param string $flavour The flavour of the .gitattributes file content. Possible values are classic and negated.
     * @return string
     */
    public function getExpectedGitattributesContent(array $postfixLessExportIgnores = [], string $flavour = ClassicExportIgnoreAnalyser::EXPORT_IGNORE_CLASSIC): string
    {
        if ($flavour !== ClassicExportIgnoreAnalyser::EXPORT_IGNORE_CLASSIC
            && $flavour !== NegatedExportIgnoreAnalyser::EXPORT_IGNORE_NEGATED
        ) {
            throw new \InvalidArgumentException("Invalid flavour provided. Expected 'classic' or 'negated'.");
        }

        if (!$this->getActualExportIgnoreAnalyser()->hasGitattributesFile() && $flavour === ClassicExportIgnoreAnalyser::EXPORT_IGNORE_CLASSIC) {
            $postfixLessExportIgnores[] = '.gitattributes';
        }

        \sort($postfixLessExportIgnores, SORT_STRING | SORT_FLAG_CASE);

        if ($flavour === NegatedExportIgnoreAnalyser::EXPORT_IGNORE_NEGATED) {
            if ($this->exportIgnoreAnalyser instanceof ClassicExportIgnoreAnalyser) {
                $formerExportIgnoreAnalyserConfiguration = $this->exportIgnoreAnalyser->getConfiguration();
                $this->exportIgnoreAnalyser = new NegatedExportIgnoreAnalyser(
                    $this->exportIgnoreAnalyser->getFinder(),
                    $this->exportIgnoreAnalyser->getGitattributesFileRepository(),
                    $this->exportIgnoreAnalyser->getDirectory(),
                    $formerExportIgnoreAnalyserConfiguration
                );

                return $this->exportIgnoreAnalyser->getGitattributesContentToBe()->getContent();
            }
            return $this->exportIgnoreAnalyser->getGitattributesContentToBe($postfixLessExportIgnores)->getContent();
        }

        return $this->exportIgnoreAnalyser->getGitattributesContentToBe($postfixLessExportIgnores)->getContent();
    }

    /**
     * Return the reformatted .gitattributes content with aligned export-ignore
     * entries, respecting the sort-alphabetically, sort-from-directories-to-files,
     * and group-non-export-ignores settings.
     *
     * @return string
     */
    public function getReformattedGitattributesContent(): string
    {
        return (new Reformatter())->reformat($this->exportIgnoreAnalyser);
    }

    public function usesNegatedExportIgnoreStrategy(string $gitattributesContent = ''): bool
    {
        if ($gitattributesContent === '') {
            if ($this->exportIgnoreAnalyser->hasGitattributesFile() === false) {
                return false;
            }
            $gitattributesContent = (string) \file_get_contents($this->exportIgnoreAnalyser->gitattributesFile);
        }

        $lines = \preg_split('/\\r\\n|\\r|\\n/', $gitattributesContent) ?: [];

        foreach ($lines as $line) {
            if (\trim($line) === '* export-ignore') {
                return true;
            }
        }

        return false;
    }

    public function hasCompleteExportIgnoresFromString(string $gitattributesContent): bool
    {
        if ($this->usesNegatedExportIgnoreStrategy($gitattributesContent)) {
            return $this->buildNegatedAnalyser()->getPresentExportIgnores(true, $gitattributesContent) !== [];
        }

        $expectedExportIgnores = $this->exportIgnoreAnalyser->collectExpectedExportIgnores();
        $presentExportIgnores = $this->exportIgnoreAnalyser->getPresentExportIgnores(
            true,
            $gitattributesContent,
            $this->getActualExportIgnoreAnalyser()->getConfiguration()->enforceStrictOrderComparison
        );

        \sort($expectedExportIgnores, SORT_STRING | SORT_FLAG_CASE);

        if ($this->getActualExportIgnoreAnalyser()->getConfiguration()->enforceStrictOrderComparison === true) {
            return $expectedExportIgnores === $presentExportIgnores;
        }

        \sort($presentExportIgnores, SORT_STRING | SORT_FLAG_CASE);

        return $expectedExportIgnores === $presentExportIgnores;
    }

    /**
     * Is existing .gitattributes file having all export-ignore(s).
     */
    public function hasCompleteExportIgnores(): bool
    {
        if ($this->usesNegatedExportIgnoreStrategy()) {
            return $this->buildNegatedAnalyser()->hasCompleteExportIgnores();
        }

        return $this->exportIgnoreAnalyser->hasCompleteExportIgnores();
    }

    private function buildNegatedAnalyser(): NegatedExportIgnoreAnalyser
    {
        if ($this->exportIgnoreAnalyser instanceof NegatedExportIgnoreAnalyser) {
            return $this->exportIgnoreAnalyser;
        }

        $directory = $this->exportIgnoreAnalyser->getDirectory();

        $analyser = new NegatedExportIgnoreAnalyser(
            $this->exportIgnoreAnalyser->getFinder(),
            new GitattributesFileRepository($this->exportIgnoreAnalyser->getDirectory()),
            $directory,
            $this->exportIgnoreAnalyser->getConfiguration(),
        );

        $analyser->setDirectory($directory);

        if ($this->exportIgnoreAnalyser->isStaleExportIgnoresComparisonEnabled()) {
            $analyser->enableStaleExportIgnoresComparison();
        }

        return $analyser;
    }
}
