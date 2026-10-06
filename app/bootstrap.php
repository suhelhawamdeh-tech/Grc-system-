<?php
declare(strict_types=1);

// Application bootstrap. APP_ROOT is the directory that contains app/, storage/, database/.
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}
const APP_VERSION = '1.1.0';
const MIN_PHP = '8.1.0';

spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'App\\', 4) !== 0) {
        return;
    }
    $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require __DIR__ . '/src/helpers.php';
