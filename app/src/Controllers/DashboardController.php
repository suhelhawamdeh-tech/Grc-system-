<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Db;
use App\Core\Money;
use App\Core\Perm;
use App\Core\Scope;
use App\Support\ActionFilter;
use App\Support\IncidentFilter;
use App\Support\Ref;

final class DashboardController extends Controller
{
    /** Resolve the selected reporting period (id, or custom from/to, or the period containing today, or the current year). */
    private function period(): array
    {
        $today = date('Y-m-d');
        $pid = isset($_GET['period']) && preg_match('/^\d+$/', (string)$_GET['period']) ? (int)$_GET['period'] : null;
        if ($pid && ($p = Ref::periods()[$pid] ?? null)) {
            return ['id' => $pid, 'from' => $p['start_date'], 'to' => $p['end_date'], 'label' => lname($p)];
        }
        $isDate = static fn($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) !== false;
        if ($isDate($_GET['from'] ?? null) && $isDate($_GET['to'] ?? null) && $_GET['from'] <= $_GET['to']) {
            return ['id' => null, 'from' => $_GET['from'], 'to' => $_GET['to'], 'label' => $_GET['from'] . ' → ' . $_GET['to']];
        }
        if (!isset($_GET['period'])) {
            foreach (Ref::periods(true) as $p) {
                if ($p['start_date'] <= $today && $p['end_date'] >= $today) {
                    return ['id' => (int)$p['id'], 'from' => $p['start_date'], 'to' => $p['end_date'], 'label' => lname($p)];
                }
            }
        }
        return ['id' => null, 'from' => date('Y-01-01'), 'to' => date('Y-12-31'), 'label' => date('Y')];
    }

    public function index(): string
    {
        $u = $this->user();
        $per = $this->period();
        [$sw, $sp] = Scope::sql($u, 'r');
        $activeWhere = "r.archived_at IS NULL AND r.status = 'active' AND $sw";
        $highMin = (int)(Db::val('SELECT MIN(min_score) FROM score_bands WHERE is_high = 1') ?? 26);
        $today = date('Y-m-d');

        $d = [];
        $d['active'] = (int)Db::val("SELECT COUNT(*) FROM risks r WHERE $activeWhere", $sp);
        $d['high'] = (int)Db::val("SELECT COUNT(*) FROM risks r WHERE $activeWhere AND r.res_score >= ?", array_merge($sp, [$highMin]));
        $d['review_overdue'] = (int)Db::val("SELECT COUNT(*) FROM risks r WHERE $activeWhere AND r.review_date < ?", array_merge($sp, [$today]));
        $d['overdue_actions'] = ActionFilter::count(['overdue' => '1', 'status' => '', 'owner' => null, 'department' => null], $u);

        $group = static function (string $join, string $nameCols, string $key) use ($activeWhere, $sp): array {
            return Db::all("SELECT $key AS gid, $nameCols, COUNT(*) AS n FROM risks r $join WHERE $activeWhere GROUP BY $key, $nameCols ORDER BY n DESC", $sp);
        };
        $d['by_category'] = $group('JOIN risk_categories x ON x.id = r.category_id', 'x.name_en, x.name_ar', 'x.id');
        $d['by_department'] = $group('JOIN departments x ON x.id = r.department_id', 'x.name_en, x.name_ar', 'x.id');
        $d['by_branch'] = $group('LEFT JOIN branches x ON x.id = r.branch_id', 'x.name_en, x.name_ar', 'x.id');

        foreach (['inh' => ['inh_likelihood', 'inh_impact'], 'res' => ['res_likelihood', 'res_impact']] as $k => [$lc, $ic]) {
            $grid = [];
            foreach (Db::all("SELECT r.$lc AS l, r.$ic AS i, COUNT(*) AS n FROM risks r WHERE $activeWhere GROUP BY r.$lc, r.$ic", $sp) as $row) {
                $grid[(int)$row['l']][(int)$row['i']] = (int)$row['n'];
            }
            $d['heat_' . $k] = $grid;
        }

        $d['top_residual'] = Db::all("SELECT r.id, r.risk_code, r.title, r.res_score, r.res_likelihood, r.res_impact, d.name_en AS dept_en, d.name_ar AS dept_ar FROM risks r JOIN departments d ON d.id = r.department_id WHERE $activeWhere AND r.res_score >= ? ORDER BY r.res_score DESC, r.id DESC LIMIT 10", array_merge($sp, [$highMin]));
        $d['overdue_list'] = ActionFilter::fetch(['overdue' => '1', 'status' => '', 'owner' => null, 'department' => null], $u, 10);

        $incF = IncidentFilter::fromArray(['from' => $per['from'], 'to' => $per['to']]);
        $d['inc'] = IncidentFilter::totals($incF, $u);
        [$iw, $ip] = IncidentFilter::where($incF, $u);
        $d['inc_near'] = (int)Db::val("SELECT COUNT(*) FROM incidents i WHERE $iw AND i.event_type = 'near_miss'", $ip);
        $d['inc_by_category'] = Db::all("SELECT c.id AS gid, c.name_en, c.name_ar, COUNT(*) AS n, COALESCE(SUM(i.net_loss),0) AS net FROM incidents i JOIN risk_categories c ON c.id = i.category_id WHERE $iw GROUP BY c.id, c.name_en, c.name_ar ORDER BY net DESC, n DESC", $ip);
        $d['inc_by_month'] = Db::all("SELECT DATE_FORMAT(i.occurrence_date, '%Y-%m') AS m, COUNT(*) AS n, COALESCE(SUM(i.net_loss),0) AS net FROM incidents i WHERE $iw GROUP BY m ORDER BY m", $ip);

        $d['p2'] = $this->governance($u);
        return $this->view('dashboard/index', ['title' => t('nav.dashboard'), 'd' => $d, 'per' => $per, 'periods' => Ref::periods(true), 'highMin' => $highMin, 'scoped' => Scope::restricted($u)]);
    }

