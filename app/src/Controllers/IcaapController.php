<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Db;
use App\Core\Request;
use App\Core\Settings;
use App\Core\Validator;
use App\Support\Docx;
use App\Support\Icaap;

/** Internal capital adequacy assessment (ICAAP): the Basel unit's annual cycle, calculations and report. */
final class IcaapController extends Controller
{
    public function index(): string
    {
        $rows = Db::all('SELECT c.*, u.full_name FROM icaap_cycles c JOIN users u ON u.id = c.created_by ORDER BY c.year DESC');
        foreach ($rows as &$r) {
            $x = Icaap::load((int)$r['id']);
            $r['res'] = $x['r'];
        }
        unset($r);
        return $this->view('icaap/index', ['title' => t('icaap.title'), 'rows' => $rows]);
    }

    public function store(): never
    {
        $v = new Validator($_POST);
        $v->intRange('year', 'icaap.year', 2000, 2100)->date('data_date', 'icaap.data_date')->date('regulatory_deadline', 'icaap.deadline', false);
        if ($v->str('year') !== '' && Db::val('SELECT 1 FROM icaap_cycles WHERE year = ?', [(int)$v->str('year')])) {
            $v->fail('year', 'icaap.err_year_exists');
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), url('icaap'));
        }
        $id = Icaap::createCycle((int)$v->str('year'), $v->str('data_date'), $v->str('regulatory_deadline') ?: null, (int)$this->user()['id']);
        $this->redirect(url('icaap/' . $id), 'icaap.created');
    }

    private function cycle(int $id): array
    {
        return Icaap::load($id) ?? $this->notFound();
    }

    private function locked(array $c): bool
    {
        return in_array($c['status'], ['submitted', 'closed'], true);
    }

    private function guard(array $c, string $back): void
    {
        if ($this->locked($c)) {
            $this->redirect($back, 'icaap.err_locked', 'error');
        }
    }

    public function show(int $id): string
    {
        $x = $this->cycle($id);
        $late = Db::all('SELECT * FROM icaap_data_requests WHERE cycle_id = ? AND received_on IS NULL AND due_on IS NOT NULL AND due_on < ? ORDER BY due_on', [$id, date('Y-m-d')]);
        $esc = Db::all("SELECT id, code, status FROM rm_escalations WHERE source_entity = 'icaap_cycle' AND source_id = ? ORDER BY id DESC", [$id]);
        return $this->view('icaap/show', ['title' => t('icaap.title') . ' ' . $x['c']['year'], 'x' => $x, 'alerts' => Icaap::alerts($x), 'late' => $late, 'esc' => $esc, 'tab' => 'overview']);
    }

    /** Phase dates, status and checklist. */
    public function phase(int $id, int $no): never
    {
        $x = $this->cycle($id);
        $back = url('icaap/' . $id) . '#phase' . $no;
        $this->guard($x['c'], $back);
        $d = Icaap::data();
        if (!isset($d['phases'][$no])) {
            $this->notFound();
        }
        $v = new Validator($_POST);
        $v->date('start_date', 'icaap.start', false)->date('end_date', 'icaap.end', false)->in('status', 'pol.status', ['not_started', 'in_progress', 'complete'])->max('note', 'rm.note', 2000);
        if ($v->str('start_date') !== '' && $v->str('end_date') !== '' && $v->str('end_date') < $v->str('start_date')) {
            $v->fail('end_date', 'icaap.err_dates');
        }
        $n = count($d['phases'][$no][5]);
        $checked = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['check'] ?? [])), fn($i) => $i >= 0 && $i < $n)));
        sort($checked);
        if ($v->str('status') === 'complete' && count($checked) < $n) {
            $v->fail('status', 'icaap.err_checklist');
        }
        if ($no === 3 && $v->str('status') === 'complete' && $x['r']['recon'] === 'difference') {
            $v->fail('status', 'icaap.err_recon');
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), $back);
        }
        Db::update('icaap_phases', ['start_date' => $v->str('start_date') ?: null, 'end_date' => $v->str('end_date') ?: null, 'status' => $v->str('status'), 'checklist' => implode(',', $checked) ?: null,
            'note' => $v->str('note') ?: null, 'updated_by' => $this->user()['id'], 'updated_at' => date('Y-m-d H:i:s')], 'cycle_id = ? AND phase_no = ?', [$id, $no]);
        if ($x['c']['status'] === 'preparation' && $v->str('status') !== 'not_started') {
            Db::update('icaap_cycles', ['status' => 'in_progress', 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        }
        $this->audit('icaap.phase_updated', 'icaap_cycle', $id, ['phase' => $no, 'status' => $v->str('status')]);
        $this->redirect($back, 'admin.saved');
    }

    private static function amount(Validator $v, string $f, string $label): ?string
    {
        $s = str_replace([',', ' '], '', $v->str($f));
        if ($s === '') {
            return null;
        }
        if (!preg_match('/^-?\d{1,15}(\.\d{1,3})?$/', $s)) {
            $v->fail($f, 'validation.amount', ['field' => t($label)]);
            return null;
        }
        return $s;
    }

    /** Percent entered as 12.5 → stored as 0.125. */
    private static function pct(Validator $v, string $f, string $label, float $min = 0, float $max = 100): ?string
    {
        $s = str_replace([' ', '%'], '', $v->str($f));
        if ($s === '') {
            return null;
        }
        if (!preg_match('/^-?\d{1,3}(\.\d{1,4})?$/', $s) || (float)$s < $min || (float)$s > $max) {
            $v->fail($f, 'icaap.err_pct', ['field' => t($label)]);
            return null;
        }
        return number_format((float)$s / 100, 6, '.', '');
    }

    public function capital(int $id): string
    {
        $x = $this->cycle($id);
        return $this->view('icaap/capital', ['title' => t('icaap.tab_capital') . ' — ' . $x['c']['year'], 'x' => $x, 'tab' => 'capital']);
    }

    public function saveCapital(int $id): never
    {
        $x = $this->cycle($id);
        $back = url('icaap/' . $id . '/capital');
        $this->guard($x['c'], $back);
        $v = new Validator($_POST);
        $v->date('data_date', 'icaap.data_date')->date('regulatory_deadline', 'icaap.deadline', false)->max('aggregation', 'icaap.aggregation', 250);
        $d = [];
        foreach (['tier1' => 'icaap.tier1', 'tier2' => 'icaap.tier2', 'rwa_credit' => 'icaap.rwa_credit', 'rwa_market' => 'icaap.rwa_market', 'rwa_operational' => 'icaap.rwa_operational'] as $f => $l) {
            $d[$f] = self::amount($v, $f, $l);
        }
        foreach (['reg_min' => 'icaap.reg_min', 'target_ratio' => 'icaap.target', 'warning_ratio' => 'icaap.warning', 'trigger_ratio' => 'icaap.trigger', 'last_reg_ratio' => 'icaap.last_reg_ratio'] as $f => $l) {
            $d[$f] = self::pct($v, $f, $l);
        }
        $d['alpha'] = self::pct($v, 'alpha', 'icaap.alpha');
        // Levels must be ordered: minimum < trigger < warning < target.
        $lv = array_filter([$d['reg_min'], $d['trigger_ratio'], $d['warning_ratio'], $d['target_ratio']], fn($z) => $z !== null);
        $sorted = $lv;
        sort($sorted, SORT_NUMERIC);
        if (array_values($lv) !== array_values($sorted) || count(array_unique($lv)) !== count($lv)) {
            $v->fail('target_ratio', 'icaap.err_levels');
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), $back);
        }
        Db::update('icaap_cycles', $d + ['data_date' => $v->str('data_date'), 'regulatory_deadline' => $v->str('regulatory_deadline') ?: null, 'aggregation' => $v->str('aggregation') ?: null, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        $this->audit('icaap.capital_saved', 'icaap_cycle', $id);
        Icaap::escalateIfNeeded($this->cycle($id), (int)$this->user()['id']);
        $this->redirect($back, 'admin.saved');
    }

    public function risks(int $id): string
    {
        $x = $this->cycle($id);
        return $this->view('icaap/risks', ['title' => t('icaap.tab_risks') . ' — ' . $x['c']['year'], 'x' => $x, 'tab' => 'risks', 'names' => array_column(Icaap::data()['risks'], null, 0)]);
    }

    public function saveRisks(int $id): never
    {
        $x = $this->cycle($id);
        $back = url('icaap/' . $id . '/risks');
        $this->guard($x['c'], $back);
        $v = new Validator($_POST);
        $rows = [];
        foreach ($x['risks'] as $r) {
            $k = $r['risk_key'];
            $imp = trim((string)($_POST['impact'][$k] ?? ''));
            $lik = trim((string)($_POST['likelihood'][$k] ?? ''));
            foreach (['impact' => $imp, 'likelihood' => $lik] as $f => $val) {
                if ($val !== '' && !preg_match('/^[1-5]$/', $val)) {
                    $v->fail("{$f}_$k", 'icaap.err_scale');
                }
            }
            $cap = str_replace([',', ' '], '', trim((string)($_POST['capital'][$k] ?? '')));
            if ($cap !== '' && !preg_match('/^\d{1,15}(\.\d{1,3})?$/', $cap)) {
                $v->fail("capital_$k", 'validation.amount', ['field' => t('icaap.capital')]);
            }
            $just = trim((string)($_POST['justification'][$k] ?? ''));
            [, , $material] = Icaap::materiality($imp !== '' ? (int)$imp : null, $lik !== '' ? (int)$lik : null);
            // A risk is never left without a documented reason; a material one needs a capital method.
            if ($imp !== '' && $lik !== '' && $just === '') {
                $v->fail("justification_$k", 'icaap.err_justification');
            }
            $method = trim((string)($_POST['method'][$k] ?? ''));
            if ($material && $cap !== '' && $method === '') {
                $v->fail("method_$k", 'icaap.err_method');
            }
            $rows[$k] = ['impact' => $imp !== '' ? (int)$imp : null, 'likelihood' => $lik !== '' ? (int)$lik : null, 'justification' => mb_substr($just, 0, 4000) ?: null,
                'change_note' => mb_substr(trim((string)($_POST['change_note'][$k] ?? '')), 0, 500) ?: null, 'method' => mb_substr($method, 0, 4000) ?: null, 'capital' => $cap !== '' ? $cap : null];
        }
        $buffer = self::pct($v, 'mgmt_buffer', 'icaap.mgmt_buffer', 0, 50);
        if (!$v->ok()) {
            $this->invalid($v->errors(), $back);
        }
        Db::tx(function () use ($rows, $id, $buffer, $v) {
            foreach ($rows as $k => $d) {
                Db::update('icaap_risks', $d, 'cycle_id = ? AND risk_key = ?', [$id, $k]);
            }
            Db::update('icaap_cycles', ['mgmt_buffer' => $buffer, 'aggregation' => $v->str('aggregation') ?: null, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        });
        $this->audit('icaap.risks_saved', 'icaap_cycle', $id);
        Icaap::escalateIfNeeded($this->cycle($id), (int)$this->user()['id']);
        $this->redirect($back, 'admin.saved');
    }

    public function stress(int $id): string
    {
        $x = $this->cycle($id);
        return $this->view('icaap/stress', ['title' => t('icaap.tab_stress') . ' — ' . $x['c']['year'], 'x' => $x, 'tab' => 'stress']);
    }

    public function saveStress(int $id): never
    {
        $x = $this->cycle($id);
        $back = url('icaap/' . $id . '/stress');
        $this->guard($x['c'], $back);
        $v = new Validator($_POST);
        $cells = [];
        foreach (Icaap::SCENARIOS as $sc) {
            for ($y = 1; $y <= 3; $y++) {
                $row = [];
                foreach (['profit', 'distributions', 'extra_provisions'] as $f) {
                    $s = str_replace([',', ' '], '', trim((string)($_POST[$f][$sc][$y] ?? '')));
                    if ($s !== '' && !preg_match('/^-?\d{1,15}(\.\d{1,3})?$/', $s)) {
                        $v->fail("{$f}_{$sc}_$y", 'validation.amount', ['field' => t('icaap.' . $f)]);
                    }
                    $row[$f] = $s !== '' ? $s : null;
                }
                $g = str_replace([' ', '%'], '', trim((string)($_POST['rwa_growth'][$sc][$y] ?? '')));
                if ($g !== '' && (!preg_match('/^-?\d{1,3}(\.\d{1,4})?$/', $g) || abs((float)$g) > 100)) {
                    $v->fail("rwa_growth_{$sc}_$y", 'icaap.err_pct', ['field' => t('icaap.rwa_growth')]);
                }
                $row['rwa_growth'] = $g !== '' ? number_format((float)$g / 100, 6, '.', '') : null;
                $cells[$sc][$y] = $row;
            }
        }
        foreach (['s9_base', 's9_moderate', 's9_severe', 's9_specific', 's9_reverse'] as $k) {
            $v->max($k, 'icaap.' . $k, 8000);
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), $back);
        }
        $narr = $x['c']['narr'];
        foreach (['s9_base', 's9_moderate', 's9_severe', 's9_specific', 's9_reverse'] as $k) {
            $narr[$k] = $v->str($k);
        }
        Db::tx(function () use ($cells, $id, $narr) {
            foreach ($cells as $sc => $years) {
                foreach ($years as $y => $row) {
                    Db::update('icaap_stress', $row, 'cycle_id = ? AND scenario = ? AND year_no = ?', [$id, $sc, $y]);
                }
            }
            Db::update('icaap_cycles', ['narratives' => json_encode($narr, JSON_UNESCAPED_UNICODE), 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        });
        $this->audit('icaap.stress_saved', 'icaap_cycle', $id);
        $esc = Icaap::escalateIfNeeded($this->cycle($id), (int)$this->user()['id']);
        $this->redirect($back, $esc ? 'icaap.saved_escalated' : 'admin.saved');
    }

    public function log(int $id): string
    {
        $x = $this->cycle($id);
        return $this->view('icaap/log', ['title' => t('icaap.tab_log') . ' — ' . $x['c']['year'], 'x' => $x, 'tab' => 'log',
            'requests' => Db::all('SELECT * FROM icaap_data_requests WHERE cycle_id = ? ORDER BY id', [$id]), 'assumptions' => Db::all('SELECT * FROM icaap_assumptions WHERE cycle_id = ? ORDER BY id', [$id])]);
    }

    public function addRequest(int $id): never
    {
        $x = $this->cycle($id);
        $back = url('icaap/' . $id . '/log') . '#requests';
        $this->guard($x['c'], $back);
        $v = new Validator($_POST);
        $v->required('provider', 'icaap.provider')->max('provider', 'icaap.provider', 150)->required('data_items', 'icaap.data_items')->max('data_items', 'icaap.data_items', 4000)
            ->date('data_date', 'icaap.data_date', false)->max('format', 'icaap.format', 40)->date('due_on', 'icaap.due_on', false);
        if (!$v->ok()) {
            $this->invalid($v->errors(), $back);
        }
        Db::insert('icaap_data_requests', ['cycle_id' => $id, 'provider' => $v->str('provider'), 'data_items' => $v->str('data_items'), 'data_date' => $v->str('data_date') ?: null, 'format' => $v->str('format') ?: null, 'due_on' => $v->str('due_on') ?: null]);
        $this->audit('icaap.request_added', 'icaap_cycle', $id);
        $this->redirect($back, 'admin.saved');
    }

    public function updateRequest(int $id, int $rid): never
    {
        $x = $this->cycle($id);
        $back = url('icaap/' . $id . '/log') . '#requests';
        $this->guard($x['c'], $back);
        Db::one('SELECT id FROM icaap_data_requests WHERE id = ? AND cycle_id = ?', [$rid, $id]) ?? $this->notFound();
        $v = new Validator($_POST);
        $v->date('due_on', 'icaap.due_on', false)->date('received_on', 'icaap.received_on', false)->max('note', 'rm.note', 500);
        if ($v->str('received_on') > date('Y-m-d')) {
            $v->fail('received_on', 'rm.err_future');
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), $back);
        }
        Db::update('icaap_data_requests', ['due_on' => $v->str('due_on') ?: null, 'received_on' => $v->str('received_on') ?: null, 'note' => $v->str('note') ?: null], 'id = ?', [$rid]);
        $this->redirect($back, 'admin.saved');
    }

    public function addAssumption(int $id): never
    {
        $x = $this->cycle($id);
        $back = url('icaap/' . $id . '/log') . '#assumptions';
        $this->guard($x['c'], $back);
        $v = new Validator($_POST);
        $v->required('stage', 'icaap.stage')->max('stage', 'icaap.stage', 80)->required('assumption', 'icaap.assumption')->max('assumption', 'icaap.assumption', 4000)->max('rationale', 'icaap.rationale', 4000)
            ->max('impact', 'icaap.impact', 250)->max('made_by', 'icaap.made_by', 150)->date('made_on', 'icaap.made_on', false)->max('approved_by', 'icaap.approved_by', 150);
        if (!$v->ok()) {
            $this->invalid($v->errors(), $back);
        }
        Db::insert('icaap_assumptions', ['cycle_id' => $id, 'stage' => $v->str('stage'), 'assumption' => $v->str('assumption'), 'rationale' => $v->str('rationale') ?: null, 'impact' => $v->str('impact') ?: null,
            'made_by' => $v->str('made_by') ?: null, 'made_on' => $v->str('made_on') ?: null, 'approved_by' => $v->str('approved_by') ?: null, 'created_by' => $this->user()['id'], 'created_at' => date('Y-m-d H:i:s')]);
        $this->audit('icaap.assumption_added', 'icaap_cycle', $id);
        $this->redirect($back, 'admin.saved');
    }

    public function report(int $id): string
    {
        $x = $this->cycle($id);
        return $this->view('icaap/report', ['title' => t('icaap.tab_report') . ' — ' . $x['c']['year'], 'x' => $x, 'tab' => 'report', 'alerts' => Icaap::alerts($x)]);
    }

    public function saveReport(int $id): never
    {
        $x = $this->cycle($id);
        $back = url('icaap/' . $id . '/report');
        $this->guard($x['c'], $back);
        $v = new Validator($_POST);
        $narr = $x['c']['narr'];
        foreach (Icaap::data()['narratives'] as $keys) {
            foreach ($keys as $k) {
                if (array_key_exists($k, $_POST)) {
                    $v->max($k, 'icaap.' . $k, 8000);
                    $narr[$k] = $v->str($k);
                }
            }
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), $back);
        }
        Db::update('icaap_cycles', ['narratives' => json_encode($narr, JSON_UNESCAPED_UNICODE), 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        $this->audit('icaap.report_saved', 'icaap_cycle', $id);
        $this->redirect($back, 'admin.saved');
    }

    /** Approval steps: the risk director's declaration, the committee's recommendation, the board's decision, submission to the Central Bank. */
    public function approve(int $id): never
    {
        $u = $this->user();
        $x = $this->cycle($id);
        $c = $x['c'];
        $back = url('icaap/' . $id . '/report') . '#approval';
        $step = Request::str('step');
        $v = new Validator($_POST);
        $now = date('Y-m-d H:i:s');
        if ($step === 'declare') {
            if ($c['declared_at'] || $x['r']['car'] === null) {
                $v->fail('step', 'icaap.err_declare');
            }
            if (!\App\Core\Perm::roleHas($u['role'], 'rm.approve')) {
                $v->fail('step', 'icaap.err_declare_role');
            }
            $data = ['declared_by' => $u['id'], 'declared_at' => $now, 'status' => 'committee'];
        } elseif ($step === 'committee') {
            $v->required('committee_meeting', 'icaap.committee_meeting')->max('committee_meeting', 'icaap.committee_meeting', 40)->date('committee_date', 'icaap.committee_date');
            if (!$c['declared_at']) {
                $v->fail('committee_meeting', 'icaap.err_order');
            }
            $data = ['committee_meeting' => $v->str('committee_meeting'), 'committee_date' => $v->str('committee_date'), 'status' => 'board'];
        } elseif ($step === 'board') {
            $v->required('board_decision', 'icaap.board_decision')->max('board_decision', 'icaap.board_decision', 40)->date('board_date', 'icaap.board_date');
            if (!$c['committee_date']) {
                $v->fail('board_decision', 'icaap.err_order');
            } elseif ($v->str('board_date') !== '' && $v->str('board_date') < $c['committee_date']) {
                $v->fail('board_date', 'icaap.err_board_date');
            }
            $data = ['board_decision' => $v->str('board_decision'), 'board_date' => $v->str('board_date')];
        } elseif ($step === 'submit') {
            $v->date('submitted_on', 'icaap.submitted_on');
            if (!$c['board_date']) {
                $v->fail('submitted_on', 'icaap.err_order');
            }
            $data = ['submitted_on' => $v->str('submitted_on'), 'status' => 'submitted'];
        } elseif ($step === 'close') {
            if ($c['status'] !== 'submitted') {
                $v->fail('step', 'icaap.err_order');
            }
            $data = ['status' => 'closed'];
        } else {
            $this->notFound();
        }
        foreach (['committee_date', 'board_date', 'submitted_on'] as $f) {
            if ($v->str($f) !== '' && $v->str($f) > date('Y-m-d')) {
                $v->fail($f, 'rm.err_future');
            }
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), $back);
        }
        Db::update('icaap_cycles', $data + ['updated_at' => $now], 'id = ?', [$id]);
        $this->audit('icaap.' . $step, 'icaap_cycle', $id);
        $this->redirect($back, 'admin.saved');
    }

    public function print(int $id): string
    {
        $x = $this->cycle($id);
        return $this->view('icaap/print', ['title' => t('icaap.report_title') . ' ' . $x['c']['year'], 'x' => $x, 'names' => array_column(Icaap::data()['risks'], null, 0)], 'layout/print');
    }

    /** The annual ICAAP report in Word, following the bank's report template structure. */
    public function docx(int $id): never
    {
        $x = $this->cycle($id);
        $c = $x['c'];
        $r = $x['r'];
        $n = $c['narr'];
        $ar = locale() === 'ar';
        $names = array_column(Icaap::data()['risks'], null, 0);
        $pct = fn($v) => $v === null ? '—' : number_format($v * 100, 2) . '%';
        $amt = fn($v) => $v === null ? '—' : number_format((float)$v, 0);
        $txt = fn(string $k) => trim((string)($n[$k] ?? '')) !== '' ? (string)$n[$k] : '—';
        $zone = fn($z) => $z ? t('enum.iczone.' . $z) : '—';
        $org = lname(['name_en' => Settings::get('org_name_en'), 'name_ar' => Settings::get('org_name_ar')]);
        $d = new Docx($ar, t('icaap.report_title') . ' ' . $c['year'], $org . ' — ' . t('icaap.report_title'), t('enum.cls.confidential') . ' — ' . t('icaap.year') . ' ' . $c['year']);
        $d->heading(t('icaap.report_title') . ' — ' . $c['year'], 1)->kv([t('icaap.year') => $c['year'], t('icaap.data_date') => $c['data_date'], t('icaap.status') => t('enum.icst.' . $c['status']),
            t('icaap.board_decision') => $c['board_decision'] ? $c['board_decision'] . ' — ' . $c['board_date'] : '—', t('docs.classification') => t('enum.cls.confidential')]);
        $d->heading('2. ' . t('icaap.s2'), 1)->bullets([
            t('icaap.sum_car', ['v' => $pct($r['car']), 'min' => $pct($c['reg_min'] !== null ? (float)$c['reg_min'] : null), 'target' => $pct($c['target_ratio'] !== null ? (float)$c['target_ratio'] : null)]),
            t('icaap.sum_capital', ['a' => $amt($r['available']), 'q' => $amt($r['required']), 'm' => $amt($r['margin'])]),
            t('icaap.sum_stress', ['v' => $pct($r['min']['severe'] ?? null), 'z' => $zone($r['min_zone']['severe'] ?? null)]),
            t('icaap.sum_material', ['l' => implode('، ', array_map(fn($x) => $ar ? $names[$x['risk_key']][2] : $names[$x['risk_key']][3], array_filter($x['risks'], fn($q) => $r['materiality'][$q['risk_key']]['material'] ?? false))) ?: '—']),
        ])->para(t('icaap.s2_conclusion') . ': ' . $txt('s2_conclusion'))->para(t('icaap.s2_actions') . ': ' . $txt('s2_actions'))->para(t('icaap.s2_weaknesses') . ': ' . $txt('s2_weaknesses'));
        foreach ([3 => ['s3_business', 's3_strategy', 's3_environment', 's3_changes'], 4 => ['s4_governance', 's4_appetite', 's4_compliance']] as $sec => $keys) {
            $d->heading($sec . '. ' . t(sprintf('icaap.s%d', $sec)), 1);
            foreach ($keys as $k) {
                $d->heading(t('icaap.' . $k), 3)->para($txt($k));
            }
        }
        $d->para(t('icaap.levels_line', ['t' => $pct($c['target_ratio'] !== null ? (float)$c['target_ratio'] : null), 'w' => $pct($c['warning_ratio'] !== null ? (float)$c['warning_ratio'] : null), 'g' => $pct($c['trigger_ratio'] !== null ? (float)$c['trigger_ratio'] : null)]));
        $d->heading('5. ' . t('icaap.s5'), 1);
        $rows = [];
        foreach ($x['risks'] as $q) {
            $m = $r['materiality'][$q['risk_key']];
            $rows[] = [$ar ? $names[$q['risk_key']][2] : $names[$q['risk_key']][3], $q['impact'] ?? '—', $q['likelihood'] ?? '—', $m['score'] ?? '—', $m['level'] ? t('enum.iclevel.' . $m['level']) : '—', $m['material'] === null ? '—' : ($m['material'] ? t('icaap.material') : t('icaap.not_material')), (string)($q['justification'] ?? '')];
        }
        $d->table([t('icaap.risk'), t('icaap.impact'), t('icaap.likelihood'), t('icaap.score'), t('icaap.level'), t('icaap.result'), t('icaap.justification')], $rows, [3, 1, 1, 1, 1.4, 1.5, 4])->para($txt('s5_results'));
        $d->heading('6. ' . t('icaap.s6'), 1);
        foreach (['s6_elements', 's6_evolution', 's6_distributions', 's6_reserves'] as $k) {
            $d->heading(t('icaap.' . $k), 3)->para($txt($k));
        }
        $d->table([t('icaap.item'), t('icaap.amount')], [[t('icaap.tier1'), $amt($c['tier1'])], [t('icaap.tier2'), $amt($c['tier2'])], [t('icaap.available'), $amt($r['available'])]], [3, 2], ['totalLast' => true]);
        $d->heading('7. ' . t('icaap.s7'), 1)->table([t('icaap.item'), t('icaap.rwa'), t('icaap.requirement')], [
            [t('icaap.rwa_credit'), $amt($c['rwa_credit']), $amt($c['rwa_credit'] !== null && $c['reg_min'] !== null ? (float)$c['rwa_credit'] * (float)$c['reg_min'] : null)],
            [t('icaap.rwa_market'), $amt($c['rwa_market']), $amt($c['rwa_market'] !== null && $c['reg_min'] !== null ? (float)$c['rwa_market'] * (float)$c['reg_min'] : null)],
            [t('icaap.rwa_operational'), $amt($c['rwa_operational']), $amt($c['rwa_operational'] !== null && $c['reg_min'] !== null ? (float)$c['rwa_operational'] * (float)$c['reg_min'] : null)],
            [t('icaap.total'), $amt($r['rwa']), $amt($r['p1'])]], [3, 2, 2], ['totalLast' => true])->para(t('icaap.s7_treatment') . ': ' . $txt('s7_treatment') . ($c['alpha'] !== null ? ' — ' . t('icaap.alpha') . ' ' . $pct((float)$c['alpha']) : ''));
        $d->heading('8. ' . t('icaap.s8'), 1);
        $rows = [];
        foreach ($x['risks'] as $q) {
            if ($r['materiality'][$q['risk_key']]['material'] ?? false) {
                $rows[] = [$ar ? $names[$q['risk_key']][2] : $names[$q['risk_key']][3], (string)($q['method'] ?? '—'), $amt($q['capital'])];
            }
        }
        $rows[] = [t('icaap.p1'), '', $amt($r['p1'])];
        $rows[] = [t('icaap.mgmt_buffer'), $pct($c['mgmt_buffer'] !== null ? (float)$c['mgmt_buffer'] : null), $amt($r['buffer'])];
        $rows[] = [t('icaap.required'), (string)($c['aggregation'] ?? t('icaap.simple_sum')), $amt($r['required'])];
        $d->table([t('icaap.risk'), t('icaap.method'), t('icaap.capital')], $rows, [3, 4, 2], ['totalLast' => true]);
        $d->heading('9. ' . t('icaap.s9'), 1);
        $rows = [];
        foreach (Icaap::SCENARIOS as $sc) {
            foreach ($r['path'][$sc] as $y => $p) {
                $rows[] = [t('enum.icsc.' . $sc), (string)$y, $amt($p['capital']), $amt($p['rwa']), $pct($p['ratio']), $zone($p['zone'])];
            }
        }
        $d->table([t('icaap.scenario'), t('icaap.year_no'), t('icaap.capital'), t('icaap.rwa'), t('icaap.car'), t('icaap.zone')], $rows, [2, 1, 2, 2, 1.4, 2]);
        foreach (['s9_base', 's9_moderate', 's9_severe', 's9_specific', 's9_reverse'] as $k) {
            $d->heading(t('icaap.' . $k), 3)->para($txt($k));
        }
        foreach ([10 => ['s10_plan', 's10_opinion', 's10_gaps'], 11 => ['s11_triggers', 's11_actions', 's11_test'], 12 => ['s12_ilaap'], 13 => ['s13_comparison', 's13_conclusion', 's13_actions'], 14 => ['s14_limitations', 's14_simplified', 's14_plan', 's14_prior'], 15 => ['s15_use']] as $sec => $keys) {
            $d->heading($sec . '. ' . t(sprintf('icaap.s%d', $sec)), 1);
            foreach ($keys as $k) {
                $d->heading(t('icaap.' . $k), 3)->para($txt($k));
            }
        }
        $decl = $c['declared_at'] ? (string)Db::val('SELECT full_name FROM users WHERE id = ?', [$c['declared_by']]) . ' — ' . $c['declared_at'] : '—';
        $d->heading('16. ' . t('icaap.s16'), 1)->para(t('icaap.declaration_text'))->kv([t('icaap.declared') => $decl, t('icaap.committee_meeting') => trim(($c['committee_meeting'] ?? '—') . ' ' . ($c['committee_date'] ?? '')),
            t('icaap.board_decision') => trim(($c['board_decision'] ?? '—') . ' ' . ($c['board_date'] ?? '')), t('icaap.submitted_on') => $c['submitted_on'] ?? '—']);
        $this->audit('icaap.exported', 'icaap_cycle', $id);
        $d->send('ICAAP-' . $c['year'] . '.docx');
    }
}
