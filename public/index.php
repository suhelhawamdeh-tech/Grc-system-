<?php
declare(strict_types=1);

// Front controller. Works when this "public" folder is the document root of a (sub)domain,
// or when the application code lives elsewhere (see app-path.php.example and docs/DEPLOYMENT_BLUEHOST.md).
$root = is_file(__DIR__ . '/../app/bootstrap.php') ? dirname(__DIR__) : null;
if ($root === null && is_file(__DIR__ . '/app-path.php')) {
    $root = require __DIR__ . '/app-path.php';
}
if (!is_string($root) || !is_file($root . '/app/bootstrap.php')) {
    http_response_code(500);
    echo 'Application files not found. See docs/DEPLOYMENT_BLUEHOST.md (app-path.php).';
    exit;
}
define('APP_ROOT', rtrim($root, '/\\'));
require APP_ROOT . '/app/bootstrap.php';
App\Core\App::run();
