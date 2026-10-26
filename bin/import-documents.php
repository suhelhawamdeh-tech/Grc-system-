<?php
declare(strict_types=1);

/**
 * Import the bank's policies, charters, manuals and templates (.docx; Excel tools and PDF plans are registered with their file) into the document register and the reference library.
 *   php bin/import-documents.php /home/ACCOUNT/risk-documents            (every .docx, .xlsx and .pdf in the folder)
 *   php bin/import-documents.php file1.docx file2.docx --user=risk.manager
 *   php bin/import-documents.php /path/to/folder --dry-run                (show what would happen)
 * The documents stay on the bank's server: keep the folder outside public_html and never commit it.
 * Exit codes: 0 all imported, 1 some files failed, 2 usage or configuration error.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}
define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/app/bootstrap.php';

use App\Core\Config;
use App\Core\Db;
use App\Core\I18n;
use App\Support\DocImporter;

$args = array_slice($argv, 1);
$opts = ['dry-run' => false, 'user' => null];
$paths = [];
foreach ($args as $a) {
    if ($a === '--dry-run') {
        $opts['dry-run'] = true;
    } elseif (str_starts_with($a, '--user=')) {
        $opts['user'] = substr($a, 7);
    } elseif ($a === '--help' || str_starts_with($a, '-')) {
        $paths = [];
        break;
    } else {
        $paths[] = $a;
    }
}
if (!$paths) {
    fwrite(STDERR, "Usage: php bin/import-documents.php FOLDER|FILE ... [--user=USERNAME] [--dry-run]\n");
    exit(2);
}
if (!Config::load()) {
    fwrite(STDERR, "No config file found.\n");
    exit(2);
}
date_default_timezone_set((string)Config::get('app.timezone', 'Africa/Tripoli'));
Db::init((array)Config::get('db'));
I18n::init('en');

$uid = $opts['user'] !== null
    ? (int)Db::val('SELECT id FROM users WHERE username = ? AND is_active = 1', [$opts['user']])
    : (int)Db::val("SELECT id FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id LIMIT 1");
if (!$uid) {
    fwrite(STDERR, "User not found or inactive.\n");
    exit(2);
}

$files = [];
foreach ($paths as $p) {
    if (is_dir($p)) {
        foreach (['docx', 'xlsx', 'pdf'] as $ext) {
            $files = array_merge($files, glob(rtrim($p, '/') . '/*.' . $ext) ?: []);
        }
    } elseif (is_file($p)) {
        $files[] = $p;
    } else {
        fwrite(STDERR, "Not found: $p\n");
    }
}
sort($files);
// Parents first: charters and frameworks, then policies, then manuals and templates, so parent links resolve in one run.
$rank = static function (string $f): int {
    $b = strtoupper(basename($f));
    foreach (['-CHR-' => 0, '-RAF-' => 1, '-ERM-' => 2, '-POL-' => 3, '-MAN-' => 4] as $k => $r) {
        if (str_contains($b, $k)) {
            return $r;
        }
    }
    return 5;
};
usort($files, fn($a, $b) => [$rank($a), $a] <=> [$rank($b), $b]);

$failed = 0;
foreach ($files as $f) {
    $name = basename($f);
    if ($err = DocImporter::check($f, $name)) {
        $failed++;
        fwrite(STDERR, "SKIP  $name: " . t($err) . "\n");
        continue;
    }
    try {
        $r = DocImporter::import($f, $name, $uid, false, $opts['dry-run']);
        printf("%-5s %-18s v%-5s %-12s %3d passages  %s%s\n", $opts['dry-run'] ? 'DRY' : 'OK', $r['code'] ?: '(no code)', $r['version'], $r['action'], $r['passages'], $name,
            $r['refs'] ? '  refs: ' . implode(', ', $r['refs']) : '');
    } catch (Throwable $e) {
        $failed++;
        fwrite(STDERR, "FAIL  $name: " . $e->getMessage() . "\n");
    }
}
if (!$opts['dry-run']) {
    foreach (DocImporter::missingReferences() as $code => $by) {
        echo "MISSING  $code  (referenced by " . implode(', ', $by) . ")\n";
    }
}
exit($failed ? 1 : 0);
