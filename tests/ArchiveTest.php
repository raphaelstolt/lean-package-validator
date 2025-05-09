<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Tests;

use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Ticket;
use Stolt\LeanPackage\Archive;
use Stolt\LeanPackage\Exceptions\GitHeadNotAvailable;
use Stolt\LeanPackage\Exceptions\GitNotAvailable;
use Stolt\LeanPackage\Exceptions\NoLicenseFilePresent;

class ArchiveTest extends TestCase
{
    public function setUp(): void
    {
        $this->setUpTemporaryDirectory();
    }

    /**
     * Tear down test environment.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        if (\is_dir($this->temporaryDirectory)) {
            $this->removeDirectory($this->temporaryDirectory);
        }
    }

    #[Test]
    public function filenameHasAnAccessor(): void
    {
        $analyser = new Archive(__DIR__, 'foo');

        $expectedFilename = __DIR__
            . DIRECTORY_SEPARATOR
            . 'foo.tar.gz';

        $this->assertEquals($expectedFilename, $analyser->getFilename());
    }

    #[Test]
    public function isGitCommandAvailableReturnsFalseOnNonExistingCommand(): void
    {
        $this->assertFalse((new Archive(__DIR__, 'foo'))->isGitCommandAvailable('no-way-i-exists'));
    }

    #[Test]
    #[Group('travis-ci-exclude')]
    public function isGitCommandAvailableReturnsTrueOnExistingCommand(): void
    {
        $this->assertTrue((new Archive(__DIR__, 'foo'))->isGitCommandAvailable('ping'));
    }


    #[Test]
    public function hasHeadThrowsExpectedExceptionWhenGitCommandNotAvailable(): void
    {
        $mock = Mockery::mock(
            'Stolt\LeanPackage\Archive[isGitCommandAvailable]',
            [__DIR__, 'foo']
        );

        $mock->shouldReceive('isGitCommandAvailable')
            ->once()
            ->withNoArgs()
            ->andReturn(false);

        $this->expectException(GitNotAvailable::class);
        $this->expectExceptionMessage('The Git command is not available.');

        $mock->hasHead();
    }

    #[Test]
    public function createArchiveThrowsExpectedExceptionWhenNoGitHeadPresent(): void
    {
        $mock = Mockery::mock(
            'Stolt\LeanPackage\Archive[hasHead]',
            [__DIR__, 'foo']
        );

        $mock->shouldReceive('hasHead')
            ->once()
            ->withNoArgs()
            ->andReturn(false);

        $this->expectException(GitHeadNotAvailable::class);
        $this->expectExceptionMessage('No Git HEAD present to create an archive from.');

        $mock->createArchive();
    }

    #[Test]
    public function removeArchiveRemovesArchive(): void
    {
        $this->createTemporaryFiles(
            ['archive-tmp.tar.gz']
        );

        $removed = (new Archive($this->temporaryDirectory, 'archive-tmp'))
            ->removeArchive();

        $this->assertTrue($removed);
    }

    #[Test]
    public function removeArchiveRemovesArchiveReturnsFalseOnNonExistentFile(): void
    {
        $removed = (new Archive($this->temporaryDirectory, 'archive-nonexistent'))
            ->removeArchive();

        $this->assertFalse($removed);
    }

    #[Test]
    public function compareArchiveReturnsExpectedFoundUnexpectedArtifacts(): void
    {
        $unexpectedArtifacts = [
            '.travis.yml',
            'docs/',
            'phpspec.yml.dist',
            'README.md',
        ];

        $fixturesDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'fixtures';

        $foundUnexpectedArtifacts = (new Archive($fixturesDirectory, 'archive-unlean'))
            ->compareArchive($unexpectedArtifacts);

        $this->assertEquals($unexpectedArtifacts, $foundUnexpectedArtifacts);
    }

    #[Test]
    public function compareArchiveReturnsNoFoundUnexpectedArtifactsOnLeanArchive(): void
    {
        $fixturesDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'fixtures';

        $unexpectedArtifacts = [
            '.travis.yml',
            'docs/',
            'phpspec.yml.dist',
            'README.md',
        ];

        $foundUnexpectedArtifacts = (new Archive($fixturesDirectory, 'archive-lean'))
            ->compareArchive($unexpectedArtifacts);

        $this->assertEquals([], $foundUnexpectedArtifacts);
    }

    #[Test]
    #[Ticket('https://github.com/raphaelstolt/lean-package-validator/issues/15')]
    public function compareArchiveThrowsExpectedExceptionWhenLicenseFileIsMissing(): void
    {
        $fixturesDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'fixtures';

        $archive = (new Archive($fixturesDirectory, 'archive-licenseless'))
            ->shouldHaveLicenseFile();

        $this->assertTrue($archive->validateLicenseFilePresence());

        $this->expectException(NoLicenseFilePresent::class);
        $this->expectExceptionMessage('No license file present in archive.');

        $archive->compareArchive([]);
    }

    #[Test]
    #[Ticket('https://github.com/raphaelstolt/lean-package-validator/issues/15')]
    public function compareArchiveDoesNotThrowsExceptionOnPresentLicenseFile(): void
    {
        $fixturesDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'fixtures';

        $archive = (new Archive($fixturesDirectory, 'archive-with-license'))
            ->shouldHaveLicenseFile();

        $this->assertTrue($archive->validateLicenseFilePresence());

        $archive->compareArchive([]);
    }

    #[Test]
    public function getUnexpectedArchiveArtifactsDelegatesWork(): void
    {
        $mock = Mockery::mock(
            'Stolt\LeanPackage\Archive[createArchive,compareArchive,removeArchive]',
            [__DIR__, 'foo']
        );

        $mock->shouldReceive('createArchive')
            ->once()
            ->withNoArgs()
            ->andReturn(true);

        $mock->shouldReceive('compareArchive')
            ->once()
            ->withAnyArgs()
            ->andReturn(['CONDUCT.md']);

        $mock->shouldReceive('removeArchive')
            ->once()
            ->withNoArgs([])
            ->andReturn(true);

        $foundUnexpectedArtifacts = $mock->getUnexpectedArchiveArtifacts(['foo']);

        $this->assertEquals(['CONDUCT.md'], $foundUnexpectedArtifacts);
    }
}
