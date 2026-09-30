<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\Db;
use App\Core\Scope;

final class IncidentFilter
{
    public const SORTS = ['code' => 'i.id', 'occurred' => 'i.occurrence_date', 'net' => 'i.net_loss', 'gross' => 'i.gross_loss', 'title' => 'i.title'];

    public static function fromArray(array $q): array
    {
        $int = static fn($k) => isset($q[$k]) && is_string($q[$k]) && preg_match('/^\d{1,10}$/', $q[$k]) ? (int)$q[$k] : null;
        $date = static fn($k) => isset($q[$k]) && is_string($q[$k]) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $q[$k]) && strtotime($q[$k]) !== false ? $q[$k] : '';
        return [
            'q' => isset($q['q']) && is_string($q['q']) ? mb_substr(trim($q['q']), 0, 100) : '',
            'category' => $int('category'),
            'department' => $int('department'),
            'branch' => $int('branch'),
            'risk' => $int('risk'),
            'type' => isset($q['type']) && in_array($q['type'], ['incident', 'near_miss'], true) ? $q['type'] : '',
            'status' => isset($q['status']) && in_array($q['status'], ['open', 'investigating', 'closed'], true) ? $q['status'] : '',
            'from' => $date('from'),
            'to' => $date('to'),
            'archived' => isset($q['archived']) && in_array($q['archived'], ['1', 'all'], true) ? $q['archived'] : '',
            'sort' => isset($q['sort']) && is_string($q['sort']) && isset(self::SORTS[$q['sort']]) ? $q['sort'] : 'occurred',
            'dir' => ($q['dir'] ?? '') === 'asc' ? 'asc' : 'desc',
        ];
    }

    public static function where(array $f, array $user): array
    {
        [$w, $p] = Scope::sql($user, 'i');
        $where = [$w];
        $params = $p;
        if ($f['q'] !== '') {
            $like = Db::like($f['q']);
            $where[] = '(i.title LIKE ? OR i.description LIKE ? OR i.incident_code LIKE ? OR i.root_cause LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }
        foreach (['category' => 'category_id', 'department' => 'department_id', 'branch' => 'branch_id', 'type' => 'event_type', 'status' => 'status'] as $k => $col) {
            if ($k === 'branch' && $f[$k] === 0) {
                $where[] = 'i.branch_id IS NULL';
            } elseif ($f[$k] !== null && $f[$k] !== '') {
                $where[] = "i.$col = ?";
                $params[] = $f[$k];
            }
        }
        if ($f['risk'] !== null) {
            $where[] = 'EXISTS (SELECT 1 FROM incident_risks ir WHERE ir.incident_id = i.id AND ir.risk_id = ?)';
            $params[] = $f['risk'];
        }
        if ($f['from'] !== '') {
            $where[] = 'i.occurrence_date >= ?';
            $params[] = $f['from'];
        }
        if ($f['to'] !== '') {
            $where[] = 'i.occurrence_date <= ?';
            $params[] = $f['to'];
        }
        if ($f['archived'] === '') {
            $where[] = 'i.archived_at IS NULL';
        } elseif ($f['archived'] === '1') {
            $where[] = 'i.archived_at IS NOT NULL';
        }
        return [implode(' AND ', $where), $params];
    }

    public const SELECT = 'SELECT i.*, c.name_en AS cat_en, c.name_ar AS cat_ar, d.name_en AS dept_en, d.name_ar AS dept_ar,
        b.name_en AS branch_en, b.name_ar AS branch_ar, u.full_name AS reporter_name
        FROM incidents i
        JOIN risk_categories c ON c.id = i.category_id
        JOIN departments d ON d.id = i.department_id
        LEFT JOIN branches b ON b.id = i.branch_id
        JOIN users u ON u.id = i.reported_by';

    public static function orderBy(array $f): string
    {
        return ' ORDER BY ' . self::SORTS[$f['sort']] . ' ' . ($f['dir'] === 'asc' ? 'ASC' : 'DESC') . ', i.id DESC';
    }

    public static function count(array $f, array $user): int
    {
        [$w, $p] = self::where($f, $user);
        return (int)Db::val('SELECT COUNT(*) FROM incidents i WHERE ' . $w, $p);
    }

    public static function fetch(array $f, array $user, int $limit, int $offset = 0): array
    {
        [$w, $p] = self::where($f, $user);
        return Db::all(self::SELECT . ' WHERE ' . $w . self::orderBy($f) . ' LIMIT ? OFFSET ?', array_merge($p, [$limit, $offset]));
    }

    /** @return array{count:int,gross:int,recoveries:int,net:int} in integer thousandths */
    public static function totals(array $f, array $user): array
    {
        [$w, $p] = self::where($f, $user);
        $r = Db::one('SELECT COUNT(*) c, COALESCE(SUM(i.gross_loss),0) g, COALESCE(SUM(i.recoveries),0) rec, COALESCE(SUM(i.net_loss),0) n FROM incidents i WHERE ' . $w, $p);
        return ['count' => (int)$r['c'], 'gross' => \App\Core\Money::fromDb($r['g']), 'recoveries' => \App\Core\Money::fromDb($r['rec']), 'net' => \App\Core\Money::fromDb($r['n'])];
    }
}
