<?php
declare(strict_types=1);

namespace App\Core;

final class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

    private const MAX_USER_FAILS = 5;
    private const MAX_IP_FAILS = 25;
    private const WINDOW = 900; // 15 minutes

    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            $id = Session::get('uid');
            if ($id && Db::ready()) {
                $u = Db::one('SELECT * FROM users WHERE id = ? AND is_active = 1', [(int)$id]);
                if ($u) {
                    self::$user = $u;
                } else {
                    Session::forget('uid');
                }
            }
        }
        return self::$user;
    }

    public static function id(): ?int
    {
        $u = self::user();
        return $u ? (int)$u['id'] : null;
    }

    public static function can(string $perm): bool
    {
        $u = self::user();
        return $u !== null && Perm::roleHas($u['role'], $perm);
    }

    public static function requirePerm(string $perm): array
    {
        $u = self::user();
        if (!$u) {
            throw new HttpException(401);
        }
        if (!Perm::roleHas($u['role'], $perm)) {
            throw new HttpException(403);
        }
        return $u;
    }

    /** Seconds the caller must wait, or 0 when login attempts are allowed. */
    public static function throttled(string $username, string $ip): int
    {
        $since = date('Y-m-d H:i:s', time() - self::WINDOW);
        $u = (int)Db::val('SELECT COUNT(*) FROM login_attempts WHERE success = 0 AND username = ? AND attempted_at >= ?', [$username, $since]);
        $i = (int)Db::val('SELECT COUNT(*) FROM login_attempts WHERE success = 0 AND ip = ? AND attempted_at >= ?', [$ip, $since]);
        if ($u >= self::MAX_USER_FAILS || $i >= self::MAX_IP_FAILS) {
            $oldest = Db::val('SELECT MIN(attempted_at) FROM (SELECT attempted_at FROM login_attempts WHERE success = 0 AND (username = ? OR ip = ?) AND attempted_at >= ? ORDER BY attempted_at DESC LIMIT ' . self::MAX_USER_FAILS . ') x', [$username, $ip, $since]);
            $wait = $oldest ? self::WINDOW - (time() - strtotime((string)$oldest)) : self::WINDOW;
            return max(1, $wait);
        }
        return 0;
    }

    public static function attempt(string $username, string $password): array
    {
        $ip = Request::ip();
        $username = mb_strtolower(trim($username));
        if ($wait = self::throttled($username, $ip)) {
            Audit::log('login.throttled', 'user', null, ['username' => $username], null, $username);
            return ['ok' => false, 'throttled' => $wait];
        }
        $u = Db::one('SELECT * FROM users WHERE username = ?', [$username]);
        // Always verify a hash so timing does not reveal whether the account exists.
        $hash = $u['password_hash'] ?? '$2y$10$usesomesillystringforsaltuOr7KcWSUcCQhVdUQ0yD9lWvmVA1S6e';
        $ok = password_verify($password, $hash) && $u && (int)$u['is_active'] === 1;
        Db::insert('login_attempts', ['username' => mb_substr($username, 0, 64), 'ip' => $ip, 'success' => $ok ? 1 : 0, 'attempted_at' => date('Y-m-d H:i:s')]);
        if (!$ok) {
            Audit::log('login.failed', 'user', $u ? (int)$u['id'] : null, ['username' => $username], null, $username);
            return ['ok' => false];
        }
        if ((int)($u['totp_enabled'] ?? 0) === 1) {
            Session::regenerate();
            Session::set('mfa_uid', (int)$u['id']);
            Session::set('mfa_at', time());
            Audit::log('login.mfa_required', 'user', (int)$u['id'], null, (int)$u['id'], $u['username']);
            return ['ok' => true, 'mfa' => true, 'user' => $u];
        }
        return self::complete($u, $password);
    }

    /** Second factor: a 6-digit authenticator code or a one-time recovery code. */
    public static function finishMfa(string $code): array
    {
        $id = (int)Session::get('mfa_uid', 0);
        $u = $id ? Db::one('SELECT * FROM users WHERE id = ? AND is_active = 1', [$id]) : null;
        if (!$u || time() - (int)Session::get('mfa_at', 0) > 300) {
            Session::forget('mfa_uid');
            return ['ok' => false, 'expired' => true];
        }
        $key = 'mfa:' . $id;
        $ip = Request::ip();
        if ($wait = self::throttled($key, $ip)) {
            return ['ok' => false, 'throttled' => $wait];
        }
        $ok = false;
        $secret = \App\Support\Crypto::decrypt((string)$u['totp_secret_enc']);
        if ($secret !== null && ($step = \App\Support\Totp::verify($secret, $code, null, 1, (int)$u['totp_last_step'])) !== null) {
            Db::update('users', ['totp_last_step' => $step], 'id = ?', [$id]);
            $ok = true;
        } elseif (self::useRecoveryCode($u, $code)) {
            $ok = true;
            Audit::log('login.recovery_code_used', 'user', $id, null, $id, $u['username']);
        }
        Db::insert('login_attempts', ['username' => $key, 'ip' => $ip, 'success' => $ok ? 1 : 0, 'attempted_at' => date('Y-m-d H:i:s')]);
        if (!$ok) {
            Audit::log('login.mfa_failed', 'user', $id, null, $id, $u['username']);
            return ['ok' => false];
        }
        Session::forget('mfa_uid');
        Session::forget('mfa_at');
        return self::complete($u, null);
    }

    private static function useRecoveryCode(array $u, string $code): bool
    {
        $code = strtolower(preg_replace('/[^0-9a-f]/i', '', $code));
        $list = json_decode((string)$u['totp_recovery'], true);
        if (strlen($code) !== 8 || !is_array($list)) {
            return false;
        }
        foreach ($list as $i => $h) {
            if (password_verify($code, $h)) {
                unset($list[$i]);
                Db::update('users', ['totp_recovery' => json_encode(array_values($list))], 'id = ?', [$u['id']]);
                return true;
            }
        }
        return false;
    }

    private static function complete(array $u, ?string $password): array
    {
        Session::regenerate();
        Session::set('uid', (int)$u['id']);
        Session::forget('_csrf');
        if ($password !== null && password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
            Db::update('users', ['password_hash' => password_hash($password, PASSWORD_DEFAULT)], 'id = ?', [$u['id']]);
        }
        Db::update('users', ['last_login_at' => date('Y-m-d H:i:s')], 'id = ?', [$u['id']]);
        // Housekeeping (no cron on shared hosting): prune old attempts.
        Db::exec('DELETE FROM login_attempts WHERE attempted_at < ?', [date('Y-m-d H:i:s', time() - 86400 * 7)]);
        self::$loaded = false;
        self::$user = null;
        Audit::log('login.success', 'user', (int)$u['id'], null, (int)$u['id'], $u['username']);
        return ['ok' => true, 'user' => $u];
    }

    public static function logout(): void
    {
        $u = self::user();
        if ($u) {
            Audit::log('logout', 'user', (int)$u['id']);
        }
        $lang = Session::get('lang');
        Session::destroy();
        self::$user = null;
        self::$loaded = true;
        unset($lang);
    }

    public static function checkPasswordPolicy(string $pw, string $username = ''): ?string
    {
        if (mb_strlen($pw) < 12) {
            return 'password.too_short';
        }
        if ($username !== '' && mb_strtolower($pw) === mb_strtolower($username)) {
            return 'password.same_as_username';
        }
        if (!preg_match('/[A-Za-z\p{L}]/u', $pw) || !preg_match('/\d/', $pw)) {
            return 'password.needs_mix';
        }
        return null;
    }
}
