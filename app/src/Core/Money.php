<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Exact decimal money handling using integer thousandths (DB scale is always DECIMAL(18,3)).
 * Avoids float rounding so that net = gross - recoveries is always exact.
 */
final class Money
{
    public const SCALE = 3;

    /** Parse user input ("1,234.5", "١٢٣٤٫٥" Arabic digits) into integer thousandths, or null if invalid. */
    public static function parse(string $s, ?int $maxDecimals = null): ?int
    {
        $s = trim(strtr($s, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '٫' => '.', '٬' => ',']));
        if ($s === '') {
            return 0;
        }
        $s = str_replace([',', ' '], '', $s);
        if (!preg_match('/^(\d{1,15})(?:\.(\d{1,3}))?$/', $s, $m)) {
            return null;
        }
        $dec = $m[2] ?? '';
        if ($maxDecimals !== null && strlen(rtrim($dec, '0')) > $maxDecimals) {
            return null;
        }
        return (int)$m[1] * 1000 + (int)str_pad($dec, 3, '0');
    }

    /** Integer thousandths -> DB decimal string. */
    public static function toDb(int $v): string
    {
        $neg = $v < 0;
        $v = abs($v);
        return ($neg ? '-' : '') . intdiv($v, 1000) . '.' . str_pad((string)($v % 1000), 3, '0', STR_PAD_LEFT);
    }

    /** DB decimal string / number -> integer thousandths. */
    public static function fromDb(mixed $v): int
    {
        if ($v === null || $v === '') {
            return 0;
        }
        $s = (string)$v;
        $neg = str_starts_with($s, '-');
        $s = ltrim($s, '-');
        [$i, $d] = array_pad(explode('.', $s, 2), 2, '0');
        $n = (int)$i * 1000 + (int)str_pad(substr($d, 0, 3), 3, '0');
        return $neg ? -$n : $n;
    }

    public static function decimals(): int
    {
        return max(0, min(3, (int)Settings::get('currency_decimals', '3')));
    }

    public static function symbol(): string
    {
        $k = I18n::locale() === 'ar' ? 'currency_symbol_ar' : 'currency_symbol_en';
        $v = (string)Settings::get($k, '');
        return $v !== '' ? $v : (string)Settings::get('currency_code', 'LYD');
    }

    /** Plain decimal string (no separators, no symbol) using the configured decimals: for CSV and edit forms. */
    public static function plain(mixed $dbValue): string
    {
        $n = self::fromDb($dbValue);
        $dec = self::decimals();
        $unit = 10 ** (3 - $dec);
        $n = intdiv(abs($n) + intdiv($unit, 2), $unit) * $unit * ($n < 0 ? -1 : 1);
        $s = self::toDb($n);
        return $dec === 0 ? explode('.', $s)[0] : substr($s, 0, strpos($s, '.') + 1 + $dec);
    }

    public static function format(mixed $dbValue, bool $withSymbol = true): string
    {
        $n = self::fromDb($dbValue);
        $dec = self::decimals();
        $neg = $n < 0;
        $n = abs($n);
        $unit = 10 ** (3 - $dec);
        $n = intdiv($n + intdiv($unit, 2), $unit) * $unit; // round half up to configured decimals
        $int = number_format(intdiv($n, 1000), 0, '.', ',');
        $frac = $dec > 0 ? '.' . substr(str_pad((string)($n % 1000), 3, '0', STR_PAD_LEFT), 0, $dec) : '';
        return ($neg ? '-' : '') . $int . $frac . ($withSymbol ? ' ' . self::symbol() : '');
    }
}
