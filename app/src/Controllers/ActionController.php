<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Support\ActionFilter;
use App\Support\Ref;

final class ActionController extends Controller
{
    private const PER_PAGE = 25;

    public function index(): string
    {
        $u = $this->user();
        $f = ActionFilter::fromArray($_GET);
        $total = ActionFilter::count($f, $u);
        $page = min($this->page(), max(1, (int)ceil($total / self::PER_PAGE)));
        return $this->view('actions/index', [
            'title' => t('action.title_plural'), 'rows' => ActionFilter::fetch($f, $u, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'f' => $f, 'total' => $total, 'page' => $page, 'per' => self::PER_PAGE, 'users' => Ref::users(true),
        ]);
    }
}
