<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Db;
use App\Core\Perm;
use App\Core\Request;
use App\Core\Scope;
use App\Core\Validator;
use App\Support\Ref;

/** Regulatory circulars: register, distribute to departments, track acknowledgement and the action taken. */
final class CircularController extends Controller
{
    private const PER_PAGE = 25;

    /** Department managers only see circulars distributed to their department. */
    private function visible(array $u, string $alias = 'c'): array
    {
        if (Perm::scopeType($u['role']) === 'department') {
            return ["$alias.status <> 'draft' AND EXISTS (SELECT 1 FROM circular_distribution d WHERE d.circular_id = $alias.id AND d.department_id = ?)", [(int)($u['department_id'] ?? 0)]];
        }
        if (Perm::scopeType($u['role']) === 'branch') {
            return ['1 = 0', []];
        }
        return Perm::roleHas($u['role'], 'circular.manage') ? ['1 = 1', []] : ["$alias.status <> 'draft'", []];
    }

    private function load(int $id): array
    {
        $u = $this->user();
        [$w, $p] = $this->visible($u);
        $c = Db::one("SELECT c.*, o.code AS obl_code, o.title AS obl_title FROM regulatory_circulars c LEFT JOIN compliance_obligations o ON o.id = c.obligation_id WHERE c.id = ? AND $w", array_merge([$id], $p));
        $c || $this->notFound();
        return $c;
    }

    public function index(): string
    {
        $u = $this->user();
        [$vw, $vp] = $this->visible($u);
        $f = ['q' => Request::str('q', true), 'status' => Request::str('status', true), 'overdue' => Request::str('overdue', true) === '1' ? '1' : ''];
        $where = [$vw];
        $p = $vp;
        if ($f['q'] !== '') {
            $like = Db::like($f['q']);
            $where[] = '(c.title LIKE ? OR c.reference_no LIKE ? OR c.summary LIKE ? OR c.code LIKE ?)';
            array_push($p, $like, $like, $like, $like);
        }
        if (in_array($f['status'], ['draft', 'distributed', 'closed'], true)) {
            $where[] = 'c.status = ?';
            $p[] = $f['status'];
        }
        if ($f['overdue'] === '1') {
            $where[] = "c.status = 'distributed' AND c.due_date < CURDATE()";
        }
        $w = implode(' AND ', $where);
        $total = (int)Db::val('SELECT COUNT(*) FROM regulatory_circulars c WHERE ' . $w, $p);
        $page = min($this->page(), max(1, (int)ceil($total / self::PER_PAGE)));
        $rows = Db::all('SELECT c.*, (SELECT COUNT(*) FROM circular_distribution d WHERE d.circular_id = c.id) AS dist_n, (SELECT COUNT(*) FROM circular_distribution d WHERE d.circular_id = c.id AND d.status = \'acknowledged\') AS ack_n FROM regulatory_circulars c WHERE ' . $w . ' ORDER BY c.received_on DESC, c.id DESC LIMIT ? OFFSET ?', array_merge($p, [self::PER_PAGE, ($page - 1) * self::PER_PAGE]));
        return $this->view('circulars/index', ['title' => t('cir.title'), 'rows' => $rows, 'f' => $f, 'total' => $total, 'page' => $page, 'per' => self::PER_PAGE]);
    }

    public function show(int $id): string
    {
        $u = $this->user();
        $c = $this->load($id);
        $dist = Db::all('SELECT d.*, dp.name_en AS dept_en, dp.name_ar AS dept_ar, u.full_name AS ack_name FROM circular_distribution d JOIN departments dp ON dp.id = d.department_id LEFT JOIN users u ON u.id = d.acknowledged_by WHERE d.circular_id = ? ORDER BY dp.name_en', [$id]);
        if (Perm::scopeType($u['role']) === 'department') {
            $dist = array_values(array_filter($dist, fn($d) => (int)$d['department_id'] === (int)$u['department_id']));
        }
        $att = Db::all("SELECT a.*, u.full_name FROM attachments a JOIN users u ON u.id = a.uploaded_by WHERE a.entity_type = 'circular' AND a.entity_id = ? AND a.deleted_at IS NULL ORDER BY a.id DESC", [$id]);
        $undist = $c['status'] === 'draft' ? Ref::departments(true) : [];
        return $this->view('circulars/show', ['title' => $c['code'] . ' — ' . $c['title'], 'c' => $c, 'dist' => $dist, 'attachments' => $att, 'departments' => $undist, 'myDept' => (int)($u['department_id'] ?? 0)]);
    }

