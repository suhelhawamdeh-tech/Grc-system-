<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\Audit;
use App\Core\Db;
use App\Core\I18n;
use App\Core\Money;

/**
 * Risk appetite framework: board-level appetite metrics (KRIs with tier = board), their template, the green/amber/red
 * dashboard and the handling of warning (amber) and maximum (red) levels.
 */
final class Appetite
{
    private const UNITS_AR = ['days' => 'يوم', 'cases' => 'حالة', 'risks' => 'خطر', 'findings' => 'ملاحظة', 'items' => 'بند', 'returns' => 'تقرير', 'pp' => 'نقطة', 'LYD' => 'د.ل'];

    public static function template(): array
    {
        return require APP_ROOT . '/app/framework/raf.php';
    }

    public static function categories(): array
    {
        return self::template()['categories'];
    }

    /**
     * Create the template metrics that do not exist yet, uncalibrated (no limits) until the board sets the values.
     * @return int metrics created
     */
    public static function createFromTemplate(int $departmentId, int $ownerId, int $userId): int
    {
        $ar = I18n::locale() === 'ar';
        $now = date('Y-m-d H:i:s');
        $n = 0;
        foreach (self::template()['metrics'] as [$key, $cat, $nAr, $nEn, $fAr, $fEn, $dir, $freq, $section, $reg, $unit]) {
            if (Db::val('SELECT 1 FROM kris WHERE template_key = ?', [$key])) {
                continue;
            }
            $id = Db::insert('kris', ['name' => $ar ? $nAr : $nEn, 'description' => $ar ? $nEn : $nAr, 'category_id' => null, 'department_id' => $departmentId, 'owner_user_id' => $ownerId, 'risk_id' => null,
                'unit' => $ar ? (self::UNITS_AR[$unit] ?? $unit) : $unit, 'direction' => $dir, 'amber_threshold' => '0.000', 'red_threshold' => '0.000', 'critical_threshold' => null, 'data_source' => 'manual', 'is_composite' => 0,
                'frequency' => in_array($freq, ['daily', 'weekly', 'monthly'], true) ? 'monthly' : 'quarterly', 'tier' => 'board', 'raf_category' => $cat, 'formula' => $ar ? $fAr : $fEn, 'monitor_section' => $section,
                'report_frequency' => $freq, 'is_regulatory' => $reg, 'calibrated' => 0, 'template_key' => $key, 'is_active' => 1, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now]);
            Db::update('kris', ['code' => sprintf('KRI-%04d', $id)], 'id = ?', [$id]);
            $n++;
        }
        if ($n) {
            Audit::log('appetite.template_created', 'kri', null, ['metrics' => $n]);
        }
        return $n;
    }

    /** Board metrics grouped by appetite category, each with its last readings (newest first). */
    public static function dashboard(): array
    {
        $rows = Db::all("SELECT k.*, d.name_en AS dept_en, d.name_ar AS dept_ar, u.full_name AS owner_name FROM kris k JOIN departments d ON d.id = k.department_id JOIN users u ON u.id = k.owner_user_id
            WHERE k.tier = 'board' AND k.is_active = 1 ORDER BY FIELD(k.raf_category, 'capital', 'credit', 'market', 'liquidity', 'operational', 'infosec', 'continuity', 'sharia', 'reputation'), k.code");
        $out = [];
        foreach ($rows as $k) {
            $k['readings'] = Db::all('SELECT reading_date, value, status FROM kri_readings WHERE kri_id = ? ORDER BY reading_date DESC, id DESC LIMIT 3', [$k['id']]);
            $k['last'] = $k['readings'][0] ?? null;
            $k['trend'] = self::trend($k);
            $out[$k['raf_category'] ?: 'other'][] = $k;
        }
        return $out;
    }

    /** better | worse | stable | null, comparing the last two readings in the direction of risk. */
    public static function trend(array $k): ?string
    {
        if (count($k['readings']) < 2) {
            return null;
        }
        $a = Money::fromDb($k['readings'][0]['value']);
        $b = Money::fromDb($k['readings'][1]['value']);
        if ($a === $b) {
            return 'stable';
        }
        $up = $a > $b;
        return ($k['direction'] === 'higher_worse') === $up ? 'worse' : 'better';
    }

    /** Totals for the dashboard tiles: green, amber, red (incl. critical), no reading, uncalibrated. */
    public static function counts(array $groups): array
    {
        $c = ['green' => 0, 'amber' => 0, 'red' => 0, 'none' => 0, 'uncalibrated' => 0];
        foreach ($groups as $metrics) {
            foreach ($metrics as $k) {
                if (!(int)$k['calibrated']) {
                    $c['uncalibrated']++;
                } elseif (!$k['last']) {
                    $c['none']++;
                } else {
                    $c[in_array($k['last']['status'], ['red', 'critical'], true) ? 'red' : $k['last']['status']]++;
                }
            }
        }
        return $c;
    }

    /**
     * After a reading of a board metric: amber opens a warning notice (analysis and preventive plan by the owner);
     * red or critical is escalated at once to the risk committee chair.
     */
    public static function afterReading(array $kri, int $readingId, string $status, int $userId): void
    {
        if (($kri['tier'] ?? 'kri') !== 'board') {
            return;
        }
        $now = date('Y-m-d H:i:s');
        if ($status === 'amber') {
            Db::insert('kri_breaches', ['kri_id' => $kri['id'], 'reading_id' => $readingId, 'level' => 'amber', 'status' => 'open', 'escalated_at' => null, 'created_at' => $now, 'updated_at' => $now]);
            Audit::log('appetite.warning', 'kri', (int)$kri['id'], ['reading_id' => $readingId]);
        } elseif (in_array($status, ['red', 'critical'], true)) {
            $value = Money::plain((string)Db::val('SELECT value FROM kri_readings WHERE id = ?', [$readingId]));
            $esc = RiskFunction::escalate('appetite_red', 'kri_reading', $readingId, t('raf.esc_title', ['code' => $kri['code'], 'name' => $kri['name']]),
                t('raf.esc_detail', ['value' => $value . ' ' . $kri['unit'], 'limit' => Money::plain((string)$kri['red_threshold']) . ' ' . $kri['unit'], 'reg' => (int)$kri['is_regulatory'] ? t('raf.esc_regulatory') : '']),
                $userId, 'committee_chair', $kri['monitor_section'] ?: null);
            Db::exec('UPDATE kri_breaches SET escalated_at = COALESCE(escalated_at, ?) WHERE reading_id = ?', [$now, $readingId]);
            Audit::log('appetite.limit_reached', 'kri', (int)$kri['id'], ['reading_id' => $readingId, 'escalation_id' => $esc]);
        }
    }
}
