<?php

$finder = Symfony\CS\Finder\DefaultFinder::create()
    ->in(__DIR__);

$fixers = [
    '-psr0',
    'extra_empty_lines',
    'double_arrow_multiline_whitespaces',
    'short_array_syntax',
    'phpdoc_order',
    'ordered_use',
];

return Symfony\CS\Config\Config::create()
    ->level(Symfony\CS\FixerInterface::PSR2_LEVEL)
    ->fixers($fixers)
    ->finder($finder)
    ->setUsingCache(true);