    /** Phase 2 headline numbers (each derived from stored data, scope-aware, shown only with the matching permission). */
    private function governance(array $u): array
    {
        $p = [];
        $today = date('Y-m-d');
        if (Perm::roleHas($u['role'], 'kri.view')) {
            [$w, $pp] = Scope::sqlDept($u, 'k');
            $latest = 'JOIN kri_readings lr ON lr.id = (SELECT x.id FROM kri_readings x WHERE x.kri_id = k.id ORDER BY x.reading_date DESC, x.id DESC LIMIT 1)';
            $p['kri_red'] = (int)Db::val("SELECT COUNT(*) FROM kris k $latest WHERE k.is_active = 1 AND lr.status = 'red' AND $w", $pp);
            $p['kri_amber'] = (int)Db::val("SELECT COUNT(*) FROM kris k $latest WHERE k.is_active = 1 AND lr.status = 'amber' AND $w", $pp);
        }
        if (Perm::roleHas($u['role'], 'control.view')) {
            [$rw, $rp] = Scope::sql($u, 'r');
            $p['unproven'] = (int)Db::val('SELECT COUNT(*) FROM risks r WHERE r.archived_at IS NULL AND ' . \App\Support\RiskFilter::UNPROVEN . " AND $rw", $rp);
            [$w, $pp] = Scope::sqlDept($u, 'c');
            $p['ctl_overdue'] = (int)Db::val("SELECT COUNT(*) FROM controls c WHERE c.archived_at IS NULL AND c.next_test_due < ? AND $w", array_merge([$today], $pp));
            $p['ctl_weak'] = (int)Db::val("SELECT COUNT(*) FROM controls c WHERE c.archived_at IS NULL AND c.last_result IN ('ineffective','partially_effective') AND $w", $pp);
        }
        if (Perm::roleHas($u['role'], 'bia.view')) {
            [$w, $pp] = Scope::sqlDept($u, 'p');
            $p['bia_unapproved'] = (int)Db::val("SELECT COUNT(*) FROM bia_processes p WHERE p.archived_at IS NULL AND p.criticality IN ('critical','high') AND p.status <> 'approved' AND $w", $pp);
            $p['bia_gaps'] = (int)Db::val("SELECT COUNT(*) FROM bia_processes p WHERE p.archived_at IS NULL AND $w AND EXISTS (SELECT 1 FROM bia_process_systems ps JOIN bia_systems s ON s.id = ps.system_id WHERE ps.process_id = p.id AND s.is_active = 1 AND (s.recovery_hours > p.rto_hours OR s.data_loss_hours > p.rpo_hours))", $pp);
        }
        if (Perm::roleHas($u['role'], 'approval.decide')) {
            $p['appr_pending'] = (int)Db::val("SELECT COUNT(*) FROM approvals WHERE status = 'pending' AND requested_by <> ?", [$u['id']]);
        }
        if (Perm::roleHas($u['role'], 'sharia.view')) {
            [$w, $pp] = Scope::sqlDept($u, 'e');
            $p['sha_open'] = (int)Db::val("SELECT COUNT(*) FROM sharia_events e WHERE e.status <> 'closed' AND $w", $pp);
            $p['sha_purify'] = (int)Db::val("SELECT COUNT(*) FROM sharia_events e WHERE e.purification_status = 'pending' AND $w", $pp);
        }
        if (Perm::roleHas($u['role'], 'appetite.view')) {
            $p['breaches'] = (int)Db::val("SELECT COUNT(*) FROM appetite_breaches WHERE status IN ('open','escalated')");
        }
        if (Perm::roleHas($u['role'], 'compliance.view')) {
            [$w, $pp] = Scope::sqlDept($u, 'o');
            $p['obl_overdue'] = (int)Db::val("SELECT COUNT(*) FROM compliance_obligations o WHERE o.archived_at IS NULL AND o.status IN ('not_started','in_progress','non_compliant') AND o.due_date < ? AND $w", array_merge([$today], $pp));
        }
        if (Perm::roleHas($u['role'], 'committee.view')) {
            $p['dec_overdue'] = (int)Db::val("SELECT COUNT(*) FROM decision_actions a WHERE a.status IN ('not_started','in_progress') AND a.deadline < ?", [$today]);
        }
        if (Perm::roleHas($u['role'], 'rcsa.view')) {
            [$w, $pp] = Scope::sql($u, 'a');
            $p['rcsa_review'] = (int)Db::val("SELECT COUNT(*) FROM rcsa_assessments a WHERE a.status = 'submitted' AND $w", $pp);
            $p['rcsa_overdue'] = (int)Db::val("SELECT COUNT(*) FROM rcsa_assessments a WHERE a.status IN ('draft','returned') AND a.due_date < ? AND $w", array_merge([$today], $pp));
        }
        if (Perm::roleHas($u['role'], 'policy.view')) {
            $p['pol_overdue'] = (int)Db::val("SELECT COUNT(*) FROM policies WHERE status <> 'retired' AND next_review_date < ?", [$today]);
        }
        return $p;
    }
}