    public function create(): string
    {
        return $this->view('circulars/form', ['title' => t('cir.new'), 'c' => ['issued_on' => date('Y-m-d'), 'received_on' => date('Y-m-d')], 'existing' => null, 'obligations' => $this->obligations()]);
    }

    public function edit(int $id): string
    {
        $c = $this->load($id);
        if ($c['status'] !== 'draft') {
            $this->redirect(url('circulars/' . $id), 'cir.err_locked', 'error');
        }
        return $this->view('circulars/form', ['title' => t('cir.edit'), 'c' => $c, 'existing' => $c, 'obligations' => $this->obligations()]);
    }

    private function obligations(): array
    {
        return Db::all('SELECT id, code, title FROM compliance_obligations WHERE archived_at IS NULL ORDER BY code LIMIT 500');
    }

    public function store(): never
    {
        $this->save(null);
    }

    public function update(int $id): never
    {
        $c = $this->load($id);
        if ($c['status'] !== 'draft') {
            $this->redirect(url('circulars/' . $id), 'cir.err_locked', 'error');
        }
        $this->save($c);
    }

    private function save(?array $ex): never
    {
        $v = new Validator($_POST);
        $v->required('title', 'cir.c_title')->max('title', 'cir.c_title', 250)->required('issuer', 'cir.issuer')->max('issuer', 'cir.issuer', 150)->max('reference_no', 'cir.reference', 80)
            ->date('issued_on', 'cir.issued_on')->date('received_on', 'cir.received_on')->required('summary', 'cir.summary')->max('summary', 'cir.summary', 8000)->max('required_action', 'cir.required_action', 4000)->date('due_date', 'cir.due_date', false);
        $today = date('Y-m-d');
        if ($v->str('issued_on') > $today) {
            $v->fail('issued_on', 'validation.not_future', ['field' => t('cir.issued_on')]);
        }
        if ($v->str('received_on') > $today) {
            $v->fail('received_on', 'validation.not_future', ['field' => t('cir.received_on')]);
        } elseif ($v->str('received_on') !== '' && $v->str('issued_on') !== '' && $v->str('received_on') < $v->str('issued_on')) {
            $v->fail('received_on', 'validation.after_date', ['field' => t('cir.received_on'), 'other' => t('cir.issued_on')]);
        }
        if ($v->str('due_date') !== '' && $v->str('due_date') < $v->str('received_on')) {
            $v->fail('due_date', 'validation.after_date', ['field' => t('cir.due_date'), 'other' => t('cir.received_on')]);
        }
        $obl = Request::int('obligation_id');
        if ($obl !== null && !Db::val('SELECT 1 FROM compliance_obligations WHERE id = ?', [$obl])) {
            $v->fail('obligation_id', 'validation.invalid_choice', ['field' => t('cir.obligation')]);
        }
        $back = $ex ? url('circulars/' . $ex['id'] . '/edit') : url('circulars/new');
        if (!$v->ok()) {
            $this->invalid($v->errors(), $back);
        }
        $now = date('Y-m-d H:i:s');
        $d = ['title' => $v->str('title'), 'issuer' => $v->str('issuer'), 'reference_no' => $v->str('reference_no') ?: null, 'issued_on' => $v->str('issued_on'), 'received_on' => $v->str('received_on'), 'summary' => $v->str('summary'),
            'required_action' => $v->str('required_action') ?: null, 'due_date' => $v->str('due_date') ?: null, 'obligation_id' => $obl, 'updated_at' => $now];
        if ($ex) {
            Db::update('regulatory_circulars', $d, 'id = ?', [$ex['id']]);
            $this->audit('circular.updated', 'circular', (int)$ex['id'], ['changes' => $this->diff($ex, $d, ['title', 'issuer', 'reference_no', 'issued_on', 'received_on', 'due_date', 'obligation_id'])]);
            $id = (int)$ex['id'];
        } else {
            $id = Db::insert('regulatory_circulars', $d + ['status' => 'draft', 'created_by' => $this->user()['id'], 'created_at' => $now]);
            Db::update('regulatory_circulars', ['code' => sprintf('CIR-%04d', $id)], 'id = ?', [$id]);
            $this->audit('circular.created', 'circular', $id, ['title' => $d['title']]);
        }
        $this->redirect(url('circulars/' . $id), 'admin.saved');
    }

