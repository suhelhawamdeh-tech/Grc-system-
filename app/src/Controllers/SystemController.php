<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Db;
use App\Core\Migrator;

final class SystemController extends Controller
{
    public function index(): string
    {
        $cfgFile = Config::file();
        $docroot = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? '')) ?: '';
        $inWeb = $cfgFile && $docroot !== '' && str_starts_with((string)realpath($cfgFile), $docroot . DIRECTORY_SEPARATOR);
        $checks = [
            'php' => [PHP_VERSION, version_compare(PHP_VERSION, MIN_PHP, '>=')],
            'db' => [(string)Db::val('SELECT VERSION()'), true],
            'storage_logs' => [Config::storagePath('logs'), is_writable(Config::storagePath('logs'))],
            'storage_uploads' => [Config::storagePath('uploads'), is_writable(Config::storagePath('uploads'))],
            'storage_sessions' => [Config::storagePath('sessions'), is_writable(Config::storagePath('sessions'))],
            'config_outside_web' => [$cfgFile ?? '-', !$inWeb],
            'https' => [\App\Core\Request::isHttps() ? 'HTTPS' : 'HTTP', \App\Core\Request::isHttps()],
            'upload_limit' => [ini_get('upload_max_filesize') . ' / post ' . ini_get('post_max_size'), true],
        ];
        return $this->view('admin/system', ['title' => t('admin.system'), 'checks' => $checks, 'pending' => array_keys(Migrator::pending()), 'applied' => Migrator::applied(), 'version' => APP_VERSION, 'mailOn' => \App\Support\Mailer::configured(), 'mailLog' => Db::all('SELECT * FROM email_log ORDER BY id DESC LIMIT 10')]);
    }

    public function migrate(): never
    {
        $done = Migrator::run();
        $this->audit('system.migrated', 'system', null, ['applied' => $done]);
        $this->redirect(url('admin/system'), 'admin.migrated', 'success', ['n' => count($done)]);
    }

    public function testMail(): never
    {
        $u = $this->user();
        $to = trim((string)($_POST['to'] ?? '')) ?: (string)$u['email'];
        $err = \App\Support\Mailer::send($to, t('mail.test_subject'), t('mail.test_body'));
        Db::insert('email_log', ['user_id' => $u['id'], 'kind' => 'test', 'recipient' => mb_substr($to, 0, 190), 'subject' => t('mail.test_subject'), 'status' => $err === null ? 'sent' : 'failed', 'error' => $err ? mb_substr($err, 0, 250) : null, 'created_at' => date('Y-m-d H:i:s')]);
        $this->audit('mail.test', 'system', null, ['ok' => $err === null]);
        if ($err !== null) {
            $this->redirect(url('admin/system'), 'mail.test_fail', 'error', ['err' => $err]);
        }
        $this->redirect(url('admin/system'), 'mail.test_ok');
    }
}
