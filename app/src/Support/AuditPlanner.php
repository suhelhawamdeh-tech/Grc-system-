<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\Db;

/**
 * Risk-based audit planning. Each auditable entity gets a priority score from 1 (low) to 5 (high)
 * as a weighted average of six factors, each rated 1..5. The weights and rules are shown to users on the plan page.
 */
final class AuditPlanner
{
    public const WEIGHTS = ['risk' => 30, 'materiality' => 20, 'regulatory' => 15, 'complexity' => 10, 'time' => 15, 'rating' => 10];
    /** Audit cycle in years for each priority: high every year, medium every two years, low every three. */
    public const CYCLE = ['high' => 1, 'medium' => 2, 'low' => 3];

    /** Residual risk score (1..25) of the department's worst active risk, mapped to 1..5; 3 when nothing is recorded. */
    public static function riskFactor(?int $maxResidual): int
    {
        return $maxResidual === null ? 3 : max(1, min(5, (int)ceil($maxResidual / 5)));
    }

    /** Years since the last audit: never or 3+ years = 5, 2+ = 4, 1+ = 3, under a year = 1. */
    public static function timeFactor(?string $lastAudit, string $asOf): int
    {
        if ($lastAudit === null || $lastAudit === '') {
            return 5;
        }
        $years = (new \DateTimeImmutable($lastAudit))->diff(new \DateTimeImmutable($asOf))->y;
        return match (true) {
            $years >= 3 => 5,
            $years >= 2 => 4,
            $years >= 1 => 3,
            default => 1,
        };
    }

    public static function ratingFactor(?string $lastRating): int
    {
        return match ($lastRating) {
            'unsatisfactory' => 5,
            'needs_improvement' => 3,
            'satisfactory' => 1,
            default => 3,
        };
    }

    /** @param array<string,int> $f factor => 1..5 */
    public static function score(array $f): float
    {
        $sum = 0;
        foreach (self::WEIGHTS as $k => $w) {
            $sum += $w * max(1, min(5, (int)($f[$k] ?? 3)));
        }
        return round($sum / array_sum(self::WEIGHTS), 2);
    }

    public static function priority(float $score): string
    {
        return $score >= 3.5 ? 'high' : ($score >= 2.5 ? 'medium' : 'low');
    }

    /** True when the entity must be audited in the given year according to its cycle. */
    public static function due(string $priority, ?string $lastAudit, int $year): bool
    {
        if ($lastAudit === null || $lastAudit === '') {
            return true;
        }
        return (int)substr($lastAudit, 0, 4) + self::CYCLE[$priority] <= $year;
    }

    /**
     * Score every active entity for a plan year, highest first.
     * @return list<array> universe rows + factors, score, priority, due
     */
    public static function ranked(int $year): array
    {
        $asOf = sprintf('%04d-01-01', $year);
        $rows = Db::all("SELECT u.*, d.name_en AS dept_en, d.name_ar AS dept_ar, (SELECT MAX(r.res_score) FROM risks r WHERE r.department_id = u.department_id AND r.archived_at IS NULL AND r.status <> 'closed') AS max_res
            FROM audit_universe u LEFT JOIN departments d ON d.id = u.department_id WHERE u.is_active = 1");
        foreach ($rows as &$r) {
            $f = ['risk' => self::riskFactor($r['max_res'] === null ? null : (int)$r['max_res']), 'materiality' => (int)$r['materiality'], 'regulatory' => (int)$r['regulatory'], 'complexity' => (int)$r['complexity'],
                'time' => self::timeFactor($r['last_audit_date'], $asOf), 'rating' => self::ratingFactor($r['last_rating'])];
            $r['factors'] = $f;
            $r['score'] = self::score($f);
            $r['priority'] = self::priority($r['score']);
            $r['due'] = self::due($r['priority'], $r['last_audit_date'], $year);
        }
        unset($r);
        usort($rows, static fn($a, $b) => [$b['score'], $a['code']] <=> [$a['score'], $b['code']]);
        return $rows;
    }
}
