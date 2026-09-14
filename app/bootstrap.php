<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

if (!is_file(APP_ROOT . '/config/config.php')) {
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $target = str_contains($script, '/public/') ? '../install/' : 'install/';
    header('Location: ' . $target);
    exit;
}

require APP_ROOT . '/app/Database.php';
require APP_ROOT . '/app/helpers.php';

$config = app_config();
date_default_timezone_set($config['app']['timezone'] ?? 'Europe/Berlin');

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

session_name('jfsystem_session');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

require APP_ROOT . '/app/Auth.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
