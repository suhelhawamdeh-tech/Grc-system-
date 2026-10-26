<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\Audit;
use App\Core\Db;
use App\Core\I18n;
use App\Core\Money;

/**
 * Quarterly board risk report, built in the platform on the bank's model: letter, structured rows per section,
 * live data from the registers (priority risks, operational events, scale), and on approval the decisions and
 * departmental recommendations become tracked decisions and actions in the follow-up register.
 */
final class BoardReport
{
    public const KINDS = ['tile', 'profile', 'message', 'source', 'reference', 'observation', 'dq', 'decision', 'recommendation'];
    public const LEVELS = ['low', 'medium', 'high', 'critical', 'assess'];
    public const TRENDS = ['improving', 'stable', 'rising', 'new'];
    public const DECISION_TYPES = ['info', 'approval', 'direction'];

    public static function template(): array
    {
        return require APP_ROOT . '/app/framework/board_report.php';
    }

    /** @return array{0:string,1:string} first and last day of a quarter */
    public static function period(int $year, int $q): array
    {
        $start = sprintf('%04d-%02d-01', $year, ($q - 1) * 3 + 1);
        return [$start, date('Y-m-t', strtotime($start . ' +2 months'))];
    }

    public static function create(int $year, int $quarter, int $userId, string $signatory, string $title): int
    {
        $tpl = self::template();
        $ar = I18n::locale() === 'ar';
        [$from, $to] = self::period($year, $quarter);
        $now = date('Y-m-d H:i:s');
        return Db::tx(function () use ($tpl, $ar, $year, $quarter, $from, $to, $userId, $signatory, $title, $now) {
            $narr = ['s10_followup' => $ar ? $tpl['followup_ar'] : $tpl['followup_en']];
            $id = Db::insert('board_reports', ['year' => $year, 'quarter' => $quarter, 'period_start' => $from, 'period_end' => $to, 'report_date' => date('Y-m-d'), 'status' => 'draft', 'classification' => 'confidential',
                'narratives' => json_encode($narr, JSON_UNESCAPED_UNICODE), 'signatory_name' => $signatory, 'signatory_title' => $title, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now]);
            Db::update('board_reports', ['code' => sprintf('BRR-%04d-Q%d', $year, $quarter)], 'id = ?', [$id]);
            foreach ($tpl['profile'] as $i => [$tAr, $tEn]) {
                Db::insert('board_report_rows', ['report_id' => $id, 'kind' => 'profile', 'sort_order' => $i + 1, 'title' => $ar ? $tAr : $tEn, 'level' => 'assess']);
            }
            Audit::log('board_report.created', 'board_report', $id, ['year' => $year, 'quarter' => $quarter]);
            return $id;
        });
    }

    public static function load(int $id): ?array
    {
        $r = Db::one('SELECT b.*, u.full_name AS creator FROM board_reports b JOIN users u ON u.id = b.created_by WHERE b.id = ?', [$id]);
        if (!$r) {
            return null;
        }
        $r['narr'] = json_decode((string)$r['narratives'], true) ?: [];
        $rows = [];
        foreach (Db::all('SELECT x.*, d.name_en AS dept_en, d.name_ar AS dept_ar FROM board_report_rows x LEFT JOIN departments d ON d.id = x.department_id WHERE x.report_id = ? ORDER BY x.kind, x.sort_order, x.id', [$id]) as $x) {
            $rows[$x['kind']][] = $x;
        }
        return ['r' => $r, 'rows' => $rows];
    }

