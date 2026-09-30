<?php
declare(strict_types=1);

/**
 * CLI installer (alternative to the web setup wizard; used by the automated tests).
 * All secrets come from environment variables, never from the command line or source:
 *   GRC_DB_HOST GRC_DB_PORT GRC_DB_NAME GRC_DB_USER GRC_DB_PASS
 *   GRC_ADMIN_USER GRC_ADMIN_NAME GRC_ADMIN_EMAIL GRC_ADMIN_PASS
 *   GRC_DEMO=1 GRC_DEMO_PASS=...   (optional synthetic data)
 *   GRC_CONFIG_FILE=/path/config.php  (where to write the config; default ../grc-config/config.php)
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}
define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/app/bootstrap.php';

use App\Core\Auth;
use App\Core\Config;
use App\Core\Db;
use App\Core\Installer;
use App\Core\Migrator;
use App\Core\Settings;
use App\Support\DemoSeeder;

$env = static fn(string $k, ?string $d = null): ?string => ($v = getenv($k)) !== false && $v !== '' ? $v : $d;
foreach (['GRC_DB_NAME', 'GRC_DB_USER', 'GRC_ADMIN_USER', 'GRC_ADMIN_PASS'] as $req) {
    if (!$env($req)) {
        fwrite(STDERR, "Missing environment variable $req\n");
        exit(2);
    }
}
$db = ['host' => $env('GRC_DB_HOST', 'localhost'), 'port' => (int)$env('GRC_DB_PORT', '3306'), 'name' => $env('GRC_DB_NAME'), 'user' => $env('GRC_DB_USER'), 'pass' => (string)$env('GRC_DB_PASS', '')];
$cfg = ['db' => $db, 'app' => ['env' => $env('GRC_APP_ENV', 'production'), 'debug' => false, 'timezone' => 'Africa/Tripoli', 'force_https' => false, 'trust_proxy' => false]];
$target = $env('GRC_CONFIG_FILE', dirname(APP_ROOT) . '/grc-config/config.php');
if (!is_dir(dirname($target)) && !mkdir(dirname($target), 0700, true)) {
    fwrite(STDERR, "Cannot create " . dirname($target) . "\n");
    exit(3);
}
file_put_contents($target, Config::render($cfg));
chmod($target, 0600);
Config::set($cfg);
date_default_timezone_set('Africa/Tripoli');
Db::init($db);
if (Installer::isInstalled()) {
    fwrite(STDERR, "Already installed (users exist or storage/installed.lock present). Aborting.\n");
    exit(4);
}
if ($e = Auth::checkPasswordPolicy((string)$env('GRC_ADMIN_PASS'), (string)$env('GRC_ADMIN_USER'))) {
    fwrite(STDERR, "Admin password rejected by policy ($e)\n");
    exit(5);
}
$done = Migrator::run();
echo 'Migrations applied: ' . implode(', ', $done) . "\n";
$now = date('Y-m-d H:i:s');
Settings::set('default_lang', $env('GRC_DEFAULT_LANG', 'ar'));
$adminId = Db::insert('users', ['username' => mb_strtolower((string)$env('GRC_ADMIN_USER')), 'email' => $env('GRC_ADMIN_EMAIL'), 'full_name' => (string)$env('GRC_ADMIN_NAME', 'System Administrator'),
    'password_hash' => password_hash((string)$env('GRC_ADMIN_PASS'), PASSWORD_DEFAULT), 'role' => 'admin', 'is_active' => 1, 'must_change_password' => 0, 'password_changed_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
if ($env('GRC_DEMO') === '1') {
    $pw = (string)$env('GRC_DEMO_PASS', '');
    if ($e = Auth::checkPasswordPolicy($pw)) {
        fwrite(STDERR, "Demo password rejected by policy ($e)\n");
        exit(6);
    }
    $users = DemoSeeder::run($pw, $adminId);
    echo 'Demo users: ' . implode(', ', $users) . "\n";
}
\App\Core\Audit::log('setup.completed', 'user', $adminId, ['via' => 'cli'], $adminId, (string)$env('GRC_ADMIN_USER'));
Installer::writeLock();
echo "Installed. Config written to $target\n";
