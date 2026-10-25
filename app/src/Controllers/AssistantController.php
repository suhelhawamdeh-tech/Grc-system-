<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Db;
use App\Core\Perm;
use App\Core\Request;
use App\Core\Session;
use App\Core\Settings;
use App\Core\Validator;
use App\Support\Assistant;
use App\Support\KbLibrary;
use App\Support\KbSearch;

/** The AI assistant page, the reference library it is grounded on, and its administration settings. */
final class AssistantController extends Controller
{
    private const MODELS = ['claude-opus-5-5', 'claude-sonnet-5-5', 'claude-haiku-5-5'];

    public function index(): string
    {
        KbLibrary::ensure();
        $u = $this->user();
        $ctx = null;
        $type = Request::str('context', true);
        $id = Request::int('id', true);
        if ($type !== '' && $id) {
            $ctx = Assistant::context($u, $type, $id);
        }
        $last = Session::get('ai_last');
        $answer = null;
        if (is_int($last)) {
            $answer = Db::one('SELECT * FROM ai_messages WHERE id = ? AND user_id = ?', [$last, $u['id']]);
            if ($answer) {
                $answer['citations'] = $this->citations($answer['chunk_ids']);
            }
            Session::forget('ai_last');
        }
        $history = Db::all('SELECT id, question, status, mode, created_at FROM ai_messages WHERE user_id = ? ORDER BY id DESC LIMIT 10', [$u['id']]);
        return $this->view('assistant/index', ['title' => t('ai.title'), 'ctx' => $ctx, 'ctxId' => $id, 'answer' => $answer, 'history' => $history,
            'enabled' => Assistant::enabled(), 'llm' => Assistant::llmReady(), 'used' => Assistant::usedLastHour((int)$u['id']), 'limit' => Assistant::limit(),
            'stats' => ['sources' => (int)Db::val("SELECT COUNT(*) FROM kb_sources WHERE status = 'active'"), 'passages' => (int)Db::val("SELECT COUNT(*) FROM kb_chunks c JOIN kb_sources s ON s.id = c.source_id WHERE s.status = 'active'")]]);
    }

