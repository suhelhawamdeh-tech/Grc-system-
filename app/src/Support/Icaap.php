<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\Audit;
use App\Core\Db;

/**
 * ICAAP calculations, identical to the bank's ICAAP workbook: capital ratios and reconciliation, materiality
 * (impact x likelihood), Pillar 1 + Pillar 2 + management buffer, capital margin, and the capital ratio path under
 * stress scenarios with the zone of each ratio (target, warning, trigger, below the minimum).
 */
final class Icaap
{
    public const SCENARIOS = ['base', 'moderate', 'severe'];
    public const STATUSES = ['preparation', 'in_progress', 'committee', 'board', 'submitted', 'closed'];
    public const LATE_DATA_DAYS = 3;

    public static function data(): array
    {
        return require APP_ROOT . '/app/framework/icaap.php';
    }

    /** Open a new annual cycle with its phases, the risks to assess, an empty stress grid and the standard data requests. */
    public static function createCycle(int $year, string $dataDate, ?string $deadline, int $userId): int
    {
        $d = self::data();
        $now = date('Y-m-d H:i:s');
        $ar = \App\Core\I18n::locale() === 'ar';
        return Db::tx(function () use ($d, $year, $dataDate, $deadline, $userId, $now, $ar) {
            $id = Db::insert('icaap_cycles', ['year' => $year, 'data_date' => $dataDate, 'regulatory_deadline' => $deadline, 'status' => 'preparation', 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now]);
            foreach (array_keys($d['phases']) as $no) {
                Db::insert('icaap_phases', ['cycle_id' => $id, 'phase_no' => $no, 'status' => 'not_started']);
            }
            foreach ($d['risks'] as $i => [$key, $section]) {
                Db::insert('icaap_risks', ['cycle_id' => $id, 'risk_key' => $key, 'section' => $section, 'sort_order' => $i]);
            }
            foreach (self::SCENARIOS as $sc) {
                for ($y = 1; $y <= 3; $y++) {
                    Db::insert('icaap_stress', ['cycle_id' => $id, 'scenario' => $sc, 'year_no' => $y]);
                }
            }
            foreach ($d['data_requests'] as [$pAr, $pEn, $iAr, $iEn, $fmt]) {
                Db::insert('icaap_data_requests', ['cycle_id' => $id, 'provider' => $ar ? $pAr : $pEn, 'data_items' => $ar ? $iAr : $iEn, 'data_date' => $dataDate, 'format' => $fmt]);
            }
            Audit::log('icaap.cycle_created', 'icaap_cycle', $id, ['year' => $year]);
            return $id;
        });
    }

    private static function n(mixed $v): ?float
    {
        return $v === null || $v === '' ? null : (float)$v;
    }

    /** Zone of a capital ratio against the approved levels. */
    public static function zone(?float $ratio, array $c): ?string
    {
        if ($ratio === null || self::n($c['reg_min']) === null) {
            return null;
        }
        if ($ratio < (float)$c['reg_min']) {
            return 'below_min';
        }
        if (self::n($c['trigger_ratio']) !== null && $ratio < (float)$c['trigger_ratio']) {
            return 'trigger';
        }
        if (self::n($c['warning_ratio']) !== null && $ratio < (float)$c['warning_ratio']) {
            return 'warning';
        }
        return 'target';
    }

    /** Materiality of one risk: [score, level, material] or nulls when not yet assessed. */
    public static function materiality(?int $impact, ?int $likelihood): array
    {
        if (!$impact || !$likelihood) {
            return [null, null, null];
        }
        $score = $impact * $likelihood;
        $level = $score <= 4 ? 'low' : ($score <= 9 ? 'medium' : ($score <= 16 ? 'high' : 'critical'));
        return [$score, $level, $impact >= 4 || $score >= 10];
    }

