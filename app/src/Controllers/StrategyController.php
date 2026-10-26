<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Db;
use App\Core\Money;
use App\Core\Request;
use App\Core\Validator;
use App\Support\Objective;
use App\Support\Ref;

/** Strategic objectives, balanced scorecard and the risks that threaten each objective. */
final class StrategyController extends Controller
{
    public const PERSPECTIVES = ['financial', 'customer', 'internal', 'learning'];

    private function load(int $id): array
    {
        $o = Db::one('SELECT o.*, u.full_name AS owner_name FROM strategic_objectives o JOIN users u ON u.id = o.owner_user_id WHERE o.id = ?', [$id]);
        $o || $this->notFound();
        return Objective::row($o);
    }

    public function index(): string
    {
        $rows = array_map([Objective::class, 'row'], Db::all('SELECT o.*, u.full_name AS owner_name, (SELECT COUNT(*) FROM objective_risks r WHERE r.objective_id = o.id) AS risk_n FROM strategic_objectives o JOIN users u ON u.id = o.owner_user_id WHERE o.archived_at IS NULL ORDER BY o.code'));
        $by = array_fill_keys(self::PERSPECTIVES, []);
        foreach ($rows as $r) {
            $by[$r['perspective']][] = $r;
        }
        return $this->view('strategy/index', ['title' => t('strat.title'), 'by' => $by, 'total' => count($rows), 'red' => count(array_filter($rows, fn($r) => $r['rag'] === 'red')), 'late' => count(array_filter($rows, fn($r) => $r['late']))]);
    }

    public function show(int $id): string
    {
        $o = $this->load($id);
        $risks = Db::all('SELECT r.id, r.risk_code, r.title, r.res_score FROM objective_risks x JOIN risks r ON r.id = x.risk_id WHERE x.objective_id = ? AND r.archived_at IS NULL ORDER BY r.res_score DESC', [$id]);
        $hist = Db::all('SELECT h.*, u.full_name FROM objective_updates h JOIN users u ON u.id = h.updated_by WHERE h.objective_id = ? ORDER BY h.id DESC LIMIT 30', [$id]);
        $linkable = can('strategy.manage') ? Db::all('SELECT id, risk_code, title FROM risks WHERE archived_at IS NULL AND id NOT IN (SELECT risk_id FROM objective_risks WHERE objective_id = ?) ORDER BY risk_code LIMIT 500', [$id]) : [];
        return $this->view('strategy/show', ['title' => $o['code'] . ' — ' . $o['title'], 'o' => $o, 'risks' => $risks, 'hist' => $hist, 'linkable' => $linkable]);
    }

    public function create(): string
    {
        return $this->view('strategy/form', ['title' => t('strat.new'), 'o' => ['perspective' => 'financial', 'direction' => 'higher_better', 'due_date' => date('Y-m-d', strtotime('+1 year'))], 'existing' => null, 'users' => Ref::users(true)]);
    }

