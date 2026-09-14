<?php
declare(strict_types=1);

define('JFS_ROOT', __DIR__);

spl_autoload_register(static function (string $class): void {
    $prefix = 'JFS\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path = JFS_ROOT . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

if (!is_file(JFS_ROOT . '/config/config.php')) {
    return null;
}

$config = require JFS_ROOT . '/config/config.php';
date_default_timezone_set((string) ($config['app']['timezone'] ?? 'Europe/Berlin'));

return new JFS\Application($config);
