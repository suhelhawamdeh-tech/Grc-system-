<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\Db;

/** Business impact analysis rules (pure logic plus the gap query). Hours are handled as integer hundredths. */
final class Bia
{
    /** Parse "12", "0.5", "36.25" (max 99999.99) into hundredths; null if invalid. Accepts Arabic-Indic digits. */
    public static function hours(string $s): ?int
    {
        $s = strtr(trim($s), ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '٫' => '.']);
        if (!preg_match('/^\d{1,5}(\.\d{1,2})?$/', $s)) {
            return null;
        }
        return (int)round((float)$s * 100);
    }

    public static function fmt(mixed $dbValue): string
    {
        $v = rtrim(rtrim(number_format((float)$dbValue, 2, '.', ''), '0'), '.');
        return $v === '' ? '0' : $v;
    }

    /** Criticality follows the single highest impact rating (1..5). */
    public static function criticality(int $fin, int $ops, int $legal, int $rep): string
    {
        $m = max($fin, $ops, $legal, $rep);
        return $m >= 5 ? 'critical' : ($m === 4 ? 'high' : ($m === 3 ? 'medium' : 'low'));
    }

    /**
     * Recovery gaps between a process and the systems it depends on.
     * @return array<int,array{system:string,kind:string,need:string,have:string}>
     */
    public static function gaps(int $processId): array
    {
        $out = [];
        $rows = Db::all('SELECT s.code, s.name, s.recovery_hours, s.data_loss_hours, p.rto_hours, p.rpo_hours FROM bia_process_systems ps JOIN bia_systems s ON s.id = ps.system_id JOIN bia_processes p ON p.id = ps.process_id WHERE ps.process_id = ? AND s.is_active = 1', [$processId]);
        foreach ($rows as $r) {
            if ((int)round((float)$r['recovery_hours'] * 100) > (int)round((float)$r['rto_hours'] * 100)) {
                $out[] = ['system' => $r['code'] . ' ' . $r['name'], 'kind' => 'rto', 'need' => self::fmt($r['rto_hours']), 'have' => self::fmt($r['recovery_hours'])];
            }
            if ((int)round((float)$r['data_loss_hours'] * 100) > (int)round((float)$r['rpo_hours'] * 100)) {
                $out[] = ['system' => $r['code'] . ' ' . $r['name'], 'kind' => 'rpo', 'need' => self::fmt($r['rpo_hours']), 'have' => self::fmt($r['data_loss_hours'])];
            }
        }
        return $out;
    }
}
