<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Db;
use App\Core\Request;
use App\Core\Validator;
use App\Support\Ref;

/** One controller for the simple admin-managed lookup tables (departments, branches, categories, periods). */
final class LookupController extends Controller
{
    private function cfg(): array
    {
        $common = [
            ['name' => 'name_en', 'label' => 'admin.name_en', 'type' => 'text', 'max' => 150, 'required' => true],
            ['name' => 'name_ar', 'label' => 'admin.name_ar', 'type' => 'text', 'max' => 150, 'required' => true, 'dir' => 'rtl'],
        ];
        $code = ['name' => 'code', 'label' => 'admin.code', 'type' => 'code', 'max' => 20, 'required' => true];
        return match ($this->extra) {
            'departments' => ['table' => 'departments', 'title' => 'admin.departments', 'singular' => 'admin.department', 'fields' => array_merge([$code], $common), 'order' => 'code', 'unique' => 'code',
                'used' => ['risks' => 'department_id', 'users' => 'department_id', 'incidents' => 'department_id']],
            'branches' => ['table' => 'branches', 'title' => 'admin.branches', 'singular' => 'admin.branch', 'fields' => array_merge([$code], $common, [['name' => 'city', 'label' => 'admin.city', 'type' => 'text', 'max' => 100, 'required' => false]]), 'order' => 'code', 'unique' => 'code',
                'used' => ['risks' => 'branch_id', 'users' => 'branch_id', 'incidents' => 'branch_id']],
            'categories' => ['table' => 'risk_categories', 'title' => 'admin.categories', 'singular' => 'admin.category', 'fields' => array_merge([$code], $common, [
                ['name' => 'kind', 'label' => 'admin.kind', 'type' => 'select', 'options' => ['conventional', 'islamic'], 'optionLabel' => 'enum.kind.', 'required' => true],
                ['name' => 'sort_order', 'label' => 'admin.sort_order', 'type' => 'int', 'required' => true],
            ]), 'order' => 'sort_order, code', 'unique' => 'code', 'used' => ['risks' => 'category_id', 'incidents' => 'category_id']],
            'periods' => ['table' => 'reporting_periods', 'title' => 'admin.periods', 'singular' => 'admin.period', 'fields' => array_merge($common, [
                ['name' => 'start_date', 'label' => 'admin.start_date', 'type' => 'date', 'required' => true],
                ['name' => 'end_date', 'label' => 'admin.end_date', 'type' => 'date', 'required' => true],
            ]), 'order' => 'start_date DESC', 'unique' => null, 'used' => []],
            default => throw new \App\Core\HttpException(404),
        };
    }

    public function index(): string
    {
        $c = $this->cfg();
        $rows = Db::all('SELECT * FROM `' . $c['table'] . '` ORDER BY ' . $c['order']);
        foreach ($rows as &$r) {
            $n = 0;
            foreach ($c['used'] as $tbl => $col) {
                $n += (int)Db::val("SELECT COUNT(*) FROM `$tbl` WHERE `$col` = ?", [$r['id']]);
            }
            $r['_used'] = $n;
        }
        return $this->view('admin/lookup_index', ['title' => t($c['title']), 'c' => $c, 'rows' => $rows, 'entity' => $this->extra]);
    }

    public function create(): string
    {
        $c = $this->cfg();
        return $this->view('admin/lookup_form', ['title' => t($c['singular']), 'c' => $c, 'row' => ['is_active' => 1, 'kind' => 'conventional', 'sort_order' => 100], 'existing' => null, 'entity' => $this->extra]);
    }

    public function edit(int $id): string
    {
        $c = $this->cfg();
        $row = Db::one('SELECT * FROM `' . $c['table'] . '` WHERE id = ?', [$id]);
        if (!$row) {
            $this->notFound();
        }
        return $this->view('admin/lookup_form', ['title' => t($c['singular']), 'c' => $c, 'row' => $row, 'existing' => $row, 'entity' => $this->extra]);
    }

    public function store(): never
    {
        $this->save(null);
    }

    public function update(int $id): never
    {
        $c = $this->cfg();
        $row = Db::one('SELECT * FROM `' . $c['table'] . '` WHERE id = ?', [$id]);
        if (!$row) {
            $this->notFound();
        }
        $this->save($row);
    }

    private function save(?array $existing): never
    {
        $c = $this->cfg();
        $v = new Validator($_POST);
        $data = [];
        foreach ($c['fields'] as $f) {
            $n = $f['name'];
            $val = $v->str($n);
            switch ($f['type']) {
                case 'code':
                    $val = strtoupper($val);
                    if (!preg_match('/^[A-Z0-9_-]{1,20}$/', $val)) {
                        $v->fail($n, 'admin.err_code');
                    }
                    break;
                case 'text':
                    if (!empty($f['required'])) {
                        $v->required($n, $f['label']);
                    }
                    $v->max($n, $f['label'], $f['max']);
                    break;
                case 'select':
                    $v->in($n, $f['label'], $f['options']);
                    break;
                case 'int':
                    if (!preg_match('/^-?\d{1,6}$/', $val)) {
                        $v->fail($n, 'validation.int_range', ['field' => t($f['label']), 'min' => -99999, 'max' => 999999]);
                    }
                    break;
                case 'date':
                    $v->date($n, $f['label']);
                    break;
            }
            $data[$n] = $f['type'] === 'int' ? (int)$val : ($val === '' ? null : $val);
        }
        if ($c['unique'] && !isset($v->errors()[$c['unique']]) && Db::val('SELECT 1 FROM `' . $c['table'] . '` WHERE `' . $c['unique'] . '` = ? AND id <> ?', [$data[$c['unique']], $existing['id'] ?? 0])) {
            $v->fail($c['unique'], 'admin.err_code_taken');
        }
        if ($this->extra === 'periods' && !$v->errors() && $data['end_date'] < $data['start_date']) {
            $v->fail('end_date', 'validation.after_date', ['field' => t('admin.end_date'), 'other' => t('admin.start_date')]);
        }
        $back = $existing ? url('admin/' . $this->extra . '/' . $existing['id'] . '/edit') : url('admin/' . $this->extra . '/new');
        if (!$v->ok()) {
            $this->invalid($v->errors(), $back);
        }
        $data['is_active'] = !empty($_POST['is_active']) ? 1 : 0;
        $data['updated_at'] = date('Y-m-d H:i:s');
        if ($existing) {
            $ch = $this->diff($existing, $data, array_keys($data));
            unset($ch['updated_at']);
            Db::update($c['table'], $data, 'id = ?', [$existing['id']]);
            $this->audit('org.' . $this->extra . '.updated', $this->extra, (int)$existing['id'], ['changes' => $ch]);
        } else {
            $data['created_at'] = $data['updated_at'];
            $id = Db::insert($c['table'], $data);
            $this->audit('org.' . $this->extra . '.created', $this->extra, $id, ['code' => $data['code'] ?? null]);
        }
        Ref::reset();
        $this->redirect(url('admin/' . $this->extra), 'admin.saved');
    }
}
