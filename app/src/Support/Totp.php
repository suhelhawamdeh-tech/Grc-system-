<?php
declare(strict_types=1);

namespace App\Support;

/** RFC 6238 time-based one-time passwords (HMAC-SHA1, 30 s step, 6 digits), compatible with common authenticator apps. */
final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function newSecret(): string
    {
        return self::base32(random_bytes(20));
    }

    public static function base32(string $bin): string
    {
        $bits = '';
        foreach (str_split($bin) as $ch) {
            $bits .= str_pad(decbin(ord($ch)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }
        return $out;
    }

    public static function unbase32(string $s): string
    {
        $bits = '';
        foreach (str_split(strtoupper(preg_replace('/[\s=-]/', '', $s))) as $ch) {
            $p = strpos(self::ALPHABET, $ch);
            if ($p === false) {
                return '';
            }
            $bits .= str_pad(decbin($p), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }
        return $out;
    }

    public static function code(string $secretBase32, int $step, int $digits = 6): string
    {
        $key = self::unbase32($secretBase32);
        $hash = hash_hmac('sha1', pack('J', $step), $key, true);
        $o = ord($hash[19]) & 0xf;
        $n = ((ord($hash[$o]) & 0x7f) << 24) | (ord($hash[$o + 1]) << 16) | (ord($hash[$o + 2]) << 8) | ord($hash[$o + 3]);
        return str_pad((string)($n % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
    }

    /** @return int|null the matching time step (to prevent replay), or null when the code is wrong */
    public static function verify(string $secretBase32, string $code, ?int $now = null, int $window = 1, int $lastUsedStep = 0): ?int
    {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== 6 || self::unbase32($secretBase32) === '') {
            return null;
        }
        $cur = intdiv($now ?? time(), 30);
        for ($d = -$window; $d <= $window; $d++) {
            $step = $cur + $d;
            if ($step > $lastUsedStep && hash_equals(self::code($secretBase32, $step), $code)) {
                return $step;
            }
        }
        return null;
    }

    public static function uri(string $secret, string $account, string $issuer): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account) . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=30';
    }
}
