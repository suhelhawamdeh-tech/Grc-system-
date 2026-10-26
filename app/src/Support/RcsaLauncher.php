<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\Db;

/** Creates an RCSA assessment with empty answers for every template question. Shared by manual launch, schedules and triggers. */
final class RcsaLauncher
{
    public static function launch(int $template, int $dept, ?int $branch, ?int $period, int $assessor, string $due, int $createdBy, string $origin = 'manual', ?string $reason = null): int
    {
        $now = date('Y-m-d H:i:s');
        return (int)Db::tx(function () use ($template, $dept, $branch, $period, $assessor, $due, $createdBy, $origin, $reason, $now) {
            $id = Db::insert('rcsa_assessments', ['template_id' => $template, 'period_id' => $period, 'department_id' => $dept, 'branch_id' => $branch, 'assessor_user_id' => $assessor, 'due_date' => $due, 'status' => 'draft', 'origin' => $origin, 'trigger_reason' => $reason, 'created_by' => $createdBy, 'created_at' => $now, 'updated_at' => $now]);
            Db::update('rcsa_assessments', ['code' => sprintf('RCSA-%04d', $id)], 'id = ?', [$id]);
            Db::exec('INSERT INTO rcsa_answers (assessment_id, question_id) SELECT ?, q.id FROM rcsa_questions q WHERE q.template_id = ?', [$id, $template]);
            return $id;
        });
    }

    public static function advance(string $from, string $frequency): string
    {
        $step = ['quarterly' => '+3 months', 'semiannual' => '+6 months', 'annual' => '+1 year'][$frequency] ?? '+1 year';
        return date('Y-m-d', strtotime($from . ' ' . $step));
    }

    /**
     * Event-triggered reassessment: when something material happens in a department that has an active schedule,
     * launch one extra assessment (if none was launched for that department in the last 30 days).
     * @return int|null id of the launched assessment
     */
    public static function trigger(int $dept, string $reason, int $byUser): ?int
    {
        $s = Db::one('SELECT * FROM rcsa_schedules WHERE department_id = ? AND is_active = 1 ORDER BY id LIMIT 1', [$dept]);
        if (!$s) {
            return null;
        }
        if (Db::val("SELECT 1 FROM rcsa_assessments WHERE department_id = ? AND template_id = ? AND origin = 'triggered' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) LIMIT 1", [$dept, $s['template_id']])) {
            return null;
        }
        $id = self::launch((int)$s['template_id'], $dept, $s['branch_id'] ? (int)$s['branch_id'] : null, null, (int)$s['assessor_user_id'], date('Y-m-d', strtotime('+14 days')), $byUser, 'triggered', $reason);
        \App\Core\Audit::log('rcsa.triggered', 'rcsa', $id, ['reason' => $reason, 'department' => $dept]);
        return $id;
    }
}
