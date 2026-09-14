<?php
declare(strict_types=1);

return [
    'app' => [
        'name' => 'JF-SYSTEM v2',
        'url' => 'http://127.0.0.1:8088',
        'timezone' => 'Europe/Berlin',
        'debug' => true,
        'installed' => true,
    ],
    'database' => [
        'driver' => 'sqlite',
        'path' => dirname(__DIR__) . '/storage/development.sqlite',
    ],
    'security' => [
        'session_name' => 'jfs_v2_session',
        'session_timeout' => 7200,
    ],
];
