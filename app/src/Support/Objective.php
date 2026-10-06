<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\Money;

/** Progress and status of a strategic objective. Values are DECIMAL strings (thousandths). */
final class Objective
{
    /** Direction and targets must be consistent: higher-better needs target > baseline, lower-better needs target < baseline. */
    public static function valid(int $baseline, int $target, string $direction): bool
    {
        return $direction === 'lower_better' ? $target < $baseline : $target > $baseline;
    }

    /** Percent of the way from baseline to target, clamped to 0..100. */
    public static function progress(int $baseline, int $target, int $current, string $direction): int
    {
        $span = $direction === 'lower_better' ? $baseline - $target : $target - $baseline;
        $done = $direction === 'lower_better' ? $baseline - $current : $current - $baseline;
        if ($span <= 0) {
            return 0;
        }
        return (int)max(0, min(100, intdiv($done * 100, $span)));
    }

    /** green >= 90 %, amber 60-89 %, red below 60 %. */
    public static function status(int $progress): string
    {
        return $progress >= 90 ? 'green' : ($progress >= 60 ? 'amber' : 'red');
    }

    public static function row(array $o): array
    {
        $p = self::progress(Money::fromDb($o['baseline']), Money::fromDb($o['target']), Money::fromDb($o['current_value']), $o['direction']);
        $o['progress'] = $p;
        $o['rag'] = self::status($p);
        $o['late'] = $p < 100 && $o['due_date'] < date('Y-m-d');
        return $o;
    }
}
