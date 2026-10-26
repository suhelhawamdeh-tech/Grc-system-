<?php
declare(strict_types=1);

/**
 * Builds the deployment ZIP: php bin/package.php [output.zip]   (default: dist/grc-platform-<version>.zip)
 * Contains runtime code only (no Composer/Node dependencies exist). Excludes tests, VCS data, configs,
 * secrets, uploads, logs and sessions. Regenerates database/install.sql first.
 */
define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/app/bootstrap.php';

passthru('php ' . escapeshellarg(__DIR__ . '/build-sql.php'), $rc);
if ($rc !== 0) {
    exit(1);
}
$out = $argv[1] ?? APP_ROOT . '/dist/grc-platform-' . APP_VERSION . '.zip';
@mkdir(dirname($out), 0755, true);
@unlink($out);
$zip = new ZipArchive();
if ($zip->open($out, ZipArchive::CREATE) !== true) {
    fwrite(STDERR, "Cannot create $out\n");
    exit(1);
}
$top = 'grc-platform';
$include = ['app', 'public', 'database', 'bin', 'docs', 'config/.htaccess', 'storage', '.htaccess', 'README.md', 'CHANGELOG.md', 'LICENSE'];
$skipPath = '#(^|/)(tests|\.git|node_modules|dist)(/|$)#';
$skipFile = '#(^|/)(config\.php|\.env[^/]*|installed\.lock|setup-token\.txt|app-path\.php|.*\.log|server\.log|\.DS_Store)$#';
$count = 0;
$add = static function (string $abs, string $rel) use ($zip, $top, $skipPath, $skipFile, &$count): void {
    if (preg_match($skipPath, $rel) || preg_match($skipFile, $rel)) {
        return;
    }
    // storage runtime content is never packaged; only the skeleton (.htaccess / .gitkeep) is.
    if (preg_match('#^storage/(?:[^/]+/)?(.+)$#', $rel, $m) && !preg_match('#^(\.htaccess|\.gitkeep)$#', $m[1])) {
        return;
    }
    $zip->addFile($abs, $top . '/' . $rel);
    $count++;
};
foreach ($include as $item) {
    $abs = APP_ROOT . '/' . $item;
    if (is_file($abs)) {
        $add($abs, $item);
    } elseif (is_dir($abs)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($abs, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile()) {
                $add($f->getPathname(), substr($f->getPathname(), strlen(APP_ROOT) + 1));
            }
        }
    }
}
// Empty storage skeleton with deny rules and index guards.
foreach (['logs', 'uploads', 'sessions', 'cache'] as $d) {
    $zip->addFromString("$top/storage/$d/index.html", '');
}
$zip->addFromString("$top/VERSION", APP_VERSION . "\n");
$zip->close();
echo "Wrote $out ($count files, " . round(filesize($out) / 1024) . " KB)\n";
