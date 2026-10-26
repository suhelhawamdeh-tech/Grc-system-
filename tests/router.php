<?php
// Run with cwd = project root (the tests copy the app to a temp dir and cd there).
// Router script for PHP's built-in server (development / tests only). Mimics public/.htaccess.
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
