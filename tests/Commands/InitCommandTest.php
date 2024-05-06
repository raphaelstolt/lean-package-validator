<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Tests\Commands;

use phpmock\functions\FixedValueFunction;
use phpmock\MockBuilder;
use Stolt\LeanPackage\Analyser;
use Stolt\LeanPackage\Commands\InitCommand;
use Stolt\LeanPackage\Presets\Finder;
use Stolt\LeanPackage\Presets\PhpPreset;
use Stolt\LeanPackage\Tests\CommandTester;
use Stolt\LeanPackage\Tests\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Output\OutputInterface;

class InitCommandTest extends TestCase
{
    /**
     * @var \Symfony\Component\Console\Application
     */
    private $application;

    /**
     * Set up test environment.
     */
    protected function setUp(): void
    {
        $this->setUpTemporaryDirectory();
        if (!\defined('WORKING_DIRECTORY')) {
            \define('WORKING_DIRECTORY', $this->temporaryDirectory);
        }
        $this->application = $this->getApplication();
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

    /**
     * @test
     */
    public function createsExpectedDefaultLpvFile(): void
    {
        $command = $this->application->find('init');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
        ]);

        $expectedDefaultLpvFile = $this->temporaryDirectory
            . DIRECTORY_SEPARATOR
            . '.lpv';
        $expectedDisplay = <<<CONTENT
Created default '{$expectedDefaultLpvFile}' file.

CONTENT;

        $expectedDefaultLpvFileContent = <<<CONTENT
.*
*.lock
*.txt
*.rst
*.{md,MD}
*.xml
*.yml
phpunit*
appveyor.yml
box.json
captainhook.json
infection*
phpstan*
sonar*
rector*
pint.json
ecs*
*.dist.*
*.dist
{B,b}uild*
{D,d}oc*
{T,t}ool*
{T,t}est*
{S,s}pec*
{E,e}xample*
LICENSE
{{M,m}ake,{B,b}ox,{V,v}agrant,{P,p}hulp}file
RMT
CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $commandTester->assertCommandIsSuccessful();
        $this->assertFileExists($expectedDefaultLpvFile);
        $this->assertEquals($expectedDefaultLpvFileContent, \file_get_contents($expectedDefaultLpvFile));
    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function failingInitReturnsExpectedStatusCode(): void
    {
        $builder = new MockBuilder();
        $builder->setNamespace('Stolt\LeanPackage\Commands')
            ->setName('file_put_contents')
            ->setFunctionProvider(new FixedValueFunction(false));

        $mock = $builder->build();
        $mock->enable();

        $command = $this->application->find('init');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
        ]);

        $expectedDisplay = <<<CONTENT
Warning: The creation of the default .lpv file failed.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() !== 0);

        $mock->disable();
    }

    /**
     * @test
     */
    public function existingDefaultLpvFileIsNotOverwritten(): void
    {
        $defaultLpvFile = $this->temporaryDirectory
            . DIRECTORY_SEPARATOR
            . '.lpv';
        \touch($defaultLpvFile);

        $command = $this->application->find('init');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
        ]);

        $expectedDisplay = <<<CONTENT
Warning: A default .lpv file already exists.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() !== 0);
    }

    /**
     * @test
     */
    public function usingANonAvailablePresetShowsWarning(): void
    {
        $expectedDisplay = <<<CONTENT
Warning: Chosen preset rust is not available. Maybe contribute it?.

CONTENT;

        $command = $this->application->find('init');
        $commandTester = new CommandTester($command);
        $commandTester->execute(
            ['command' => $command->getName(),
             'directory' => WORKING_DIRECTORY,
             '--preset' => 'rust'],
        );

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() !== 0);
    }

    /**
     * @test
     */
    public function verboseOutputIsAvailableWhenDesired(): void
    {
        $defaultLpvFile = $this->temporaryDirectory
            . DIRECTORY_SEPARATOR
            . '.lpv';

        $expectedDisplay = <<<CONTENT
+ Checking .lpv file existence in {$this->temporaryDirectory}.
Warning: A default .lpv file already exists.

CONTENT;

        \touch($defaultLpvFile);

        $command = $this->application->find('init');
        $commandTester = new CommandTester($command);
        $commandTester->execute(
            ['command' => $command->getName(),
            'directory' => WORKING_DIRECTORY],
            ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]
        );

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $this->assertTrue($commandTester->getStatusCode() !== 0);
    }

    /**
     * @test
     */
    public function existingDefaultLpvFileIsOverwrittenWhenDesired(): void
    {
        $expectedDefaultLpvFile = $this->temporaryDirectory
            . DIRECTORY_SEPARATOR
            . '.lpv';
        \touch($expectedDefaultLpvFile);

        $command = $this->application->find('init');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'command' => $command->getName(),
            'directory' => WORKING_DIRECTORY,
            '-o' => true,
        ]);

        $expectedDisplay = <<<CONTENT
Created default '{$expectedDefaultLpvFile}' file.

CONTENT;

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $commandTester->assertCommandIsSuccessful();
        $this->assertFileExists($expectedDefaultLpvFile);
    }

    /**
     * @return \Symfony\Component\Console\Application
     */
    protected function getApplication(): Application
    {
        $application = new Application();
        $application->add(new InitCommand(new Analyser(new Finder(new PhpPreset()))));

        return $application;
    }
}
