<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\Config;

/** Authenticated encryption (AES-256-GCM) for small secrets stored in the database. Key: config app.secret, else derived from the database credentials. */
final class Crypto
{
    private static function key(): string
    {
        $s = (string)Config::get('app.secret', '');
        return hash('sha256', $s !== '' ? $s : (string)Config::get('db.pass', '') . '|' . (string)Config::get('db.name', '') . '|grc-secrets-v1', true);
    }

    public static function encrypt(string $plain): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $ct = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        return base64_encode($iv . $tag . $ct);
    }

    public static function decrypt(string $blob): ?string
    {
        $raw = base64_decode($blob, true);
        if ($raw === false || strlen($raw) < 29) {
            return null;
        }
        $p = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        return $p === false ? null : $p;
    }
}
