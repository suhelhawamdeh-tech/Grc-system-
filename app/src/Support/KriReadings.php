<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\Audit;
use App\Core\Db;
use App\Core\Money;

/** Recording a KRI reading: status calculation, data-quality gate and breach register (shared by the form and the CSV import). */
final class KriReadings
{
    /** @return string|null translation key of the data-quality problem, null when acceptable */
    public static function quality(array $kri, int $val, string $comment): ?string
    {
        $prev = Db::val('SELECT value FROM kri_readings WHERE kri_id = ? ORDER BY reading_date DESC, id DESC LIMIT 1', [$kri['id']]);
        if (Kri::isOutlier($val, $prev === null || $prev === false ? null : Money::fromDb($prev)) && trim($comment) === '') {
            return 'kri.err_outlier';
        }
        return null;
    }

    /** @return array{status:string,reading_id:int,breach_id:?int} */
    public static function record(array $kri, int $val, string $date, string $comment, int $userId, ?int $period = null): array
    {
        $crit = $kri['critical_threshold'] === null ? null : Money::fromDb($kri['critical_threshold']);
        $status = Kri::status($val, Money::fromDb($kri['amber_threshold']), Money::fromDb($kri['red_threshold']), $kri['direction'], $crit);
        $now = date('Y-m-d H:i:s');
        $rid = Db::insert('kri_readings', ['kri_id' => $kri['id'], 'period_id' => $period, 'reading_date' => $date, 'value' => Money::toDb($val), 'status' => $status, 'comment' => trim($comment) ?: null, 'entered_by' => $userId, 'created_at' => $now]);
        Audit::log('kri.reading_added', 'kri', (int)$kri['id'], ['reading_id' => $rid, 'value' => Money::toDb($val), 'status' => $status]);
        $bid = null;
        if (in_array($status, ['red', 'critical'], true)) {
            $bid = Db::insert('kri_breaches', ['kri_id' => $kri['id'], 'reading_id' => $rid, 'level' => $status, 'status' => 'open', 'escalated_at' => $status === 'critical' ? $now : null, 'created_at' => $now, 'updated_at' => $now]);
            Audit::log('kri.breach_opened', 'kri', (int)$kri['id'], ['breach_id' => $bid, 'level' => $status]);
        }
        // Board-level appetite metrics: amber warning notice, or immediate escalation at the maximum.
        Appetite::afterReading($kri, $rid, $status, $userId);
        return ['status' => $status, 'reading_id' => $rid, 'breach_id' => $bid];
    }
}
