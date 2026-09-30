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
    ],
    'git' => [
        'commit_messages' => [
            'before_tag' => 'Prepares Composer manifest for release',
            'after_tag' => 'Restores development Composer manifest',
        ],
    ],
];