    /** Residual risks at or above the high band, highest first (section 5). */
    public static function priorityRisks(int $limit = 12): array
    {
        $min = (int)(Db::val('SELECT MIN(min_score) FROM score_bands WHERE is_high = 1') ?? 15);
        return Db::all("SELECT r.id, r.risk_code, r.title, r.res_likelihood, r.res_impact, r.res_score, r.treatment_notes, d.name_en AS dept_en, d.name_ar AS dept_ar FROM risks r JOIN departments d ON d.id = r.department_id
            WHERE r.archived_at IS NULL AND r.status = 'active' AND r.res_score >= ? ORDER BY r.res_score DESC, r.risk_code LIMIT ?", [$min, $limit]);
    }

    /** Operational events of the quarter (annex). */
    public static function events(string $from, string $to): array
    {
        return Db::all('SELECT i.id, i.incident_code, i.title, i.occurrence_date, i.net_loss, i.status, c.name_en AS cat_en, c.name_ar AS cat_ar FROM incidents i LEFT JOIN risk_categories c ON c.id = i.category_id
            WHERE i.archived_at IS NULL AND i.occurrence_date BETWEEN ? AND ? ORDER BY i.occurrence_date, i.id', [$from, $to]);
    }

    public static function bands(): array
    {
        return Db::all('SELECT * FROM score_bands ORDER BY min_score DESC');
    }

    /** Suggested KPI tiles from the platform's own data for the quarter (the user keeps, edits or deletes them). @return int tiles added */
    public static function suggestTiles(int $id): int
    {
        $x = self::load($id);
        $r = $x['r'];
        $ar = I18n::locale() === 'ar';
        $tiles = [];
        // Latest readings of calibrated board metrics with a value in or before the quarter.
        foreach (Db::all("SELECT k.code, k.name, k.unit, (SELECT value FROM kri_readings x WHERE x.kri_id = k.id AND x.reading_date <= ? ORDER BY x.reading_date DESC, x.id DESC LIMIT 1) AS v,
            (SELECT status FROM kri_readings x WHERE x.kri_id = k.id AND x.reading_date <= ? ORDER BY x.reading_date DESC, x.id DESC LIMIT 1) AS st FROM kris k WHERE k.tier = 'board' AND k.is_active = 1 AND k.calibrated = 1 ORDER BY k.code", [$r['period_end'], $r['period_end']]) as $k) {
            if ($k['v'] !== null && count($tiles) < 5) {
                $tiles[] = [$k['name'], (str_contains($p = Money::plain($k['v']), '.') ? rtrim(rtrim($p, '0'), '.') : $p) . ' ' . $k['unit'], $k['code'], match ($k['st']) { 'green' => 'ok', 'amber' => 'warn', default => 'danger' }];
            }
        }
        $ev = Db::one('SELECT COUNT(*) AS n, COALESCE(SUM(net_loss),0) AS loss FROM incidents WHERE archived_at IS NULL AND occurrence_date BETWEEN ? AND ?', [$r['period_start'], $r['period_end']]);
        $tiles[] = [$ar ? 'الأحداث التشغيلية المسجلة' : 'Operational events recorded', (string)(int)$ev['n'], ($ar ? 'صافي الخسارة ' : 'Net loss ') . Money::format($ev['loss']), ''];
        $high = count(self::priorityRisks(500));
        $tiles[] = [$ar ? 'مخاطر متبقية مرتفعة وحرجة' : 'High and critical residual risks', (string)$high, $ar ? 'من سجل المخاطر' : 'from the risk register', $high ? 'warn' : 'ok'];
        $esc = (int)Db::val('SELECT COUNT(*) FROM rm_escalations WHERE escalated_at BETWEEN ? AND ?', [$r['period_start'], $r['period_end'] . ' 23:59:59']);
        $tiles[] = [$ar ? 'حالات مصعدة إلى رئيس لجنة المخاطر' : 'Cases escalated to the risk committee chair', (string)$esc, $ar ? 'خلال الربع' : 'in the quarter', $esc ? 'danger' : 'ok'];
        $cy = Db::one("SELECT * FROM icaap_cycles ORDER BY year DESC LIMIT 1");
        if ($cy && ($ic = Icaap::load((int)$cy['id'])) && $ic['r']['car'] !== null) {
            $tiles[] = [$ar ? 'كفاية رأس المال (آخر تقييم داخلي)' : 'Capital adequacy (latest ICAAP)', number_format($ic['r']['car'] * 100, 1) . '%', 'ICAAP ' . $cy['year'], $ic['r']['zone'] === 'target' ? 'ok' : 'warn'];
        }
        $n = (int)Db::val("SELECT COALESCE(MAX(sort_order),0) FROM board_report_rows WHERE report_id = ? AND kind = 'tile'", [$id]);
        foreach ($tiles as [$t, $v, $note, $lvl]) {
            Db::insert('board_report_rows', ['report_id' => $id, 'kind' => 'tile', 'sort_order' => ++$n, 'title' => $t, 'detail' => $v, 'extra' => $note, 'level' => $lvl ?: null]);
        }
        return count($tiles);
    }

    /** Owner for an action of a department: its active department manager, otherwise the report's author. */
    private static function owner(?int $deptId, int $fallback): int
    {
        if ($deptId && ($u = (int)Db::val("SELECT id FROM users WHERE department_id = ? AND role = 'dept_manager' AND is_active = 1 ORDER BY id LIMIT 1", [$deptId]))) {
            return $u;
        }
        return $fallback;
    }

    /**
     * On board approval: each proposed decision becomes a board decision, and each recommendation an action under the
     * decision it is linked to (or under a general "recommendations" decision), due on board date + its duration.
     * @return array{decisions:int,actions:int}
     */
    public static function createFollowUp(int $id, int $userId): array
    {
        $x = self::load($id);
        $r = $x['r'];
        if ((int)$r['actions_created'] === 1 || !$r['board_date']) {
            return ['decisions' => 0, 'actions' => 0];
        }
        $now = date('Y-m-d H:i:s');
        $ar = I18n::locale() === 'ar';
        return Db::tx(function () use ($x, $r, $id, $userId, $now, $ar) {
            $nd = $na = 0;
            $byCode = [];
            foreach ($x['rows']['decision'] ?? [] as $d) {
                $text = trim(($d['code'] ? $d['code'] . ': ' : '') . $d['title']);
                $did = Db::insert('committee_decisions', ['committee' => 'board', 'meeting_date' => $r['board_date'], 'title' => mb_substr($text, 0, 250), 'decision_text' => $text . ($d['detail'] ? "\n" . $d['detail'] : '') . ($d['extra'] ? ' — ' . $d['extra'] : ''),
                    'status' => 'open', 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now]);
                Db::update('committee_decisions', ['code' => sprintf('DEC-%04d', $did)], 'id = ?', [$did]);
                Db::update('board_report_rows', ['decision_id' => $did], 'id = ?', [$d['id']]);
                if ($d['code']) {
                    $byCode[$d['code']] = $did;
                }
                // A decision with a duration and no linked recommendation is itself followed as an action.
                $nd++;
            }
            $general = null;
            foreach ($x['rows']['recommendation'] ?? [] as $rec) {
                $did = null;
                foreach (preg_split('/[\s,،]+/u', (string)$rec['refs']) ?: [] as $c) {
                    if ($c !== '' && isset($byCode[$c])) {
                        $did = $byCode[$c];
                        break;
                    }
                }
                if (!$did) {
                    if ($general === null) {
                        $t = ($ar ? 'التوصيات التنفيذية للتقرير الربعي ' : 'Recommendations of the quarterly report ') . $r['code'];
                        $general = Db::insert('committee_decisions', ['committee' => 'board', 'meeting_date' => $r['board_date'], 'title' => $t, 'decision_text' => $t, 'status' => 'open', 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now]);
                        Db::update('committee_decisions', ['code' => sprintf('DEC-%04d', $general)], 'id = ?', [$general]);
                        $nd++;
                    }
                    $did = $general;
                }
                $days = $rec['days'] !== null ? (int)$rec['days'] : 30;
                $dept = $rec['dept_ar'] ? ($ar ? $rec['dept_ar'] : $rec['dept_en']) . ': ' : '';
                $aid = Db::insert('decision_actions', ['decision_id' => $did, 'title' => mb_substr($dept . $rec['title'], 0, 250), 'owner_user_id' => self::owner($rec['department_id'] ? (int)$rec['department_id'] : null, $userId),
                    'deadline' => date('Y-m-d', strtotime($r['board_date'] . ' +' . $days . ' days')), 'status' => 'not_started', 'created_at' => $now, 'updated_at' => $now]);
                Db::update('board_report_rows', ['action_id' => $aid, 'decision_id' => $did], 'id = ?', [$rec['id']]);
                $na++;
            }
            Db::update('board_reports', ['actions_created' => 1, 'updated_at' => $now], 'id = ?', [$id]);
            Audit::log('board_report.followup_created', 'board_report', $id, ['decisions' => $nd, 'actions' => $na]);
            return ['decisions' => $nd, 'actions' => $na];
        });
    }

    /** Follow-up status of the approved report's actions (for the next quarter and the monthly report to the committee). */
    public static function followUp(int $id): array
    {
        return Db::all("SELECT x.code AS rcode, x.title AS rtitle, x.priority, a.id AS action_id, a.deadline, a.status, a.completed_on, u.full_name, a.decision_id FROM board_report_rows x
            JOIN decision_actions a ON a.id = x.action_id JOIN users u ON u.id = a.owner_user_id WHERE x.report_id = ? ORDER BY a.status IN ('completed','cancelled'), a.deadline", [$id]);
    }
}
