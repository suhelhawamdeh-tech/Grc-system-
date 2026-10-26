<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\Db;

/** Request-scoped cache of reference data. */
final class Ref
{
    private static array $c = [];

    private static function keyed(string $key, string $sql): array
    {
        if (!isset(self::$c[$key])) {
            self::$c[$key] = [];
            foreach (Db::all($sql) as $r) {
                self::$c[$key][(int)$r['id']] = $r;
            }
        }
        return self::$c[$key];
    }

    public static function reset(): void
    {
        self::$c = [];
    }

    public static function departments(bool $activeOnly = false): array
    {
        $all = self::keyed('dept', 'SELECT * FROM departments ORDER BY code');
        return $activeOnly ? array_filter($all, static fn($r) => (int)$r['is_active'] === 1) : $all;
    }

    public static function branches(bool $activeOnly = false): array
    {
        $all = self::keyed('branch', 'SELECT * FROM branches ORDER BY code');
        return $activeOnly ? array_filter($all, static fn($r) => (int)$r['is_active'] === 1) : $all;
    }

    public static function categories(bool $activeOnly = false): array
    {
        $all = self::keyed('cat', 'SELECT * FROM risk_categories ORDER BY sort_order, code');
        return $activeOnly ? array_filter($all, static fn($r) => (int)$r['is_active'] === 1) : $all;
    }

    public static function periods(bool $activeOnly = false): array
    {
        $all = self::keyed('period', 'SELECT * FROM reporting_periods ORDER BY start_date DESC');
        return $activeOnly ? array_filter($all, static fn($r) => (int)$r['is_active'] === 1) : $all;
    }

    public static function users(bool $activeOnly = false): array
    {
        $all = self::keyed('user', 'SELECT id, username, full_name, role, department_id, branch_id, is_active FROM users ORDER BY full_name');
        return $activeOnly ? array_filter($all, static fn($r) => (int)$r['is_active'] === 1) : $all;
    }

    /** @return array<int,array> level => row */
    public static function levels(string $scale): array
    {
        if (!isset(self::$c['lvl_' . $scale])) {
            self::$c['lvl_' . $scale] = [];
            foreach (Db::all('SELECT * FROM rating_levels WHERE scale = ? ORDER BY level', [$scale]) as $r) {
                self::$c['lvl_' . $scale][(int)$r['level']] = $r;
            }
        }
        return self::$c['lvl_' . $scale];
    }

    public static function bands(): array
    {
        if (!isset(self::$c['bands'])) {
            self::$c['bands'] = Db::all('SELECT * FROM score_bands ORDER BY min_score');
        }
        return self::$c['bands'];
    }

    public static function band(int $score): ?array
    {
        foreach (self::bands() as $b) {
            if ($score >= (int)$b['min_score'] && $score <= (int)$b['max_score']) {
                return $b;
            }
        }
        return null;
    }

    public static function name(string $type, ?int $id): string
    {
        if (!$id) {
            return '';
        }
        $row = match ($type) {
            'department' => self::departments()[$id] ?? null,
            'branch' => self::branches()[$id] ?? null,
            'category' => self::categories()[$id] ?? null,
            default => null,
        };
        if ($type === 'user') {
            return (string)(self::users()[$id]['full_name'] ?? '');
        }
        return lname($row);
    }
}
