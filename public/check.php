<?php
declare(strict_types=1);

/**
 * Pre-installation compatibility check. Standalone: does not need the application, config or database.
 *   Browser : https://your-domain/check.php     (disabled automatically once the platform is installed)
 *   CLI     : php public/check.php
 * Delete this file after a successful installation if you prefer.
 */
$cli = PHP_SAPI === 'cli';
$root = is_file(__DIR__ . '/../app/bootstrap.php') ? dirname(__DIR__) : (is_file(__DIR__ . '/app-path.php') ? (string)(require __DIR__ . '/app-path.php') : dirname(__DIR__));
$storage = $root . '/storage';

if (!$cli && is_file($storage . '/installed.lock')) {
    http_response_code(404);
    echo 'Not found.';
    exit;
}

$rows = [];
$add = static function (string $group, string $name, string $result, string $level, string $hint = '') use (&$rows): void {
    $rows[] = compact('group', 'name', 'result', 'level', 'hint');
};
$ok = 'PASS'; $warn = 'WARN'; $fail = 'FAIL';

$add('PHP', 'PHP version >= 8.1', PHP_VERSION, version_compare(PHP_VERSION, '8.1.0', '>=') ? $ok : $fail, 'Select PHP 8.1, 8.2 or 8.3 in cPanel > MultiPHP Manager.');
foreach (['pdo' => 'PDO', 'pdo_mysql' => 'PDO MySQL driver', 'mbstring' => 'mbstring (Arabic text handling)', 'json' => 'JSON', 'fileinfo' => 'fileinfo (upload MIME checks)', 'ctype' => 'ctype', 'session' => 'Sessions', 'hash' => 'hash'] as $ext => $label) {
    $add('Required extensions', $label, extension_loaded($ext) ? 'loaded' : 'MISSING', extension_loaded($ext) ? $ok : $fail, 'Enable "' . $ext . '" in cPanel > Select PHP Version > Extensions.');
}
foreach (['openssl' => 'OpenSSL', 'zip' => 'zip (extra validation of .docx/.xlsx uploads)', 'intl' => 'intl'] as $ext => $label) {
    $add('Optional extensions', $label, extension_loaded($ext) ? 'loaded' : 'not loaded', extension_loaded($ext) ? $ok : $warn, 'Optional.');
}
$add('Security functions', 'password_hash (bcrypt/argon2)', function_exists('password_hash') && defined('PASSWORD_DEFAULT') ? 'available' : 'MISSING', function_exists('password_hash') ? $ok : $fail);
$add('Security functions', 'random_bytes', function_exists('random_bytes') ? 'available' : 'MISSING', function_exists('random_bytes') ? $ok : $fail);

$toBytes = static function (string $v): int {
    $v = trim($v);
    $n = (int)$v;
    return match (strtolower(substr($v, -1))) { 'g' => $n * 1073741824, 'm' => $n * 1048576, 'k' => $n * 1024, default => $n };
};
$up = ini_get('upload_max_filesize');
$add('PHP limits', 'upload_max_filesize >= 6M (app limit is 5 MB)', (string)$up, $toBytes((string)$up) >= 6 * 1048576 ? $ok : $warn, 'Attachments larger than this limit will be refused.');
$pm = ini_get('post_max_size');
$add('PHP limits', 'post_max_size >= 8M', (string)$pm, $toBytes((string)$pm) >= 8 * 1048576 ? $ok : $warn);
$mem = ini_get('memory_limit');
$add('PHP limits', 'memory_limit >= 128M', (string)$mem, $mem === '-1' || $toBytes((string)$mem) >= 128 * 1048576 ? $ok : $warn);
$add('PHP limits', 'file_uploads enabled', ini_get('file_uploads') ? 'on' : 'off', ini_get('file_uploads') ? $ok : $fail);

$add('Files', 'Application folder found', is_file($root . '/app/bootstrap.php') ? $root : 'NOT FOUND: ' . $root, is_file($root . '/app/bootstrap.php') ? $ok : $fail, 'If the app is outside this folder, copy app-path.php.example to app-path.php and set the path.');
$add('Files', 'database/migrations present', is_dir($root . '/database/migrations') ? 'yes' : 'no', is_dir($root . '/database/migrations') ? $ok : $fail);
foreach (['logs', 'uploads', 'sessions', 'cache'] as $d) {
    $p = $storage . '/' . $d;
    if (!is_dir($p)) {
        @mkdir($p, 0750, true);
    }
    $add('Writable folders', 'storage/' . $d, is_dir($p) && is_writable($p) ? 'writable' : 'NOT writable', is_dir($p) && is_writable($p) ? $ok : $fail, 'Set permissions to 750 (or 755) and ensure the folder belongs to your cPanel user.');
}
$cfgDir = dirname($root) . '/grc-config';
$cfgOk = (is_dir($cfgDir) && is_writable($cfgDir)) || (!is_dir($cfgDir) && is_writable(dirname($root)));
$add('Configuration', 'Config can be stored outside the app folder (' . $cfgDir . ')', $cfgOk ? 'yes' : 'no', $cfgOk ? $ok : $warn, 'If "no", setup falls back to ' . $root . '/config (protected by .htaccess) or shows the file for manual creation.');

