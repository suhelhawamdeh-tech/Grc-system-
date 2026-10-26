<?php
declare(strict_types=1);

namespace App\Core;

final class Settings
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            if (Db::ready()) {
                try {
                    foreach (Db::all('SELECT setting_key, setting_value FROM settings') as $r) {
                        self::$cache[$r['setting_key']] = $r['setting_value'];
                    }
                } catch (\Throwable) {
                    // settings table may not exist yet (before install)
                }
            }
        }
        return self::$cache;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $all = self::all();
        return array_key_exists($key, $all) && $all[$key] !== null ? (string)$all[$key] : $default;
    }

    public static function set(string $key, string $value): void
    {
        Db::exec('INSERT INTO settings (setting_key, setting_value, updated_at) VALUES (?,?,?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)', [$key, $value, date('Y-m-d H:i:s')]);
        self::$cache = null;
    }
}
