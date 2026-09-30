<?php
declare(strict_types=1);

namespace App\Support;

final class Kri
{
    /** @param int $value/$amber/$red integer thousandths */
    public static function status(int $value, int $amber, int $red, string $direction): string
    {
        if ($direction === 'lower_worse') {
            return $value <= $red ? 'red' : ($value <= $amber ? 'amber' : 'green');
        }
        return $value >= $red ? 'red' : ($value >= $amber ? 'amber' : 'green');
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
