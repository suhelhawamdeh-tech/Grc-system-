<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Db;
use App\Core\Perm;
use App\Core\Request;
use App\Core\Scope;
use App\Support\Ref;

final class ReportController extends Controller
{
    public function index(): string
    {
        $u = $this->user();
        $deps = Ref::departments(true);
        $brs = Ref::branches(true);
        if (Perm::scopeType($u['role']) === 'department') {
            $deps = array_filter($deps, static fn($d) => (int)$d['id'] === (int)$u['department_id']);
        }
        if (Perm::scopeType($u['role']) === 'branch') {
            $brs = array_filter($brs, static fn($b) => (int)$b['id'] === (int)$u['branch_id']);
        }
        return $this->view('reports/index', ['title' => t('nav.reports'), 'categories' => Ref::categories(true), 'departments' => $deps, 'branches' => $brs, 'bands' => Ref::bands(), 'periods' => Ref::periods(true)]);
    }

    /** One-page-per-topic board pack (print to PDF from the browser). Every section follows the viewer's permissions and scope. */
    public function board(): string
    {
        $u = $this->user();
        $role = $u['role'];
        $from = Request::str('from', true);
        $to = Request::str('to', true);
        $ok = static fn(string $d) => (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && checkdate((int)substr($d, 5, 2), (int)substr($d, 8, 2), (int)substr($d, 0, 4));
        $q0 = date('Y-m-d', mktime(0, 0, 0, (int)floor((date('n') - 1) / 3) * 3 + 1, 1, (int)date('Y')));
        $from = $ok($from) ? $from : $q0;
        $to = $ok($to) ? $to : date('Y-m-d');
        $d = ['from' => $from, 'to' => $to];
        [$rw, $rp] = Scope::sql($u, 'r');
        $d['risks_active'] = (int)Db::val("SELECT COUNT(*) FROM risks r WHERE r.archived_at IS NULL AND r.status = 'active' AND $rw", $rp);
        $d['top_risks'] = Db::all("SELECT r.risk_code, r.title, r.res_score, d.name_en AS dept_en, d.name_ar AS dept_ar FROM risks r JOIN departments d ON d.id = r.department_id WHERE r.archived_at IS NULL AND r.status = 'active' AND $rw ORDER BY r.res_score DESC, r.id DESC LIMIT 10", $rp);
        [$iw, $ip] = Scope::sql($u, 'i');
        $d['loss'] = Db::one("SELECT COUNT(*) AS n, COALESCE(SUM(i.gross_loss),0) AS gross, COALESCE(SUM(i.net_loss),0) AS net FROM incidents i WHERE i.archived_at IS NULL AND i.occurrence_date BETWEEN ? AND ? AND $iw", array_merge([$from, $to], $ip));
        $d['loss_cat'] = Db::all("SELECT c.name_en, c.name_ar, COUNT(*) AS n, COALESCE(SUM(i.net_loss),0) AS net FROM incidents i JOIN risk_categories c ON c.id = i.category_id WHERE i.archived_at IS NULL AND i.occurrence_date BETWEEN ? AND ? AND $iw GROUP BY c.id, c.name_en, c.name_ar ORDER BY net DESC LIMIT 8", array_merge([$from, $to], $ip));
        $d['overdue'] = Db::all("SELECT a.title, a.deadline, o.full_name, r.risk_code FROM risk_actions a JOIN risks r ON r.id = a.risk_id JOIN users o ON o.id = a.owner_user_id WHERE a.status IN ('not_started','in_progress') AND a.deadline < CURDATE() AND r.archived_at IS NULL AND $rw ORDER BY a.deadline LIMIT 10", $rp);
        if (Perm::roleHas($role, 'kri.view')) {
            [$kw, $kp] = Scope::sqlDept($u, 'k');
            $d['kris'] = Db::all("SELECT k.code, k.name, lr.value, lr.status, k.unit FROM kris k JOIN kri_readings lr ON lr.id = (SELECT x.id FROM kri_readings x WHERE x.kri_id = k.id ORDER BY x.reading_date DESC, x.id DESC LIMIT 1) WHERE k.is_active = 1 AND lr.status IN ('amber','red') AND $kw ORDER BY FIELD(lr.status,'red','amber'), k.code LIMIT 15", $kp);
        }
        if (Perm::roleHas($role, 'appetite.view')) {
            $d['breaches'] = Db::all("SELECT b.*, s.title FROM appetite_breaches b JOIN appetite_statements s ON s.id = b.statement_id WHERE b.status IN ('open','escalated') ORDER BY b.id DESC LIMIT 10");
        }
        if (Perm::roleHas($role, 'control.view')) {
            [$cw, $cp] = Scope::sqlDept($u, 'c');
            $d['controls'] = Db::all("SELECT c.code, c.name, c.last_result, c.next_test_due FROM controls c WHERE c.archived_at IS NULL AND (c.last_result IN ('ineffective','partially_effective') OR c.next_test_due < CURDATE()) AND $cw ORDER BY c.is_key DESC, c.code LIMIT 10", $cp);
        }
        if (Perm::roleHas($role, 'bia.view')) {
            [$bw, $bp] = Scope::sqlDept($u, 'p');
            $d['bia'] = Db::all("SELECT p.id, p.code, p.name, p.criticality, p.status FROM bia_processes p WHERE p.archived_at IS NULL AND p.criticality IN ('critical','high') AND (p.status <> 'approved' OR EXISTS (SELECT 1 FROM bia_process_systems ps JOIN bia_systems s ON s.id = ps.system_id WHERE ps.process_id = p.id AND s.is_active = 1 AND (s.recovery_hours > p.rto_hours OR s.data_loss_hours > p.rpo_hours))) AND $bw ORDER BY p.code LIMIT 10", $bp);
        }
        if (Perm::roleHas($role, 'sharia.view')) {
            [$sw, $sp] = Scope::sqlDept($u, 'e');
            $d['sharia'] = Db::all("SELECT e.code, e.title, e.severity, e.status, e.purification_status FROM sharia_events e WHERE e.status <> 'closed' AND $sw ORDER BY FIELD(e.severity,'high','medium','low'), e.id DESC LIMIT 10", $sp);
        }
        $this->audit('report.board_pack', 'report', null, ['from' => $from, 'to' => $to]);
        return $this->view('reports/board', ['title' => t('board.title'), 'd' => $d], 'layout/print');
    }
}
