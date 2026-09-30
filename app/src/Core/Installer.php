<?php
declare(strict_types=1);

namespace App\Core;

final class Installer
{
    public static function lockFile(): string
    {
        return Config::storagePath('installed.lock');
    }

    public static function isInstalled(): bool
    {
        if (is_file(self::lockFile())) {
            return true;
        }
        try {
            if (Db::ready() && (int)Db::val('SELECT COUNT(*) FROM users') > 0) {
                @file_put_contents(self::lockFile(), date('c'));
                return true;
            }
        } catch (\Throwable) {
            // tables not created yet
        }
        return false;
    }

    public static function writeLock(): bool
    {
        return @file_put_contents(self::lockFile(), date('c') . "\n") !== false;
    }

    public static function tokenFile(): string
    {
        return Config::storagePath('setup-token.txt');
    }

    /** Returns the setup token, creating it on first use. null if storage is not writable. */
    public static function token(): ?string
    {
        $f = self::tokenFile();
        if (is_file($f)) {
            return trim((string)file_get_contents($f));
        }
        $t = bin2hex(random_bytes(16));
        return @file_put_contents($f, $t . "\n") !== false ? $t : null;
    }
}
