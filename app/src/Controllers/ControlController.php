<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Db;
use App\Core\Request;
use App\Core\Scope;
use App\Core\Validator;
use App\Support\Ref;
use App\Support\Upload;

/** Central control library, risk-control mapping and independent control testing. */
final class ControlController extends Controller
{
    private const PER_PAGE = 25;
    public const TYPES = ['preventive', 'detective', 'corrective'];
    public const NATURES = ['manual', 'automated', 'semi_automated'];
    public const FREQS = ['continuous', 'daily', 'weekly', 'monthly', 'quarterly', 'semiannual', 'annual', 'event'];
    public const TEST_FREQS = ['quarterly', 'semiannual', 'annual'];
    public const RESULTS = ['effective', 'partially_effective', 'ineffective'];

    private function load(int $id): array
    {
        $c = Db::one('SELECT c.*, d.name_en AS dept_en, d.name_ar AS dept_ar, o.full_name AS owner_name FROM controls c JOIN departments d ON d.id = c.department_id JOIN users o ON o.id = c.owner_user_id WHERE c.id = ?', [$id]);
        if (!$c || !Scope::allowsDept($this->user(), $c)) {
            $this->notFound();
        }
        return $c;
    }

    public function index(): string
    {
        $u = $this->user();
        [$sw, $sp] = Scope::sqlDept($u, 'c');
        $f = ['q' => Request::str('q', true), 'department' => Request::int('department', true), 'type' => Request::str('type', true), 'key' => Request::str('key', true) === '1' ? '1' : '', 'state' => Request::str('state', true), 'archived' => Request::str('archived', true) === '1' ? '1' : ''];
        $where = [$sw];
        $p = $sp;
        if ($f['archived'] !== '1') {
            $where[] = 'c.archived_at IS NULL';
        }
        if ($f['q'] !== '') {
            $where[] = '(c.name LIKE ? OR c.code LIKE ?)';
            $like = Db::like($f['q']);
            array_push($p, $like, $like);
        }
        if ($f['department'] !== null) {
            $where[] = 'c.department_id = ?';
            $p[] = $f['department'];
        }
        if (in_array($f['type'], self::TYPES, true)) {
            $where[] = 'c.control_type = ?';
            $p[] = $f['type'];
        }
        if ($f['key'] === '1') {
            $where[] = 'c.is_key = 1';
        }
        match ($f['state']) {
            'untested' => $where[] = 'c.last_tested_on IS NULL',
            'overdue' => $where[] = 'c.next_test_due IS NOT NULL AND c.next_test_due < CURDATE()',
            'ineffective' => $where[] = "c.last_result IN ('ineffective','partially_effective')",
            'unlinked' => $where[] = 'NOT EXISTS (SELECT 1 FROM risk_controls rc WHERE rc.control_id = c.id)',
            default => null,
        };
        $w = implode(' AND ', $where);
        $total = (int)Db::val('SELECT COUNT(*) FROM controls c WHERE ' . $w, $p);
        $page = min($this->page(), max(1, (int)ceil($total / self::PER_PAGE)));
        $rows = Db::all('SELECT c.*, d.name_en AS dept_en, d.name_ar AS dept_ar, o.full_name AS owner_name, (SELECT COUNT(*) FROM risk_controls rc WHERE rc.control_id = c.id) AS risk_count FROM controls c JOIN departments d ON d.id = c.department_id JOIN users o ON o.id = c.owner_user_id WHERE ' . $w . ' ORDER BY c.code LIMIT ? OFFSET ?', array_merge($p, [self::PER_PAGE, ($page - 1) * self::PER_PAGE]));
        $stats = Db::one("SELECT COUNT(*) AS total, SUM(c.last_tested_on IS NULL) AS untested, SUM(c.next_test_due IS NOT NULL AND c.next_test_due < CURDATE()) AS overdue, SUM(c.last_result IN ('ineffective','partially_effective')) AS weak FROM controls c WHERE c.archived_at IS NULL AND $sw", $sp);
        return $this->view('controls/index', ['title' => t('ctl.title_plural'), 'rows' => $rows, 'f' => $f, 'total' => $total, 'page' => $page, 'per' => self::PER_PAGE, 'departments' => Ref::departments(true), 'stats' => $stats]);
    }

