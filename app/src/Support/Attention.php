<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\Perm;
use App\Core\Db;

/** "What needs my attention": open work items assigned to the signed-in user, plus due personal reminders. */
final class Attention
{
    private static ?array $cache = null;

    /** Section key => translation key of its heading (used by My tasks and e-mail digests). */
    public const LABELS = ['actions' => 'my.actions', 'decisions' => 'my.decisions', 'obligations' => 'my.obligations', 'kris' => 'my.kris', 'rcsa' => 'my.rcsa', 'rcsa_review' => 'my.rcsa_review', 'control_tests' => 'my.control_tests',
        'sharia_events' => 'my.sharia_events', 'sharia_reviews' => 'my.sharia_reviews', 'circulars' => 'my.circulars', 'circulars_overdue' => 'my.circulars_overdue', 'kri_breaches' => 'my.kri_breaches', 'approvals' => 'my.approvals', 'bia_review' => 'my.bia_review', 'ia_respond' => 'my.ia_respond', 'ia_implement' => 'my.ia_implement', 'ia_verify' => 'my.ia_verify', 'ia_review_wp' => 'my.ia_review_wp', 'aml_reviews' => 'my.aml_reviews', 'aml_reports' => 'my.aml_reports', 'rm_tasks' => 'my.rm_tasks', 'rm_opinions' => 'my.rm_opinions', 'rm_plans' => 'my.rm_plans', 'rm_escalations' => 'my.rm_escalations', 'icaap' => 'my.icaap', 'registers' => 'my.registers', 'notes' => 'my.notes'];

