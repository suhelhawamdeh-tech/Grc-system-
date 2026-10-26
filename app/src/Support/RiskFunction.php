<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\Audit;
use App\Core\Db;
use App\Core\Settings;

/**
 * The risk function's work programme: the procedure catalogue, dated tasks for recurring procedures, and the unified
 * escalation log that limit breaches, contrary decisions and red appetite metrics feed automatically.
 */
final class RiskFunction
{
    public const FREQUENCIES = ['daily', 'weekly', 'monthly', 'quarterly', 'semiannual', 'annual', 'event'];
    public const DATED = ['monthly', 'quarterly', 'semiannual', 'annual'];
    public const APPROVERS = ['director', 'head', 'committee', 'board', 'owner'];
    /** Days after the end of the period by which the output is due, unless the procedure sets its own. */
    public const DUE_DAYS = ['monthly' => 10, 'quarterly' => 30, 'semiannual' => 30, 'annual' => 60];

    public const ESC_SOURCES = ['appetite_red', 'regulatory_breach', 'material_breach', 'contrary_decision', 'critical_event', 'access_restriction', 'data_integrity', 'icaap', 'security_exception', 'other'];
    public const ESC_TO = ['committee_chair', 'board', 'cbl'];

    /** Add catalogue procedures that are not yet in the database (existing ones keep the bank's changes). @return int added */
    public static function syncProcedures(bool $force = false): int
    {
        $lib = require APP_ROOT . '/app/framework/procedures.php';
        if (!$force && Settings::get('rm_procedures_version') === $lib['version']) {
            return 0;
        }
        $now = date('Y-m-d H:i:s');
        $n = 0;
        foreach ($lib['procedures'] as [$code, $section, $freq, $approver, $tAr, $tEn, $oAr, $oEn]) {
            if (Db::val('SELECT 1 FROM rm_procedures WHERE code = ?', [$code])) {
                continue;
            }
            Db::insert('rm_procedures', ['code' => $code, 'section' => $section, 'frequency' => $freq, 'approver' => $approver, 'title_ar' => $tAr, 'title_en' => $tEn, 'output_ar' => $oAr, 'output_en' => $oEn,
                'is_builtin' => 1, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
            $n++;
        }
        Settings::set('rm_procedures_version', $lib['version']);
        return $n;
    }

    /** @return array{key:string,start:string,end:string} the period of a dated frequency that contains $date */
    public static function period(string $freq, string $date): array
    {
        $t = strtotime($date);
        $y = (int)date('Y', $t);
        $m = (int)date('n', $t);
        return match ($freq) {
            'monthly' => ['key' => date('Y-m', $t), 'start' => date('Y-m-01', $t), 'end' => date('Y-m-t', $t)],
            'quarterly' => (function () use ($y, $m) {
                $q = intdiv($m - 1, 3) + 1;
                $s = sprintf('%04d-%02d-01', $y, ($q - 1) * 3 + 1);
                return ['key' => "$y-Q$q", 'start' => $s, 'end' => date('Y-m-t', strtotime($s . ' +2 months'))];
            })(),
            'semiannual' => $m <= 6 ? ['key' => "$y-H1", 'start' => "$y-01-01", 'end' => "$y-06-30"] : ['key' => "$y-H2", 'start' => "$y-07-01", 'end' => "$y-12-31"],
            default => ['key' => (string)$y, 'start' => "$y-01-01", 'end' => "$y-12-31"],
        };
    }

    /** Create this period's task for every active dated procedure (idempotent). @return int created */
    public static function generateTasks(?string $today = null): int
    {
        $today ??= date('Y-m-d');
        $now = date('Y-m-d H:i:s');
        $n = 0;
        foreach (Db::all("SELECT * FROM rm_procedures WHERE is_active = 1 AND frequency IN ('monthly','quarterly','semiannual','annual')") as $p) {
            $per = self::period($p['frequency'], $today);
            if (Db::val('SELECT 1 FROM rm_tasks WHERE procedure_id = ? AND period_key = ?', [$p['id'], $per['key']])) {
                continue;
            }
            $days = $p['due_days'] !== null ? (int)$p['due_days'] : self::DUE_DAYS[$p['frequency']];
            Db::insert('rm_tasks', ['procedure_id' => $p['id'], 'period_key' => $per['key'], 'period_start' => $per['start'], 'period_end' => $per['end'],
                'due_date' => date('Y-m-d', strtotime($per['end'] . ' +' . $days . ' days')), 'assignee_user_id' => $p['assignee_user_id'], 'status' => 'open', 'created_at' => $now]);
            $n++;
        }
        return $n;
    }

    /** Sync the catalogue and create due tasks; cheap enough to call when the workbench opens. */
    public static function ensure(): void
    {
        self::syncProcedures();
        self::generateTasks();
    }

    public static function code(string $table, string $prefix, int $id): string
    {
        $code = sprintf('%s-%s-%04d', $prefix, date('Y'), $id);
        Db::update($table, ['code' => $code], 'id = ?', [$id]);
        return $code;
    }

    /**
     * Record an escalation to the risk committee chair (or the board / the Central Bank). One open escalation per source record.
     * @return int escalation id
     */
    public static function escalate(string $source, ?string $entity, ?int $entityId, string $title, string $detail, int $userId, string $to = 'committee_chair', ?string $section = null, ?string $ceoExcludedReason = null): int
    {
        if ($entity !== null && $entityId !== null && ($id = (int)Db::val("SELECT id FROM rm_escalations WHERE source_entity = ? AND source_id = ? AND source_type = ? AND status <> 'closed'", [$entity, $entityId, $source]))) {
            return $id;
        }
        $now = date('Y-m-d H:i:s');
        $id = Db::insert('rm_escalations', ['source_type' => $source, 'source_entity' => $entity, 'source_id' => $entityId, 'section' => $section, 'title' => mb_substr($title, 0, 250), 'detail' => $detail,
            'escalated_to' => $to, 'ceo_informed' => $ceoExcludedReason === null ? 1 : 0, 'ceo_excluded_reason' => $ceoExcludedReason, 'escalated_at' => $now, 'escalated_by' => $userId, 'status' => 'open', 'created_at' => $now, 'updated_at' => $now]);
        $code = self::code('rm_escalations', 'ESC', $id);
        Audit::log('escalation.created', 'rm_escalation', $id, ['code' => $code, 'source' => $source, 'to' => $to]);
        return $id;
    }

    /** A decision is contrary to the risk opinion when a reservation was overridden, or a conditional opinion's conditions were not adopted. */
    public static function isContrary(?string $opinion, ?string $decision, ?int $conditionsAdopted): bool
    {
        if (!in_array($decision, ['approved', 'approved_conditions'], true)) {
            return false;
        }
        return $opinion === 'reservation' || ($opinion === 'conditional' && $conditionsAdopted === 0);
    }

    /** Excess over (or shortfall under) the limit as a percentage of the limit. */
    public static function excessPct(?string $limit, ?string $actual): ?float
    {
        if ($limit === null || $actual === null || (float)$limit == 0.0) {
            return null;
        }
        return round(abs((float)$actual - (float)$limit) / abs((float)$limit) * 100, 1);
    }
}
