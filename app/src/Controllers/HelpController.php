<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Support\Help;
use App\Support\Prefs;

final class HelpController extends Controller
{
    /** Help centre: role quick-start, topic cards, glossary. ?t=<slug> opens one topic. */
    public function index(): string
    {
        $u = $this->user();
        $slug = Request::str('t', true);
        $topics = Help::topicsFor($u['role']);
        $topic = $slug !== '' ? ($topics[$slug] ?? null) : null;
        return $this->view('help/index', ['title' => t('help.title'), 'topics' => $topics, 'slug' => $topic ? $slug : '', 'topic' => $topic, 'start' => Help::quickStart($u['role']), 'glossary' => Help::data()['glossary'], 'q' => Request::str('q', true)]);
    }

    /** HTML fragment for the slide-over panel: help for the page the user is looking at. */
    public function panel(): string
    {
        $u = $this->user();
        $path = Request::str('path', true);
        $slug = Help::slugForPath($path !== '' ? $path : '/') ?? 'start';
        $topics = Help::topicsFor($u['role']);
        $topic = $topics[$slug] ?? ($topics['start'] ?? null);
        return $this->view('help/panel', ['slug' => isset($topics[$slug]) ? $slug : 'start', 'topic' => $topic], '');
    }

    public function dismiss(): never
    {
        $u = $this->user();
        $key = Request::str('key');
        if (in_array($key, ['welcome_seen'], true)) {
            Prefs::set((int)$u['id'], $key, '1');
        }
        Response::redirect(url(''));
    }
}