    /** All results of a cycle (the workbook's "summary" sheet and everything it is built from). */
    public static function compute(array $c, array $risks, array $stress): array
    {
        $t1 = self::n($c['tier1']);
        $t2 = self::n($c['tier2']);
        $available = $t1 === null && $t2 === null ? null : (float)$t1 + (float)$t2;
        $rwaParts = array_filter([self::n($c['rwa_credit']), self::n($c['rwa_market']), self::n($c['rwa_operational'])], fn($x) => $x !== null);
        $rwa = $rwaParts ? array_sum($rwaParts) : null;
        $car = $available !== null && $rwa ? $available / $rwa : null;
        $core = $t1 !== null && $rwa ? $t1 / $rwa : null;
        $recon = $car !== null && self::n($c['last_reg_ratio']) !== null ? (abs($car - (float)$c['last_reg_ratio']) < 0.00005 ? 'match' : 'difference') : null;

        $mat = [];
        $p2 = 0.0;
        $nMaterial = 0;
        foreach ($risks as $r) {
            [$score, $level, $material] = self::materiality($r['impact'] !== null ? (int)$r['impact'] : null, $r['likelihood'] !== null ? (int)$r['likelihood'] : null);
            $mat[$r['risk_key']] = ['score' => $score, 'level' => $level, 'material' => $material];
            if ($material) {
                $nMaterial++;
                $p2 += (float)($r['capital'] ?? 0);
            }
        }
        $p1 = $rwa !== null && self::n($c['reg_min']) !== null ? $rwa * (float)$c['reg_min'] : null;
        $buffer = self::n($c['mgmt_buffer']) !== null ? ((float)$p1 + $p2) * (float)$c['mgmt_buffer'] : null;
        $required = $p1 !== null ? $p1 + $p2 + (float)$buffer : null;
        $margin = $required !== null && $available !== null ? $available - $required : null;
        $marginPct = $margin !== null && $required ? $margin / $required : null;

        // Stress path: capital(y) = capital(y-1) + profit - distributions - extra provisions; RWA(y) = RWA(y-1) * (1 + growth).
        $path = [];
        $min = [];
        $grid = [];
        foreach ($stress as $s) {
            $grid[$s['scenario']][(int)$s['year_no']] = $s;
        }
        foreach (self::SCENARIOS as $sc) {
            $cap = $available;
            $rw = $rwa;
            $min[$sc] = null;
            for ($y = 1; $y <= 3; $y++) {
                $s = $grid[$sc][$y] ?? [];
                $cap = $cap === null ? null : $cap + (float)($s['profit'] ?? 0) - (float)($s['distributions'] ?? 0) - (float)($s['extra_provisions'] ?? 0);
                $rw = $rw === null ? null : $rw * (1 + (float)($s['rwa_growth'] ?? 0));
                $ratio = $cap !== null && $rw ? $cap / $rw : null;
                $path[$sc][$y] = ['capital' => $cap, 'rwa' => $rw, 'ratio' => $ratio, 'zone' => self::zone($ratio, $c)];
                if ($ratio !== null && ($min[$sc] === null || $ratio < $min[$sc])) {
                    $min[$sc] = $ratio;
                }
            }
        }
        return ['available' => $available, 'rwa' => $rwa, 'car' => $car, 'core' => $core, 'zone' => self::zone($car, $c), 'recon' => $recon,
            'materiality' => $mat, 'material_count' => $nMaterial, 'p1' => $p1, 'p2' => $p2, 'buffer' => $buffer, 'required' => $required, 'margin' => $margin, 'margin_pct' => $marginPct,
            'path' => $path, 'min' => $min, 'min_zone' => array_map(fn($r) => self::zone($r, $c), $min)];
    }

    /** Load a cycle with its parts and results. */
    public static function load(int $id): ?array
    {
        $c = Db::one('SELECT * FROM icaap_cycles WHERE id = ?', [$id]);
        if (!$c) {
            return null;
        }
        $risks = Db::all('SELECT * FROM icaap_risks WHERE cycle_id = ? ORDER BY sort_order, id', [$id]);
        $stress = Db::all('SELECT * FROM icaap_stress WHERE cycle_id = ? ORDER BY FIELD(scenario, \'base\', \'moderate\', \'severe\'), year_no', [$id]);
        $c['narr'] = json_decode((string)($c['narratives'] ?? ''), true) ?: [];
        return ['c' => $c, 'risks' => $risks, 'stress' => $stress, 'phases' => Db::all('SELECT * FROM icaap_phases WHERE cycle_id = ? ORDER BY phase_no', [$id]), 'r' => self::compute($c, $risks, $stress)];
    }

    /** Conditions of the escalation card that need the risk committee chair (deficit, base scenario below warning, deadline at risk). */
    public static function alerts(array $x): array
    {
        $c = $x['c'];
        $r = $x['r'];
        $out = [];
        if ($r['recon'] === 'difference') {
            $out[] = 'recon';
        }
        if ($r['margin'] !== null && $r['margin'] < 0) {
            $out[] = 'deficit';
        }
        if (in_array($r['min_zone']['base'] ?? null, ['warning', 'trigger', 'below_min'], true)) {
            $out[] = 'base_below_warning';
        }
        if (in_array($r['min_zone']['severe'] ?? null, ['below_min'], true)) {
            $out[] = 'severe_below_min';
        }
        if ($c['regulatory_deadline'] && !in_array($c['status'], ['submitted', 'closed'], true) && $c['regulatory_deadline'] <= date('Y-m-d', strtotime('+14 days'))) {
            $out[] = 'deadline';
        }
        $late = (int)Db::val('SELECT COUNT(*) FROM icaap_data_requests WHERE cycle_id = ? AND received_on IS NULL AND due_on IS NOT NULL AND due_on < ?', [$c['id'], date('Y-m-d', strtotime('-' . self::LATE_DATA_DAYS . ' days'))]);
        if ($late) {
            $out[] = 'late_data';
        }
        return $out;
    }

    /** Escalate to the risk committee chair when the capital margin is negative or the base scenario falls below the warning level. */
    public static function escalateIfNeeded(array $x, int $userId): ?int
    {
        $a = array_intersect(self::alerts($x), ['deficit', 'base_below_warning', 'severe_below_min']);
        if (!$a) {
            return null;
        }
        $c = $x['c'];
        return RiskFunction::escalate('icaap', 'icaap_cycle', (int)$c['id'], t('icaap.esc_title', ['y' => $c['year']]),
            implode(' ', array_map(fn($k) => t(sprintf('icaap.alert_%s', $k)), $a)), $userId, 'committee_chair', 'basel');
    }
}
