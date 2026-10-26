<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\Db;

/** Small per-user key/value store (help tour state etc.). */
final class Prefs
{
    public static function get(int $userId, string $key): ?string
    {
        $v = Db::val('SELECT pvalue FROM user_prefs WHERE user_id = ? AND pkey = ?', [$userId, $key]);
        return $v === null || $v === false ? null : (string)$v;
    }

    public static function set(int $userId, string $key, string $value): void
    {
        Db::exec('INSERT INTO user_prefs (user_id, pkey, pvalue) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE pvalue = VALUES(pvalue)', [$userId, $key, $value]);
    }
}