    /** @return array<string,array> section key => rows (each row: label, due, link, overdue) */
    public static function items(array $u): array
    {
        $id = (int)$u['id'];
        $horizon = date('Y-m-d', strtotime('+14 days'));
        $today = date('Y-m-d');
        $s = [];
        $s['actions'] = Db::all("SELECT a.id, a.title, a.deadline AS due, a.risk_id FROM risk_actions a JOIN risks r ON r.id = a.risk_id WHERE a.owner_user_id = ? AND a.status IN ('not_started','in_progress') AND r.archived_at IS NULL AND a.deadline <= ? ORDER BY a.deadline LIMIT 50", [$id, $horizon]);
        $s['decisions'] = Db::all("SELECT a.id, a.title, a.deadline AS due, a.decision_id FROM decision_actions a WHERE a.owner_user_id = ? AND a.status IN ('not_started','in_progress') AND a.deadline <= ? ORDER BY a.deadline LIMIT 50", [$id, $horizon]);
        $s['obligations'] = Db::all("SELECT o.id, o.title, o.due_date AS due FROM compliance_obligations o WHERE o.owner_user_id = ? AND o.archived_at IS NULL AND o.status IN ('not_started','in_progress','non_compliant') AND o.due_date <= ? ORDER BY o.due_date LIMIT 50", [$id, date('Y-m-d', strtotime('+30 days'))]);
        $kris = Db::all("SELECT k.id, k.code, k.name, k.frequency, (SELECT MAX(reading_date) FROM kri_readings x WHERE x.kri_id = k.id) AS last_date FROM kris k WHERE k.owner_user_id = ? AND k.is_active = 1", [$id]);
        $s['kris'] = [];
        foreach ($kris as $k) {
            $start = Kri::cycleStart($k['frequency']);
            if (!$k['last_date'] || $k['last_date'] < $start) {
                $s['kris'][] = ['id' => $k['id'], 'title' => $k['code'] . ' — ' . $k['name'], 'due' => $start];
            }
        }
        $s['rcsa'] = Db::all("SELECT a.id, a.code AS title, a.due_date AS due FROM rcsa_assessments a WHERE a.assessor_user_id = ? AND a.status IN ('draft','returned') ORDER BY a.due_date LIMIT 50", [$id]);
        $s['rcsa_review'] = Perm::roleHas($u['role'], 'rcsa.manage') ? Db::all("SELECT a.id, a.code AS title, a.due_date AS due FROM rcsa_assessments a WHERE a.status = 'submitted' ORDER BY a.due_date LIMIT 50") : [];
        $s['control_tests'] = Perm::roleHas($u['role'], 'control.test') ? Db::all("SELECT c.id, CONCAT(c.code, ' — ', c.name) AS title, c.next_test_due AS due FROM controls c WHERE c.archived_at IS NULL AND c.owner_user_id <> ? AND c.next_test_due IS NOT NULL AND c.next_test_due <= ? ORDER BY c.is_key DESC, c.next_test_due LIMIT 50", [$id, $horizon]) : [];
        $s['sharia_events'] = Perm::roleHas($u['role'], 'sharia.manage') ? Db::all("SELECT e.id, CONCAT(e.code, ' — ', e.title) AS title, e.detected_on AS due FROM sharia_events e WHERE e.status IN ('reported','under_review') ORDER BY FIELD(e.severity,'high','medium','low'), e.detected_on LIMIT 50") : [];
        $s['sharia_reviews'] = Db::all("SELECT r.id, CONCAT(r.code, ' — ', r.title) AS title, r.planned_date AS due FROM sharia_reviews r WHERE r.reviewer_user_id = ? AND r.status <> 'completed' AND r.planned_date <= ? ORDER BY r.planned_date LIMIT 50", [$id, $horizon]);
        $s['approvals'] = Perm::roleHas($u['role'], 'approval.decide') ? Db::all("SELECT a.id, CONCAT(a.entity_type, ' #', a.entity_id) AS title, DATE(a.requested_at) AS due FROM approvals a WHERE a.status = 'pending' AND a.requested_by <> ? ORDER BY a.requested_at LIMIT 50", [$id]) : [];
        $s['bia_review'] = Perm::roleHas($u['role'], 'bia.manage') ? Db::all("SELECT p.id, CONCAT(p.code, ' — ', p.name) AS title, p.next_review_due AS due FROM bia_processes p WHERE p.owner_user_id = ? AND p.archived_at IS NULL AND p.status = 'approved' AND p.next_review_due <= ? ORDER BY p.next_review_due LIMIT 50", [$id, date('Y-m-d', strtotime('+30 days'))]) : [];
        $s['kri_breaches'] = Db::all("SELECT b.id AS id, b.kri_id AS kri_id, CONCAT(k.code, ' — ', k.name) AS title, DATE(b.created_at) AS due FROM kri_breaches b JOIN kris k ON k.id = b.kri_id WHERE b.status = 'open' AND b.justification IS NULL AND k.owner_user_id = ? ORDER BY b.created_at LIMIT 50", [$id]);
        $s['circulars'] = Perm::roleHas($u['role'], 'circular.ack') && (int)($u['department_id'] ?? 0) ? Db::all("SELECT c.id, CONCAT(c.code, ' — ', c.title) AS title, c.due_date AS due FROM circular_distribution d JOIN regulatory_circulars c ON c.id = d.circular_id WHERE d.department_id = ? AND d.status = 'pending' AND c.status = 'distributed' ORDER BY c.due_date IS NULL, c.due_date LIMIT 50", [(int)$u['department_id']]) : [];
        $s['circulars_overdue'] = Perm::roleHas($u['role'], 'circular.manage') ? Db::all("SELECT c.id, CONCAT(c.code, ' — ', c.title) AS title, c.due_date AS due FROM regulatory_circulars c WHERE c.status = 'distributed' AND c.due_date IS NOT NULL AND c.due_date <= ? ORDER BY c.due_date LIMIT 50", [$horizon]) : [];
        $s['ia_respond'] = Db::all("SELECT f.id, CONCAT(f.code, ' — ', f.title) AS title, f.response_due AS due FROM audit_findings f WHERE f.owner_user_id = ? AND f.status = 'open' ORDER BY f.response_due LIMIT 50", [$id]);
        $s['ia_implement'] = Db::all("SELECT f.id, CONCAT(f.code, ' — ', f.title) AS title, f.due_date AS due FROM audit_findings f WHERE f.owner_user_id = ? AND f.status = 'responded' AND f.due_date <= ? ORDER BY f.due_date LIMIT 50", [$id, date('Y-m-d', strtotime('+30 days'))]);
        $s['ia_verify'] = Perm::roleHas($u['role'], 'ia.manage') ? Db::all("SELECT f.id, CONCAT(f.code, ' — ', f.title) AS title, DATE(f.implemented_at) AS due FROM audit_findings f WHERE f.status = 'implemented' ORDER BY f.implemented_at LIMIT 50") : [];
        $s['ia_review_wp'] = Perm::roleHas($u['role'], 'ia.manage') ? Db::all("SELECT w.id, CONCAT(e.code, ' / ', w.ref, ' — ', w.title) AS title, DATE(w.prepared_at) AS due FROM audit_workpapers w JOIN audit_engagements e ON e.id = w.engagement_id WHERE w.status = 'prepared' AND e.status = 'fieldwork' AND w.prepared_by <> ? ORDER BY w.prepared_at LIMIT 50", [$id]) : [];
        $s['aml_reviews'] = Perm::roleHas($u['role'], 'aml.manage') ? Db::all("SELECT c.id, CONCAT(c.customer_ref, ' — ', c.full_name) AS title, c.next_review_due AS due FROM aml_customers c WHERE c.status = 'active' AND c.next_review_due <= ? ORDER BY FIELD(c.rating, 'high', 'medium', 'low'), c.next_review_due LIMIT 50", [date('Y-m-d', strtotime('+30 days'))]) : [];
        $s['aml_reports'] = Perm::roleHas($u['role'], 'aml.manage') ? Db::all("SELECT r.id, CONCAT(r.code, ' — ', r.title) AS title, r.due_date AS due FROM aml_reports r WHERE r.status = 'draft' AND r.due_date <= ? ORDER BY r.due_date LIMIT 50", [$horizon]) : [];
        // Risk function: procedures assigned to me (or unassigned, for the risk team), opinions awaiting my review or issue, plans my department owes, open escalations.
        $s['rm_tasks'] = Perm::roleHas($u['role'], 'rm.work') ? Db::all("SELECT t.id, CONCAT(p.code, ' — ', IF(? = 'ar', p.title_ar, p.title_en), ' (', t.period_key, ')') AS title, t.due_date AS due FROM rm_tasks t JOIN rm_procedures p ON p.id = t.procedure_id
            WHERE t.status = 'open' AND (t.assignee_user_id = ? OR t.assignee_user_id IS NULL) AND t.due_date <= ? ORDER BY t.due_date LIMIT 50", [\App\Core\I18n::locale(), $id, $horizon]) : [];
        $s['rm_opinions'] = Perm::roleHas($u['role'], 'rm.work') ? Db::all("SELECT o.id, CONCAT(o.code, ' — ', o.title) AS title, COALESCE(o.due_on, o.received_on) AS due FROM rm_opinions o
            WHERE o.prepared_by <> ? AND (o.status = 'submitted' OR (o.status = 'reviewed' AND ? = 1)) ORDER BY o.received_on LIMIT 50", [$id, Perm::roleHas($u['role'], 'rm.approve') ? 1 : 0]) : [];
        $s['rm_plans'] = Perm::roleHas($u['role'], 'rm.respond') && (int)($u['department_id'] ?? 0) ? Db::all("SELECT b.id, CONCAT(b.code, ' — ', b.limit_name) AS title, b.plan_due AS due FROM rm_breaches b
            WHERE b.kind = 'breach' AND b.status = 'open' AND b.owner_department_id = ? ORDER BY b.plan_due IS NULL, b.plan_due LIMIT 50", [(int)$u['department_id']]) : [];
        $s['rm_escalations'] = Perm::roleHas($u['role'], 'rm.approve') ? Db::all("SELECT e.id, CONCAT(e.code, ' — ', e.title) AS title, DATE(e.escalated_at) AS due FROM rm_escalations e WHERE e.status <> 'closed' ORDER BY e.escalated_at LIMIT 50") : [];
        // ICAAP: phases past their end date and data requests not received, for the Basel unit.
        $s['icaap'] = Perm::roleHas($u['role'], 'icaap.manage') ? Db::all("SELECT c.id, CONCAT('ICAAP ', c.year, ' — ', p.phase_no) AS title, p.end_date AS due FROM icaap_phases p JOIN icaap_cycles c ON c.id = p.cycle_id
            WHERE c.status IN ('preparation','in_progress','committee','board') AND p.status <> 'complete' AND p.end_date IS NOT NULL AND p.end_date <= ?
            UNION ALL SELECT c.id, CONCAT('ICAAP ', c.year, ' — ', r.provider) AS title, r.due_on AS due FROM icaap_data_requests r JOIN icaap_cycles c ON c.id = r.cycle_id
            WHERE c.status IN ('preparation','in_progress') AND r.received_on IS NULL AND r.due_on IS NOT NULL AND r.due_on <= ? ORDER BY due LIMIT 50", [$horizon, $horizon]) : [];
        // Supporting registers: open items I own that fall due (model validations, data deliveries, security acceptances, requirements, plan items).
        $open = [];
        foreach (Registers::all() as $rk => $rd) {
            foreach ($rd['open'] as $os) {
                $open[] = Db::pdo()->quote($rk . ':' . $os);
            }
        }
        $s['registers'] = $open ? Db::all("SELECT i.id, CONCAT(i.code, ' — ', i.title) AS title, i.due_date AS due FROM rm_register_items i WHERE i.owner_user_id = ? AND i.due_date IS NOT NULL AND i.due_date <= ?
            AND CONCAT(i.register, ':', i.status) IN (" . implode(',', $open) . ') ORDER BY i.due_date LIMIT 50', [$id, $horizon]) : [];
        $s['notes'] = Db::all('SELECT n.id, n.body AS title, n.remind_on AS due, n.page_path FROM user_notes n WHERE n.user_id = ? AND n.is_done = 0 AND n.remind_on IS NOT NULL AND n.remind_on <= ? ORDER BY n.remind_on LIMIT 50', [$id, $today]);
        foreach ($s as &$rows) {
            foreach ($rows as &$r) {
                $r['overdue'] = !empty($r['due']) && $r['due'] < $today;
            }
        }
        return $s;
    }

    /** Total count shown on the top-bar badge (cached per request). */
    public static function count(array $u): int
    {
        if (self::$cache === null) {
            self::$cache = self::items($u);
        }
        $n = 0;
        foreach (self::$cache as $rows) {
            $n += count($rows);
        }
        return $n;
    }
}
