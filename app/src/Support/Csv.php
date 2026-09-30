<?php
declare(strict_types=1);

namespace App\Support;

/** CSV output safe for Excel (UTF-8 BOM, CRLF) and protected against formula injection. */
final class Csv
{
    /**
     * Neutralise spreadsheet formulas. Text cells starting (after optional whitespace/control chars)
     * with = + - @ or a tab/CR are prefixed with an apostrophe so Excel treats them as text.
     * Real numbers (int/float) are emitted as-is.
     */
    public static function safe(mixed $v): string
    {
        if ($v === null) {
            return '';
        }
        if (is_int($v) || is_float($v)) {
            return (string)$v;
        }
        $s = (string)$v;
        if ($s !== '' && preg_match('/^[\s\x00-\x1f]*[=+\-@\t\r]/u', $s)) {
            return "'" . $s;
        }
        // Fullwidth / Arabic-Indic lookalikes are not interpreted as formulas by Excel; nothing to do.
        return $s;
    }

    public static function line(array $cells, string $delim = ','): string
    {
        $out = [];
        foreach ($cells as $c) {
            $s = self::safe($c);
            if (preg_match('/[",;\r\n\t]/', $s) || $s !== trim($s)) {
                $s = '"' . str_replace('"', '""', $s) . '"';
            }
            $out[] = $s;
        }
        return implode($delim, $out) . "\r\n";
    }

    /** Stream a CSV download. $rows is iterable of arrays (already localised values). */
    public static function download(string $filename, array $header, iterable $rows, string $delim = ','): never
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        $delim = $delim === ';' ? ';' : ',';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) . '"');
        header('X-Content-Type-Options: nosniff');
        echo "\xEF\xBB\xBF";
        echo self::line($header, $delim);
        foreach ($rows as $r) {
            echo self::line($r, $delim);
        }
        exit;
    }
}
