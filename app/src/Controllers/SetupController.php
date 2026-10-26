<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Db;
use App\Core\HttpException;
use App\Core\Installer;
use App\Core\Migrator;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Settings;
use App\Core\Validator;
use App\Support\DemoSeeder;

/** First-run installer. Inaccessible (404) once any user exists or storage/installed.lock is present. */
final class SetupController extends Controller
{
    private function guard(): void
    {
        if (Installer::isInstalled()) {
            throw new HttpException(404);
        }
    }

    private function tokenOk(): bool
    {
        return Session::get('setup_ok') === true;
    }

    public function index(): string
    {
        $this->guard();
        $token = Installer::token();
        $step = !$this->tokenOk() ? 'token' : (Db::ready() ? 'admin' : 'db');
        return $this->view('setup/index', [
            'title' => t('setup.title'), 'step' => $step, 'tokenFile' => Installer::tokenFile(), 'tokenAvailable' => $token !== null,
            'checks' => $this->requirements(), 'migrations' => count(Migrator::files()),
        ], 'layout/guest');
    }

    private function requirements(): array
    {
        $r = [];
        $r['php'] = [PHP_VERSION, version_compare(PHP_VERSION, MIN_PHP, '>=')];
        foreach (['pdo_mysql', 'mbstring', 'json', 'fileinfo', 'ctype'] as $ext) {
            $r[$ext] = [extension_loaded($ext) ? 'OK' : 'missing', extension_loaded($ext)];
        }
        $r['storage'] = [Config::storagePath(), is_writable(Config::storagePath())];
        return $r;
    }

    public function token(): never
    {
        $this->guard();
        $given = (string)($_POST['token'] ?? '');
        $real = Installer::token();
        if ($real === null || !hash_equals($real, trim($given))) {
            sleep(1);
            $this->invalid(['token' => t('setup.err_token')], url('setup'));
        }
        Session::regenerate();
        Session::set('setup_ok', true);
        Response::redirect(url('setup'));
    }

    public function saveDb(): string
    {
        $this->guard();
        if (!$this->tokenOk()) {
            throw new HttpException(403);
        }
        $v = new Validator($_POST);
        $v->required('db_host', 'setup.db_host')->required('db_name', 'setup.db_name')->required('db_user', 'setup.db_user');
        if (!$v->ok()) {
            $this->invalid($v->errors(), url('setup'));
        }
        $db = ['host' => $v->str('db_host'), 'port' => Request::int('db_port') ?: 3306, 'name' => $v->str('db_name'), 'user' => $v->str('db_user'), 'pass' => (string)($_POST['db_pass'] ?? '')];
        try {
            Db::connect($db);
        } catch (\Throwable $e) {
            error_log('setup db test failed: ' . $e->getMessage());
            $this->invalid(['db_host' => t('setup.err_db', ['code' => $e instanceof \PDOException ? (string)$e->getCode() : 'n/a'])], url('setup'));
        }
        $cfg = ['db' => $db, 'app' => ['env' => 'production', 'debug' => false, 'timezone' => Request::str('timezone') ?: 'Africa/Tripoli', 'force_https' => !empty($_POST['force_https']), 'trust_proxy' => false]];
        if (!in_array($cfg['app']['timezone'], \DateTimeZone::listIdentifiers(), true)) {
            $cfg['app']['timezone'] = 'Africa/Tripoli';
        }
        $written = null;
        foreach ([dirname(APP_ROOT) . '/grc-config', APP_ROOT . '/config'] as $dir) {
            if ((is_dir($dir) || @mkdir($dir, 0700, true)) && is_writable($dir)) {
                $f = $dir . '/config.php';
                if (@file_put_contents($f, Config::render($cfg), LOCK_EX) !== false) {
                    @chmod($f, 0600);
                    @file_put_contents($dir . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
                    @file_put_contents($dir . '/index.html', '');
                    $written = $f;
                    break;
                }
            }
        }
        if ($written === null) {
            // Restricted hosting: show the file so the administrator can create it manually.
            return $this->view('setup/manual_config', ['title' => t('setup.title'), 'source' => Config::render($cfg), 'paths' => [dirname(APP_ROOT) . '/grc-config/config.php', APP_ROOT . '/config/config.php']], 'layout/guest');
        }
        Session::message('success', t('setup.db_saved'));
        Response::redirect(url('setup'));
    }

    public function finish(): never
    {
        $this->guard();
        if (!$this->tokenOk() || !Db::ready()) {
            throw new HttpException(403);
        }
        $v = new Validator($_POST);
        $v->required('org_name_en', 'settings.org_name_en')->max('org_name_en', 'settings.org_name_en', 150)->required('org_name_ar', 'settings.org_name_ar')->max('org_name_ar', 'settings.org_name_ar', 150)
            ->required('full_name', 'admin.full_name')->max('full_name', 'admin.full_name', 150)->email('email', 'admin.email')->required('email', 'admin.email');
        $username = mb_strtolower($v->str('username'));
        if (!preg_match('/^[a-z0-9._-]{3,64}$/', $username)) {
            $v->fail('username', 'admin.err_username');
        }
        $pw = (string)($_POST['password'] ?? '');
        if ($e = Auth::checkPasswordPolicy($pw, $username)) {
            $v->fail('password', $e);
        } elseif ($pw !== (string)($_POST['password_confirm'] ?? '')) {
            $v->fail('password_confirm', 'password.mismatch');
        }
        $demo = !empty($_POST['load_demo']);
        $demoPw = (string)($_POST['demo_password'] ?? '');
        if ($demo && ($e = Auth::checkPasswordPolicy($demoPw))) {
            $v->fail('demo_password', $e);
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), url('setup'));
        }
        try {
            Migrator::run();
            $now = date('Y-m-d H:i:s');
            Settings::set('org_name_en', $v->str('org_name_en'));
            Settings::set('org_name_ar', $v->str('org_name_ar'));
            $id = Db::insert('users', [
                'username' => $username, 'email' => $v->str('email'), 'full_name' => $v->str('full_name'), 'password_hash' => password_hash($pw, PASSWORD_DEFAULT),
                'role' => 'admin', 'lang' => \App\Core\I18n::locale(), 'is_active' => 1, 'must_change_password' => 0, 'password_changed_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]);
            Audit::log('setup.completed', 'user', $id, ['admin' => $username, 'demo_data' => $demo], $id, $username);
            if ($demo) {
                DemoSeeder::run($demoPw, $id);
                Audit::log('setup.demo_data_loaded', 'system', null, null, $id, $username);
            }
            if (!Installer::writeLock()) {
                error_log('Could not write installed.lock; setup stays closed because a user exists.');
            }
        } catch (\Throwable $e) {
            error_log('setup failed: ' . $e->getMessage());
            $this->invalid(['form' => t('setup.err_install', ['detail' => $e->getMessage()])], url('setup'));
        }
        Session::forget('setup_ok');
        @unlink(Installer::tokenFile());
        $this->redirect(url('login'), 'setup.done');
    }
}