    public function show(int $id): string
    {
        $u = $this->user();
        $c = $this->load($id);
        [$rw, $rp] = Scope::sql($u, 'r');
        $risks = Db::all('SELECT r.id, r.risk_code, r.title, r.res_score FROM risk_controls rc JOIN risks r ON r.id = rc.risk_id WHERE rc.control_id = ? AND r.archived_at IS NULL AND ' . $rw . ' ORDER BY r.risk_code', array_merge([$id], $rp));
        $tests = Db::all('SELECT t.*, u.full_name AS tester_name FROM control_tests t JOIN users u ON u.id = t.tester_user_id WHERE t.control_id = ? ORDER BY t.test_date DESC, t.id DESC', [$id]);
        $evidence = [];
        foreach (Db::all("SELECT a.* FROM attachments a JOIN control_tests t ON t.id = a.entity_id WHERE a.entity_type = 'control_test' AND t.control_id = ? AND a.deleted_at IS NULL", [$id]) as $a) {
            $evidence[(int)$a['entity_id']][] = $a;
        }
        $linkable = can('control.manage') ? Db::all('SELECT r.id, r.risk_code, r.title FROM risks r WHERE r.archived_at IS NULL AND ' . $rw . ' AND r.id NOT IN (SELECT risk_id FROM risk_controls WHERE control_id = ?) ORDER BY r.risk_code LIMIT 500', array_merge($rp, [$id])) : [];
        return $this->view('controls/show', ['title' => $c['code'] . ' — ' . $c['name'], 'c' => $c, 'risks' => $risks, 'tests' => $tests, 'evidence' => $evidence, 'linkable' => $linkable, 'canTest' => can('control.test') && $c['archived_at'] === null && (int)$c['owner_user_id'] !== (int)$u['id']]);
    }

    public function create(): string
    {
        return $this->view('controls/form', ['title' => t('ctl.new'), 'c' => ['control_type' => 'preventive', 'nature' => 'manual', 'frequency' => 'monthly', 'test_frequency' => 'annual'], 'existing' => null, 'lists' => $this->lists()]);
    }

    public function edit(int $id): string
    {
        $c = $this->load($id);
        return $this->view('controls/form', ['title' => t('ctl.edit'), 'c' => $c, 'existing' => $c, 'lists' => $this->lists()]);
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
        $v->required('name', 'ctl.name')->max('name', 'ctl.name', 250)->max('description', 'ctl.description', 3000)
            ->in('control_type', 'ctl.type', self::TYPES)->in('nature', 'ctl.nature', self::NATURES)->in('frequency', 'ctl.frequency', self::FREQS)->in('test_frequency', 'ctl.test_frequency', self::TEST_FREQS);
        $dept = Request::int('department_id');
        $owner = Request::int('owner_user_id');
        if ($dept === null || !isset(Ref::departments(true)[$dept]) && $dept !== ($ex['department_id'] ?? null)) {
            $v->fail('department_id', 'validation.invalid_choice', ['field' => t('ctl.department')]);
        }
        if ($owner === null || !isset(Ref::users(true)[$owner]) && $owner !== ($ex['owner_user_id'] ?? null)) {
            $v->fail('owner_user_id', 'validation.invalid_choice', ['field' => t('ctl.owner')]);
        }
        $back = $ex ? url('controls/' . $ex['id'] . '/edit') : url('controls/new');
        if (!$v->ok()) {
            $this->invalid($v->errors(), $back);
        }
        $now = date('Y-m-d H:i:s');
        $data = ['name' => $v->str('name'), 'description' => $v->str('description') ?: null, 'control_type' => $v->str('control_type'), 'nature' => $v->str('nature'), 'frequency' => $v->str('frequency'),
            'is_key' => isset($_POST['is_key']) ? 1 : 0, 'department_id' => $dept, 'owner_user_id' => $owner, 'test_frequency' => $v->str('test_frequency'), 'updated_at' => $now];
        if ($ex) {
            if ($ex['last_tested_on'] && $ex['test_frequency'] !== $data['test_frequency']) {
                $data['next_test_due'] = self::nextDue($ex['last_tested_on'], $data['test_frequency']);
            }
            Db::update('controls', $data, 'id = ?', [$ex['id']]);
            $this->audit('control.updated', 'control', (int)$ex['id'], ['changes' => $this->diff($ex, $data, ['name', 'control_type', 'nature', 'frequency', 'is_key', 'department_id', 'owner_user_id', 'test_frequency'])]);
            $id = (int)$ex['id'];
        } else {
            $id = Db::insert('controls', $data + ['next_test_due' => date('Y-m-d', strtotime('+30 days')), 'created_by' => $this->user()['id'], 'created_at' => $now]);
            Db::update('controls', ['code' => sprintf('CTL-%04d', $id)], 'id = ?', [$id]);
            $this->audit('control.created', 'control', $id, ['name' => $data['name']]);
        }
        $this->redirect(url('controls/' . $id), 'admin.saved');
    }

