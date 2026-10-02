<?php

declare(strict_types=1);

return [
    'peel' => [
        'sections' => [
            'require-dev',
            'autoload-dev',
            'scripts',
            'scripts-descriptions',
            'scripts-aliases',
        ],
    ],
    'release' => [
        'backup' => [
            'enabled' => true,
            'path' => '.composer-unpeeled.json',
        ],
        'files' => [
            'CHANGELOG.md',
            'bin/',
        ],
        'commit_message' => 'Release {{version}}',
    ],
    'rollback' => [
        'commit_message' => 'Restores development Composer manifest',
    ],
];
