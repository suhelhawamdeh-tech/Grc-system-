<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Db;
use App\Core\Money;
use App\Core\Request;
use App\Core\Validator;
use App\Support\Ref;

final class AppetiteController extends Controller
{
    /** Live measurement of a statement against stored data. @return array{value:int,breach:bool,detail:string} value: score, or thousandths */
    public static function measure(array $s): array
    {
        $catSql = '';
        $p = [];
        if ($s['category_id']) {
            $catSql .= ' AND r.category_id = ?';
            $p[] = (int)$s['category_id'];
        }
        if ($s['department_id']) {
            $catSql .= ' AND r.department_id = ?';
            $p[] = (int)$s['department_id'];
        }
        if ($s['metric'] === 'max_residual_score') {
            $limit = (int)Money::fromDb($s['limit_value']) / 1000;
            $row = Db::one("SELECT COALESCE(MAX(r.res_score),0) AS m, SUM(r.res_score > ?) AS n FROM risks r WHERE r.archived_at IS NULL AND r.status = 'active'" . $catSql, array_merge([(int)$limit], $p));
            return ['value' => (int)$row['m'], 'breach' => (int)$row['n'] > 0, 'detail' => (string)(int)$row['n']];
        }
        $today = date('Y-m-d');
        $per = Db::one('SELECT start_date, end_date FROM reporting_periods WHERE is_active = 1 AND start_date <= ? AND end_date >= ? ORDER BY (end_date - start_date) ASC LIMIT 1', [$today, $today]);
        $from = $per['start_date'] ?? date('Y-01-01');
        $to = $per['end_date'] ?? date('Y-12-31');
        $catI = str_replace('r.', 'i.', $catSql);
        $sum = Db::val('SELECT COALESCE(SUM(i.net_loss),0) FROM incidents i WHERE i.archived_at IS NULL AND i.occurrence_date BETWEEN ? AND ?' . $catI, array_merge([$from, $to], $p));
        $v = Money::fromDb($sum);
        return ['value' => $v, 'breach' => $v > Money::fromDb($s['limit_value']), 'detail' => "$from → $to"];
    }

    public function index(): string
    {
        $stmts = Db::all('SELECT s.* FROM appetite_statements s ORDER BY s.is_active DESC, s.code');
        foreach ($stmts as &$s) {
            $s['m'] = self::measure($s);
            $s['open_breaches'] = (int)Db::val("SELECT COUNT(*) FROM appetite_breaches WHERE statement_id = ? AND status IN ('open','escalated')", [$s['id']]);
        }
        $status = Request::str('status', true);
        $bw = in_array($status, ['open', 'escalated', 'accepted', 'resolved'], true) ? 'b.status = ?' : "b.status IN ('open','escalated')";
        $breaches = Db::all('SELECT b.*, s.code AS s_code, s.title AS s_title, o.full_name AS owner_name FROM appetite_breaches b JOIN appetite_statements s ON s.id = b.statement_id LEFT JOIN users o ON o.id = b.owner_user_id WHERE ' . $bw . ' ORDER BY b.id DESC LIMIT 100', in_array($status, ['open', 'escalated', 'accepted', 'resolved'], true) ? [$status] : []);
        return $this->view('appetite/index', ['title' => t('appetite.title'), 'stmts' => $stmts, 'breaches' => $breaches, 'status' => $status, 'users' => Ref::users(true)]);
    }

    public function create(): string
    {
        return $this->view('appetite/form', ['title' => t('appetite.new'), 's' => ['metric' => 'max_residual_score', 'is_active' => 1], 'existing' => null, 'lists' => $this->lists()]);
    }

    public function edit(int $id): string
    {
        $s = Db::one('SELECT * FROM appetite_statements WHERE id = ?', [$id]) ?? $this->notFound();
        $s['limit_value'] = $s['metric'] === 'max_residual_score' ? (string)(int)(Money::fromDb($s['limit_value']) / 1000) : Money::plain($s['limit_value']);
        return $this->view('appetite/form', ['title' => t('appetite.edit'), 's' => $s, 'existing' => $s, 'lists' => $this->lists()]);
    }

    private function lists(): array
    {
        return ['categories' => Ref::categories(true), 'departments' => Ref::departments(true)];
    }

    public function store(): never
    {
        $this->save(null);
    }

    public function update(int $id): never
    {
        $this->save(Db::one('SELECT * FROM appetite_statements WHERE id = ?', [$id]) ?? $this->notFound());
    }