    public function archive(int $id): never
    {
        $c = $this->load($id);
        $arch = $c['archived_at'] === null;
        Db::update('controls', ['archived_at' => $arch ? date('Y-m-d H:i:s') : null], 'id = ?', [$id]);
        $this->audit($arch ? 'control.archived' : 'control.restored', 'control', $id);
        $this->redirect(url('controls/' . $id), 'admin.saved');
    }

    /** Link a risk to a control (the risk must be visible to the user). */
    public function link(int $id): never
    {
        $u = $this->user();
        $c = $this->load($id);
        $rid = Request::int('risk_id');
        $risk = $rid ? Db::one('SELECT id, department_id, branch_id, archived_at FROM risks WHERE id = ?', [$rid]) : null;
        if (!$risk || $risk['archived_at'] !== null || !Scope::allows($u, $risk)) {
            $this->redirect(url('controls/' . $id) . '#risks', 'validation.invalid_choice', 'error', ['field' => t('ctl.risk')]);
        }
        Db::exec('INSERT IGNORE INTO risk_controls (risk_id, control_id, created_by, created_at) VALUES (?, ?, ?, ?)', [$rid, $id, $u['id'], date('Y-m-d H:i:s')]);
        $this->audit('control.linked', 'control', $id, ['risk_id' => $rid]);
        $this->redirect(url('controls/' . $id) . '#risks', 'ctl.linked');
    }

    public function unlink(int $id, int $rid): never
    {
        $u = $this->user();
        $this->load($id);
        $risk = Db::one('SELECT department_id, branch_id FROM risks WHERE id = ?', [$rid]);
        if (!$risk || !Scope::allows($u, $risk)) {
            $this->notFound();
        }
        Db::exec('DELETE FROM risk_controls WHERE control_id = ? AND risk_id = ?', [$id, $rid]);
        $this->audit('control.unlinked', 'control', $id, ['risk_id' => $rid]);
        $this->redirect(url('controls/' . $id) . '#risks', 'ctl.unlink_done');
    }

