<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    private static ?string $base = null;

    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function isPost(): bool
    {
        return self::method() === 'POST';
    }

    public static function requestPath(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        return is_string($path) ? rawurldecode($path) : '/';
    }

    /** Base path of the app ('' for domain root, '/grc' for a subdirectory). */
    public static function basePath(): string
    {
        if (self::$base !== null) {
            return self::$base;
        }
        $cfg = Config::get('app.base_path');
        if (is_string($cfg)) {
            return self::$base = rtrim($cfg, '/');
        }
        $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
        $base = rtrim(str_replace('\\', '/', dirname($script)), '/');
        // Fallback layout: whole project in the doc root and a root .htaccess forwarding to /public.
        if (str_ends_with($base, '/public') && !str_starts_with(self::requestPath() . '/', $base . '/')) {
            $base = substr($base, 0, -7);
        }
        return self::$base = $base;
    }

    /** Path relative to base path, always starting with '/'. */
    public static function path(): string
    {
        $p = self::requestPath();
        $base = self::basePath();
        if ($base !== '' && str_starts_with($p, $base)) {
            $p = substr($p, strlen($base));
        }
        // Tolerate direct /index.php/ prefix.
        if (str_starts_with($p, '/index.php')) {
            $p = substr($p, 10);
        }
        $p = '/' . trim($p, '/');
        return $p;
    }

    public static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }
        if (Config::get('app.trust_proxy', false) && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
            return true;
        }
        return ($_SERVER['SERVER_PORT'] ?? '') === '443';
    }

    public static function ip(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (Config::get('app.trust_proxy', false) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $first = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
            if (filter_var($first, FILTER_VALIDATE_IP)) {
                $ip = $first;
            }
        }
        return substr($ip, 0, 45);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    public static function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    /** Trimmed string input from POST (or GET fallback if $fromGet). */
    public static function str(string $key, bool $fromGet = false): string
    {
        $v = $fromGet ? ($_GET[$key] ?? '') : ($_POST[$key] ?? '');
        return is_string($v) ? trim($v) : '';
    }

    public static function int(string $key, bool $fromGet = false): ?int
    {
        $s = self::str($key, $fromGet);
        return preg_match('/^\d{1,10}$/', $s) ? (int)$s : null;
    }
}
