<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\Db;

/**
 * Customer money-laundering risk rating (risk-based approach): four dimensions, each taking the highest
 * risk level (1 low .. 3 high) among the customer's factors, combined with fixed weights, then hard rules.
 */
final class AmlScoring
{
    public const DIMENSIONS = ['customer', 'product', 'channel', 'geography'];
    public const WEIGHTS = ['customer' => 35, 'product' => 25, 'channel' => 15, 'geography' => 25];
    public const HIGH_FROM = 2.4;
    public const MEDIUM_FROM = 1.7;
    /** Months between periodic reviews for each rating. */
    public const REVIEW_MONTHS = ['high' => 12, 'medium' => 24, 'low' => 36];
    public const CDD = ['high' => 'enhanced', 'medium' => 'standard', 'low' => 'simplified'];

    private static array $factors = [];

    /** @param array<string,int> $levels dimension => 1..3 */
    public static function score(array $levels): float
    {
        $sum = 0;
        foreach (self::WEIGHTS as $d => $w) {
            $sum += $w * max(1, min(3, (int)($levels[$d] ?? 1)));
        }
        return round($sum / array_sum(self::WEIGHTS), 2);
    }

    /**
     * Calculated rating with the hard rules: a politically exposed person or a possible sanctions match is always high;
     * a high-risk customer type or high-risk country never rates below medium.
     */
    public static function rating(array $levels, bool $pep, bool $sanctions): string
    {
        $s = self::score($levels);
        $r = $s >= self::HIGH_FROM ? 'high' : ($s >= self::MEDIUM_FROM ? 'medium' : 'low');
        if ($pep || $sanctions) {
            return 'high';
        }
        if ($r === 'low' && (($levels['customer'] ?? 1) >= 3 || ($levels['geography'] ?? 1) >= 3)) {
            return 'medium';
        }
        return $r;
    }

    public static function nextReview(string $rating, string $from): string
    {
        return date('Y-m-d', strtotime($from . ' +' . self::REVIEW_MONTHS[$rating] . ' months'));
    }

