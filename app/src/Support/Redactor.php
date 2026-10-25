<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Removes data that identifies people or accounts before text leaves the platform: e-mail addresses,
 * IBANs, and runs of 7 or more digits (account, card, phone and national numbers). Names cannot be detected
 * reliably, so users are told not to type them; contextual questions send only de-identified fields.
 */
final class Redactor
{
    public const MASK = '[•••]';

    /** @return array{0:string,1:int} redacted text and number of replacements */
    public static function scrub(string $text): array
    {
        $n = 0;
        $patterns = [
            '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/u',
            '/\b[A-Z]{2}[0-9]{2}(?:[ ]?[A-Z0-9]){10,30}\b/u',
            '/(?<![0-9٠-٩])(?:[0-9٠-٩][ \-]?){6,}[0-9٠-٩](?![0-9٠-٩])/u',
        ];
        foreach ($patterns as $re) {
            $text = preg_replace_callback($re, static function ($m) use (&$n) {
                // A plain year range or a date written with dashes is not personal data.
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $m[0])) {
                    return $m[0];
                }
                $n++;
                return self::MASK;
            }, $text) ?? $text;
        }
        return [$text, $n];
    }
}
