<?php
// Development-only router for PHP's built-in server: php -S localhost:8080 bin/dev-router.php (run from the project root).
// NOT for production: use Apache with public/ as the document root.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base = getenv('GRC_TEST_BASE') ?: '';
if ($base !== '' && str_starts_with($path, $base)) {
    $rel = substr($path, strlen($base));
} else {
    $rel = $path;
}
$file = getcwd() . '/public' . $rel;
if ($rel !== '/' && is_file($file) && !str_ends_with($file, '.php')) {
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    $types = ['css' => 'text/css', 'js' => 'application/javascript'];
    header('Content-Type: ' . ($types[$ext] ?? 'application/octet-stream'));
    readfile($file);
    return true;
}
$_SERVER['SCRIPT_NAME'] = ($base !== '' ? $base : '') . '/index.php';
require getcwd() . '/public/index.php';
