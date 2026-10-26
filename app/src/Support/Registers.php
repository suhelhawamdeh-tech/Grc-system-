<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\Audit;
use App\Core\Db;
use App\Core\I18n;

/**
 * Supporting registers of the risk function, driven by app/framework/registers.php: one table, common columns,
 * register-specific fields as JSON, overdue flags on the due date, standard templates, and escalation of
 * expired temporary security acceptances.
 */
final class Registers
{
    private static ?array $cfg = null;

    public static function config(): array
    {
        return self::$cfg ??= require APP_ROOT . '/app/framework/registers.php';
    }

    /** @return array<string,array> register key => definition */
    public static function all(): array
    {
        return array_filter(self::config(), fn($d, $k) => is_array($d) && isset($d['prefix']), ARRAY_FILTER_USE_BOTH);
    }

    public static function def(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    public static function decode(array $row): array
    {
        $row['d'] = json_decode((string)$row['data'], true) ?: [];
        return $row;
    }

    public static function isOpen(string $key, string $status): bool
    {
        return in_array($status, self::def($key)['open'] ?? [], true);
    }

    public static function overdue(array $row): bool
    {
        return $row['due_date'] !== null && $row['due_date'] < date('Y-m-d') && self::isOpen($row['register'], $row['status']);
    }

    /**
     * Status that follows from the fields: a data delivery is received (on time or late) once its receipt date is
     * entered; a security acceptance whose expiry has passed is expired.
     */
    public static function derive(string $key, string $status, ?string $due, array $d): string
    {
        if ($key === 'data' && !empty($d['received_on'])) {
            return $due !== null && $d['received_on'] > $due ? 'late_received' : 'received';
        }
        if ($key === 'data' && $status !== 'not_received') {
            return 'expected';
        }
        if ($key === 'security' && in_array($status, ['active', 'renewed'], true) && $due !== null && $due < date('Y-m-d')) {
            return 'expired';
        }
        return $status;
    }

    /** Counts per register: total, open, overdue. */
    public static function counts(): array
    {
        $out = [];
        foreach (self::all() as $k => $d) {
            $out[$k] = ['total' => 0, 'open' => 0, 'overdue' => 0];
        }
        foreach (Db::all('SELECT register, status, due_date FROM rm_register_items') as $r) {
            if (!isset($out[$r['register']])) {
                continue;
            }
            $out[$r['register']]['total']++;
            if (self::isOpen($r['register'], $r['status'])) {
                $out[$r['register']]['open']++;
                if (self::overdue($r)) {
                    $out[$r['register']]['overdue']++;
                }
            }
        }
        return $out;
    }

    /** Linked records offered for a register's `ref` type. @return array<int,string> */
    public static function refOptions(?string $type): array
    {
        return match ($type) {
            'circular' => array_column(Db::all("SELECT id, CONCAT(COALESCE(code, ''), ' — ', title) AS l FROM regulatory_circulars ORDER BY issued_on DESC, id DESC"), 'l', 'id'),
            'risk' => array_column(Db::all("SELECT id, CONCAT(risk_code, ' — ', title) AS l FROM risks WHERE archived_at IS NULL ORDER BY risk_code"), 'l', 'id'),
            default => [],
        };
    }

    public static function refUrl(?string $type, ?int $id): ?string
    {
        return $id ? match ($type) { 'circular' => 'circulars/' . $id, 'risk' => 'risks/' . $id, default => null } : null;
    }

    public static function create(string $key, array $common, array $d, int $userId): int
    {
        $def = self::def($key);
        $now = date('Y-m-d H:i:s');
        $common['status'] = self::derive($key, $common['status'], $common['due_date'] ?? null, $d);
        $id = Db::insert('rm_register_items', $common + ['register' => $key, 'data' => json_encode($d, JSON_UNESCAPED_UNICODE), 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now]);
        RiskFunction::code('rm_register_items', $def['prefix'], $id);
        Audit::log('register.created', 'register_item', $id, ['register' => $key]);
        return $id;
    }

    /**
     * Standard items: the eight maturity axes for a year, or the board risk committee's annual plan for a year.
     * Items already present (same title and year) are skipped. @return int items added
     */
    public static function applyTemplate(string $key, int $year, int $userId): int
    {
        $ar = I18n::locale() === 'ar';
        $have = [];
        foreach (Db::all('SELECT title, data FROM rm_register_items WHERE register = ?', [$key]) as $r) {
            $have[$r['title'] . '|' . ((json_decode((string)$r['data'], true) ?: [])['year'] ?? '')] = true;
        }
        $n = 0;
        if ($key === 'maturity') {
            foreach (self::def('maturity')['fields']['axis'][2] as $axis) {
                $title = t('enum.rg_axis.' . $axis) . ' — ' . $year;
                if (!isset($have[$title . '|' . $year])) {
                    self::create('maturity', ['title' => $title, 'status' => 'assessed'], ['axis' => $axis, 'year' => $year], $userId);
                    $n++;
                }
            }
        } elseif ($key === 'committee_plan') {
            foreach (self::config()['committee_template'] as [$type, $months, $tAr, $tEn]) {
                foreach ($months as $m) {
                    $title = ($ar ? $tAr : $tEn) . ' — ' . sprintf('%04d-%02d', $year, $m);
                    if (!isset($have[$title . '|' . $year])) {
                        self::create('committee_plan', ['title' => $title, 'status' => 'planned', 'due_date' => date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $year, $m)))],
                            ['committee' => 'board_risk', 'year' => $year, 'item_type' => $type], $userId);
                        $n++;
                    }
                }
            }
        }
        return $n;
    }

    /** Daily: expire temporary security acceptances past their expiry and escalate each once. @return int escalated */
    public static function expireSecurity(): int
    {
        $n = 0;
        foreach (Db::all("SELECT * FROM rm_register_items WHERE register = 'security' AND status IN ('active','renewed') AND due_date < ?", [date('Y-m-d')]) as $r) {
            $d = self::decode($r)['d'];
            $esc = RiskFunction::escalate('security_exception', 'register_item', (int)$r['id'], $r['code'] . ' — ' . $r['title'],
                t('reg.expired_detail', ['system' => $d['system'] ?? '', 'control' => $d['control_ref'] ?? '', 'date' => $r['due_date']]), (int)$r['created_by'], 'committee_chair', 'infosec');
            Db::update('rm_register_items', ['status' => 'expired', 'escalation_id' => $esc, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$r['id']]);
            $n++;
        }
        return $n;
    }

    /** Maturity summary for a year: axis => [level, target]. */
    public static function maturity(int $year): array
    {
        $out = [];
        foreach (Db::all("SELECT data FROM rm_register_items WHERE register = 'maturity'") as $r) {
            $d = json_decode((string)$r['data'], true) ?: [];
            if ((int)($d['year'] ?? 0) === $year && !empty($d['axis'])) {
                $out[$d['axis']] = [isset($d['level']) && $d['level'] !== '' ? (int)$d['level'] : null, isset($d['target']) && $d['target'] !== '' ? (int)$d['target'] : null];
            }
        }
        return $out;
    }
}
