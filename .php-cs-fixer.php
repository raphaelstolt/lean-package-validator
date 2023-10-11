<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in(__DIR__);

$rules = [
    'psr_autoloading' => false,
    '@PSR2' => true,
    'phpdoc_order' => true,
    'ordered_imports' => true,
    'native_function_invocation' => [
        'include' => ['@internal'],
        'exclude' => ['file_put_contents']
    ]
];

$cacheDir = \getenv('HOME') ? \getenv('HOME') : __DIR__;

$config = new Config();

return $config->setRules($rules)
    ->setFinder($finder)
    ->setCacheFile($cacheDir . '/.php-cs-fixer.cache');
