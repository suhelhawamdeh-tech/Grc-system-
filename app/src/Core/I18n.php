<?php
declare(strict_types=1);

namespace App\Core;

final class I18n
{
    public const LOCALES = ['ar', 'en'];
    private static string $locale = 'ar';
    private static array $msgs = [];
    private static array $fallback = [];

    public static function init(string $locale): void
    {
        self::$locale = in_array($locale, self::LOCALES, true) ? $locale : 'ar';
        self::$msgs = require APP_ROOT . '/app/lang/' . self::$locale . '.php';
        self::$fallback = self::$locale === 'en' ? self::$msgs : require APP_ROOT . '/app/lang/en.php';
    }

    public static function ready(): bool
    {
        return self::$msgs !== [];
    }

    public static function locale(): string
    {
        return self::$locale;
    }

    public static function t(string $key, array $params = []): string
    {
        $s = self::$msgs[$key] ?? self::$fallback[$key] ?? $key;
        foreach ($params as $k => $v) {
            $s = str_replace(':' . $k, (string)$v, $s);
        }
        return $s;
    }

    public static function has(string $key): bool
    {
        return isset(self::$msgs[$key]);
    }

    /** Pick the locale from session, user preference, cookie, then the configured default. */
    public static function detect(?array $user, string $default): string
    {
        $cands = [Session::get('lang'), $user['lang'] ?? null, $_COOKIE['grc_lang'] ?? null, $default];
        foreach ($cands as $c) {
            if (is_string($c) && in_array($c, self::LOCALES, true)) {
                return $c;
            }
        }
        return 'ar';
    }
}
