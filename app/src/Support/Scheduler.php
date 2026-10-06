<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\Audit;
use App\Core\Db;

/** Work that must happen on a date, run by the daily cron job (bin/notify.php). */
final class Scheduler
{
    /** @return array{rcsa_launched:int} */
    public static function run(): array
    {
        $out = ['rcsa_launched' => 0];
        $today = date('Y-m-d');
        foreach (Db::all('SELECT * FROM rcsa_schedules WHERE is_active = 1 AND next_run <= ? ORDER BY next_run, id', [$today]) as $s) {
            if (!Db::val('SELECT 1 FROM rcsa_templates WHERE id = ? AND is_active = 1', [$s['template_id']]) || !Db::val('SELECT 1 FROM users WHERE id = ? AND is_active = 1', [$s['assessor_user_id']])) {
                continue; // inactive template or assessor: leave the schedule untouched so it is visible and fixable
            }
            $id = RcsaLauncher::launch((int)$s['template_id'], (int)$s['department_id'], $s['branch_id'] ? (int)$s['branch_id'] : null, null, (int)$s['assessor_user_id'], date('Y-m-d', strtotime($today . ' +' . (int)$s['lead_days'] . ' days')), (int)$s['created_by'], 'scheduled');
            $next = $s['next_run'];
            do {
                $next = RcsaLauncher::advance($next, $s['frequency']);
            } while ($next <= $today);
            Db::update('rcsa_schedules', ['last_run_on' => $today, 'next_run' => $next], 'id = ?', [$s['id']]);
            Audit::log('rcsa.scheduled_launch', 'rcsa', $id, ['schedule_id' => (int)$s['id']], null, 'cron');
            $out['rcsa_launched']++;
        }
        return $out;
    }
}
