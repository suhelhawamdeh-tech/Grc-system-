<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Db;
use App\Core\Request;
use App\Core\Validator;
use App\Support\Ref;
use App\Support\Registers;

/** Supporting registers of the risk function (models, data deliveries, maturity, security acceptances, emerging risks, requirements, committee plan). */
final class RegisterController extends Controller
{
    private function key(): string
    {
        $k = Request::str('r') ?: Request::str('r', true);
        return Registers::def($k) ? $k : $this->notFound();
    }

    public function index(): string
    {
        $k = Request::str('r', true);
        if ($k === '' || !Registers::def($k)) {
            $year = (int)date('Y');
            return $this->view('registers/hub', ['title' => t('reg.title'), 'defs' => Registers::all(), 'counts' => Registers::counts(), 'year' => $year, 'maturity' => Registers::maturity($year) ?: Registers::maturity($year - 1),
                'mYear' => Registers::maturity($year) ? $year : $year - 1]);
        }
        $def = Registers::def($k);
        $f = ['status' => Request::str('status', true), 'dept' => Request::int('dept', true), 'ref' => Request::int('ref', true), 'overdue' => Request::str('overdue', true) === '1', 'q' => Request::str('q', true)];
        $where = ['i.register = ?'];
        $args = [$k];
        if ($f['status'] !== '' && in_array($f['status'], $def['statuses'], true)) {
            $where[] = 'i.status = ?';
            $args[] = $f['status'];
        }
        if ($f['dept']) {
            $where[] = 'i.department_id = ?';
            $args[] = $f['dept'];
        }
        if ($f['ref']) {
            $where[] = 'i.ref_id = ?';
            $args[] = $f['ref'];
        }
        if ($f['q'] !== '') {
            $where[] = '(i.title LIKE ? OR i.code LIKE ?)';
            $args[] = '%' . Db::like($f['q']) . '%';
            $args[] = '%' . Db::like($f['q']) . '%';
        }
        $rows = array_map([Registers::class, 'decode'], Db::all('SELECT i.*, d.name_en AS dept_en, d.name_ar AS dept_ar, u.full_name AS owner FROM rm_register_items i LEFT JOIN departments d ON d.id = i.department_id
            LEFT JOIN users u ON u.id = i.owner_user_id WHERE ' . implode(' AND ', $where) . ' ORDER BY i.due_date IS NULL, i.due_date, i.id DESC LIMIT 500', $args));
        foreach ($rows as &$r) {
            $r['overdue'] = Registers::overdue($r);
        }
        unset($r);
        if ($f['overdue']) {
            $rows = array_values(array_filter($rows, fn($r) => $r['overdue']));
        }
        return $this->view('registers/index', ['title' => t(sprintf('reg.%s', $k)), 'k' => $k, 'def' => $def, 'rows' => $rows, 'f' => $f, 'departments' => Ref::departments(),
            'refs' => Registers::refOptions($def['ref']), 'maturity' => $k === 'maturity' ? Registers::maturity((int)date('Y')) : []]);
    }

    private function item(int $id): array
    {
        $r = Db::one('SELECT i.*, d.name_en AS dept_en, d.name_ar AS dept_ar, u.full_name AS owner, c.full_name AS creator FROM rm_register_items i LEFT JOIN departments d ON d.id = i.department_id
            LEFT JOIN users u ON u.id = i.owner_user_id JOIN users c ON c.id = i.created_by WHERE i.id = ?', [$id]);
        return $r ? Registers::decode($r) : $this->notFound();
    }

    private function form(string $k, ?array $item): string
    {
        $def = Registers::def($k);
        return $this->view('registers/form', ['title' => t(sprintf('reg.%s', $k)), 'k' => $k, 'def' => $def, 'item' => $item, 'departments' => Ref::departments(true), 'users' => Ref::users(true), 'refs' => Registers::refOptions($def['ref'])]);
    }

    public function create(): string
    {
        return $this->form($this->key(), null);
    }

    public function edit(int $id): string
    {
        $item = $this->item($id);
        return $this->form($item['register'], $item);
    }

    public function show(int $id): string
    {
        $item = $this->item($id);
        $def = Registers::def($item['register']) ?? $this->notFound();
        $item['overdue'] = Registers::overdue($item);
        $refLabel = $item['ref_id'] ? (Registers::refOptions($def['ref'])[(int)$item['ref_id']] ?? '#' . $item['ref_id']) : null;
        $esc = $item['escalation_id'] ? Db::one('SELECT id, code, status FROM rm_escalations WHERE id = ?', [$item['escalation_id']]) : null;
        return $this->view('registers/show', ['title' => $item['code'] . ' — ' . $item['title'], 'item' => $item, 'def' => $def, 'k' => $item['register'], 'refLabel' => $refLabel, 'esc' => $esc]);
    }

    /** Validate the common columns and the register's own fields. @return array{0:array,1:array,2:Validator} */
    private function input(string $k): array
    {
        $def = Registers::def($k);
        $v = new Validator($_POST);
        $v->in('status', 'reg.status', $def['statuses'])->date('due_date', $def['due'] ?? 'reg.due', false);
        if ($def['due'] !== null && in_array($k, ['data', 'security', 'requirements'], true)) {
            $v->required('due_date', $def['due']);
        }
        $d = [];
        foreach ($def['fields'] as $f => $spec) {
            [$type, $req] = $spec;
            $label = sprintf('reg.f_%s', $f);
            $s = $v->str($f);
            if ($req && $s === '') {
                $v->fail($f, 'validation.required', ['field' => t($label)]);
                continue;
            }
            if ($s === '') {
                $d[$f] = null;
                continue;
            }
            match ($type) {
                'select' => in_array($s, $spec[2], true) ? null : $v->fail($f, 'validation.invalid_choice', ['field' => t($label)]),
                'score' => preg_match('/^[1-5]$/', $s) ? null : $v->fail($f, 'validation.int_range', ['field' => t($label), 'min' => 1, 'max' => 5]),
                'int' => preg_match('/^\d{4}$/', $s) && (int)$s >= 2000 && (int)$s <= 2100 ? null : $v->fail($f, 'validation.int_range', ['field' => t($label), 'min' => 2000, 'max' => 2100]),
                'date' => $v->date($f, $label, false),
                default => mb_strlen($s) > ($type === 'textarea' ? 8000 : 250) ? $v->fail($f, 'validation.max_length', ['field' => t($label), 'max' => $type === 'textarea' ? 8000 : 250]) : null,
            };
            $d[$f] = in_array($type, ['score', 'int'], true) ? (int)$s : $s;
        }
        $title = $v->str('title');
        if ($title === '' && $k === 'maturity' && !empty($d['axis']) && !empty($d['year'])) {
            $title = t('enum.rg_axis.' . $d['axis']) . ' — ' . $d['year'];
        }
        if ($title === '') {
            $v->fail('title', 'validation.required', ['field' => t('reg.f_title')]);
        } elseif (mb_strlen($title) > 250) {
            $v->fail('title', 'validation.max_length', ['field' => t('reg.f_title'), 'max' => 250]);
        }
        $dept = Request::int('department_id');
        if ($dept && !isset(Ref::departments()[$dept])) {
            $v->fail('department_id', 'validation.invalid_choice', ['field' => t('reg.department')]);
        }
        $owner = Request::int('owner_user_id');
        if ($owner && !isset(Ref::users(true)[$owner])) {
            $v->fail('owner_user_id', 'validation.invalid_choice', ['field' => t('reg.owner')]);
        }
        $ref = Request::int('ref_id');
        if ($def['ref'] !== null) {
            if ($ref && !isset(Registers::refOptions($def['ref'])[$ref])) {
                $v->fail('ref_id', 'validation.invalid_choice', ['field' => t(sprintf('reg.ref_%s', $def['ref']))]);
            } elseif (!$ref && $def['ref'] === 'circular') {
                $v->fail('ref_id', 'validation.required', ['field' => t('reg.ref_circular')]);
            }
        }
        if ($k === 'data' && !empty($d['received_on']) && $d['received_on'] > date('Y-m-d')) {
            $v->fail('received_on', 'validation.not_future', ['field' => t('reg.f_received_on')]);
        }
        if ($k === 'security' && !empty($d['granted_on']) && $v->str('due_date') !== '' && $v->str('due_date') <= $d['granted_on']) {
            $v->fail('due_date', 'validation.after_date', ['field' => t('reg.f_expiry'), 'other' => t('reg.f_granted_on')]);
        }
        if ($k === 'maturity' && $d['level'] !== null && $d['target'] !== null && $d['target'] < $d['level']) {
            $v->fail('target', 'reg.err_target_below');
        }
        $common = ['title' => $title, 'status' => $v->str('status'), 'department_id' => $dept ?: null, 'owner_user_id' => $owner ?: null, 'due_date' => $v->str('due_date') ?: null, 'ref_id' => $def['ref'] !== null && $ref ? $ref : null];
        return [$common, $d, $v];
    }

    public function store(): never
    {
        $k = $this->key();
        [$common, $d, $v] = $this->input($k);
        if (!$v->ok()) {
            $this->invalid($v->errors(), url('registers/new') . '?r=' . $k);
        }
        $id = Registers::create($k, $common, $d, (int)$this->user()['id']);
        $this->redirect(url('registers/' . $id), 'admin.saved');
    }

    public function update(int $id): never
    {
        $item = $this->item($id);
        $k = $item['register'];
        [$common, $d, $v] = $this->input($k);
        if (!$v->ok()) {
            $this->invalid($v->errors(), url('registers/' . $id . '/edit'));
        }
        $common['status'] = Registers::derive($k, $common['status'], $common['due_date'], $d);
        Db::update('rm_register_items', $common + ['data' => json_encode($d, JSON_UNESCAPED_UNICODE), 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        $this->audit('register.updated', 'register_item', $id, $this->diff($item, $common, ['title', 'status', 'due_date', 'department_id', 'owner_user_id']));
        $this->redirect(url('registers/' . $id), 'admin.saved');
    }

    public function template(): never
    {
        $k = $this->key();
        $year = Request::int('year');
        $back = url('registers') . '?r=' . $k;
        if (!in_array($k, ['maturity', 'committee_plan'], true) || $year < 2000 || $year > 2100) {
            $this->redirect($back, 'reg.err_template', 'error');
        }
        $n = Registers::applyTemplate($k, $year, (int)$this->user()['id']);
        $this->redirect($back, 'reg.template_added', 'success', ['n' => $n]);
    }
}
