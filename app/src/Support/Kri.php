<?php
declare(strict_types=1);

namespace App\Support;

final class Kri
{
    /** @param int $value/$amber/$red integer thousandths */
    public static function status(int $value, int $amber, int $red, string $direction, ?int $critical = null): string
    {
        if ($direction === 'lower_worse') {
            if ($critical !== null && $value <= $critical) {
                return 'critical';
            }
            return $value <= $red ? 'red' : ($value <= $amber ? 'amber' : 'green');
        }
        if ($critical !== null && $value >= $critical) {
            return 'critical';
        }
        return $value >= $red ? 'red' : ($value >= $amber ? 'amber' : 'green');
    }

    /** The critical level must lie beyond red in the direction of risk. */
    public static function criticalValid(int $red, int $critical, string $direction): bool
    {
        return $direction === 'lower_worse' ? $critical <= $red : $critical >= $red;
    }

    /** A reading that moves by more than 200% of the previous one (above 3x or below -2x) needs an explanatory comment. */
    public static function isOutlier(int $value, ?int $previous): bool
    {
        return $previous !== null && $previous !== 0 && abs($value - $previous) > 2 * abs($previous);
    }

    /** Thresholds must be ordered consistently with the direction. */
    public static function thresholdsValid(int $amber, int $red, string $direction): bool
    {
        return $direction === 'lower_worse' ? $red <= $amber : $red >= $amber;
    }

    /** First day of the current reporting cycle for a frequency. */
    public static function cycleStart(string $frequency, ?string $today = null): string
    {
        $t = strtotime($today ?? date('Y-m-d'));
        if ($frequency === 'quarterly') {
            $m = (int)date('n', $t);
            return date('Y-', $t) . sprintf('%02d', intdiv($m - 1, 3) * 3 + 1) . '-01';
        }
        return date('Y-m-01', $t);
    }
}
