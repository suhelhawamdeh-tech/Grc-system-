<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Db;
use App\Core\Perm;
use App\Core\Request;
use App\Core\Scope;
use App\Core\Validator;
use App\Support\Csv;
use App\Support\Ref;

final class ComplianceController extends Controller
{
    private const PER_PAGE = 25;
    private const OPEN = "o.status IN ('not_started','in_progress','non_compliant')";
    private const FREQ = ['once', 'monthly', 'quarterly', 'semiannual', 'annual'];

    private function load(int $id): array
    {
        $u = $this->user();
        $o = Db::one('SELECT o.*, d.name_en AS dept_en, d.name_ar AS dept_ar, w.full_name AS owner_name FROM compliance_obligations o JOIN departments d ON d.id = o.department_id JOIN users w ON w.id = o.owner_user_id WHERE o.id = ?', [$id]);
        if (!$o || !Scope::allowsDept($u, $o)) {
            $this->notFound();
        }
        return $o;
    }

    private function filter(array $u): array
    {
        [$sw, $sp] = Scope::sqlDept($u, 'o');
        $f = ['q' => mb_substr(Request::str('q', true), 0, 100), 'status' => Request::str('status', true), 'department' => Request::int('department', true), 'overdue' => Request::str('overdue', true) === '1' ? '1' : '',
            'due_days' => Request::int('due_days', true), 'archived' => Request::str('archived', true) === '1' ? '1' : ''];
        $w = [$sw, $f['archived'] === '1' ? 'o.archived_at IS NOT NULL' : 'o.archived_at IS NULL'];
        $p = $sp;
        if ($f['q'] !== '') {
            $w[] = '(o.title LIKE ? OR o.source LIKE ? OR o.code LIKE ? OR o.description LIKE ?)';
            $l = Db::like($f['q']);
            array_push($p, $l, $l, $l, $l);
        }
        if (in_array($f['status'], ['not_started', 'in_progress', 'compliant', 'non_compliant', 'not_applicable'], true)) {
            $w[] = 'o.status = ?';
            $p[] = $f['status'];
        }
        if ($f['department'] !== null) {
            $w[] = 'o.department_id = ?';
            $p[] = $f['department'];
        }
        if ($f['overdue'] === '1') {
            $w[] = self::OPEN . ' AND o.due_date < ?';
            $p[] = date('Y-m-d');
        }
        if ($f['due_days'] !== null) {
            $w[] = self::OPEN . ' AND o.due_date BETWEEN ? AND ?';
            array_push($p, date('Y-m-d'), date('Y-m-d', strtotime('+' . $f['due_days'] . ' days')));
        }
        return [$f, implode(' AND ', $w), $p];
    }

    private const SEL = 'SELECT o.*, d.name_en AS dept_en, d.name_ar AS dept_ar, w.full_name AS owner_name, (SELECT COUNT(*) FROM attachments a WHERE a.entity_type = \'obligation\' AND a.entity_id = o.id AND a.deleted_at IS NULL) AS evidence_count FROM compliance_obligations o JOIN departments d ON d.id = o.department_id JOIN users w ON w.id = o.owner_user_id';

    public function index(): string
    {
        $u = $this->user();
        [$f, $w, $p] = $this->filter($u);
        $total = (int)Db::val('SELECT COUNT(*) FROM compliance_obligations o WHERE ' . $w, $p);
        $page = min($this->page(), max(1, (int)ceil($total / self::PER_PAGE)));
        $rows = Db::all(self::SEL . ' WHERE ' . $w . ' ORDER BY o.due_date ASC, o.id ASC LIMIT ? OFFSET ?', array_merge($p, [self::PER_PAGE, ($page - 1) * self::PER_PAGE]));
        return $this->view('compliance/index', ['title' => t('comp.title_plural'), 'rows' => $rows, 'f' => $f, 'total' => $total, 'page' => $page, 'per' => self::PER_PAGE, 'departments' => Ref::departments(true)]);
    }

