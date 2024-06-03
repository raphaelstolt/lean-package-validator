<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Tests\Archive;

use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Stolt\LeanPackage\Archive;
use Stolt\LeanPackage\Archive\Validator;
use Stolt\LeanPackage\Exceptions\GitArchiveNotValidatedYet;
use Stolt\LeanPackage\Tests\TestCase;

class ValidatorTest extends TestCase
{
    /**
     * Set up test environment.
     */
    protected function setUp(): void
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
    public function getFoundUnexpectedArchiveArtifactsOnNonValidatedArchiveThrowsExpectedException(): void
    {
        $archive = new Archive($this->temporaryDirectory, 'archive-tmp');

        $this->expectException(GitArchiveNotValidatedYet::class);
        $expectedExceptionMessage ='Git archive ' . $archive->getFilename()
                . ' not validated. Run validate first.';
        $this->expectExceptionMessage($expectedExceptionMessage);

        (new Validator($archive))->getFoundUnexpectedArchiveArtifacts();
    }

    #[Test]
    public function ranValidateAllowsAccessorCallOnUnexpectedArchiveArtifacts(): void
    {
        $unexpectedArchiveArtifacts = ['mock-foo'];

        $mock = Mockery::mock(
            'Stolt\LeanPackage\Archive[getUnexpectedArchiveArtifacts]',
            [__DIR__, 'foo']
        );

        $mock->shouldReceive('getUnexpectedArchiveArtifacts')
            ->once()
            ->with($unexpectedArchiveArtifacts)
            ->andReturn([]);

        $validator = new Validator($mock);
        $this->assertTrue($validator->validate($unexpectedArchiveArtifacts));
        $this->assertEquals(
            [],
            $validator->getFoundUnexpectedArchiveArtifacts()
        );
    }

    #[Test]
    public function findingUnexpectedArchiveArtifactsFailsValidation(): void
    {
        $unexpectedArchiveArtifacts = ['mock-foo'];

        $mock = Mockery::mock(
            'Stolt\LeanPackage\Archive[getUnexpectedArchiveArtifacts,getFoundUnexpectedArtifacts]',
            [__DIR__, 'foo']
        );

        $mock->shouldReceive('getUnexpectedArchiveArtifacts')
            ->once()
            ->with($unexpectedArchiveArtifacts)
            ->andReturn($unexpectedArchiveArtifacts);

        $mock->shouldReceive('getFoundUnexpectedArtifacts')
            ->once()
            ->withNoArgs()
            ->andReturn($unexpectedArchiveArtifacts);

        $validator = new Validator($mock);

        $this->assertFalse($validator->validate($unexpectedArchiveArtifacts));
        $this->assertEquals(
            $unexpectedArchiveArtifacts,
            $validator->getFoundUnexpectedArchiveArtifacts()
        );
    }
}
