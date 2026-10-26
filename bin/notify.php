<?php
declare(strict_types=1);

/**
 * Send e-mail digests and escalations. Run once a day from cPanel cron, e.g.:
 *   0 7 * * *  /usr/local/bin/php /home/ACCOUNT/grc/bin/notify.php
 * Options: --dry-run (count what would be sent, send nothing).
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
use App\Support\Mailer;
use App\Support\Notifier;

if (!Config::load()) {
    fwrite(STDERR, "No config file found.\n");
    exit(2);
}
date_default_timezone_set((string)Config::get('app.timezone', 'Africa/Tripoli'));
Db::init((array)Config::get('db'));
I18n::init('ar');
$sched = \App\Support\Scheduler::run();
echo "rcsa_launched={$sched['rcsa_launched']}\n";
echo "rm_tasks={$sched['rm_tasks']}\n";
echo "security_expired={$sched['security_expired']}\n";
if ($sched['rcsa_launched'] > 0) {
    Audit::log('scheduler.run', 'system', null, $sched, null, 'cron');
}
if (!Mailer::configured()) {
    fwrite(STDERR, "Mail is not configured (add a 'mail' section to the config file).\n");
    exit(3);
}
$dry = in_array('--dry-run', $argv, true);
$r = Notifier::run($dry);
if (!$dry) {
    Audit::log('mail.notify_run', 'system', null, $r, null, 'cron');
}
echo ($dry ? '[dry run] ' : '') . "sent={$r['sent']} failed={$r['failed']} skipped={$r['skipped']} escalations={$r['escalations']}\n";
exit($r['failed'] > 0 ? 1 : 0);
