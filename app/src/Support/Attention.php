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
        'sharia_events' => 'my.sharia_events', 'sharia_reviews' => 'my.sharia_reviews', 'approvals' => 'my.approvals', 'bia_review' => 'my.bia_review', 'notes' => 'my.notes'];

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
