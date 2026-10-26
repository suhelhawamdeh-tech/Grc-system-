<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Db;
use App\Core\I18n;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Settings;

final class AuthController extends Controller
{
    public function loginForm(): string
    {
        if (Auth::user()) {
            Response::redirect(url(''));
        }
        return $this->view('auth/login', ['title' => t('auth.login'), 'next' => Request::str('next', true), 'expired' => Session::get('_expired')], 'layout/guest');
    }

    public function login(): never
    {
        $username = Request::str('username');
        $password = (string)($_POST['password'] ?? '');
        $next = Request::str('next');
        if ($username === '' || $password === '') {
            $this->invalid(['login' => t('auth.invalid')], url('login', ['next' => $next]));
        }
        $r = Auth::attempt($username, $password);
        if (!$r['ok']) {
            if (!empty($r['throttled'])) {
                $this->invalid(['login' => t('auth.throttled', ['minutes' => (int)ceil($r['throttled'] / 60)])], url('login', ['next' => $next]));
            }
            $this->invalid(['login' => t('auth.invalid')], url('login', ['next' => $next]));
        }
        if (!empty($r['mfa'])) {
            Response::redirect(url('login/mfa', ['next' => $next]));
        }
        $this->afterLogin($r, $next);
    }

    private function afterLogin(array $r, string $next): never
    {
        Session::forget('_expired');
        if (!empty($r['user']['lang'])) {
            Session::set('lang', $r['user']['lang']);
        }
        if ((int)$r['user']['must_change_password'] === 1) {
            Response::redirect(url('account'));
        }
        Response::redirect(Response::safeNext($next, url('')));
    }

    public function logout(): never
    {
        Auth::logout();
        Response::redirect(url('login'));
    }

    public function account(): string
    {
        return $this->view('auth/account', ['title' => t('account.title'), 'user' => $this->user()]);
    }