    private function save(?array $ex): never
    {
        $v = new Validator($_POST);
        $v->required('title', 'appetite.name')->max('title', 'appetite.name', 250)->required('statement', 'appetite.statement')->max('statement', 'appetite.statement', 5000)
            ->in('metric', 'appetite.metric', ['max_residual_score', 'max_net_loss'])->max('approved_by', 'appetite.approved_by', 150)->date('approved_on', 'appetite.approved_on', false);
        $cat = Request::int('category_id');
        $dept = Request::int('department_id');
        if ($cat !== null && !isset(Ref::categories()[$cat])) {
            $v->fail('category_id', 'validation.invalid_choice', ['field' => t('appetite.category')]);
        }
        if ($dept !== null && !isset(Ref::departments()[$dept])) {
            $v->fail('department_id', 'validation.invalid_choice', ['field' => t('appetite.department')]);
        }
        $limit = null;
        if ($v->str('metric') === 'max_residual_score') {
            if (!preg_match('/^\d{1,2}$/', $v->str('limit_value')) || (int)$v->str('limit_value') < 1 || (int)$v->str('limit_value') > 25) {
                $v->fail('limit_value', 'validation.int_range', ['field' => t('appetite.limit'), 'min' => 1, 'max' => 25]);
            } else {
                $limit = (int)$v->str('limit_value') * 1000;
            }
        } else {
            $limit = Money::parse($v->str('limit_value'), Money::decimals());
            if ($v->str('limit_value') === '' || $limit === null) {
                $v->fail('limit_value', 'validation.amount', ['field' => t('appetite.limit')]);
            }
        }
        $back = $ex ? url('appetite/' . $ex['id'] . '/edit') : url('appetite/new');
        if (!$v->ok()) {
            $this->invalid($v->errors(), $back);
        }
        $data = ['title' => $v->str('title'), 'statement' => $v->str('statement'), 'metric' => $v->str('metric'), 'limit_value' => Money::toDb($limit), 'category_id' => $cat, 'department_id' => $dept,
            'approved_by' => $v->str('approved_by') ?: null, 'approved_on' => $v->str('approved_on') ?: null, 'is_active' => $ex ? (!empty($_POST['is_active']) ? 1 : 0) : 1, 'updated_at' => date('Y-m-d H:i:s')];
        if ($ex) {
            Db::update('appetite_statements', $data, 'id = ?', [$ex['id']]);
            $this->audit('appetite.updated', 'appetite', (int)$ex['id'], ['changes' => $this->diff($ex, $data, ['title', 'metric', 'limit_value', 'category_id', 'department_id', 'is_active'])]);
        } else {
            $id = Db::insert('appetite_statements', $data + ['created_by' => $this->user()['id'], 'created_at' => $data['updated_at']]);
            Db::update('appetite_statements', ['code' => sprintf('RAS-%03d', $id)], 'id = ?', [$id]);
            $this->audit('appetite.created', 'appetite', $id);
        }
        $this->redirect(url('appetite'), 'admin.saved');
    }

    /** Detect breaches now and open a tracked breach record when none is open for that statement. */
    public function evaluate(): never
    {
        $u = $this->user();
        $n = 0;
        foreach (Db::all('SELECT * FROM appetite_statements WHERE is_active = 1') as $s) {
            $m = self::measure($s);
            if (!$m['breach']) {
                continue;
            }
            if (Db::val("SELECT 1 FROM appetite_breaches WHERE statement_id = ? AND status IN ('open','escalated')", [$s['id']])) {
                continue;
            }
            $now = date('Y-m-d H:i:s');
            $bid = Db::insert('appetite_breaches', ['statement_id' => $s['id'], 'detected_on' => date('Y-m-d'), 'measured_value' => Money::toDb($s['metric'] === 'max_residual_score' ? $m['value'] * 1000 : $m['value']),
                'description' => t('appetite.auto_description', ['metric' => t('enum.metric.' . $s['metric']), 'detail' => $m['detail']]), 'source' => 'auto', 'status' => 'open', 'created_by' => $u['id'], 'created_at' => $now, 'updated_at' => $now]);
            $this->audit('appetite.breach_detected', 'appetite', (int)$s['id'], ['breach_id' => $bid]);
            $n++;
        }
        $this->redirect(url('appetite'), 'appetite.evaluated', 'success', ['n' => $n]);
    }

    public function addBreach(int $id): never
    {
        $u = $this->user();
        Db::one('SELECT id FROM appetite_statements WHERE id = ?', [$id]) ?? $this->notFound();
        $v = new Validator($_POST);
        $v->required('description', 'appetite.breach_description')->max('description', 'appetite.breach_description', 3000)->date('detected_on', 'appetite.detected_on');
        if (!$v->ok()) {
            $this->invalid($v->errors(), url('appetite'));
        }
        $now = date('Y-m-d H:i:s');
        $bid = Db::insert('appetite_breaches', ['statement_id' => $id, 'detected_on' => $v->str('detected_on'), 'description' => $v->str('description'), 'source' => 'manual', 'status' => 'open', 'owner_user_id' => Request::int('owner_user_id'), 'created_by' => $u['id'], 'created_at' => $now, 'updated_at' => $now]);
        $this->audit('appetite.breach_recorded', 'appetite', $id, ['breach_id' => $bid]);
        $this->redirect(url('appetite'), 'admin.saved');
    }

    public function updateBreach(int $id): never
    {
        $b = Db::one('SELECT * FROM appetite_breaches WHERE id = ?', [$id]) ?? $this->notFound();
        $v = new Validator($_POST);
        $v->in('status', 'appetite.breach_status', ['open', 'escalated', 'accepted', 'resolved'])->max('resolution_note', 'appetite.resolution_note', 3000);
        if (in_array($v->str('status'), ['accepted', 'resolved'], true) && $v->str('resolution_note') === '') {
            $v->fail('resolution_note', 'appetite.err_note_required');
        }
        $owner = Request::int('owner_user_id');
        if ($owner !== null && !isset(Ref::users()[$owner])) {
            $v->fail('owner_user_id', 'validation.invalid_choice', ['field' => t('appetite.owner')]);
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), url('appetite'));
        }
        $done = in_array($v->str('status'), ['accepted', 'resolved'], true);
        Db::update('appetite_breaches', ['status' => $v->str('status'), 'owner_user_id' => $owner, 'resolution_note' => $v->str('resolution_note') ?: null, 'resolved_on' => $done ? ($b['resolved_on'] ?? date('Y-m-d')) : null, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        $this->audit('appetite.breach_updated', 'appetite', (int)$b['statement_id'], ['breach_id' => $id, 'status' => $v->str('status')]);
        $this->redirect(url('appetite'), 'admin.saved');
    }
}
