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
        self::$msgs = self::load(self::$locale);
        self::$fallback = self::$locale === 'en' ? self::$msgs : self::load('en');
    }

    /** Base strings plus optional per-module files (<locale>_*.php). */
    public static function load(string $locale): array
    {
        $m = require APP_ROOT . '/app/lang/' . $locale . '.php';
        foreach (glob(APP_ROOT . '/app/lang/' . $locale . '_*.php') ?: [] as $f) {
            $m = array_merge($m, require $f);
        }
        return $m;
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
