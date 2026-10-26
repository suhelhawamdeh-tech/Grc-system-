<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\Money;

final class Scoring
{
    public const MIN = 1;
    public const MAX = 5;

    public static function score(int $likelihood, int $impact): int
    {
        return $likelihood * $impact;
    }

    /**
     * Advisory only: the highest impact level whose configured financial threshold
     * does not exceed the estimated amount (integer thousandths).
     */
    public static function impactForAmount(int $amount): ?int
    {
        $best = null;
        foreach (Ref::levels('impact') as $lvl => $row) {
            if ($row['financial_min'] === null) {
                continue;
            }
            if (Money::fromDb($row['financial_min']) <= $amount) {
                $best = $lvl;
            }
        }
        return $best;
    }

    /** Check that bands tile 1..25 exactly (no gaps, no overlaps). @return string[] error keys */
    public static function validateBands(array $bands): array
    {
        usort($bands, static fn($a, $b) => $a['min'] <=> $b['min']);
        $errors = [];
        $expect = 1;
        foreach ($bands as $b) {
            if ($b['min'] > $b['max']) {
                $errors[] = 'scales.err_band_order';
            }
            if ($b['min'] !== $expect) {
                $errors[] = 'scales.err_band_coverage';
            }
            $expect = $b['max'] + 1;
        }
        if ($expect !== self::MAX * self::MAX + 1) {
            $errors[] = 'scales.err_band_coverage';
        }
        return array_values(array_unique($errors));
    }
}