    /** Re-open a past answer from the user's own history. */
    private function citations(string $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', explode(',', $ids))));
        if (!$ids) {
            return [];
        }
        $rows = Db::all('SELECT c.*, s.code, s.title_ar, s.title_en, s.url, s.id AS sid FROM kb_chunks c JOIN kb_sources s ON s.id = c.source_id WHERE c.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids);
        $by = array_column($rows, null, 'id');
        return array_values(array_filter(array_map(fn($i) => $by[$i] ?? null, $ids)));
    }

    public function ask(): never
    {
        $u = $this->user();
        if (!Assistant::enabled()) {
            $this->redirect(url('assistant'), 'ai.disabled', 'error');
        }
        $v = new Validator($_POST);
        $v->required('question', 'ai.question')->max('question', 'ai.question', Assistant::MAX_QUESTION);
        $type = Request::str('context');
        $id = Request::int('context_id');
        $ctx = $type !== '' && $id ? Assistant::context($u, $type, $id) : null;
        if ($type !== '' && !$ctx) {
            $this->notFound();
        }
        $back = url('assistant', $ctx ? ['context' => $type, 'id' => $id] : []);
        if (!$v->ok()) {
            $this->invalid($v->errors(), $back);
        }
        if (Assistant::usedLastHour((int)$u['id']) >= Assistant::limit()) {
            $this->redirect($back, 'ai.rate_limited', 'error', ['n' => Assistant::limit()]);
        }
        KbLibrary::ensure();
        $r = Assistant::ask($u, $v->str('question'), $ctx);
        Session::set('ai_last', (int)$r['id']);
        if ($r['redactions'] > 0) {
            Session::message('info', t('ai.redacted', ['n' => $r['redactions']]));
        }
        $this->redirect($back . '#answer');
    }

    public function show(int $id): never
    {
        $u = $this->user();
        if (!Db::val('SELECT 1 FROM ai_messages WHERE id = ? AND user_id = ?', [$id, $u['id']])) {
            $this->notFound();
        }
        Session::set('ai_last', $id);
        $this->redirect(url('assistant') . '#answer');
    }

    // ------------------------------------------------------------------ library

    public function library(): string
    {
        KbLibrary::ensure();
        $u = $this->user();
        $ok = Perm::roleHas($u['role'], 'kb.manage');
        $f = ['q' => Request::str('q', true), 'category' => Request::str('category', true), 'jurisdiction' => Request::str('jurisdiction', true)];
        $w = ["s.status = 'active'"];
        $p = [];
        if (!$ok) {
            $w[] = 's.restricted = 0';
        }
        if (in_array($f['category'], KbLibrary::CATEGORIES, true)) {
            $w[] = 's.category = ?';
            $p[] = $f['category'];
        }
        if (in_array($f['jurisdiction'], KbLibrary::JURISDICTIONS, true)) {
            $w[] = 's.jurisdiction = ?';
            $p[] = $f['jurisdiction'];
        }
        $sources = Db::all('SELECT s.*, (SELECT COUNT(*) FROM kb_chunks c WHERE c.source_id = s.id) AS n FROM kb_sources s WHERE ' . implode(' AND ', $w) . " ORDER BY FIELD(s.jurisdiction, 'libya', 'bank', 'regional', 'international'), s.category, s.code", $p);
        $hits = $f['q'] !== '' ? KbSearch::search($f['q'], 10, $ok) : [];
        return $this->view('assistant/library', ['title' => t('ai.library'), 'sources' => $sources, 'f' => $f, 'hits' => $hits]);
    }

    private function source(int $id): array
    {
        $u = $this->user();
        $s = Db::one("SELECT s.*, u.full_name FROM kb_sources s LEFT JOIN users u ON u.id = s.created_by WHERE s.id = ?", [$id]) ?? $this->notFound();
        if ((int)$s['restricted'] === 1 && !Perm::roleHas($u['role'], 'kb.manage')) {
            $this->notFound();
        }
        return $s;
    }

    public function sourceShow(int $id): string
    {
        $s = $this->source($id);
        $chunks = Db::all('SELECT * FROM kb_chunks WHERE source_id = ? ORDER BY sort_order, id', [$id]);
        return $this->view('assistant/source', ['title' => lname($s, 'title'), 's' => $s, 'chunks' => $chunks]);
    }

    public function sourceNew(): string
    {
        return $this->view('assistant/source_form', ['title' => t('ai.source_new'), 'd' => ['category' => 'bank', 'jurisdiction' => 'libya', 'authority' => 'regulation']]);
    }

    public function sourceStore(): never
    {
        $v = new Validator($_POST);
        $v->max('title_ar', 'ai.s_title_ar', 250)->max('title_en', 'ai.s_title_en', 250)->required('issuer', 'ai.s_issuer')->max('issuer', 'ai.s_issuer', 150)
            ->in('category', 'ai.s_category', KbLibrary::CATEGORIES)->in('jurisdiction', 'ai.s_jurisdiction', KbLibrary::JURISDICTIONS)->in('authority', 'ai.s_authority', KbLibrary::AUTHORITIES)
            ->max('url', 'ai.s_url', 300)->max('note', 'ai.s_note', 1000)->required('text', 'ai.s_text')->max('text', 'ai.s_text', 200000);
        if ($v->str('title_ar') === '' && $v->str('title_en') === '') {
            $v->fail('title_ar', 'ai.err_title');
        }
        if ($v->str('url') !== '' && !preg_match('~^https?://[^\s<>"]+$~i', $v->str('url'))) {
            $v->fail('url', 'ai.err_url');
        }
        $year = $v->str('year');
        if ($year !== '' && (!ctype_digit($year) || (int)$year < 1950 || (int)$year > (int)date('Y'))) {
            $v->fail('year', 'ai.err_year');
        }
        $mods = array_values(array_intersect((array)($_POST['modules'] ?? []), KbLibrary::MODULES));
        if (!$v->ok()) {
            $this->invalid($v->errors(), url('assistant/library/new'));
        }
        $id = KbLibrary::addDocument(['title_ar' => $v->str('title_ar'), 'title_en' => $v->str('title_en'), 'issuer' => $v->str('issuer'), 'category' => $v->str('category'), 'jurisdiction' => $v->str('jurisdiction'),
            'authority' => $v->str('authority'), 'year' => $year !== '' ? (int)$year : null, 'url' => $v->str('url') ?: null, 'modules' => implode(',', $mods), 'note' => $v->str('note'), 'restricted' => isset($_POST['restricted']) ? 1 : 0],
            (string)$_POST['text'], (int)$this->user()['id']);
        $this->audit('kb.source_added', 'kb_source', $id, ['passages' => (int)Db::val('SELECT COUNT(*) FROM kb_chunks WHERE source_id = ?', [$id])]);
        $this->redirect(url('assistant/library/' . $id), 'ai.source_added');
    }

    public function sourceRetire(int $id): never
    {
        $s = $this->source($id);
        if ((int)$s['is_builtin'] === 1) {
            $this->redirect(url('assistant/library/' . $id), 'ai.err_builtin', 'error');
        }
        Db::update('kb_sources', ['status' => $s['status'] === 'active' ? 'retired' : 'active', 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        KbSearch::reset();
        $this->audit('kb.source_' . ($s['status'] === 'active' ? 'retired' : 'restored'), 'kb_source', $id);
        $this->redirect(url('assistant/library/' . $id), 'admin.saved');
    }

    /** Add a registered regulatory circular (summary and required action) to the library. */
    public function fromCircular(int $id): never
    {
        $c = Db::one('SELECT * FROM regulatory_circulars WHERE id = ?', [$id]) ?? $this->notFound();
        if ($sid = (int)Db::val("SELECT id FROM kb_sources WHERE circular_id = ? AND status = 'active'", [$id])) {
            $this->redirect(url('assistant/library/' . $sid), 'ai.already_in_library', 'error');
        }
        $text = $c['summary'] . ($c['required_action'] ? "\n\n" . t('cir.required_action') . ': ' . $c['required_action'] : '');
        $title = mb_substr(trim(($c['code'] ?? '') . ' — ' . $c['title'], ' —'), 0, 250);
        $sid = KbLibrary::addDocument(['title_ar' => $title, 'title_en' => $title, 'issuer' => $c['issuer'], 'category' => 'compliance', 'jurisdiction' => 'libya',
            'authority' => 'regulation', 'year' => (int)substr((string)$c['issued_on'], 0, 4), 'url' => null, 'modules' => 'compliance', 'note' => ($c['reference_no'] ? $c['reference_no'] . ' · ' : '') . fdate($c['issued_on']), 'restricted' => 0],
            $text, (int)$this->user()['id'], $id);
        $this->audit('kb.circular_added', 'circular', $id, ['source_id' => $sid]);
        $this->redirect(url('assistant/library/' . $sid), 'ai.source_added');
    }

    // ------------------------------------------------------------------ administration

    public function settings(): string
    {
        KbLibrary::ensure();
        $usage = Db::one("SELECT COUNT(*) AS n, COALESCE(SUM(input_tokens),0) AS inp, COALESCE(SUM(output_tokens),0) AS outp, SUM(status = 'refused') AS refused, SUM(status = 'error') AS errors, SUM(status = 'no_sources') AS nosrc, SUM(redactions) AS red FROM ai_messages WHERE created_at >= ?", [date('Y-m-d', strtotime('-30 days'))]);
        $top = Db::all("SELECT context_type, COUNT(*) AS n FROM ai_messages WHERE created_at >= ? GROUP BY context_type ORDER BY n DESC", [date('Y-m-d', strtotime('-30 days'))]);
        return $this->view('assistant/settings', ['title' => t('ai.settings'), 'usage' => $usage, 'top' => $top, 'hasKey' => Assistant::apiKey() !== '', 'models' => self::MODELS,
            's' => ['enabled' => Settings::get('ai_enabled', '1'), 'mode' => Settings::get('ai_mode', 'library'), 'model' => Settings::get('ai_model', 'claude-opus-5-5'), 'effort' => Settings::get('ai_effort', 'medium'), 'limit' => (string)Assistant::limit()],
            'version' => Settings::get('kb_library_version', '—')]);
    }

    public function saveSettings(): never
    {
        $v = new Validator($_POST);
        $v->in('mode', 'ai.mode', ['library', 'llm'])->in('model', 'ai.model', self::MODELS)->in('effort', 'ai.effort', Assistant::EFFORTS)->intRange('rate_limit', 'ai.rate_limit', 1, 500);
        if ($v->str('mode') === 'llm' && Assistant::apiKey() === '') {
            $v->fail('mode', 'ai.err_no_key');
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), url('admin/assistant'));
        }
        $new = ['ai_enabled' => isset($_POST['enabled']) ? '1' : '0', 'ai_mode' => $v->str('mode'), 'ai_model' => $v->str('model'), 'ai_effort' => $v->str('effort'), 'ai_rate_limit' => $v->str('rate_limit')];
        foreach ($new as $k => $val) {
            Settings::set($k, $val);
        }
        $this->audit('ai.settings_updated', 'settings', null, $new);
        $this->redirect(url('admin/assistant'), 'admin.saved');
    }

    public function testConnection(): never
    {
        if (Assistant::apiKey() === '') {
            $this->redirect(url('admin/assistant'), 'ai.err_no_key', 'error');
        }
        $r = Assistant::test();
        $this->audit('ai.connection_tested', 'settings', null, ['ok' => $r['ok']]);
        $this->redirect(url('admin/assistant'), $r['ok'] ? 'ai.test_ok' : 'ai.test_failed', $r['ok'] ? 'success' : 'error', ['e' => $r['detail']]);
    }

    public function syncLibrary(): never
    {
        $r = KbLibrary::sync();
        $this->audit('kb.library_synced', 'settings', null, $r);
        $this->redirect(url('admin/assistant'), 'ai.synced', 'success', ['s' => $r['sources'], 'p' => $r['passages']]);
    }
}