    /** Dimension levels of a stored customer, read from its factors. */
    public static function levelsFor(int $customerId): array
    {
        $c = Db::one('SELECT customer_type_id, geography_id FROM aml_customers WHERE id = ?', [$customerId]);
        $lv = ['customer' => 1, 'product' => 1, 'channel' => 1, 'geography' => 1];
        if (!$c) {
            return $lv;
        }
        $ids = array_merge([(int)$c['customer_type_id'], (int)$c['geography_id']], array_map('intval', array_column(Db::all('SELECT factor_id FROM aml_customer_factors WHERE customer_id = ?', [$customerId]), 'factor_id')));
        foreach (Db::all('SELECT dimension, risk_level FROM aml_factors WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids) as $f) {
            $lv[$f['dimension']] = max($lv[$f['dimension']], (int)$f['risk_level']);
        }
        return $lv;
    }

    /**
     * Recalculate and store a customer's rating. When the effective rating goes up, the next review is brought forward
     * to within 30 days so the change is looked at promptly.
     * @return array{score:float,calc:string,rating:string,raised:bool}
     */
    public static function recalc(int $customerId): array
    {
        $c = Db::one('SELECT * FROM aml_customers WHERE id = ?', [$customerId]);
        $lv = self::levelsFor($customerId);
        $calc = self::rating($lv, (bool)$c['is_pep'], (bool)$c['sanctions_match']);
        $eff = $c['override_rating'] ?: $calc;
        $order = ['low' => 1, 'medium' => 2, 'high' => 3];
        $raised = $order[$eff] > $order[$c['rating']];
        $next = $c['next_review_due'];
        if ($raised) {
            $soon = date('Y-m-d', strtotime('+30 days'));
            $next = min($next, $soon);
        }
        Db::update('aml_customers', ['score' => self::score($lv), 'calc_rating' => $calc, 'rating' => $eff, 'cdd_level' => self::CDD[$eff], 'next_review_due' => $next, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$customerId]);
        return ['score' => self::score($lv), 'calc' => $calc, 'rating' => $eff, 'raised' => $raised];
    }

    /**
     * Enterprise-wide view per dimension: inherent level from the customer base, control effectiveness from the
     * linked controls' latest test results, residual = inherent x (1 - 0.5 x effectiveness).
     * @return array<string,array>
     */
    public static function dimensionSummary(): array
    {
        $out = [];
        $n = (int)Db::val("SELECT COUNT(*) FROM aml_customers WHERE status = 'active'");
        foreach (self::DIMENSIONS as $d) {
            if ($d === 'customer' || $d === 'geography') {
                $col = $d === 'customer' ? 'customer_type_id' : 'geography_id';
                $dist = Db::all("SELECT f.risk_level AS lvl, COUNT(*) AS n FROM aml_customers c JOIN aml_factors f ON f.id = c.$col WHERE c.status = 'active' GROUP BY f.risk_level");
            } else {
                $dist = Db::all("SELECT m.lvl, COUNT(*) AS n FROM (SELECT c.id, MAX(f.risk_level) AS lvl FROM aml_customers c JOIN aml_customer_factors cf ON cf.customer_id = c.id JOIN aml_factors f ON f.id = cf.factor_id AND f.dimension = ? WHERE c.status = 'active' GROUP BY c.id) m GROUP BY m.lvl", [$d]);
            }
            $by = [1 => 0, 2 => 0, 3 => 0];
            foreach ($dist as $r) {
                $by[(int)$r['lvl']] = (int)$r['n'];
            }
            $tot = array_sum($by);
            $inh = $tot ? round(($by[1] + 2 * $by[2] + 3 * $by[3]) / $tot, 2) : null;
            $ctl = Db::all('SELECT c.id, c.code, c.name, c.last_result, c.next_test_due FROM aml_dimension_controls x JOIN controls c ON c.id = x.control_id WHERE x.dimension = ? AND c.archived_at IS NULL ORDER BY c.code', [$d]);
            $eff = null;
            if ($ctl) {
                $pts = array_map(static fn($c) => match ($c['last_result']) { 'effective' => 1.0, 'partially_effective' => 0.5, default => 0.0 }, $ctl);
                $eff = array_sum($pts) / count($pts);
            }
            $res = $inh === null ? null : round($inh * (1 - 0.5 * ($eff ?? 0)), 2);
            $out[$d] = ['dist' => $by, 'customers' => $tot, 'inherent' => $inh, 'inherent_level' => self::level($inh), 'controls' => $ctl, 'effectiveness' => $eff === null ? null : (int)round($eff * 100), 'residual' => $res, 'residual_level' => self::level($res), 'base' => $n];
        }
        return $out;
    }

    public static function level(?float $v): ?string
    {
        return $v === null ? null : ($v >= self::HIGH_FROM ? 'high' : ($v >= self::MEDIUM_FROM ? 'medium' : 'low'));
    }

    /** Factors of one dimension whose T24 code list (pipe-separated) contains the value; the dimension default otherwise. */
    public static function factorForCode(string $dimension, string $value): ?array
    {
        $cache = &self::$factors;
        if (!isset($cache[$dimension])) {
            $cache[$dimension] = Db::all('SELECT * FROM aml_factors WHERE dimension = ? AND is_active = 1 ORDER BY risk_level DESC, id', [$dimension]);
        }
        $v = strtoupper(trim($value));
        if ($v !== '') {
            foreach ($cache[$dimension] as $f) {
                if ($f['t24_code'] !== null && in_array($v, array_map(static fn($x) => strtoupper(trim($x)), explode('|', $f['t24_code'])), true)) {
                    return $f;
                }
            }
        }
        foreach ($cache[$dimension] as $f) {
            if ((int)$f['is_default'] === 1) {
                return $f;
            }
        }
        return null;
    }

    /** factorForCode caches the factor list per request; call this after factors change in the same request. */
    public static function resetCache(): void
    {
        self::$factors = [];
    }
}
