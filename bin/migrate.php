<?php
declare(strict_types=1);

/** Apply pending database migrations from the command line: php bin/migrate.php */
if (PHP_SAPI !== 'cli') {
    exit(1);
}
define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/app/bootstrap.php';

use App\Core\Config;
use App\Core\Db;
use App\Core\Migrator;

if (!Config::load()) {
    fwrite(STDERR, "No config file found. Run the web setup or bin/install.php first.\n");
    exit(2);
}
Db::init((array)Config::get('db'));
$pending = array_keys(Migrator::pending());
echo $pending ? 'Pending: ' . implode(', ', $pending) . "\n" : "Nothing to migrate.\n";
if ($pending) {
    echo 'Applied: ' . implode(', ', Migrator::run()) . "\n";
}
