<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\Db;
use App\Core\Scope;

/** Treatment actions list / overdue logic shared by the actions page and the dashboard. */
final class ActionFilter
{
    public static function fromArray(array $q): array
    {
        $int = static fn($k) => isset($q[$k]) && is_string($q[$k]) && preg_match('/^\d{1,10}$/', $q[$k]) ? (int)$q[$k] : null;
        return [
            'overdue' => !empty($q['overdue']) ? '1' : '',
            'status' => isset($q['status']) && in_array($q['status'], ['not_started', 'in_progress', 'completed', 'cancelled'], true) ? $q['status'] : '',
            'owner' => $int('owner'),
            'department' => $int('department'),
        ];
    }

    public const OPEN = "a.status IN ('not_started','in_progress')";

    public static function where(array $f, array $user): array
    {
        [$w, $p] = Scope::sql($user, 'r');
        $where = ['r.archived_at IS NULL', $w];
        $params = $p;
        if ($f['overdue'] === '1') {
            $where[] = self::OPEN . ' AND a.deadline < ?';
            $params[] = date('Y-m-d');
        }
        if ($f['status'] !== '') {
            $where[] = 'a.status = ?';
            $params[] = $f['status'];
        }
        if ($f['owner'] !== null) {
            $where[] = 'a.owner_user_id = ?';
            $params[] = $f['owner'];
        }
        if ($f['department'] !== null) {
            $where[] = 'r.department_id = ?';
            $params[] = $f['department'];
        }
        return [implode(' AND ', $where), $params];
    }

    public static function count(array $f, array $user): int
    {
        [$w, $p] = self::where($f, $user);
        return (int)Db::val('SELECT COUNT(*) FROM risk_actions a JOIN risks r ON r.id = a.risk_id WHERE ' . $w, $p);
    }

    public static function fetch(array $f, array $user, int $limit, int $offset = 0): array
    {
        [$w, $p] = self::where($f, $user);
        return Db::all('SELECT a.*, r.risk_code, r.title AS risk_title, o.full_name AS owner_name FROM risk_actions a JOIN risks r ON r.id = a.risk_id JOIN users o ON o.id = a.owner_user_id WHERE ' . $w . ' ORDER BY a.deadline ASC, a.id ASC LIMIT ? OFFSET ?', array_merge($p, [$limit, $offset]));
    }
}
