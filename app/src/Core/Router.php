<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<int,array{0:string,1:string,2:string,3:?string}> */
    private array $routes = [];

    /** perm: null = public, '@auth' = any signed-in user, otherwise a permission name */
    public function add(string $method, string $pattern, string $handler, ?string $perm = '@auth'): void
    {
        $this->routes[] = [$method, $pattern, $handler, $perm];
    }

    public function get(string $p, string $h, ?string $perm = '@auth'): void
    {
        $this->add('GET', $p, $h, $perm);
    }

    public function post(string $p, string $h, ?string $perm = '@auth'): void
    {
        $this->add('POST', $p, $h, $perm);
    }

    public function dispatch(string $method, string $path): string
    {
        $pathMatched = false;
        foreach ($this->routes as [$m, $pattern, $handler, $perm]) {
            $re = '#^' . preg_replace('/\{(\w+)\}/', '(?P<$1>\d+)', $pattern) . '$#';
            if (!preg_match($re, $path, $mm)) {
                continue;
            }
            $pathMatched = true;
            if ($m !== $method) {
                continue;
            }
            if ($perm !== null) {
                $user = Auth::user();
                if (!$user) {
                    throw new HttpException(401);
                }
                if ($perm !== '@auth' && !Perm::roleHas($user['role'], $perm)) {
                    throw new HttpException(403);
                }
                if ((int)$user['must_change_password'] === 1 && !in_array($path, ['/account', '/account/password', '/account/language', '/logout', '/lang'], true)) {
                    Response::redirect(url('account'));
                }
                if (Settings::get('mfa_required') === '1' && (int)($user['totp_enabled'] ?? 0) !== 1 && !in_array($path, ['/account', '/account/mfa', '/account/mfa/enable', '/account/language', '/account/password', '/logout', '/lang', '/help', '/help/panel'], true)) {
                    Response::redirect(url('account/mfa'));
                }
            }
            if ($method === 'POST' && !Csrf::valid()) {
                throw new HttpException(419);
            }
            $params = array_map('intval', array_filter($mm, 'is_string', ARRAY_FILTER_USE_KEY));
            [$handler, $extra] = array_pad(explode(':', $handler, 2), 2, null);
            [$class, $action] = explode('@', $handler);
            $class = 'App\\Controllers\\' . $class;
            $out = (new $class($extra))->$action(...array_values($params));
            return is_string($out) ? $out : '';
        }
        throw new HttpException($pathMatched ? 405 : 404);
    }
}