    public function export(): never
    {
        $u = Auth::requirePerm('export.run');
        if (!Perm::roleHas($u['role'], 'compliance.view')) {
            throw new \App\Core\HttpException(403);
        }
        [$f, $w, $p] = $this->filter($u);
        $rows = Db::all(self::SEL . ' WHERE ' . $w . ' ORDER BY o.due_date ASC LIMIT 20000', $p);
        $this->audit('export.compliance', 'obligation', null, ['rows' => count($rows)]);
        $gen = (static function () use ($rows) {
            foreach ($rows as $o) {
                yield [$o['code'], $o['title'], $o['source'], lname($o, 'dept'), $o['owner_name'], t('enum.freq.' . $o['frequency']), $o['due_date'], t('enum.obl_status.' . $o['status']), $o['last_reviewed_on'], (int)$o['evidence_count'], $o['status_note']];
            }
        })();
        Csv::download('compliance-obligations-' . date('Ymd-His') . '.csv', [t('comp.id'), t('comp.name'), t('comp.source'), t('comp.department'), t('comp.owner'), t('comp.frequency'), t('comp.due_date'), t('comp.status'), t('comp.last_reviewed'), t('comp.evidence'), t('comp.status_note')], $gen, Request::str('sep', true) === ';' ? ';' : ',');
    }

    public function show(int $id): string
    {
        $o = $this->load($id);
        $atts = Db::all('SELECT a.*, u.full_name FROM attachments a JOIN users u ON u.id = a.uploaded_by WHERE a.entity_type = ? AND a.entity_id = ? AND a.deleted_at IS NULL ORDER BY a.id DESC', ['obligation', $id]);
        return $this->view('compliance/show', ['title' => $o['code'] . ' — ' . $o['title'], 'o' => $o, 'attachments' => $atts]);
    }

    public function create(): string
    {
        return $this->view('compliance/form', ['title' => t('comp.new'), 'o' => ['frequency' => 'annual', 'status' => 'not_started'], 'existing' => null, 'lists' => $this->lists()]);
    }

    public function edit(int $id): string
    {
        $o = $this->load($id);
        return $this->view('compliance/form', ['title' => t('comp.edit'), 'o' => $o, 'existing' => $o, 'lists' => $this->lists()]);
    }

    private function lists(): array
    {
        return ['departments' => Ref::departments(true), 'users' => Ref::users(true)];
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
        $v->required('title', 'comp.name')->max('title', 'comp.name', 250)->required('source', 'comp.source')->max('source', 'comp.source', 250)->max('description', 'comp.description', 3000)
            ->date('due_date', 'comp.due_date')->in('frequency', 'comp.frequency', self::FREQ);
        $dept = Request::int('department_id');
        $owner = Request::int('owner_user_id');
        if ($dept === null || !isset(Ref::departments(true)[$dept]) && $dept !== ($ex['department_id'] ?? null)) {
            $v->fail('department_id', 'validation.invalid_choice', ['field' => t('comp.department')]);
        }
        if ($owner === null || !isset(Ref::users(true)[$owner]) && $owner !== ($ex['owner_user_id'] ?? null)) {
            $v->fail('owner_user_id', 'validation.invalid_choice', ['field' => t('comp.owner')]);
        }
        $back = $ex ? url('compliance/' . $ex['id'] . '/edit') : url('compliance/new');
        if (!$v->ok()) {
            $this->invalid($v->errors(), $back);
        }
        $data = ['title' => $v->str('title'), 'source' => $v->str('source'), 'description' => $v->str('description') ?: null, 'department_id' => $dept, 'owner_user_id' => $owner,
            'frequency' => $v->str('frequency'), 'due_date' => $v->str('due_date'), 'updated_at' => date('Y-m-d H:i:s')];
        if ($ex) {
            Db::update('compliance_obligations', $data, 'id = ?', [$ex['id']]);
            $this->audit('compliance.updated', 'obligation', (int)$ex['id'], ['changes' => $this->diff($ex, $data, ['title', 'source', 'department_id', 'owner_user_id', 'frequency', 'due_date'])]);
            $id = (int)$ex['id'];
        } else {
            $id = Db::insert('compliance_obligations', $data + ['status' => 'not_started', 'created_by' => $this->user()['id'], 'created_at' => $data['updated_at']]);
            Db::update('compliance_obligations', ['code' => sprintf('OBL-%04d', $id)], 'id = ?', [$id]);
            $this->audit('compliance.created', 'obligation', $id, ['title' => $data['title']]);
        }
        $this->redirect(url('compliance/' . $id), 'admin.saved');
    }

