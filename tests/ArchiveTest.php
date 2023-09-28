<?php

namespace Stolt\LeanPackage\Tests;

use Mockery;
use Stolt\LeanPackage\Archive;
use Stolt\LeanPackage\Exceptions\GitHeadNotAvailable;
use Stolt\LeanPackage\Exceptions\GitNotAvailable;
use Stolt\LeanPackage\Exceptions\NoLicenseFilePresent;
use Stolt\LeanPackage\Tests\TestCase;

class ArchiveTest extends TestCase
{
    public function setUp():void
    {
        $this->setUpTemporaryDirectory();
    }

    /**
     * Tear down test environment.
     *
     * @return void
     */
    protected function tearDown():void
    {
        if (\is_dir($this->temporaryDirectory)) {
            $this->removeDirectory($this->temporaryDirectory);
        }
    }

    /**
     * @test
     */
    public function filenameHasAnAccessor()
    {
        $analyser = new Archive(__DIR__, 'foo');

        $expectedFilename = __DIR__
            . DIRECTORY_SEPARATOR
            . 'foo.tar.gz';

        $this->assertEquals($expectedFilename, $analyser->getFilename());
    }
    /**
     * @test
     */
    public function isGitCommandAvailableReturnsFalseOnNonExistingCommand()
    {
        $this->assertFalse((new Archive(__DIR__, 'foo'))->isGitCommandAvailable('no-way-i-exists'));
    }

    /**
     * @test
     * @group travis-ci-exclude
     */
    public function isGitCommandAvailableReturnsTrueOnExistingCommand()
    {
        $this->assertTrue((new Archive(__DIR__, 'foo'))->isGitCommandAvailable('ping'));
    }

    /**
     * @test
     */
    public function hasHeadThrowsExpectedExceptionWhenGitCommandNotAvailable()
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

    /**
     * @test
     */
    public function createArchiveThrowsExpectedExceptionWhenNoGitHeadPresent()
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

    /**
     * @test
     */
    public function removeArchiveRemovesArchive()
    {
        $this->createTemporaryFiles(
            ['archive-tmp.tar.gz']
        );

        $removed = (new Archive($this->temporaryDirectory, 'archive-tmp'))
            ->removeArchive();

        $this->assertTrue($removed);
    }

    /**
     * @test
     */
    public function removeArchiveRemovesArchiveReturnsFalseOnNonExtistentFile()
    {
        $removed = (new Archive($this->temporaryDirectory, 'archive-nonexistent'))
            ->removeArchive();

        $this->assertFalse($removed);
    }

    /**
     * @test
     */
    public function compareArchiveReturnsExpectedFoundUnexpectedArtifacts()
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

    /**
     * @test
     */
    public function compareArchiveReturnsNoFoundUnexpectedArtifactsOnLeanArchive()
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

    /**
     * @test
     * @ticket 15 (https://github.com/raphaelstolt/lean-package-validator/issues/15)
     */
    public function compareArchiveThrowsExpectedExceptionWhenLicenseFileIsMissing()
    {
        $fixturesDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'fixtures';

        $archive = (new Archive($fixturesDirectory, 'archive-licenseless'))
            ->shouldHaveLicenseFile();

        $this->assertTrue($archive->validateLicenseFilePresence());

        $this->expectException(NoLicenseFilePresent::class);
        $this->expectExceptionMessage('No license file present in archive.');

        $archive->compareArchive([]);
    }

    /**
     * @test
     * @ticket 15 (https://github.com/raphaelstolt/lean-package-validator/issues/15)
     */
    public function compareArchiveDoesNotThrowsExceptionOnPresentLicenseFile()
    {
        $fixturesDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'fixtures';

        $archive = (new Archive($fixturesDirectory, 'archive-with-license'))
            ->shouldHaveLicenseFile();

        $this->assertTrue($archive->validateLicenseFilePresence());

        $archive->compareArchive([]);
    }

    /**
     * @test
     */
    public function getUnexpectedArchiveArtifactsDelegatesWork()
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
