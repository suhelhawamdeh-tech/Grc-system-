<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Db;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;

/** Personal notes and reminders. Always scoped to the signed-in user; other users' notes answer 404. */
final class NoteController extends Controller
{
    private function mine(int $id): array
    {
        $n = Db::one('SELECT * FROM user_notes WHERE id = ? AND user_id = ?', [$id, $this->user()['id']]);
        if (!$n) {
            $this->notFound();
        }
        return $n;
    }

    public function index(): string
    {
        $u = $this->user();
        $open = Db::all('SELECT * FROM user_notes WHERE user_id = ? AND is_done = 0 ORDER BY (remind_on IS NULL), remind_on, id DESC', [$u['id']]);
        $done = Db::all('SELECT * FROM user_notes WHERE user_id = ? AND is_done = 1 ORDER BY done_at DESC LIMIT 20', [$u['id']]);
        return $this->view('notes/index', ['title' => t('notes.title'), 'open' => $open, 'done' => $done, 'page' => Request::str('page_path', true)]);
    }

    /** Fragment used by the slide-over panel. */
    public function panel(): string
    {
        $u = $this->user();
        $open = Db::all('SELECT * FROM user_notes WHERE user_id = ? AND is_done = 0 ORDER BY (remind_on IS NULL), remind_on, id DESC LIMIT 5', [$u['id']]);
        return $this->view('notes/panel', ['open' => $open], '');
    }

    public function store(): never
    {
        $u = $this->user();
        $v = new Validator($_POST);
        $v->required('body', 'notes.body')->max('body', 'notes.body', 500);
        $remind = Request::str('remind_on');
        if ($remind !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $remind) || !checkdate((int)substr($remind, 5, 2), (int)substr($remind, 8, 2), (int)substr($remind, 0, 4)))) {
            $v->fail('remind_on', 'validation.date', ['field' => t('notes.remind_on')]);
        } elseif ($remind !== '' && $remind < date('Y-m-d')) {
            $v->fail('remind_on', 'notes.past_date');
        }
        $back = $this->safePath(Request::str('page_path')) ?? '/notes';
        if ($v->errors()) {
            $this->invalid($v->errors(), url('notes', ['page_path' => $back === '/notes' ? null : $back]));
        }
        Db::insert('user_notes', ['user_id' => $u['id'], 'body' => $v->str('body'), 'page_path' => $this->safePath(Request::str('page_path')), 'remind_on' => $remind !== '' ? $remind : null, 'created_at' => date('Y-m-d H:i:s')]);
        $this->redirect(url('notes'), 'notes.saved');
    }

    public function done(int $id): never
    {
        $n = $this->mine($id);
        Db::update('user_notes', ['is_done' => $n['is_done'] ? 0 : 1, 'done_at' => $n['is_done'] ? null : date('Y-m-d H:i:s')], 'id = ?', [$id]);
        Response::redirect(Request::str('next') === 'my' ? url('my') : url('notes'));
    }

    public function delete(int $id): never
    {
        $this->mine($id);
        Db::exec('DELETE FROM user_notes WHERE id = ?', [$id]);
        $this->redirect(url('notes'), 'notes.deleted');
    }

    /** Only same-site absolute paths may be stored as a note's page link. */
    private function safePath(string $p): ?string
    {
        $p = trim($p);
        if ($p === '' || $p[0] !== '/' || str_starts_with($p, '//') || strlen($p) > 255 || preg_match('/[\x00-\x1f\\\\]/', $p)) {
            return null;
        }
        return $p;
    }
}
