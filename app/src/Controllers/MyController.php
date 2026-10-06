<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Support\Attention;

final class MyController extends Controller
{
    public function index(): string
    {
        $u = $this->user();
        return $this->view('my/index', ['title' => t('my.title'), 'sections' => Attention::items($u)]);
    }
}