    private function requireEdit(): array
    {
        $u = $this->user();
        if (!Perm::roleHas($u['role'], 'compliance.manage') && !Perm::roleHas($u['role'], 'compliance.update')) {
            throw new \App\Core\HttpException(403);
        }
        return $u;
    }

    /** Status/evidence update: allowed to managers and to department managers for their own department. */
    public function status(int $id): never
    {
        $this->requireEdit();
        $o = $this->load($id);
        if ($o['archived_at'] !== null) {
            $this->redirect(url('compliance/' . $id), 'risk.err_archived_readonly', 'error');
        }
        $v = new Validator($_POST);
        $v->in('status', 'comp.status', ['not_started', 'in_progress', 'compliant', 'non_compliant', 'not_applicable'])->max('status_note', 'comp.status_note', 3000);
        $st = $v->str('status');
        if (in_array($st, ['non_compliant', 'not_applicable'], true) && $v->str('status_note') === '') {
            $v->fail('status_note', 'comp.err_note_required');
        }
        if ($st === 'compliant') {
            $ev = (int)Db::val("SELECT COUNT(*) FROM attachments WHERE entity_type = 'obligation' AND entity_id = ? AND deleted_at IS NULL", [$id]);
            if ($ev === 0 && $v->str('status_note') === '') {
                $v->fail('status_note', 'comp.err_evidence_required');
            }
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), url('compliance/' . $id) . '#status');
        }
        Db::update('compliance_obligations', ['status' => $st, 'status_note' => $v->str('status_note') ?: null, 'last_reviewed_on' => date('Y-m-d'), 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        $this->audit('compliance.status', 'obligation', $id, ['from' => $o['status'], 'to' => $st]);
        $this->redirect(url('compliance/' . $id), 'admin.saved');
    }

    /** Recurring obligations: move the due date to the next cycle and reset the status. */
    public function roll(int $id): never
    {
        $o = $this->load($id);
        $step = ['monthly' => '+1 month', 'quarterly' => '+3 months', 'semiannual' => '+6 months', 'annual' => '+1 year'][$o['frequency']] ?? null;
        if ($step === null) {
            $this->redirect(url('compliance/' . $id), 'comp.err_not_recurring', 'error');
        }
        $next = date('Y-m-d', strtotime($o['due_date'] . ' ' . $step));
        Db::update('compliance_obligations', ['due_date' => $next, 'status' => 'not_started', 'status_note' => null, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        $this->audit('compliance.rolled', 'obligation', $id, ['from' => $o['due_date'], 'to' => $next]);
        $this->redirect(url('compliance/' . $id), 'comp.rolled');
    }

    public function archive(int $id): never
    {
        $o = $this->load($id);
        $arch = $o['archived_at'] === null;
        Db::update('compliance_obligations', ['archived_at' => $arch ? date('Y-m-d H:i:s') : null], 'id = ?', [$id]);
        $this->audit($arch ? 'compliance.archived' : 'compliance.restored', 'obligation', $id);
        $this->redirect(url('compliance/' . $id), 'admin.saved');
    }

    public function upload(int $id): never
    {
        $u = $this->requireEdit();
        $this->load($id);
        AttachmentController::handleUpload('obligation', $id, $u, url('compliance/' . $id) . '#attachments');
    }
}
