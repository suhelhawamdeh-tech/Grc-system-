<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Db;
use App\Core\Request;
use App\Core\Validator;
use App\Support\Ref;

final class CommitteeController extends Controller
{
    public const COMMITTEES = ['board_risk', 'board_audit', 'executive_risk', 'alco', 'sharia_board', 'other'];
    private const PER_PAGE = 25;

    public function index(): string
    {
        $f = ['committee' => Request::str('committee', true), 'status' => Request::str('status', true), 'q' => mb_substr(Request::str('q', true), 0, 100), 'overdue' => Request::str('overdue', true) === '1' ? '1' : ''];
        $w = ['1=1'];
        $p = [];
        if (in_array($f['committee'], self::COMMITTEES, true)) {
            $w[] = 'c.committee = ?';
            $p[] = $f['committee'];
        }
        if (in_array($f['status'], ['open', 'implemented', 'superseded'], true)) {
            $w[] = 'c.status = ?';
            $p[] = $f['status'];
        }
        if ($f['q'] !== '') {
            $w[] = '(c.title LIKE ? OR c.decision_text LIKE ? OR c.code LIKE ?)';
            $l = Db::like($f['q']);
            array_push($p, $l, $l, $l);
        }
        if ($f['overdue'] === '1') {
            $w[] = "EXISTS (SELECT 1 FROM decision_actions a WHERE a.decision_id = c.id AND a.status IN ('not_started','in_progress') AND a.deadline < ?)";
            $p[] = date('Y-m-d');
        }
        $ws = implode(' AND ', $w);
        $total = (int)Db::val('SELECT COUNT(*) FROM committee_decisions c WHERE ' . $ws, $p);
        $page = min($this->page(), max(1, (int)ceil($total / self::PER_PAGE)));
        $rows = Db::all("SELECT c.*, (SELECT COUNT(*) FROM decision_actions a WHERE a.decision_id = c.id) AS n_actions, (SELECT COUNT(*) FROM decision_actions a WHERE a.decision_id = c.id AND a.status IN ('not_started','in_progress') AND a.deadline < CURDATE()) AS n_overdue FROM committee_decisions c WHERE $ws ORDER BY c.meeting_date DESC, c.id DESC LIMIT ? OFFSET ?", array_merge($p, [self::PER_PAGE, ($page - 1) * self::PER_PAGE]));
        return $this->view('committees/index', ['title' => t('cmt.title_plural'), 'rows' => $rows, 'f' => $f, 'total' => $total, 'page' => $page, 'per' => self::PER_PAGE]);
    }

    private function load(int $id): array
    {
        return Db::one('SELECT * FROM committee_decisions WHERE id = ?', [$id]) ?? $this->notFound();
    }

    public function show(int $id): string
    {
        $c = $this->load($id);
        $actions = Db::all('SELECT a.*, u.full_name AS owner_name FROM decision_actions a JOIN users u ON u.id = a.owner_user_id WHERE a.decision_id = ? ORDER BY a.deadline', [$id]);
        return $this->view('committees/show', ['title' => $c['code'] . ' — ' . $c['title'], 'c' => $c, 'actions' => $actions, 'users' => Ref::users(true)]);
    }

    public function create(): string
    {
        return $this->view('committees/form', ['title' => t('cmt.new'), 'c' => ['meeting_date' => date('Y-m-d'), 'status' => 'open'], 'existing' => null]);
    }

    public function edit(int $id): string
    {
        $c = $this->load($id);
        return $this->view('committees/form', ['title' => t('cmt.edit'), 'c' => $c, 'existing' => $c]);
    }

    public function store(): never
    {
        $this->save(null);
    }

    public function update(int $id): never
    {
        $this->save($this->load($id));
    }

