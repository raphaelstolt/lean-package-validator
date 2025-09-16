<?php

declare(strict_types=1);

namespace Stolt\LeanPackage\Tests;

use PHPUnit\Framework\Attributes\Test;
use Stolt\LeanPackage\PhpConfigLoader;

final class PhpConfigLoaderTest extends TestCase
{
    public function setUp(): void
    {
        $this->setUpTemporaryDirectory();
    }

    /**
     * Tear down the test environment.
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
    public function loadThrowsExpectedExceptionOnNonArrayReturn(): void
    {
        $file = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'conf.php';
        file_put_contents($file, '<?php return 123;');

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('The configuration file must return an array.');

        PhpConfigLoader::load($file);
    }

    #[Test]
    public function loadThrowsExpectedExceptionOnUnknownKeys(): void
    {
        $file = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'conf.php';
        file_put_contents($file, "<?php return ['nope' => true];");

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Unknown configuration keys: nope');

        PhpConfigLoader::load($file);
    }

    #[Test]
    public function loadThrowsExpectedExceptionOnInvalidTypes(): void
    {
        $file = $this->temporaryDirectory . DIRECTORY_SEPARATOR . 'conf.php';
        file_put_contents($file, "<?php return ['glob-pattern' => true];");

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Configuration "glob-pattern" must be a string.');

        PhpConfigLoader::load($file);
    }

    #[Test]
    public function discoverFindsDefaultFile(): void
    {
        $cwd = \getcwd() ?: '.';

        try {
            \chdir($this->temporaryDirectory);
            file_put_contents($this->temporaryDirectory . DIRECTORY_SEPARATOR . '.lpv.php.dist', '<?php return [];');

            $discovered = PhpConfigLoader::discover();

            $this->assertNotNull($discovered);
            $this->assertStringEndsWith('.lpv.php.dist', (string) $discovered);
        } finally {
            \chdir($cwd);
        }
    }
}
