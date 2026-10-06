<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\Auth;
use App\Core\Db;

/** "What needs my attention": open work items assigned to the signed-in user, plus due personal reminders. */
final class Attention
{
    private static ?array $cache = null;

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
        $s['rcsa_review'] = Auth::can('rcsa.manage') ? Db::all("SELECT a.id, a.code AS title, a.due_date AS due FROM rcsa_assessments a WHERE a.status = 'submitted' ORDER BY a.due_date LIMIT 50") : [];
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
