<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Db;
use App\Core\Money;
use App\Core\Perm;
use App\Core\Request;
use App\Core\Scope;
use App\Core\Validator;
use App\Support\Kri;
use App\Support\Ref;

final class KriController extends Controller
{
    private const PER_PAGE = 25;
    private const LATEST = 'LEFT JOIN kri_readings lr ON lr.id = (SELECT x.id FROM kri_readings x WHERE x.kri_id = k.id ORDER BY x.reading_date DESC, x.id DESC LIMIT 1)';

    private function load(int $id): array
    {
        $u = $this->user();
        $k = Db::one('SELECT k.*, d.name_en AS dept_en, d.name_ar AS dept_ar, o.full_name AS owner_name FROM kris k JOIN departments d ON d.id = k.department_id JOIN users o ON o.id = k.owner_user_id WHERE k.id = ?', [$id]);
        if (!$k || !Scope::allowsDept($u, $k)) {
            $this->notFound();
        }
        return $k;
    }

    public function index(): string
    {
        $u = $this->user();
        [$sw, $sp] = Scope::sqlDept($u, 'k');
        $where = [$sw];
        $p = $sp;
        $f = ['department' => Request::int('department', true), 'status' => Request::str('status', true), 'alert' => Request::str('alert', true) === '1' ? '1' : '', 'missing' => Request::str('missing', true) === '1' ? '1' : '', 'inactive' => Request::str('inactive', true) === '1' ? '1' : ''];
        if ($f['inactive'] !== '1') {
            $where[] = 'k.is_active = 1';
        }
        if ($f['department'] !== null) {
            $where[] = 'k.department_id = ?';
            $p[] = $f['department'];
        }
        if (in_array($f['status'], ['green', 'amber', 'red', 'critical'], true)) {
            $where[] = 'lr.status = ?';
            $p[] = $f['status'];
        }
        if ($f['alert'] === '1') {
            $where[] = "lr.status IN ('amber','red','critical')";
        }
        if ($f['missing'] === '1') {
            $where[] = "(lr.id IS NULL OR lr.reading_date < IF(k.frequency = 'quarterly', DATE_FORMAT(CURDATE(), CONCAT('%Y-', LPAD(FLOOR((MONTH(CURDATE()) - 1) / 3) * 3 + 1, 2, '0'), '-01')), DATE_FORMAT(CURDATE(), '%Y-%m-01')))";
        }
        $w = implode(' AND ', $where);
        $total = (int)Db::val('SELECT COUNT(*) FROM kris k ' . self::LATEST . ' WHERE ' . $w, $p);
        $page = min($this->page(), max(1, (int)ceil($total / self::PER_PAGE)));
        $rows = Db::all('SELECT k.*, d.name_en AS dept_en, d.name_ar AS dept_ar, o.full_name AS owner_name, lr.value AS last_value, lr.status AS last_status, lr.reading_date AS last_date FROM kris k JOIN departments d ON d.id = k.department_id JOIN users o ON o.id = k.owner_user_id ' . self::LATEST . ' WHERE ' . $w . " ORDER BY FIELD(lr.status,'critical','red','amber','green'), k.code LIMIT ? OFFSET ?", array_merge($p, [self::PER_PAGE, ($page - 1) * self::PER_PAGE]));
        return $this->view('kris/index', ['title' => t('kri.title_plural'), 'rows' => $rows, 'f' => $f, 'total' => $total, 'page' => $page, 'per' => self::PER_PAGE, 'departments' => Ref::departments(true)]);
    }

    public function show(int $id): string
    {
        $k = $this->load($id);
        $readings = Db::all('SELECT r.*, u.full_name, p.name_en AS p_en, p.name_ar AS p_ar FROM kri_readings r JOIN users u ON u.id = r.entered_by LEFT JOIN reporting_periods p ON p.id = r.period_id WHERE r.kri_id = ? ORDER BY r.reading_date DESC, r.id DESC LIMIT 60', [$id]);
        return $this->view('kris/show', ['title' => $k['code'] . ' — ' . $k['name'], 'k' => $k, 'readings' => $readings, 'breaches' => Db::all('SELECT b.*, r.reading_date, r.value FROM kri_breaches b JOIN kri_readings r ON r.id = b.reading_id WHERE b.kri_id = ? ORDER BY b.id DESC LIMIT 30', [$id]), 'periods' => Ref::periods(true), 'linked' => $k['risk_id'] ? Db::one('SELECT id, risk_code, title FROM risks WHERE id = ?', [$k['risk_id']]) : null]);
    }

