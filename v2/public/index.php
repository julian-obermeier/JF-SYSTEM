<?php
declare(strict_types=1);

$application = require dirname(__DIR__) . '/bootstrap.php';
require dirname(__DIR__) . '/app/helpers.php';

if (!$application instanceof JFS\Application) {
    header('Location: /install/');
    exit;
}

$application->run();
