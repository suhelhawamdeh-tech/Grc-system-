<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Db;
use App\Core\Request;
use App\Core\Scope;
use App\Core\Validator;
use App\Support\Approvals;
use App\Support\Bia;
use App\Support\Ref;

/** Business impact analysis: critical processes, impact ratings, recovery objectives and supporting systems. */
final class BiaController extends Controller
{
    private const PER_PAGE = 25;
    public const CRIT = ['critical', 'high', 'medium', 'low'];

    private function load(int $id): array
    {
        $p = Db::one('SELECT p.*, d.name_en AS dept_en, d.name_ar AS dept_ar, o.full_name AS owner_name FROM bia_processes p JOIN departments d ON d.id = p.department_id JOIN users o ON o.id = p.owner_user_id WHERE p.id = ?', [$id]);
        if (!$p || !Scope::allowsDept($this->user(), $p)) {
            $this->notFound();
        }
        return $p;
    }

    public function index(): string
    {
        $u = $this->user();
        [$sw, $sp] = Scope::sqlDept($u, 'p');
        $f = ['q' => Request::str('q', true), 'department' => Request::int('department', true), 'criticality' => Request::str('criticality', true), 'status' => Request::str('status', true), 'archived' => Request::str('archived', true) === '1' ? '1' : '', 'gap' => Request::str('gap', true) === '1' ? '1' : '', 'review' => Request::str('review', true) === '1' ? '1' : ''];
        $where = [$sw];
        $p = $sp;
        if ($f['archived'] !== '1') {
            $where[] = 'p.archived_at IS NULL';
        }
        if ($f['q'] !== '') {
            $like = Db::like($f['q']);
            $where[] = '(p.name LIKE ? OR p.code LIKE ? OR p.description LIKE ?)';
            array_push($p, $like, $like, $like);
        }
        if ($f['department'] !== null) {
            $where[] = 'p.department_id = ?';
            $p[] = $f['department'];
        }
        if (in_array($f['criticality'], self::CRIT, true)) {
            $where[] = 'p.criticality = ?';
            $p[] = $f['criticality'];
        }
        if (in_array($f['status'], ['draft', 'pending_approval', 'approved'], true)) {
            $where[] = 'p.status = ?';
            $p[] = $f['status'];
        }
        $gapSql = 'EXISTS (SELECT 1 FROM bia_process_systems ps JOIN bia_systems s ON s.id = ps.system_id WHERE ps.process_id = p.id AND s.is_active = 1 AND (s.recovery_hours > p.rto_hours OR s.data_loss_hours > p.rpo_hours))';
        if ($f['gap'] === '1') {
            $where[] = $gapSql;
        }
        if ($f['review'] === '1') {
            $where[] = "p.status = 'approved' AND p.next_review_due < CURDATE()";
        }
        $w = implode(' AND ', $where);
        $total = (int)Db::val('SELECT COUNT(*) FROM bia_processes p WHERE ' . $w, $p);
        $page = min($this->page(), max(1, (int)ceil($total / self::PER_PAGE)));
        $rows = Db::all("SELECT p.*, d.name_en AS dept_en, d.name_ar AS dept_ar, o.full_name AS owner_name, ($gapSql) AS has_gap FROM bia_processes p JOIN departments d ON d.id = p.department_id JOIN users o ON o.id = p.owner_user_id WHERE $w ORDER BY FIELD(p.criticality,'critical','high','medium','low'), p.code LIMIT ? OFFSET ?", array_merge($p, [self::PER_PAGE, ($page - 1) * self::PER_PAGE]));
        $stats = Db::one("SELECT COUNT(*) AS total, SUM(p.criticality = 'critical') AS crit, SUM(p.criticality IN ('critical','high') AND p.status <> 'approved') AS unapproved, SUM($gapSql) AS gaps, SUM(p.status = 'approved' AND p.next_review_due < CURDATE()) AS review FROM bia_processes p WHERE p.archived_at IS NULL AND $sw", $sp);
        return $this->view('bia/index', ['title' => t('bia.title'), 'rows' => $rows, 'f' => $f, 'total' => $total, 'page' => $page, 'per' => self::PER_PAGE, 'departments' => Ref::departments(true), 'stats' => $stats]);
    }

