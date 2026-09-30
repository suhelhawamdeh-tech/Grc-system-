<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Perm;
use App\Support\Ref;

final class ReportController extends Controller
{
    public function index(): string
    {
        $u = $this->user();
        $deps = Ref::departments(true);
        $brs = Ref::branches(true);
        if (Perm::scopeType($u['role']) === 'department') {
            $deps = array_filter($deps, static fn($d) => (int)$d['id'] === (int)$u['department_id']);
        }
        if (Perm::scopeType($u['role']) === 'branch') {
            $brs = array_filter($brs, static fn($b) => (int)$b['id'] === (int)$u['branch_id']);
        }
        return $this->view('reports/index', ['title' => t('nav.reports'), 'categories' => Ref::categories(true), 'departments' => $deps, 'branches' => $brs, 'bands' => Ref::bands(), 'periods' => Ref::periods(true)]);
    }
}