    public function create(): string
    {
        return $this->view('kris/form', ['title' => t('kri.new'), 'k' => ['direction' => 'higher_worse', 'frequency' => 'monthly', 'is_active' => 1], 'existing' => null, 'lists' => $this->lists()]);
    }

    public function edit(int $id): string
    {
        $k = $this->load($id);
        foreach (['amber_threshold', 'red_threshold'] as $c) {
            $k[$c] = Money::plain($k[$c]);
        }
        return $this->view('kris/form', ['title' => t('kri.edit'), 'k' => $k, 'existing' => $k, 'lists' => $this->lists()]);
    }

    private function lists(): array
    {
        return ['departments' => Ref::departments(true), 'categories' => Ref::categories(true), 'users' => Ref::users(true), 'risks' => Db::all('SELECT id, risk_code, title FROM risks WHERE archived_at IS NULL ORDER BY id DESC LIMIT 500')];
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
        $v->required('name', 'kri.name')->max('name', 'kri.name', 250)->max('description', 'kri.description', 2000)->max('unit', 'kri.unit', 30)
            ->in('direction', 'kri.direction', ['higher_worse', 'lower_worse'])->in('frequency', 'kri.frequency', ['monthly', 'quarterly']);
        $dept = Request::int('department_id');
        $owner = Request::int('owner_user_id');
        $cat = Request::int('category_id');
        $risk = Request::int('risk_id');
        if ($dept === null || !isset(Ref::departments(true)[$dept]) && $dept !== ($ex['department_id'] ?? null)) {
            $v->fail('department_id', 'validation.invalid_choice', ['field' => t('kri.department')]);
        }
        if ($owner === null || !isset(Ref::users(true)[$owner]) && $owner !== ($ex['owner_user_id'] ?? null)) {
            $v->fail('owner_user_id', 'validation.invalid_choice', ['field' => t('kri.owner')]);
        }
        if ($cat !== null && !isset(Ref::categories()[$cat])) {
            $v->fail('category_id', 'validation.invalid_choice', ['field' => t('kri.category')]);
        }
        if ($risk !== null && !Db::val('SELECT 1 FROM risks WHERE id = ?', [$risk])) {
            $v->fail('risk_id', 'validation.invalid_choice', ['field' => t('kri.linked_risk')]);
        }
        $amber = Money::parse($v->str('amber_threshold'), Money::decimals());
        $red = Money::parse($v->str('red_threshold'), Money::decimals());
        if ($v->str('amber_threshold') === '' || $amber === null) {
            $v->fail('amber_threshold', 'validation.amount', ['field' => t('kri.amber')]);
        }
        if ($v->str('red_threshold') === '' || $red === null) {
            $v->fail('red_threshold', 'validation.amount', ['field' => t('kri.red')]);
        }
        if ($amber !== null && $red !== null && !Kri::thresholdsValid($amber, $red, $v->str('direction'))) {
            $v->fail('red_threshold', 'kri.err_thresholds');
        }
        $critRaw = $v->str('critical_threshold');
        $crit = $critRaw === '' ? null : Money::parse($critRaw, Money::decimals());
        if ($critRaw !== '' && $crit === null) {
            $v->fail('critical_threshold', 'validation.amount', ['field' => t('kri.critical')]);
        } elseif ($crit !== null && $red !== null && !Kri::criticalValid($red, $crit, $v->str('direction'))) {
            $v->fail('critical_threshold', 'kri.err_critical');
        }
        $v->in('data_source', 'kri.data_source', ['manual', 'import', 'api'], false);
        $back = $ex ? url('kris/' . $ex['id'] . '/edit') : url('kris/new');
        if (!$v->ok()) {
            $this->invalid($v->errors(), $back);
        }
        $data = ['name' => $v->str('name'), 'description' => $v->str('description') ?: null, 'category_id' => $cat, 'department_id' => $dept, 'owner_user_id' => $owner, 'risk_id' => $risk,
            'unit' => $v->str('unit'), 'direction' => $v->str('direction'), 'amber_threshold' => Money::toDb($amber), 'red_threshold' => Money::toDb($red), 'critical_threshold' => $crit === null ? null : Money::toDb($crit), 'data_source' => $v->str('data_source') ?: 'manual', 'frequency' => $v->str('frequency'),
            'is_active' => $ex ? (!empty($_POST['is_active']) ? 1 : 0) : 1, 'updated_at' => date('Y-m-d H:i:s')];
        if ($ex) {
            $ch = $this->diff($ex, $data, ['name', 'department_id', 'owner_user_id', 'direction', 'amber_threshold', 'red_threshold', 'critical_threshold', 'frequency', 'is_active']);
            Db::update('kris', $data, 'id = ?', [$ex['id']]);
            // Re-evaluate the status of existing readings against the new thresholds so history stays consistent.
            $this->recompute((int)$ex['id'], $amber, $red, $data['direction'], $crit);
            $this->audit('kri.updated', 'kri', (int)$ex['id'], ['changes' => $ch]);
            $id = (int)$ex['id'];
        } else {
            $u = $this->user();
            $id = Db::insert('kris', $data + ['created_by' => $u['id'], 'created_at' => $data['updated_at']]);
            Db::update('kris', ['code' => sprintf('KRI-%04d', $id)], 'id = ?', [$id]);
            $this->audit('kri.created', 'kri', $id, ['name' => $data['name']]);
        }
        $this->redirect(url('kris/' . $id), 'admin.saved');
    }

