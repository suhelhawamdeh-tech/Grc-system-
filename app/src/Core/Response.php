<?php
declare(strict_types=1);

namespace App\Core;

final class Response
{
    public static function redirect(string $to, int $code = 302): never
    {
        header('Location: ' . $to, true, $code);
        exit;
    }

    /** Only allow same-site relative redirect targets. */
    public static function safeNext(?string $next, string $fallback = ''): string
    {
        if ($next && preg_match('#^/(?!/)[^\r\n\\\\]*$#', $next)) {
            return $next;
        }
        return $fallback !== '' ? $fallback : url('');
    }

    public static function securityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: same-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'self'");
        if (Request::isHttps() && Config::get('app.hsts', false)) {
            header('Strict-Transport-Security: max-age=31536000');
        }
        header('Cache-Control: no-store');
    }
}