    public function show(int $id): string
    {
        $u = $this->user();
        $p = $this->load($id);
        $systems = Db::all('SELECT s.* FROM bia_process_systems ps JOIN bia_systems s ON s.id = ps.system_id WHERE ps.process_id = ? ORDER BY s.code', [$id]);
        $history = Db::all('SELECT a.*, r.full_name AS requester, d.full_name AS decider FROM approvals a JOIN users r ON r.id = a.requested_by LEFT JOIN users d ON d.id = a.decided_by WHERE a.entity_type = ? AND a.entity_id = ? ORDER BY a.id DESC LIMIT 20', ['bia_process', $id]);
        $pending = Approvals::pending('bia_process', $id);
        return $this->view('bia/show', ['title' => $p['code'] . ' — ' . $p['name'], 'p' => $p, 'systems' => $systems, 'gaps' => Bia::gaps($id), 'history' => $history, 'pending' => $pending, 'canDecide' => $pending && can('approval.decide') && (int)$pending['requested_by'] !== (int)$u['id']]);
    }

    private function lists(): array
    {
        return ['departments' => Ref::departments(true), 'users' => Ref::users(true), 'systems' => Db::all('SELECT id, code, name, recovery_hours, data_loss_hours FROM bia_systems WHERE is_active = 1 ORDER BY code')];
    }

    public function create(): string
    {
        $u = $this->user();
        return $this->view('bia/form', ['title' => t('bia.new'), 'p' => ['department_id' => $u['department_id'] ?? '', 'owner_user_id' => $u['id']], 'existing' => null, 'linked' => [], 'lists' => $this->lists()]);
    }

