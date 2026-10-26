<?php
declare(strict_types=1);

/**
 * Load data from Temenos T24 without a browser (for cron).
 *   php bin/t24-sync.php --dataset=customers --api
 *   php bin/t24-sync.php --dataset=gl --file=/home/ACCOUNT/t24/gl-export.csv
 * The load is recorded against the first active administrator and logged as a scheduled job.
 * Exit codes: 0 loaded, 1 rejected or failed, 2 usage or configuration error.
 */
if (PHP_SAPI !== 'cli') {
    exit(1);
}
define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/app/bootstrap.php';

use App\Core\Audit;
use App\Core\Config;
use App\Core\Db;
use App\Core\I18n;
use App\Support\T24;

$opt = getopt('', ['dataset:', 'file:', 'api', 'help']);
if (isset($opt['help']) || !isset($opt['dataset']) || (!isset($opt['file']) && !isset($opt['api'])) || !isset(T24::DATASETS[(string)$opt['dataset']])) {
    fwrite(STDERR, "Usage: php bin/t24-sync.php --dataset=customers|gl (--api | --file=PATH)\n");
    exit(2);
}
if (!Config::load()) {
    fwrite(STDERR, "No config file found.\n");
    exit(2);
}
date_default_timezone_set((string)Config::get('app.timezone', 'Africa/Tripoli'));
Db::init((array)Config::get('db'));
I18n::init('en');

$ds = (string)$opt['dataset'];
$admin = (int)Db::val("SELECT id FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id LIMIT 1");
if (!$admin) {
    fwrite(STDERR, "No active administrator account.\n");
    exit(2);
}
if (isset($opt['file'])) {
    $path = (string)$opt['file'];
    if (!is_file($path) || !is_readable($path)) {
        fwrite(STDERR, "File not readable: $path\n");
        exit(2);
    }
    $raw = (string)file_get_contents($path);
    $name = basename($path);
    if (preg_match('/\.json$/i', $name)) {
        $recs = T24::recordsFromJson($raw);
        $run = $recs === null ? T24::logFailure($ds, 'cli', $name, t('t24.err_json'), null) : T24::load($ds, $recs, 'cli', $name, $admin, null);
    } else {
        $p = T24::parseFile($raw);
        $run = $p['error'] ? T24::logFailure($ds, 'cli', $name, t($p['error']), null) : T24::load($ds, $p['rows'], 'cli', $name, $admin, null);
    }
} else {
    $r = T24::fetch($ds);
    $run = $r['error'] !== null ? T24::logFailure($ds, 'cli', null, $r['error'], null) : T24::load($ds, $r['records'], 'cli', null, $admin, null);
}
Audit::log('t24.load_' . $run['status'], 'integration', (int)$run['id'], ['dataset' => $ds, 'mode' => 'cli', 'read' => $run['rows_read'], 'created' => $run['rows_created'], 'updated' => $run['rows_updated']]);
echo "run={$run['id']} status={$run['status']} read={$run['rows_read']} created={$run['rows_created']} updated={$run['rows_updated']}\n";
if ($run['status'] !== 'success') {
    fwrite(STDERR, (string)$run['error_text'] . "\n");
    exit(1);
}
exit(0);