    public function edit(int $id): string
    {
        $o = $this->load($id);
        foreach (['baseline', 'target', 'current_value'] as $k) {
            $o[$k] = Money::plain($o[$k]);
        }
        return $this->view('strategy/form', ['title' => t('strat.edit'), 'o' => $o, 'existing' => $o, 'users' => Ref::users(true)]);
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
        $v->required('title', 'strat.o_title')->max('title', 'strat.o_title', 250)->max('description', 'strat.o_description', 3000)->max('unit', 'strat.unit', 30)->in('perspective', 'strat.perspective', self::PERSPECTIVES)->in('direction', 'strat.direction', ['higher_better', 'lower_better'])->date('due_date', 'strat.due_date');
        $owner = Request::int('owner_user_id');
        if ($owner === null || !isset(Ref::users(true)[$owner]) && $owner !== ($ex['owner_user_id'] ?? null)) {
            $v->fail('owner_user_id', 'validation.invalid_choice', ['field' => t('strat.owner')]);
        }
        $num = [];
        foreach (['baseline', 'target', 'current_value'] as $k) {
            $raw = $v->str($k);
            if ($k === 'current_value' && $raw === '' && !$ex) {
                $raw = $v->str('baseline');
            }
            $num[$k] = Money::parse($raw, Money::decimals());
            if ($raw === '' || $num[$k] === null) {
                $v->fail($k, 'validation.amount', ['field' => t('strat.' . $k)]);
            }
        }
        if ($num['baseline'] !== null && $num['target'] !== null && !Objective::valid($num['baseline'], $num['target'], $v->str('direction'))) {
            $v->fail('target', 'strat.err_target');
        }
        $back = $ex ? url('strategy/' . $ex['id'] . '/edit') : url('strategy/new');
        if (!$v->ok()) {
            $this->invalid($v->errors(), $back);
        }
        $now = date('Y-m-d H:i:s');
        $d = ['title' => $v->str('title'), 'description' => $v->str('description') ?: null, 'perspective' => $v->str('perspective'), 'owner_user_id' => $owner, 'unit' => $v->str('unit'), 'direction' => $v->str('direction'),
            'baseline' => Money::toDb($num['baseline']), 'target' => Money::toDb($num['target']), 'current_value' => Money::toDb($num['current_value']), 'due_date' => $v->str('due_date'), 'updated_at' => $now];
        if ($ex) {
            Db::update('strategic_objectives', $d, 'id = ?', [$ex['id']]);
            $this->audit('strategy.updated', 'objective', (int)$ex['id'], ['changes' => $this->diff($ex, $d, ['title', 'perspective', 'owner_user_id', 'direction', 'baseline', 'target', 'due_date'])]);
            $id = (int)$ex['id'];
        } else {
            $id = Db::insert('strategic_objectives', $d + ['created_by' => $u['id'], 'created_at' => $now]);
            Db::update('strategic_objectives', ['code' => sprintf('OBJ-%04d', $id)], 'id = ?', [$id]);
            $this->audit('strategy.created', 'objective', $id, ['title' => $d['title']]);
        }
        $this->redirect(url('strategy/' . $id), 'admin.saved');
    }

    /** Record a new measurement of the objective. */
    public function progress(int $id): never
    {
        $u = $this->user();
        $o = $this->load($id);
        $v = new Validator($_POST);
        $v->max('note', 'strat.note', 500);
        $val = Money::parse($v->str('value'), Money::decimals());
        if ($v->str('value') === '' || $val === null) {
            $v->fail('value', 'validation.amount', ['field' => t('strat.value')]);
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), url('strategy/' . $id) . '#update');
        }
        $now = date('Y-m-d H:i:s');
        Db::tx(function () use ($id, $val, $v, $u, $now) {
            Db::insert('objective_updates', ['objective_id' => $id, 'value' => Money::toDb($val), 'note' => $v->str('note') ?: null, 'updated_by' => $u['id'], 'created_at' => $now]);
            Db::update('strategic_objectives', ['current_value' => Money::toDb($val), 'updated_at' => $now], 'id = ?', [$id]);
        });
        $this->audit('strategy.progress', 'objective', $id, ['value' => Money::toDb($val)]);
        $this->redirect(url('strategy/' . $id), 'strat.progress_saved');
    }

    public function link(int $id): never
    {
        $this->load($id);
        $rid = Request::int('risk_id');
        if ($rid === null || !Db::val('SELECT 1 FROM risks WHERE id = ? AND archived_at IS NULL', [$rid])) {
            $this->redirect(url('strategy/' . $id), 'validation.invalid_choice', 'error', ['field' => t('ctl.risk')]);
        }
        Db::exec('INSERT IGNORE INTO objective_risks (objective_id, risk_id) VALUES (?, ?)', [$id, $rid]);
        $this->audit('strategy.risk_linked', 'objective', $id, ['risk_id' => $rid]);
        $this->redirect(url('strategy/' . $id) . '#risks', 'ctl.linked');
    }

    public function unlink(int $id, int $rid): never
    {
        $this->load($id);
        Db::exec('DELETE FROM objective_risks WHERE objective_id = ? AND risk_id = ?', [$id, $rid]);
        $this->audit('strategy.risk_unlinked', 'objective', $id, ['risk_id' => $rid]);
        $this->redirect(url('strategy/' . $id) . '#risks', 'ctl.unlink_done');
    }

    public function archive(int $id): never
    {
        $o = $this->load($id);
        Db::update('strategic_objectives', ['archived_at' => $o['archived_at'] === null ? date('Y-m-d H:i:s') : null], 'id = ?', [$id]);
        $this->audit($o['archived_at'] === null ? 'strategy.archived' : 'strategy.restored', 'objective', $id);
        $this->redirect(url('strategy'), 'admin.saved');
    }
}