    private function recompute(int $kriId, int $amber, int $red, string $dir, ?int $crit = null): void
    {
        foreach (Db::all('SELECT id, value FROM kri_readings WHERE kri_id = ?', [$kriId]) as $r) {
            Db::update('kri_readings', ['status' => Kri::status(Money::fromDb($r['value']), $amber, $red, $dir, $crit)], 'id = ?', [$r['id']]);
        }
    }

    public function addReading(int $id): never
    {
        $u = $this->user();
        $k = $this->load($id);
        $v = new Validator($_POST);
        $v->date('reading_date', 'kri.reading_date')->max('comment', 'kri.comment', 2000);
        $val = Money::parse($v->str('value'), Money::decimals());
        if ($v->str('value') === '' || $val === null) {
            $v->fail('value', 'validation.amount', ['field' => t('kri.value')]);
        }
        if ($v->str('reading_date') > date('Y-m-d')) {
            $v->fail('reading_date', 'validation.not_future', ['field' => t('kri.reading_date')]);
        }
        $period = Request::int('period_id');
        if ($period !== null && !isset(Ref::periods()[$period])) {
            $v->fail('period_id', 'validation.invalid_choice', ['field' => t('kri.period')]);
        }
        if (!$k['is_active']) {
            $v->fail('value', 'kri.err_inactive');
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), url('kris/' . $id) . '#reading');
        }
        if ($val !== null && ($q = \App\Support\KriReadings::quality($k, $val, $v->str('comment'))) !== null) {
            $this->invalid(['comment' => t($q)], url('kris/' . $id) . '#reading');
        }
        $status = \App\Support\KriReadings::record($k, (int)$val, $v->str('reading_date'), $v->str('comment'), (int)$u['id'], $period)['status'];
        $this->redirect(url('kris/' . $id), $status === 'green' ? 'kri.reading_saved' : 'kri.reading_saved_alert');
    }

    /** Record the justification and action plan of a breach, and optionally close it. */
    public function updateBreach(int $id, int $bid): never
    {
        $u = $this->user();
        $this->load($id);
        $b = Db::one('SELECT * FROM kri_breaches WHERE id = ? AND kri_id = ?', [$bid, $id]);
        $b || $this->notFound();
        if ($b['status'] === 'resolved') {
            $this->redirect(url('kris/' . $id) . '#breaches', 'kri.err_breach_closed', 'error');
        }
        $v = new Validator($_POST);
        $v->required('justification', 'kri.justification')->max('justification', 'kri.justification', 3000)->required('action_plan', 'kri.action_plan')->max('action_plan', 'kri.action_plan', 3000)->max('resolution_note', 'kri.resolution_note', 3000);
        $resolve = isset($_POST['resolve']);
        if ($resolve && $v->str('resolution_note') === '') {
            $v->fail('resolution_note', 'validation.required', ['field' => t('kri.resolution_note')]);
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), url('kris/' . $id) . '#breaches');
        }
        Db::update('kri_breaches', ['justification' => $v->str('justification'), 'action_plan' => $v->str('action_plan'), 'status' => $resolve ? 'resolved' : 'open', 'resolved_on' => $resolve ? date('Y-m-d') : null, 'resolution_note' => $resolve ? $v->str('resolution_note') : null, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$bid]);
        $this->audit($resolve ? 'kri.breach_resolved' : 'kri.breach_updated', 'kri', $id, ['breach_id' => $bid]);
        $this->redirect(url('kris/' . $id) . '#breaches', 'admin.saved');
    }
}
