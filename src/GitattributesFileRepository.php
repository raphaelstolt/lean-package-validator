<?php

declare(strict_types=1);

namespace Stolt\LeanPackage;

use Stolt\LeanPackage\Exceptions\GitattributesCreationFailed;

final class GitattributesFileRepository
{
    protected Analyser $analyser;

    public function __construct(Analyser $analyser)
    {
        $this->analyser = $analyser;
    }

    /**
     * Create the gitattributes file.
     *
     * @param  string  $content The content of the gitattributes file
     * @throws GitattributesCreationFailed
     * @return string
     *
     */
    public function createGitattributesFile(string $content): string
    {
        $bytesWritten = file_put_contents(
            $this->analyser->getGitattributesFilePath(),
            $content
        );

        if ($bytesWritten) {
            $content = 'Created a .gitattributes file with the shown content:'
                . PHP_EOL . '<info>' . $content . '</info>';

            return PHP_EOL . PHP_EOL . $content;
        }

        $message = 'Creation of .gitattributes file failed.';
        throw new GitattributesCreationFailed($message);
    }

    /**
     * Overwrite an existing gitattributes file.
     *
     * @param  string  $content The content of the gitattributes file
     * @throws GitattributesCreationFailed
     * @return string
     *
     */
    public function overwriteGitattributesFile(string $content): string
    {
        $bytesWritten = file_put_contents(
            $this->analyser->getGitattributesFilePath(),
            $content
        );

        if ($bytesWritten) {
            $content = 'Overwrote it with the shown content:'
                . PHP_EOL . '<info>' . $content . '</info>';

            return PHP_EOL . PHP_EOL . $content;
        }

        $message = 'Overwrite of .gitattributes file failed.';
        throw new GitattributesCreationFailed($message);
    }
}