    public function changePassword(): never
    {
        $u = $this->user();
        $cur = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $conf = (string)($_POST['confirm_password'] ?? '');
        $errors = [];
        if (!password_verify($cur, $u['password_hash'])) {
            $errors['current_password'] = t('account.current_wrong');
        }
        if ($err = Auth::checkPasswordPolicy($new, $u['username'])) {
            $errors['new_password'] = t($err);
        } elseif ($new === $cur) {
            $errors['new_password'] = t('password.same_as_old');
        }
        if ($new !== $conf) {
            $errors['confirm_password'] = t('password.mismatch');
        }
        if ($errors) {
            $this->invalid($errors, url('account'));
        }
        Db::update('users', ['password_hash' => password_hash($new, PASSWORD_DEFAULT), 'must_change_password' => 0, 'password_changed_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$u['id']]);
        Session::regenerate();
        $this->audit('password.changed', 'user', (int)$u['id']);
        $this->redirect(url(''), 'account.password_changed');
    }

    public function setLanguagePref(): never
    {
        $u = $this->user();
        $l = Request::str('lang');
        if (in_array($l, I18n::LOCALES, true)) {
            Db::update('users', ['lang' => $l], 'id = ?', [$u['id']]);
            Session::set('lang', $l);
        }
        $this->redirect(url('account'), 'account.language_saved');
    }

    /** GET /lang?l=ar|en&next=/path : language switch (works before login too). */
    public function lang(): never
    {
        $l = Request::str('l', true);
        if (in_array($l, I18n::LOCALES, true)) {
            Session::set('lang', $l);
            setcookie('grc_lang', $l, ['expires' => time() + 86400 * 365, 'path' => Request::basePath() . '/', 'secure' => Request::isHttps(), 'httponly' => true, 'samesite' => 'Lax']);
            if ($u = Auth::user()) {
                Db::update('users', ['lang' => $l], 'id = ?', [$u['id']]);
            }
        }
        Response::redirect(Response::safeNext(Request::str('next', true), url('')));
    }

    public function setNotifications(): never
    {
        $u = $this->user();
        \App\Support\Prefs::set((int)$u['id'], 'email_notifications', isset($_POST['email_on']) ? '1' : '0');
        $this->redirect(url('account'), 'account.email_saved');
    }

    // ---- multi-factor authentication (TOTP)
    public function mfaForm(): string
    {
        if (!Session::get('mfa_uid')) {
            Response::redirect(url('login'));
        }
        return $this->view('auth/mfa', ['title' => t('mfa.login_title'), 'next' => Request::str('next', true)], 'layout/guest');
    }

    public function mfaVerify(): never
    {
        $next = Request::str('next');
        $r = Auth::finishMfa((string)($_POST['code'] ?? ''));
        if (!$r['ok']) {
            if (!empty($r['expired'])) {
                Response::redirect(url('login'));
            }
            $msg = !empty($r['throttled']) ? t('auth.throttled', ['minutes' => (int)ceil($r['throttled'] / 60)]) : t('mfa.invalid');
            $this->invalid(['login' => $msg], url('login/mfa', ['next' => $next]));
        }
        $this->afterLogin($r, $next);
    }

    public function mfaSetup(): string
    {
        $u = $this->user();
        $secret = null;
        if (!(int)$u['totp_enabled']) {
            $secret = (string)Session::get('mfa_setup_secret', '');
            if ($secret === '') {
                $secret = \App\Support\Totp::newSecret();
                Session::set('mfa_setup_secret', $secret);
            }
        }
        $org = \App\Core\Settings::get('org_name_en', 'GRC') ?: 'GRC';
        return $this->view('auth/mfa_setup', ['title' => t('mfa.title'), 'user' => $u, 'secret' => $secret, 'uri' => $secret ? \App\Support\Totp::uri($secret, $u['username'], $org) : null, 'codes' => Session::peek('recovery_codes'), 'required' => \App\Core\Settings::get('mfa_required') === '1']);
    }

    public function mfaEnable(): never
    {
        $u = $this->user();
        $secret = (string)Session::get('mfa_setup_secret', '');
        $step = $secret !== '' ? \App\Support\Totp::verify($secret, (string)($_POST['code'] ?? '')) : null;
        if ($step === null) {
            $this->invalid(['code' => t('mfa.invalid')], url('account/mfa'));
        }
        $codes = [];
        $hashes = [];
        for ($i = 0; $i < 8; $i++) {
            $c = bin2hex(random_bytes(4));
            $codes[] = substr($c, 0, 4) . '-' . substr($c, 4);
            $hashes[] = password_hash($c, PASSWORD_DEFAULT);
        }
        Db::update('users', ['totp_secret_enc' => \App\Support\Crypto::encrypt($secret), 'totp_enabled' => 1, 'totp_last_step' => $step, 'totp_recovery' => json_encode($hashes)], 'id = ?', [$u['id']]);
        Session::forget('mfa_setup_secret');
        Session::flash('recovery_codes', $codes);
        $this->audit('mfa.enabled', 'user', (int)$u['id']);
        $this->redirect(url('account/mfa'), 'mfa.enabled');
    }

    public function mfaDisable(): never
    {
        $u = $this->user();
        if (!password_verify((string)($_POST['password'] ?? ''), $u['password_hash'])) {
            $this->invalid(['password' => t('account.current_wrong')], url('account/mfa'));
        }
        if (\App\Core\Settings::get('mfa_required') === '1') {
            $this->redirect(url('account/mfa'), 'mfa.err_required', 'error');
        }
        Db::update('users', ['totp_secret_enc' => null, 'totp_enabled' => 0, 'totp_recovery' => null, 'totp_last_step' => 0], 'id = ?', [$u['id']]);
        $this->audit('mfa.disabled', 'user', (int)$u['id']);
        $this->redirect(url('account/mfa'), 'mfa.disabled');
    }

    // ---- password reset by e-mail
    public static function resetAvailable(): bool
    {
        return \App\Support\Mailer::configured() && (string)\App\Core\Config::get('app.base_url', '') !== '';
    }

    public function forgotForm(): string
    {
        return $this->view('auth/forgot', ['title' => t('reset.title'), 'available' => self::resetAvailable()], 'layout/guest');
    }

    public function forgot(): never
    {
        $ident = mb_strtolower(trim((string)($_POST['identifier'] ?? '')));
        if (self::resetAvailable() && $ident !== '') {
            $u = Db::one("SELECT * FROM users WHERE is_active = 1 AND email IS NOT NULL AND email <> '' AND (username = ? OR email = ?)", [$ident, $ident]);
            $recent = $u ? (int)Db::val('SELECT COUNT(*) FROM password_resets WHERE user_id = ? AND created_at >= ?', [$u['id'], date('Y-m-d H:i:s', time() - 3600)]) : 99;
            if ($u && $recent < 3) {
                $token = bin2hex(random_bytes(32));
                Db::insert('password_resets', ['user_id' => $u['id'], 'token_hash' => hash('sha256', $token), 'expires_at' => date('Y-m-d H:i:s', time() + 3600), 'ip' => Request::ip(), 'created_at' => date('Y-m-d H:i:s')]);
                $link = rtrim((string)\App\Core\Config::get('app.base_url'), '/') . '/password/reset?token=' . $token;
                $orig = I18n::locale();
                I18n::init($u['lang'] ?: 'ar');
                $err = \App\Support\Mailer::send($u['email'], t('reset.mail_subject'), t('reset.mail_body', ['link' => $link]));
                $subject = t('reset.mail_subject');
                I18n::init($orig);
                Db::insert('email_log', ['user_id' => $u['id'], 'kind' => 'reset', 'recipient' => $u['email'], 'subject' => mb_substr($subject, 0, 250), 'status' => $err === null ? 'sent' : 'failed', 'error' => $err ? mb_substr($err, 0, 250) : null, 'created_at' => date('Y-m-d H:i:s')]);
                Audit::log('password.reset_requested', 'user', (int)$u['id'], null, (int)$u['id'], $u['username']);
            }
        }
        // Same answer whether or not the account exists.
        $this->redirect(url('password/forgot'), 'reset.sent', 'info');
    }

    private function resetRow(string $token): ?array
    {
        if (!preg_match('/^[0-9a-f]{64}$/', $token)) {
            return null;
        }
        return Db::one('SELECT r.*, u.username FROM password_resets r JOIN users u ON u.id = r.user_id WHERE r.token_hash = ? AND r.used_at IS NULL AND r.expires_at > NOW() AND u.is_active = 1', [hash('sha256', $token)]);
    }

    public function resetForm(): string
    {
        $token = Request::str('token', true);
        return $this->view('auth/reset', ['title' => t('reset.new_title'), 'token' => $token, 'valid' => $this->resetRow($token) !== null], 'layout/guest');
    }

    public function reset(): never
    {
        $token = Request::str('token');
        $row = $this->resetRow($token);
        if (!$row) {
            $this->redirect(url('password/forgot'), 'reset.invalid', 'error');
        }
        $new = (string)($_POST['new_password'] ?? '');
        $conf = (string)($_POST['confirm_password'] ?? '');
        $errors = [];
        if ($err = Auth::checkPasswordPolicy($new, $row['username'])) {
            $errors['new_password'] = t($err);
        }
        if ($new !== $conf) {
            $errors['confirm_password'] = t('password.mismatch');
        }
        if ($errors) {
            $this->invalid($errors, url('password/reset', ['token' => $token]));
        }
        Db::tx(function () use ($row, $new) {
            Db::update('users', ['password_hash' => password_hash($new, PASSWORD_DEFAULT), 'must_change_password' => 0, 'password_changed_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$row['user_id']]);
            Db::exec('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL', [$row['user_id']]);
        });
        Audit::log('password.reset_completed', 'user', (int)$row['user_id'], null, (int)$row['user_id'], $row['username']);
        $this->redirect(url('login'), 'reset.done');
    }
}