    /** Record a design or operating-effectiveness test. Rules: tester is independent of the owner; "effective" needs evidence and no exceptions. */
    public function addTest(int $id): never
    {
        $u = $this->user();
        $c = $this->load($id);
        $back = url('controls/' . $id) . '#test';
        if ($c['archived_at'] !== null) {
            $this->redirect(url('controls/' . $id), 'risk.err_archived_readonly', 'error');
        }
        if ((int)$c['owner_user_id'] === (int)$u['id']) {
            $this->redirect(url('controls/' . $id), 'ctl.err_independent', 'error');
        }
        $v = new Validator($_POST);
        $v->in('test_type', 'ctl.test_type', ['design', 'operating'])->in('result', 'ctl.result', self::RESULTS)->date('test_date', 'ctl.test_date')->max('conclusion', 'ctl.conclusion', 3000);
        $date = $v->str('test_date');
        if ($date !== '' && $date > date('Y-m-d')) {
            $v->fail('test_date', 'validation.not_future', ['field' => t('ctl.test_date')]);
        }
        $type = $v->str('test_type');
        $result = $v->str('result');
        $sample = $exceptions = null;
        if ($type === 'operating') {
            $sample = Request::int('sample_size');
            $exceptions = Request::int('exceptions');
            if ($sample === null || $sample < 1 || $sample > 1000000) {
                $v->fail('sample_size', 'validation.int_range', ['field' => t('ctl.sample_size'), 'min' => 1, 'max' => 1000000]);
            } elseif ($exceptions === null || $exceptions < 0 || $exceptions > $sample) {
                $v->fail('exceptions', 'ctl.err_exceptions');
            } elseif ($result === 'effective' && $exceptions > 0) {
                $v->fail('result', 'ctl.err_effective_exceptions');
            }
        }
        if ($result !== 'effective' && $v->str('conclusion') === '') {
            $v->fail('conclusion', 'ctl.err_conclusion_required');
        }
        $file = $_FILES['file'] ?? null;
        $hasFile = is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if ($hasFile && ($err = Upload::validate($file))) {
            $v->fail('file', $err, ['max' => '5 MB', 'types' => implode(', ', array_keys(Upload::TYPES))]);
        }
        if ($result === 'effective' && !$hasFile) {
            $v->fail('file', 'ctl.err_evidence_required');
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), $back);
        }
        $now = date('Y-m-d H:i:s');
        $created = [];
        $tid = Db::tx(function () use ($id, $c, $u, $v, $date, $type, $result, $sample, $exceptions, $now, $hasFile, $file, &$created) {
            $tid = Db::insert('control_tests', ['control_id' => $id, 'test_type' => $type, 'test_date' => $date, 'tester_user_id' => $u['id'], 'sample_size' => $sample, 'exceptions' => $exceptions, 'result' => $result, 'conclusion' => $v->str('conclusion') ?: null, 'created_at' => $now]);
            if ($hasFile) {
                Upload::store($file, 'control_test', $tid, (int)$u['id']);
            }
            Db::update('controls', ['last_tested_on' => $date, 'last_result' => $result, 'next_test_due' => self::nextDue($date, $c['test_frequency']), 'updated_at' => $now], 'id = ?', [$id]);
            if ($result !== 'effective') {
                $title = sprintf('%s: %s %s', t('ctl.remediate'), $c['code'], $c['name']);
                foreach (Db::all('SELECT r.id FROM risk_controls rc JOIN risks r ON r.id = rc.risk_id WHERE rc.control_id = ? AND r.archived_at IS NULL', [$id]) as $r) {
                    $exists = Db::val("SELECT 1 FROM risk_actions WHERE risk_id = ? AND title = ? AND status IN ('not_started','in_progress')", [$r['id'], mb_substr($title, 0, 250)]);
                    if (!$exists) {
                        $created[] = Db::insert('risk_actions', ['risk_id' => $r['id'], 'title' => mb_substr($title, 0, 250), 'description' => $v->str('conclusion') ?: null, 'owner_user_id' => $c['owner_user_id'], 'deadline' => date('Y-m-d', strtotime('+30 days')), 'status' => 'not_started', 'created_by' => $u['id'], 'created_at' => $now, 'updated_at' => $now]);
                    }
                }
            }
            return $tid;
        });
        $this->audit('control.tested', 'control', $id, ['test_id' => $tid, 'type' => $type, 'result' => $result, 'actions_created' => count($created)]);
        $this->redirect(url('controls/' . $id), $created ? 'ctl.tested_actions' : 'ctl.tested', 'success', ['n' => count($created)]);
    }

    public static function nextDue(string $from, string $freq): string
    {
        $step = ['quarterly' => '+3 months', 'semiannual' => '+6 months', 'annual' => '+1 year'][$freq] ?? '+1 year';
        return date('Y-m-d', strtotime($from . ' ' . $step));
    }
}
