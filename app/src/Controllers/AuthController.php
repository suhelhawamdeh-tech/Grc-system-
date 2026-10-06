<?php
declare(strict_types=1);

namespace App\Controllers;

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
}