    /** Send the circular to the selected departments (at least one). */
    public function distribute(int $id): never
    {
        $c = $this->load($id);
        $ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['departments'] ?? [])))));
        $valid = array_keys(Ref::departments(true));
        $ids = array_values(array_intersect($ids, $valid));
        if ($c['status'] !== 'draft') {
            $this->redirect(url('circulars/' . $id), 'cir.err_locked', 'error');
        }
        if (!$ids) {
            $this->redirect(url('circulars/' . $id), 'cir.err_no_departments', 'error');
        }
        Db::tx(function () use ($id, $ids) {
            foreach ($ids as $dp) {
                Db::exec('INSERT IGNORE INTO circular_distribution (circular_id, department_id) VALUES (?, ?)', [$id, $dp]);
            }
            Db::update('regulatory_circulars', ['status' => 'distributed', 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        });
        $this->audit('circular.distributed', 'circular', $id, ['departments' => $ids]);
        $this->redirect(url('circulars/' . $id), 'cir.distributed', 'success', ['n' => count($ids)]);
    }

    /** A department manager confirms receipt and records what the department did. */
    public function acknowledge(int $id): never
    {
        $u = $this->user();
        $c = $this->load($id);
        $dept = (int)($u['department_id'] ?? 0);
        $row = Db::one('SELECT * FROM circular_distribution WHERE circular_id = ? AND department_id = ?', [$id, $dept]);
        if (!$row || $c['status'] !== 'distributed') {
            $this->notFound();
        }
        if ($row['status'] === 'acknowledged') {
            $this->redirect(url('circulars/' . $id), 'cir.err_already', 'error');
        }
        $v = new Validator($_POST);
        $v->max('action_taken', 'cir.action_taken', 4000);
        if ($c['required_action'] !== null && $v->str('action_taken') === '') {
            $v->fail('action_taken', 'cir.err_action_required');
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), url('circulars/' . $id) . '#ack');
        }
        Db::tx(function () use ($id, $dept, $u, $v) {
            Db::update('circular_distribution', ['status' => 'acknowledged', 'acknowledged_by' => $u['id'], 'acknowledged_at' => date('Y-m-d H:i:s'), 'action_taken' => $v->str('action_taken') ?: null], 'circular_id = ? AND department_id = ?', [$id, $dept]);
            if (!(int)Db::val("SELECT COUNT(*) FROM circular_distribution WHERE circular_id = ? AND status = 'pending'", [$id])) {
                Db::update('regulatory_circulars', ['status' => 'closed', 'closed_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
            }
        });
        $this->audit('circular.acknowledged', 'circular', $id, ['department_id' => $dept]);
        $this->redirect(url('circulars/' . $id), 'cir.acknowledged');
    }

    public function upload(int $id): never
    {
        $this->load($id);
        AttachmentController::handleUpload('circular', $id, $this->user(), url('circulars/' . $id) . '#attachments');
    }
}
