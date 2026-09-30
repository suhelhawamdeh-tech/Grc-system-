<?php
declare(strict_types=1);

namespace App\Core;

final class App
{
    public static function run(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
        ini_set('default_charset', 'UTF-8');
        mb_internal_encoding('UTF-8');

        $hasConfig = Config::load();
        date_default_timezone_set((string)Config::get('app.timezone', 'Africa/Tripoli'));
        if ($logdir = Config::storagePath('logs')) {
            if (is_dir($logdir) && is_writable($logdir)) {
                ini_set('error_log', $logdir . '/php-error.log');
            }
        }
        if (Config::get('app.debug', false) === true && Config::get('app.env') === 'development') {
            ini_set('display_errors', '1');
        }

        Response::securityHeaders();
        header('Content-Type: text/html; charset=UTF-8');

        if (version_compare(PHP_VERSION, MIN_PHP, '<')) {
            self::plainError(500, 'PHP ' . MIN_PHP . '+ is required.');
            return;
        }

        try {
            if (Config::get('app.force_https', false) && !Request::isHttps()) {
                Response::redirect('https://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/'), 301);
            }
            $installed = false;
            if ($hasConfig) {
                try {
                    Db::init((array)Config::get('db'));
                    $installed = Installer::isInstalled();
                } catch (\PDOException $e) {
                    error_log('DB connect failed: ' . $e->getMessage());
                    if (!str_starts_with(Request::path(), '/setup')) {
                        self::renderError(new HttpException(503));
                        return;
                    }
                }
            }
            Session::start();
            Session::rotateFlash();
            I18n::init(I18n::detect($installed ? Auth::user() : null, $installed ? (string)Settings::get('default_lang', 'ar') : 'ar'));

            $router = new Router();
            require APP_ROOT . '/app/routes.php';
            $path = Request::path();

            if (!$installed && !str_starts_with($path, '/setup') && !str_starts_with($path, '/lang/')) {
                Response::redirect(url('setup'));
            }
            echo $router->dispatch(Request::method(), $path);
        } catch (\Throwable $e) {
            self::renderError($e);
        }
    }

    private static function renderError(\Throwable $e): void
    {
        $status = $e instanceof HttpException ? $e->status : 500;
        if ($status === 401) {
            Response::redirect(url('login', ['next' => Request::method() === 'GET' ? ($_SERVER['REQUEST_URI'] ?? null) : null]));
        }
        $ref = null;
        if ($status >= 500) {
            $ref = strtoupper(bin2hex(random_bytes(4)));
            error_log("[$ref] " . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            $lf = Config::storagePath('logs') . '/app.log';
            @file_put_contents($lf, '[' . date('c') . "] [$ref] " . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n", FILE_APPEND | LOCK_EX);
        }
        if (!headers_sent()) {
            http_response_code(in_array($status, [403, 404, 405, 419, 503], true) ? $status : 500);
        }
        try {
            if (!I18n::ready()) {
                I18n::init('ar');
            }
            $view = in_array($status, [403, 404, 405, 419, 503], true) ? 'errors/' . $status : 'errors/500';
            $debug = (Config::get('app.debug', false) === true && Config::get('app.env') === 'development') ? $e : null;
            echo View::render($view, ['ref' => $ref, 'title' => t('error.title'), 'debug' => $debug], Auth::user() && $status !== 503 ? 'layout/main' : 'layout/guest');
        } catch (\Throwable) {
            self::plainError($status, 'Error' . ($ref ? " (ref $ref)" : ''));
        }
    }

    private static function plainError(int $status, string $msg): void
    {
        if (!headers_sent()) {
            http_response_code($status);
        }
        echo '<!doctype html><meta charset="utf-8"><title>Error</title><p>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p>';
    }
}
