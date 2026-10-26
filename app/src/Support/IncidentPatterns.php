<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\Db;
use App\Core\Scope;

/** Recurrence detection: incidents sharing category and department within the 12 months before an occurrence date. */
final class IncidentPatterns
{
    public static function similarCount(int $excludeId, int $category, int $dept, string $occurred): int
    {
        return (int)Db::val("SELECT COUNT(*) FROM incidents i WHERE i.id <> ? AND i.archived_at IS NULL AND i.event_type = 'incident' AND i.category_id = ? AND i.department_id = ? AND i.occurrence_date BETWEEN DATE_SUB(?, INTERVAL 365 DAY) AND ?", [$excludeId, $category, $dept, $occurred, $occurred]);
    }

    public static function similar(array $user, int $excludeId, int $category, int $dept, string $occurred): array
    {
        [$w, $p] = Scope::sql($user, 'i');
        return Db::all("SELECT i.id, i.incident_code, i.title, i.occurrence_date, i.net_loss FROM incidents i WHERE i.id <> ? AND i.archived_at IS NULL AND i.event_type = 'incident' AND i.category_id = ? AND i.department_id = ? AND i.occurrence_date BETWEEN DATE_SUB(?, INTERVAL 365 DAY) AND ? AND $w ORDER BY i.occurrence_date DESC LIMIT 10", array_merge([$excludeId, $category, $dept, $occurred, $occurred], $p));
    }
}
