<?php

namespace Stolt\LeanPackage\Tests\Archive;

use Mockery;
use Stolt\LeanPackage\Tests\TestCase;
use Stolt\LeanPackage\Archive;
use Stolt\LeanPackage\Archive\Validator;
use Stolt\LeanPackage\Exceptions\GitArchiveNotValidatedYet;

class ValidatorTest extends TestCase
{
    /**
     * Set up test environment.
     */
    protected function setUp()
    {
        $this->setUpTemporaryDirectory();
    }

    /**
     * Tear down test environment.
     *
     * @return void
     */
    protected function tearDown()
    {
        if (is_dir($this->temporaryDirectory)) {
            $this->removeDirectory($this->temporaryDirectory);
        }
    }

    /**
     * @test
     */
    public function getFoundUnexpectedArchiveArtifactsOnNonValidatedArchiveThrowsExpectedException()
    {
        $archive = new Archive($this->temporaryDirectory, 'archive-tmp');

        $this->expectException(GitArchiveNotValidatedYet::class);
        $expectedExceptionMessage ='Git archive ' . $archive->getFilename()
                . ' not validated. Run validate first.';
        $this->expectExceptionMessage($expectedExceptionMessage);

        (new Validator($archive))->getFoundUnexpectedArchiveArtifacts();
    }

    /**
     * @test
     */
    public function ranValidateAllowsAccessorCallOnUnexpectedArchiveArtifacts()
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

    /**
     * @test
     */
    public function findingUnexpectedArchiveArtifactsFailsValidation()
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