if (!$cli) {
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $add('Web server', 'HTTPS', $https ? 'yes' : 'no', $https ? $ok : $warn, 'Enable AutoSSL / Let\'s Encrypt in cPanel > SSL/TLS Status before real use.');
    $rewrite = function_exists('apache_get_modules') ? in_array('mod_rewrite', apache_get_modules(), true) : null;
    $add('Web server', 'mod_rewrite', $rewrite === null ? 'cannot detect (CGI/FPM) - Bluehost enables it by default' : ($rewrite ? 'enabled' : 'disabled'), $rewrite === false ? $fail : ($rewrite === null ? $warn : $ok));
    $docroot = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? '')) ?: '';
    $inside = $docroot !== '' && str_starts_with((string)realpath($root), $docroot . DIRECTORY_SEPARATOR);
    $add('Web server', 'Application code outside the public document root', $inside ? 'NO (inside document root)' : 'yes', $inside ? $warn : $ok, 'Preferred layout: only the public/ folder is web-accessible. If everything is inside the document root, keep the bundled .htaccess files.');
}

$failed = count(array_filter($rows, static fn($r) => $r['level'] === $fail));
$warned = count(array_filter($rows, static fn($r) => $r['level'] === $warn));

if ($cli) {
    foreach ($rows as $r) {
        printf("[%s] %s: %s%s\n", $r['level'], $r['name'], $r['result'], $r['level'] !== $ok && $r['hint'] ? '  -> ' . $r['hint'] : '');
    }
    printf("\n%d failed, %d warning(s). %s\n", $failed, $warned, $failed ? 'NOT ready for installation.' : 'Ready for installation.');
    exit($failed ? 1 : 0);
}
header('Content-Type: text/html; charset=UTF-8');
header('X-Robots-Tag: noindex');
header('Cache-Control: no-store');
$h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>GRC compatibility check</title>
<style>body{font:16px/1.5 system-ui,Segoe UI,Arial;margin:0;background:#f4f6f9;color:#1d2733}main{max-width:900px;margin:0 auto;padding:24px 16px}h1{color:#0b2545}
table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #d5dbe3}td,th{padding:8px 10px;border-bottom:1px solid #e6eaf0;text-align:left;vertical-align:top}th{background:#eaeff6}
.PASS{color:#1b6e2f;font-weight:700}.WARN{color:#8a5a00;font-weight:700}.FAIL{color:#a61e1e;font-weight:700}.box{padding:12px 16px;border-radius:6px;margin:16px 0}.ok{background:#e5f4e8}.bad{background:#fdeaea}small{color:#566274}</style></head><body><main>
<h1>GRC platform &mdash; compatibility check</h1>
<div class="box <?= $failed ? 'bad' : 'ok' ?>"><strong><?= $failed ? $failed . ' blocking problem(s) found.' : 'All required checks passed.' ?></strong> <?= $warned ? $warned . ' warning(s).' : '' ?></div>
<table><thead><tr><th>Check</th><th>Result</th><th>Status</th></tr></thead><tbody>
<?php $g = ''; foreach ($rows as $r): if ($r['group'] !== $g): $g = $r['group']; ?><tr><th colspan="3"><?= $h($g) ?></th></tr><?php endif; ?>
<tr><td><?= $h($r['name']) ?><?= $r['hint'] && $r['level'] !== $ok ? '<br><small>' . $h($r['hint']) . '</small>' : '' ?></td><td><?= $h($r['result']) ?></td><td class="<?= $h($r['level']) ?>"><?= $h($r['level']) ?></td></tr>
<?php endforeach; ?></tbody></table>
<p><small>This page discloses server details. It disables itself once installation is complete; you may also delete <code>check.php</code>. It checks PHP capabilities only &mdash; it cannot verify database credentials or your host's MySQL version (MySQL 5.7+ / MariaDB 10.3+ required).</small></p>
</main></body></html>
