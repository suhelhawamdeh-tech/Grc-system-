<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\Db;
use App\Core\Scope;

/** Shared filter logic for the risk list, CSV export, printable report and dashboard drill-downs. */
final class RiskFilter
{
    public const SORTS = [
        'code' => 'r.id',
        'title' => 'r.title',
        'inh' => 'r.inh_score',
        'res' => 'r.res_score',
        'review' => 'r.review_date',
        'identified' => 'r.identified_date',
    ];

    public static function fromArray(array $q): array
    {
        $int = static fn($k) => isset($q[$k]) && is_string($q[$k]) && preg_match('/^\d{1,10}$/', $q[$k]) ? (int)$q[$k] : null;
        $lvl = static fn($k) => ($v = $int($k)) !== null && $v >= 1 && $v <= 5 ? $v : null;
        $f = [
            'q' => isset($q['q']) && is_string($q['q']) ? mb_substr(trim($q['q']), 0, 100) : '',
            'category' => $int('category'),
            'department' => $int('department'),
            'branch' => $int('branch'),
            'owner' => $int('owner'),
            'status' => isset($q['status']) && in_array($q['status'], ['active', 'closed'], true) ? $q['status'] : '',
            'archived' => isset($q['archived']) && in_array($q['archived'], ['1', 'all'], true) ? $q['archived'] : '',
            'band' => $int('band'),          // score band id (residual)
            'inh_band' => $int('inh_band'),  // score band id (inherent)
            'inh_l' => $lvl('inh_l'), 'inh_i' => $lvl('inh_i'),
            'res_l' => $lvl('res_l'), 'res_i' => $lvl('res_i'),
            'control' => isset($q['control']) && in_array($q['control'], ['not_assessed', 'effective', 'partially_effective', 'ineffective'], true) ? $q['control'] : '',
            'overdue_review' => !empty($q['overdue_review']) ? '1' : '',
            'high' => !empty($q['high']) ? '1' : '',
            'sort' => isset($q['sort']) && is_string($q['sort']) && isset(self::SORTS[$q['sort']]) ? $q['sort'] : 'code',
            'dir' => ($q['dir'] ?? '') === 'asc' ? 'asc' : 'desc',
        ];
        return $f;
    }

    /** @return array{0:string,1:array} WHERE clause (without the word WHERE) and params */
    public static function where(array $f, array $user): array
    {
        [$w, $p] = Scope::sql($user, 'r');
        $where = [$w];
        $params = $p;
        if ($f['q'] !== '') {
            $like = Db::like($f['q']);
            $where[] = '(r.title LIKE ? OR r.description LIKE ? OR r.risk_code LIKE ? OR r.causes LIKE ? OR r.consequences LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like);
        }
        foreach (['category' => 'category_id', 'department' => 'department_id', 'branch' => 'branch_id', 'owner' => 'owner_user_id'] as $k => $col) {
            if ($f[$k] === 0 && $k === 'branch') {
                $where[] = 'r.branch_id IS NULL';
            } elseif ($f[$k] !== null) {
                $where[] = "r.$col = ?";
                $params[] = $f[$k];
            }
        }
        if ($f['status'] !== '') {
            $where[] = 'r.status = ?';
            $params[] = $f['status'];
        }
        if ($f['archived'] === '') {
            $where[] = 'r.archived_at IS NULL';
        } elseif ($f['archived'] === '1') {
            $where[] = 'r.archived_at IS NOT NULL';
        }
        foreach (['band' => 'res_score', 'inh_band' => 'inh_score'] as $k => $col) {
            if ($f[$k] !== null) {
                $b = Db::one('SELECT min_score, max_score FROM score_bands WHERE id = ?', [$f[$k]]);
                if ($b) {
                    $where[] = "r.$col BETWEEN ? AND ?";
                    array_push($params, (int)$b['min_score'], (int)$b['max_score']);
                } else {
                    $where[] = '1=0';
                }
            }
        }
        foreach (['inh_l' => 'inh_likelihood', 'inh_i' => 'inh_impact', 'res_l' => 'res_likelihood', 'res_i' => 'res_impact'] as $k => $col) {
            if ($f[$k] !== null) {
                $where[] = "r.$col = ?";
                $params[] = $f[$k];
            }
        }
        if ($f['control'] !== '') {
            $where[] = 'r.control_assessment = ?';
            $params[] = $f['control'];
        }
        if ($f['overdue_review'] === '1') {
            $where[] = 'r.review_date < ? AND r.status = ?';
            array_push($params, date('Y-m-d'), 'active');
        }
        if ($f['high'] === '1') {
            $where[] = 'r.res_score >= COALESCE((SELECT MIN(min_score) FROM score_bands WHERE is_high = 1), 26)';
        }
        return [implode(' AND ', $where), $params];
    }

    public const SELECT = 'SELECT r.*, c.name_en AS cat_en, c.name_ar AS cat_ar, c.kind AS cat_kind,
        d.name_en AS dept_en, d.name_ar AS dept_ar, b.name_en AS branch_en, b.name_ar AS branch_ar,
        u.full_name AS owner_name
        FROM risks r
        JOIN risk_categories c ON c.id = r.category_id
        JOIN departments d ON d.id = r.department_id
        LEFT JOIN branches b ON b.id = r.branch_id
        JOIN users u ON u.id = r.owner_user_id';

    public static function orderBy(array $f): string
    {
        return ' ORDER BY ' . self::SORTS[$f['sort']] . ' ' . ($f['dir'] === 'asc' ? 'ASC' : 'DESC') . ', r.id DESC';
    }

    public static function count(array $f, array $user): int
    {
        [$w, $p] = self::where($f, $user);
        return (int)Db::val('SELECT COUNT(*) FROM risks r WHERE ' . $w, $p);
    }

    public static function fetch(array $f, array $user, int $limit, int $offset = 0): array
    {
        [$w, $p] = self::where($f, $user);
        return Db::all(self::SELECT . ' WHERE ' . $w . self::orderBy($f) . ' LIMIT ? OFFSET ?', array_merge($p, [$limit, $offset]));
    }
}
