<?php
declare(strict_types=1);

namespace App\Core;

final class Session
{
    private const IDLE = 1800;      // 30 min inactivity
    private const ABSOLUTE = 28800; // 8 h

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $dir = Config::storagePath('sessions');
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        if (is_dir($dir) && is_writable($dir)) {
            session_save_path($dir);
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_maxlifetime', (string)self::ABSOLUTE);
        // Shared hosts often disable session GC; sessions are pruned in Auth on login as well.
        session_name('GRCSESSID');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => Request::basePath() . '/',
            'secure' => Request::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
        $now = time();
        if (isset($_SESSION['_last']) && ($now - $_SESSION['_last'] > self::IDLE || $now - ($_SESSION['_born'] ?? $now) > self::ABSOLUTE)) {
            $lang = $_SESSION['lang'] ?? null;
            $_SESSION = [];
            session_regenerate_id(true);
            if ($lang) {
                $_SESSION['lang'] = $lang;
            }
            $_SESSION['_expired'] = true;
        }
        $_SESSION['_last'] = $now;
        $_SESSION['_born'] ??= $now;
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
        $_SESSION['_born'] = time();
    }

    public static function get(string $k, mixed $d = null): mixed
    {
        return $_SESSION[$k] ?? $d;
    }

    public static function set(string $k, mixed $v): void
    {
        $_SESSION[$k] = $v;
    }

    public static function forget(string $k): void
    {
        unset($_SESSION[$k]);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /** Value flashed on the previous request; kept for the current one. */
    public static function peek(string $k): mixed
    {
        return $_SESSION['_flash_now'][$k] ?? null;
    }

    public static function flash(string $k, mixed $v): void
    {
        $_SESSION['_flash_next'][$k] = $v;
    }

    /** Rotate flash bags: call once per request after session start. */
    public static function rotateFlash(): void
    {
        $_SESSION['_flash_now'] = $_SESSION['_flash_next'] ?? [];
        $_SESSION['_flash_next'] = [];
    }

    public static function message(string $type, string $text): void
    {
        $_SESSION['_flash_next']['messages'][] = ['type' => $type, 'text' => $text];
    }

    /** Messages set earlier in this same request (used by redirects) are moved to next by default. */
    public static function messages(): array
    {
        return $_SESSION['_flash_now']['messages'] ?? [];
    }
}