    public function edit(int $id): string
    {
        $p = $this->load($id);
        $linked = array_map('intval', array_column(Db::all('SELECT system_id FROM bia_process_systems WHERE process_id = ?', [$id]), 'system_id'));
        return $this->view('bia/form', ['title' => t('bia.edit'), 'p' => $p, 'existing' => $p, 'linked' => $linked, 'lists' => $this->lists()]);
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
        $u = $this->user();
        $v = new Validator($_POST);
        $v->required('name', 'bia.name')->max('name', 'bia.name', 250)->max('description', 'bia.description', 3000)->max('dependencies', 'bia.dependencies', 3000);
        $dept = Request::int('department_id');
        $owner = Request::int('owner_user_id');
        if ($dept === null || !isset(Ref::departments(true)[$dept]) && $dept !== ($ex['department_id'] ?? null)) {
            $v->fail('department_id', 'validation.invalid_choice', ['field' => t('bia.department')]);
        } elseif (!Scope::allowsDept($u, ['department_id' => $dept])) {
            $v->fail('department_id', 'validation.out_of_scope');
        }
        if ($owner === null || !isset(Ref::users(true)[$owner]) && $owner !== ($ex['owner_user_id'] ?? null)) {
            $v->fail('owner_user_id', 'validation.invalid_choice', ['field' => t('bia.owner')]);
        }
        $imp = [];
        foreach (['impact_financial', 'impact_operational', 'impact_legal', 'impact_reputation'] as $k) {
            $v->intRange($k, 'bia.' . $k, 1, 5);
            $imp[$k] = Request::int($k) ?? 1;
        }
        $h = [];
        foreach (['mtpd_hours', 'rto_hours', 'rpo_hours'] as $k) {
            $h[$k] = Bia::hours($v->str($k));
            if ($h[$k] === null) {
                $v->fail($k, 'bia.err_hours', ['field' => t('bia.' . $k)]);
            }
        }
        if ($h['mtpd_hours'] !== null && $h['rto_hours'] !== null && $h['rto_hours'] > $h['mtpd_hours']) {
            $v->fail('rto_hours', 'bia.err_rto_mtpd');
        }
        $sys = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['systems'] ?? [])))));
        foreach ($sys as $sid) {
            if (!Db::val('SELECT 1 FROM bia_systems WHERE id = ?', [$sid])) {
                $v->fail('systems', 'validation.invalid_choice', ['field' => t('bia.systems')]);
            }
        }
        $back = $ex ? url('bia/' . $ex['id'] . '/edit') : url('bia/new');
        if (!$v->ok()) {
            $this->invalid($v->errors(), $back);
        }
        $now = date('Y-m-d H:i:s');
        $d = ['name' => $v->str('name'), 'description' => $v->str('description') ?: null, 'department_id' => $dept, 'owner_user_id' => $owner, 'dependencies' => $v->str('dependencies') ?: null,
            'criticality' => Bia::criticality($imp['impact_financial'], $imp['impact_operational'], $imp['impact_legal'], $imp['impact_reputation']),
            'mtpd_hours' => number_format($h['mtpd_hours'] / 100, 2, '.', ''), 'rto_hours' => number_format($h['rto_hours'] / 100, 2, '.', ''), 'rpo_hours' => number_format($h['rpo_hours'] / 100, 2, '.', ''), 'updated_at' => $now] + $imp;
        $id = Db::tx(function () use ($ex, $d, $u, $now, $sys) {
            if ($ex) {
                $material = false;
                foreach (['impact_financial', 'impact_operational', 'impact_legal', 'impact_reputation', 'mtpd_hours', 'rto_hours', 'rpo_hours'] as $k) {
                    $material = $material || (string)$ex[$k] !== (string)$d[$k] && abs((float)$ex[$k] - (float)$d[$k]) > 0.001;
                }
                if ($material && $ex['status'] !== 'draft') {
                    $d['status'] = 'draft';
                    $d['approved_at'] = null;
                    Db::exec("UPDATE approvals SET status = 'rejected', decided_at = ?, comment = ? WHERE entity_type = 'bia_process' AND entity_id = ? AND status = 'pending'", [$now, 'Superseded by edit', $ex['id']]);
                }
                Db::update('bia_processes', $d, 'id = ?', [$ex['id']]);
                $id = (int)$ex['id'];
                Db::exec('DELETE FROM bia_process_systems WHERE process_id = ?', [$id]);
            } else {
                $id = Db::insert('bia_processes', $d + ['status' => 'draft', 'created_by' => $u['id'], 'created_at' => $now]);
                Db::update('bia_processes', ['code' => sprintf('BIA-%04d', $id)], 'id = ?', [$id]);
            }
            foreach ($sys as $sid) {
                Db::exec('INSERT INTO bia_process_systems (process_id, system_id) VALUES (?, ?)', [$id, $sid]);
            }
            return $id;
        });
        $this->audit($ex ? 'bia.updated' : 'bia.created', 'bia_process', $id, ['name' => $d['name'], 'criticality' => $d['criticality']]);
        $this->redirect(url('bia/' . $id), $ex && ($d['status'] ?? '') === 'draft' && $ex['status'] !== 'draft' ? 'bia.saved_reset' : 'admin.saved');
    }

    /** Send the process for approval. Blocked while a supporting system cannot meet the recovery objectives. */
    public function submit(int $id): never
    {
        $u = $this->user();
        $p = $this->load($id);
        if ($p['archived_at'] !== null || $p['status'] !== 'draft') {
            $this->redirect(url('bia/' . $id), 'bia.err_state', 'error');
        }
        if (Bia::gaps($id)) {
            $this->redirect(url('bia/' . $id), 'bia.err_gaps', 'error');
        }
        Db::tx(function () use ($id, $u) {
            Approvals::request('bia_process', $id, (int)$u['id']);
            Db::update('bia_processes', ['status' => 'pending_approval', 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        });
        $this->audit('bia.submitted', 'bia_process', $id);
        $this->redirect(url('bia/' . $id), 'bia.submitted');
    }

    public function archive(int $id): never
    {
        $p = $this->load($id);
        $arch = $p['archived_at'] === null;
        Db::update('bia_processes', ['archived_at' => $arch ? date('Y-m-d H:i:s') : null], 'id = ?', [$id]);
        $this->audit($arch ? 'bia.archived' : 'bia.restored', 'bia_process', $id);
        $this->redirect(url('bia/' . $id), 'admin.saved');
    }

    // ---------- supporting systems ----------
    public function systems(): string
    {
        $rows = Db::all('SELECT s.*, d.name_en AS dept_en, d.name_ar AS dept_ar, o.full_name AS owner_name, (SELECT COUNT(*) FROM bia_process_systems ps WHERE ps.system_id = s.id) AS process_count FROM bia_systems s JOIN departments d ON d.id = s.department_id JOIN users o ON o.id = s.owner_user_id ORDER BY s.code');
        return $this->view('bia/systems', ['title' => t('bia.systems'), 'rows' => $rows]);
    }

    public function systemForm(?int $id = null): string
    {
        $s = $id ? Db::one('SELECT * FROM bia_systems WHERE id = ?', [$id]) : ['is_active' => 1];
        $s || $this->notFound();
        return $this->view('bia/system_form', ['title' => t($id ? 'bia.system_edit' : 'bia.system_new'), 's' => $s, 'existing' => $id ? $s : null, 'lists' => ['departments' => Ref::departments(true), 'users' => Ref::users(true)]]);
    }

    public function systemNew(): string
    {
        return $this->systemForm();
    }

    public function systemEdit(int $id): string
    {
        return $this->systemForm($id);
    }

    public function systemStore(): never
    {
        $this->systemSave(null);
    }

    public function systemUpdate(int $id): never
    {
        $s = Db::one('SELECT * FROM bia_systems WHERE id = ?', [$id]);
        $s || $this->notFound();
        $this->systemSave($s);
    }

    private function systemSave(?array $ex): never
    {
        $v = new Validator($_POST);
        $v->required('name', 'bia.s_name')->max('name', 'bia.s_name', 250)->max('description', 'bia.description', 2000);
        $dept = Request::int('department_id');
        $owner = Request::int('owner_user_id');
        if ($dept === null || !isset(Ref::departments(true)[$dept]) && $dept !== ($ex['department_id'] ?? null)) {
            $v->fail('department_id', 'validation.invalid_choice', ['field' => t('bia.department')]);
        }
        if ($owner === null || !isset(Ref::users(true)[$owner]) && $owner !== ($ex['owner_user_id'] ?? null)) {
            $v->fail('owner_user_id', 'validation.invalid_choice', ['field' => t('bia.owner')]);
        }
        $rec = Bia::hours($v->str('recovery_hours'));
        $loss = Bia::hours($v->str('data_loss_hours'));
        if ($rec === null) {
            $v->fail('recovery_hours', 'bia.err_hours', ['field' => t('bia.s_recovery')]);
        }
        if ($loss === null) {
            $v->fail('data_loss_hours', 'bia.err_hours', ['field' => t('bia.s_loss')]);
        }
        $back = $ex ? url('bia/systems/' . $ex['id'] . '/edit') : url('bia/systems/new');
        if (!$v->ok()) {
            $this->invalid($v->errors(), $back);
        }
        $now = date('Y-m-d H:i:s');
        $d = ['name' => $v->str('name'), 'description' => $v->str('description') ?: null, 'department_id' => $dept, 'owner_user_id' => $owner, 'recovery_hours' => number_format($rec / 100, 2, '.', ''), 'data_loss_hours' => number_format($loss / 100, 2, '.', ''), 'is_active' => isset($_POST['is_active']) || !$ex ? 1 : 0, 'updated_at' => $now];
        if ($ex) {
            Db::update('bia_systems', $d, 'id = ?', [$ex['id']]);
            $this->audit('bia.system_updated', 'bia_system', (int)$ex['id'], ['changes' => $this->diff($ex, $d, ['name', 'recovery_hours', 'data_loss_hours', 'is_active'])]);
        } else {
            $id = Db::insert('bia_systems', $d + ['created_by' => $this->user()['id'], 'created_at' => $now]);
            Db::update('bia_systems', ['code' => sprintf('SYS-%04d', $id)], 'id = ?', [$id]);
            $this->audit('bia.system_created', 'bia_system', $id, ['name' => $d['name']]);
        }
        $this->redirect(url('bia/systems'), 'admin.saved');
    }

    // ---------- report ----------
    public function report(): string
    {
        $u = $this->user();
        [$sw, $sp] = Scope::sqlDept($u, 'p');
        $byDept = Db::all("SELECT d.id, d.name_en, d.name_ar, COUNT(p.id) AS total, SUM(p.criticality = 'critical') AS crit, SUM(p.status = 'approved') AS approved FROM departments d JOIN bia_processes p ON p.department_id = d.id AND p.archived_at IS NULL WHERE $sw GROUP BY d.id, d.name_en, d.name_ar ORDER BY d.name_en", $sp);
        $procs = Db::all("SELECT p.id, p.code, p.name, p.criticality, p.rto_hours, p.rpo_hours FROM bia_processes p WHERE p.archived_at IS NULL AND $sw ORDER BY p.code", $sp);
        $gaps = [];
        foreach ($procs as $p) {
            foreach (Bia::gaps((int)$p['id']) as $g) {
                $gaps[] = $g + ['process' => $p];
            }
        }
        return $this->view('bia/report', ['title' => t('bia.report'), 'byDept' => $byDept, 'gaps' => $gaps]);
    }
}
