<?php

declare(strict_types=1);


use Stolt\LeanPackage\Analyser;
use Stolt\LeanPackage\Commands\MissingPipeCommand;
use Stolt\LeanPackage\Tests\CommandTester;
use Stolt\LeanPackage\Tests\TestCase;
use Symfony\Component\Console\Application;

class MissingPipeCommandTest extends TestCase
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
        $this->application = $this->getApplication();
    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function readsFromPipeAsExpected(): void
    {
        $this->markTestIncomplete('Pending implementation');

        $command = $this->application->find('missing');
        $commandTester = new CommandTester($command);

        $pipedCatOutput = <<<OUTPUT
* text=auto eol=lf

.editorconfig export-ignore
.gitattributes export-ignore
.github/ export-ignore
.gitignore export-ignore
.gitmessage export-ignore
.markdownlint.json export-ignore
.php-cs-fixer.php export-ignore
.phpunit.result.cache export-ignore
bin/application-version export-ignore
bin/lean-package-validator.phar export-ignore
bin/release-version export-ignore
bin/start-watchman export-ignore
box.json.dist export-ignore
CHANGELOG.md export-ignore
example/ export-ignore
LICENSE.md export-ignore
phpstan.neon.dist export-ignore
phpunit.xml.dist export-ignore
README.md export-ignore
tests/ export-ignore
OUTPUT;

        $commandTester->setInputs([$pipedCatOutput]);
        $commandTester->execute([]);

        $expectedDisplay = 'TBD';

        $this->assertSame($expectedDisplay, $commandTester->getDisplay());
        $commandTester->assertCommandIsSuccessful();
    }

    /**
     * @return \Symfony\Component\Console\Application
     */
    protected function getApplication(): Application
    {
        $application = new Application();
        $application->add(new MissingPipeCommand(new Analyser));

        return $application;
    }
}