    private function save(?array $ex): never
    {
        $v = new Validator($_POST);
        $v->in('committee', 'cmt.committee', self::COMMITTEES)->date('meeting_date', 'cmt.meeting_date')->required('title', 'cmt.name')->max('title', 'cmt.name', 250)
            ->required('decision_text', 'cmt.decision')->max('decision_text', 'cmt.decision', 8000)->in('status', 'cmt.status', ['open', 'implemented', 'superseded']);
        if ($v->str('meeting_date') > date('Y-m-d', strtotime('+1 day'))) {
            $v->fail('meeting_date', 'validation.not_future', ['field' => t('cmt.meeting_date')]);
        }
        $back = $ex ? url('committees/' . $ex['id'] . '/edit') : url('committees/new');
        if (!$v->ok()) {
            $this->invalid($v->errors(), $back);
        }
        $data = ['committee' => $v->str('committee'), 'meeting_date' => $v->str('meeting_date'), 'title' => $v->str('title'), 'decision_text' => $v->str('decision_text'), 'status' => $v->str('status'), 'updated_at' => date('Y-m-d H:i:s')];
        if ($ex) {
            Db::update('committee_decisions', $data, 'id = ?', [$ex['id']]);
            $this->audit('committee.updated', 'decision', (int)$ex['id'], ['changes' => $this->diff($ex, $data, ['committee', 'meeting_date', 'title', 'decision_text', 'status'])]);
            $id = (int)$ex['id'];
        } else {
            $id = Db::insert('committee_decisions', $data + ['created_by' => $this->user()['id'], 'created_at' => $data['updated_at']]);
            Db::update('committee_decisions', ['code' => sprintf('DEC-%04d', $id)], 'id = ?', [$id]);
            $this->audit('committee.created', 'decision', $id, ['committee' => $data['committee']]);
        }
        $this->redirect(url('committees/' . $id), 'admin.saved');
    }

    public function addAction(int $id): never
    {
        $this->load($id);
        $v = new Validator($_POST);
        $v->required('title', 'action.title')->max('title', 'action.title', 250)->date('deadline', 'action.deadline')->in('status', 'action.status', ['not_started', 'in_progress', 'completed', 'cancelled']);
        $owner = Request::int('owner_user_id');
        if ($owner === null || !isset(Ref::users(true)[$owner])) {
            $v->fail('owner_user_id', 'validation.invalid_choice', ['field' => t('action.owner')]);
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), url('committees/' . $id) . '#actions');
        }
        $now = date('Y-m-d H:i:s');
        $aid = Db::insert('decision_actions', ['decision_id' => $id, 'title' => $v->str('title'), 'owner_user_id' => $owner, 'deadline' => $v->str('deadline'), 'status' => $v->str('status'), 'completed_on' => $v->str('status') === 'completed' ? date('Y-m-d') : null, 'created_at' => $now, 'updated_at' => $now]);
        $this->audit('committee.action_added', 'decision', $id, ['action_id' => $aid]);
        $this->redirect(url('committees/' . $id) . '#actions', 'action.added');
    }

    public function updateAction(int $id, int $aid): never
    {
        $this->load($id);
        $a = Db::one('SELECT * FROM decision_actions WHERE id = ? AND decision_id = ?', [$aid, $id]) ?? $this->notFound();
        $v = new Validator($_POST);
        $v->date('deadline', 'action.deadline')->in('status', 'action.status', ['not_started', 'in_progress', 'completed', 'cancelled']);
        $owner = Request::int('owner_user_id');
        if ($owner === null || (!isset(Ref::users(true)[$owner]) && $owner !== (int)$a['owner_user_id'])) {
            $v->fail('owner_user_id', 'validation.invalid_choice', ['field' => t('action.owner')]);
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), url('committees/' . $id) . '#actions');
        }
        $st = $v->str('status');
        Db::update('decision_actions', ['deadline' => $v->str('deadline'), 'status' => $st, 'owner_user_id' => $owner, 'completed_on' => $st === 'completed' ? ($a['completed_on'] ?? date('Y-m-d')) : null, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$aid]);
        $this->audit('committee.action_updated', 'decision', $id, ['action_id' => $aid, 'status' => $st]);
        $this->redirect(url('committees/' . $id) . '#actions', 'action.updated');
    }
}
