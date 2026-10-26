<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;

abstract class Controller
{
    public function __construct(protected ?string $extra = null)
    {
    }

    protected function user(): array
    {
        $u = Auth::user();
        if (!$u) {
            throw new HttpException(401);
        }
        return $u;
    }

    protected function view(string $view, array $data = [], string $layout = 'layout/main'): string
    {
        return View::render($view, $data, $layout);
    }

    protected function redirect(string $to, ?string $msgKey = null, string $type = 'success', array $params = []): never
    {
        if ($msgKey) {
            Session::message($type, t($msgKey, $params));
        }
        Response::redirect($to);
    }

    /** Flash validation errors + old input and go back to the form. */
    protected function invalid(array $errors, string $to): never
    {
        $old = array_filter($_POST, static fn($k) => !preg_match('/pass|_csrf/i', (string)$k), ARRAY_FILTER_USE_KEY);
        Session::flash('errors', $errors);
        Session::flash('old', $old);
        Session::message('error', t('validation.fix_errors'));
        Response::redirect($to);
    }

    protected function page(): int
    {
        $p = (int)($_GET['page'] ?? 1);
        return max(1, min($p, 100000));
    }

    protected function notFound(): never
    {
        throw new HttpException(404);
    }

    /** Field-level diff of two rows for the given keys. @return array<string,array{0:mixed,1:mixed}> */
    protected function diff(array $old, array $new, array $keys): array
    {
        $out = [];
        foreach ($keys as $k) {
            $a = $old[$k] ?? null;
            $b = $new[$k] ?? null;
            if ((string)$a !== (string)$b) {
                $out[$k] = [$a, $b];
            }
        }
        return $out;
    }

    protected function audit(string $action, ?string $type = null, ?int $id = null, ?array $detail = null): void
    {
        Audit::log($action, $type, $id, $detail);
    }
}
