<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Db;
use App\Core\Request;
use App\Core\Settings;
use App\Core\Validator;
use App\Support\BoardReport;
use App\Support\Docx;
use App\Support\Ref;

/** Quarterly board risk report: built from the model structure, approved by the board, followed up as actions. */
final class BoardReportController extends Controller
{
    /** Editable fields of each row type: field => [input type, label key, required]. */
    public const FIELDS = [
        'tile' => ['title' => ['text', 'brr.f_label', true], 'detail' => ['text', 'brr.f_value', true], 'extra' => ['text', 'brr.f_note', false], 'level' => ['tilelevel', 'brr.f_colour', false]],
        'profile' => ['title' => ['text', 'brr.f_domain', true], 'level' => ['level', 'brr.f_level', true], 'trend' => ['trend', 'brr.f_trend', false], 'detail' => ['textarea', 'brr.f_drivers', false], 'refs' => ['text', 'brr.f_decisions', false]],
        'message' => ['title' => ['text', 'brr.f_topic', true], 'detail' => ['textarea', 'brr.f_message', true]],
        'source' => ['title' => ['text', 'brr.f_source', true], 'detail' => ['text', 'brr.f_prepared_by', false], 'extra' => ['text', 'brr.f_date_ref', false], 'note' => ['text', 'brr.f_used_for', false]],
        'reference' => ['title' => ['text', 'brr.f_reference', true], 'detail' => ['reftype', 'brr.f_ref_type', false], 'extra' => ['text', 'brr.f_ref_status', false]],
        'observation' => ['code' => ['text', 'brr.f_code', false], 'title' => ['textarea', 'brr.f_observation', true], 'detail' => ['textarea', 'brr.f_evidence', false], 'extra' => ['text', 'brr.f_impact', false], 'level' => ['level', 'brr.f_level', false]],
        'dq' => ['title' => ['textarea', 'brr.f_observation', true], 'detail' => ['text', 'brr.f_source', false], 'extra' => ['textarea', 'brr.f_treatment', false]],
        'decision' => ['code' => ['text', 'brr.f_code', true], 'title' => ['textarea', 'brr.f_decision', true], 'level' => ['dtype', 'brr.f_dtype', true], 'detail' => ['text', 'brr.f_owner', false], 'extra' => ['text', 'brr.f_duration', false], 'days' => ['number', 'brr.f_days', false]],
        'recommendation' => ['department_id' => ['dept', 'brr.f_department', true], 'title' => ['textarea', 'brr.f_action', true], 'detail' => ['text', 'brr.f_output', false], 'extra' => ['text', 'brr.f_duration', false], 'days' => ['number', 'brr.f_days', false],
            'priority' => ['priority', 'brr.f_priority', true], 'refs' => ['text', 'brr.f_decisions', false]],
    ];
    public const REF_TYPES = ['internal', 'regulatory', 'international', 'sharia'];

    public function index(): string
    {
        $rows = Db::all('SELECT b.*, (SELECT COUNT(*) FROM board_report_rows x WHERE x.report_id = b.id AND x.kind = \'recommendation\') AS n_rec FROM board_reports b ORDER BY b.year DESC, b.quarter DESC');
        $u = $this->user();
        return $this->view('board_reports/index', ['title' => t('brr.title'), 'rows' => $rows, 'me' => $u]);
    }

    public function store(): never
    {
        $v = new Validator($_POST);
        $v->intRange('year', 'icaap.year', 2000, 2100)->intRange('quarter', 'brr.quarter', 1, 4)->max('signatory_name', 'brr.signatory_name', 150)->max('signatory_title', 'brr.signatory_title', 150);
        if ($v->ok() && Db::val('SELECT 1 FROM board_reports WHERE year = ? AND quarter = ?', [(int)$v->str('year'), (int)$v->str('quarter')])) {
            $v->fail('quarter', 'brr.err_exists');
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), url('board-reports'));
        }
        $u = $this->user();
        $id = BoardReport::create((int)$v->str('year'), (int)$v->str('quarter'), (int)$u['id'], $v->str('signatory_name') ?: $u['full_name'], $v->str('signatory_title') ?: t('rm.director'));
        $this->redirect(url('board-reports/' . $id), 'brr.created');
    }

    private function report(int $id): array
    {
        return BoardReport::load($id) ?? $this->notFound();
    }

    private function editable(array $r, string $back): void
    {
        if ($r['status'] === 'approved') {
            $this->redirect($back, 'brr.err_locked', 'error');
        }
    }

    public function show(int $id): string
    {
        $x = $this->report($id);
        $r = $x['r'];
        return $this->view('board_reports/show', ['title' => $r['code'], 'x' => $x, 'departments' => Ref::departments(true), 'priority' => BoardReport::priorityRisks(), 'events' => BoardReport::events($r['period_start'], $r['period_end']),
            'followup' => $r['actions_created'] ? BoardReport::followUp($id) : []]);
    }

    public function update(int $id): never
    {
        $x = $this->report($id);
        $anchor = preg_match('/^[a-z0-9_-]{1,30}$/', Request::str('anchor')) ? '#' . Request::str('anchor') : '';
        $back = url('board-reports/' . $id) . $anchor;
        $this->editable($x['r'], $back);
        $v = new Validator($_POST);
        $meta = array_key_exists('report_date', $_POST);
        if ($meta) {
            $v->max('ref_no', 'brr.ref_no', 60)->date('report_date', 'brr.report_date')->in('classification', 'docs.classification', ['internal', 'confidential', 'secret'])->max('signatory_name', 'brr.signatory_name', 150)->max('signatory_title', 'brr.signatory_title', 150);
        }
        $narr = $x['r']['narr'];
        foreach (array_keys(BoardReport::template()['narratives']) as $k) {
            if (array_key_exists($k, $_POST)) {
                $v->max($k, 'brr.' . $k, 12000);
                $narr[$k] = $v->str($k);
            }
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), $back);
        }
        $data = ['narratives' => json_encode($narr, JSON_UNESCAPED_UNICODE), 'updated_at' => date('Y-m-d H:i:s')];
        if ($meta) {
            $data += ['ref_no' => $v->str('ref_no') ?: null, 'report_date' => $v->str('report_date'), 'classification' => $v->str('classification'), 'signatory_name' => $v->str('signatory_name') ?: null, 'signatory_title' => $v->str('signatory_title') ?: null];
        }
        Db::update('board_reports', $data, 'id = ?', [$id]);
        $this->audit('board_report.updated', 'board_report', $id);
        $this->redirect($back, 'admin.saved');
    }

    /** Validate the posted fields of a row of the given kind. @return array{0:array,1:Validator} */
    private function rowData(string $kind): array
    {
        $v = new Validator($_POST);
        $data = [];
        foreach (self::FIELDS[$kind] as $f => [$type, $label, $req]) {
            $s = $v->str($f);
            if ($req && $s === '') {
                $v->fail($f, 'validation.required', ['field' => t($label)]);
                continue;
            }
            $ok = match ($type) {
                'level' => $s === '' || in_array($s, BoardReport::LEVELS, true),
                'trend' => $s === '' || in_array($s, BoardReport::TRENDS, true),
                'dtype' => $s === '' || in_array($s, BoardReport::DECISION_TYPES, true),
                'priority' => $s === '' || in_array($s, BoardReport::template()['priorities'], true),
                'tilelevel' => $s === '' || in_array($s, ['ok', 'warn', 'danger'], true),
                'reftype' => $s === '' || in_array($s, self::REF_TYPES, true),
                'number' => $s === '' || (preg_match('/^\d{1,4}$/', $s) && (int)$s <= 3650),
                'dept' => $s === '' || isset(Ref::departments()[(int)$s]),
                default => mb_strlen($s) <= ($type === 'textarea' ? 6000 : 250),
            };
            if (!$ok) {
                $v->fail($f, 'validation.invalid_choice', ['field' => t($label)]);
            }
            $data[$f] = $s === '' ? null : ($type === 'number' || $type === 'dept' ? (int)$s : $s);
        }
        if (isset($data['code']) && $data['code'] !== null && mb_strlen((string)$data['code']) > 20) {
            $v->fail('code', 'validation.max_length', ['field' => t('brr.f_code'), 'max' => 20]);
        }
        $data['title'] ??= '';
        return [$data, $v];
    }

    public function addRow(int $id): never
    {
        $x = $this->report($id);
        $kind = Request::str('kind');
        if (!isset(self::FIELDS[$kind])) {
            $this->notFound();
        }
        $back = url('board-reports/' . $id) . '#k-' . $kind;
        $this->editable($x['r'], $back);
        [$data, $v] = $this->rowData($kind);
        if (!$v->ok()) {
            $this->invalid($v->errors(), $back);
        }
        $n = (int)Db::val('SELECT COALESCE(MAX(sort_order),0) FROM board_report_rows WHERE report_id = ? AND kind = ?', [$id, $kind]);
        Db::insert('board_report_rows', $data + ['report_id' => $id, 'kind' => $kind, 'sort_order' => $n + 1]);
        $this->redirect($back, 'admin.saved');
    }

    private function row(int $id, int $rid): array
    {
        return Db::one('SELECT * FROM board_report_rows WHERE id = ? AND report_id = ?', [$rid, $id]) ?? $this->notFound();
    }

    public function updateRow(int $id, int $rid): never
    {
        $x = $this->report($id);
        $row = $this->row($id, $rid);
        $back = url('board-reports/' . $id) . '#k-' . $row['kind'];
        $this->editable($x['r'], $back);
        [$data, $v] = $this->rowData($row['kind']);
        if (!$v->ok()) {
            $this->invalid($v->errors(), $back);
        }
        Db::update('board_report_rows', $data, 'id = ?', [$rid]);
        $this->redirect($back, 'admin.saved');
    }

    public function deleteRow(int $id, int $rid): never
    {
        $x = $this->report($id);
        $row = $this->row($id, $rid);
        $back = url('board-reports/' . $id) . '#k-' . $row['kind'];
        $this->editable($x['r'], $back);
        Db::exec('DELETE FROM board_report_rows WHERE id = ?', [$rid]);
        $this->redirect($back, 'admin.saved');
    }

    public function suggestTiles(int $id): never
    {
        $x = $this->report($id);
        $back = url('board-reports/' . $id) . '#k-tile';
        $this->editable($x['r'], $back);
        $n = BoardReport::suggestTiles($id);
        $this->redirect($back, 'brr.tiles_added', 'success', ['n' => $n]);
    }

    /** Add the bank's registered framework documents (levels 1-4) as internal references of section 2. */
    public function addFrameworkRefs(int $id): never
    {
        $x = $this->report($id);
        $back = url('board-reports/' . $id) . '#k-reference';
        $this->editable($x['r'], $back);
        $have = array_flip(array_map(fn($r) => mb_strtolower($r['title']), $x['rows']['reference'] ?? []));
        $n = (int)Db::val("SELECT COALESCE(MAX(sort_order),0) FROM board_report_rows WHERE report_id = ? AND kind = 'reference'", [$id]);
        $added = 0;
        foreach (Db::all("SELECT p.code, p.title, p.status, v.version_label FROM policies p LEFT JOIN policy_versions v ON v.policy_id = p.id AND v.is_current = 1 WHERE p.status <> 'retired' AND p.doc_level BETWEEN 1 AND 4 ORDER BY p.doc_level, p.code") as $p) {
            $title = $p['title'] . ' (' . $p['code'] . ')';
            if (isset($have[mb_strtolower($title)])) {
                continue;
            }
            Db::insert('board_report_rows', ['report_id' => $id, 'kind' => 'reference', 'sort_order' => ++$n, 'title' => $title, 'detail' => 'internal', 'extra' => t('enum.pstatus.' . $p['status']) . ($p['version_label'] ? ' — v' . $p['version_label'] : '')]);
            $added++;
        }
        $this->redirect($back, 'brr.refs_added', 'success', ['n' => $added]);
    }

    /** Draft → risk committee → board → approved (decisions and recommendations become tracked actions). */
    public function status(int $id): never
    {
        $u = $this->user();
        $x = $this->report($id);
        $r = $x['r'];
        $back = url('board-reports/' . $id) . '#approval';
        $step = Request::str('step');
        $v = new Validator($_POST);
        $now = date('Y-m-d H:i:s');
        if ($step === 'submit') {
            if ($r['status'] !== 'draft') {
                $v->fail('step', 'icaap.err_order');
            }
            if (empty($x['rows']['decision']) || empty($x['rows']['profile'])) {
                $v->fail('step', 'brr.err_incomplete');
            }
            $data = ['status' => 'committee'];
        } elseif ($step === 'committee') {
            $v->required('committee_meeting', 'icaap.committee_meeting')->max('committee_meeting', 'icaap.committee_meeting', 40)->date('committee_date', 'icaap.committee_date');
            if ($r['status'] !== 'committee') {
                $v->fail('committee_meeting', 'icaap.err_order');
            }
            $data = ['committee_meeting' => $v->str('committee_meeting'), 'committee_date' => $v->str('committee_date'), 'status' => 'board'];
        } elseif ($step === 'board') {
            $v->required('board_decision', 'icaap.board_decision')->max('board_decision', 'icaap.board_decision', 40)->date('board_date', 'icaap.board_date');
            if ($r['status'] !== 'board') {
                $v->fail('board_decision', 'icaap.err_order');
            } elseif ($v->str('board_date') !== '' && $v->str('board_date') < (string)$r['committee_date']) {
                $v->fail('board_date', 'icaap.err_board_date');
            }
            if (!\App\Core\Perm::roleHas($u['role'], 'rm.approve')) {
                $v->fail('board_decision', 'icaap.err_declare_role');
            }
            $data = ['board_decision' => $v->str('board_decision'), 'board_date' => $v->str('board_date'), 'status' => 'approved'];
        } elseif ($step === 'reopen') {
            if ($r['status'] === 'approved' || $r['status'] === 'draft') {
                $v->fail('step', 'icaap.err_order');
            }
            $data = ['status' => 'draft', 'committee_meeting' => null, 'committee_date' => null];
        } else {
            $this->notFound();
        }
        foreach (['committee_date', 'board_date'] as $f) {
            if ($v->str($f) !== '' && $v->str($f) > date('Y-m-d')) {
                $v->fail($f, 'rm.err_future');
            }
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), $back);
        }
        Db::update('board_reports', $data + ['updated_at' => $now], 'id = ?', [$id]);
        $this->audit('board_report.' . $step, 'board_report', $id);
        if ($step === 'board') {
            $f = BoardReport::createFollowUp($id, (int)$u['id']);
            $this->redirect($back, 'brr.approved', 'success', ['d' => $f['decisions'], 'a' => $f['actions']]);
        }
        $this->redirect($back, 'admin.saved');
    }

    public function print(int $id): string
    {
        $x = $this->report($id);
        $r = $x['r'];
        return $this->view('board_reports/print', ['title' => t('brr.report_title') . ' — ' . $r['code'], 'x' => $x, 'priority' => BoardReport::priorityRisks(), 'events' => BoardReport::events($r['period_start'], $r['period_end']), 'bands' => BoardReport::bands()], 'layout/print');
    }

    /** The report in Word, in the model's section order. */
    public function docx(int $id): never
    {
        $x = $this->report($id);
        $r = $x['r'];
        $rows = $x['rows'];
        $n = $r['narr'];
        $tpl = BoardReport::template();
        $ar = locale() === 'ar';
        $txt = fn(string $k) => trim((string)($n[$k] ?? ''));
        $org = lname(['name_en' => Settings::get('org_name_en'), 'name_ar' => Settings::get('org_name_ar')]);
        $q = t('brr.quarter_label', ['q' => $r['quarter'], 'y' => $r['year']]);
        $d = new Docx($ar, t('brr.report_title') . ' — ' . $q, $org . ' — ' . t('brr.report_title'), t('enum.cls.' . $r['classification']) . ' — ' . t('brr.board_only'));
        $d->heading(t('brr.report_title'), 1)->para($q . ' — ' . t('brr.period_line', ['f' => $r['period_start'], 't' => $r['period_end']]), ['bold' => true])
            ->kv([t('brr.prepared_by') => t('brr.prepared_by_value'), t('brr.submitted_to') => t('brr.submitted_to_value'), t('brr.report_date') => $r['report_date'], t('brr.ref_no') => $r['ref_no'] ?? '—', t('docs.classification') => t('enum.cls.' . $r['classification'])]);
        // Cover letter
        $d->para(t('brr.letter_to'))->para(t('brr.letter_through'))->para(t('brr.letter_subject', ['q' => $q]), ['bold' => true])->para(t('brr.letter_greeting'));
        foreach (['letter_intro', 'letter_messages', 'letter_request'] as $k) {
            if ($txt($k) !== '') {
                $d->para($txt($k), ['align' => 'both']);
            }
        }
        $d->para(t('brr.letter_closing'))->para(trim(($r['signatory_title'] ?? '') . "\n" . ($r['signatory_name'] ?? '')), ['bold' => true])->pageBreak();
        // Contents and reading note
        $d->heading(t('brr.contents'), 2)->table([t('brr.section'), t('brr.title_col'), t('brr.purpose')], array_map(fn($no, $s) => [$no === 11 ? ($ar ? 'م' : 'A') : (string)$no, $ar ? $s[0] : $s[1], t('enum.brpurpose.' . $s[2])], array_keys($tpl['sections']), $tpl['sections']), [1, 6, 2]);
        $d->box(t('brr.reading_note'), $ar ? $tpl['reading_ar'] : $tpl['reading_en']);
        $sec = fn(int $no) => $no . '. ' . ($ar ? $tpl['sections'][$no][0] : $tpl['sections'][$no][1]);
        // 1
        $d->heading($sec(1), 1);
        if ($txt('s1_overview') !== '') {
            $d->para($txt('s1_overview'), ['align' => 'both']);
        }
        if (!empty($rows['tile'])) {
            $d->table([t('brr.f_label'), t('brr.f_value'), t('brr.f_note')], array_map(fn($t) => [$t['title'], $t['detail'], $t['extra']], $rows['tile']), [3, 2, 3]);
        }
        $d->heading(t('brr.profile'), 2)->table([t('brr.f_domain'), t('brr.f_level'), t('brr.f_trend'), t('brr.f_drivers'), t('brr.f_decisions')],
            array_map(fn($p) => [$p['title'], $p['level'] ? t('enum.brlevel.' . $p['level']) : '—', $p['trend'] ? t('enum.brtrend.' . $p['trend']) : '—', $p['detail'], $p['refs']], $rows['profile'] ?? []), [2.4, 1.2, 1.2, 4, 1.4]);
        if (!empty($rows['message'])) {
            $d->heading(t('brr.messages'), 2)->bullets(array_map(fn($m) => $m['title'] . ': ' . $m['detail'], $rows['message']));
        }
        // 2
        $d->heading($sec(2), 1);
        if (!empty($rows['source'])) {
            $d->heading(t('brr.sources'), 2)->table([t('brr.f_source'), t('brr.f_prepared_by'), t('brr.f_date_ref'), t('brr.f_used_for')], array_map(fn($s) => [$s['title'], $s['detail'], $s['extra'], $s['note']], $rows['source']), [3, 2, 2, 3]);
        }
        if (!empty($rows['reference'])) {
            $d->heading(t('brr.references'), 2)->table([t('brr.f_reference'), t('brr.f_ref_type'), t('brr.f_ref_status')], array_map(fn($s) => [$s['title'], $s['detail'] ? t('enum.brref.' . $s['detail']) : '', $s['extra']], $rows['reference']), [5, 1.5, 3]);
        }
        // 3-6
        $d->heading($sec(3), 1)->para($txt('s3_credit') ?: '—', ['align' => 'both']);
        if ($txt('s3_assessment') !== '') {
            $d->box(t('brr.s3_assessment'), [$txt('s3_assessment')]);
        }
        $d->heading($sec(4), 1)->para($txt('s4_market') ?: '—', ['align' => 'both']);
        if (!empty($rows['observation'])) {
            $d->heading(t('brr.observations'), 2)->table([t('brr.f_code'), t('brr.f_observation'), t('brr.f_evidence'), t('brr.f_impact'), t('brr.f_level')], array_map(fn($o) => [$o['code'], $o['title'], $o['detail'], $o['extra'], $o['level'] ? t('enum.brlevel.' . $o['level']) : ''], $rows['observation']), [0.8, 3, 3, 2.4, 1]);
        }
        if ($txt('s4_responsibility') !== '') {
            $d->heading(t('brr.s4_responsibility'), 2)->para($txt('s4_responsibility'), ['align' => 'both']);
        }
        $d->heading($sec(5), 1)->para($txt('s5_operational') ?: '—', ['align' => 'both']);
        $pr = BoardReport::priorityRisks();
        if ($pr) {
            $d->heading(t('brr.priority_risks'), 2)->table([t('risk.id'), t('risk.title'), t('brr.score'), t('risk.department')], array_map(fn($p) => [$p['risk_code'], $p['title'], $p['res_score'] . ' (' . $p['res_likelihood'] . '×' . $p['res_impact'] . ')', $ar ? $p['dept_ar'] : $p['dept_en']], $pr), [1.4, 5, 1.4, 2.4]);
        }
        $d->heading($sec(6), 1)->para($txt('s6_capital') ?: '—', ['align' => 'both']);
        if ($txt('s6_reading') !== '') {
            $d->box(t('brr.s6_reading'), [$txt('s6_reading')]);
        }
        // 7-9
        $d->heading($sec(7), 1);
        if ($txt('s7_intro') !== '') {
            $d->para($txt('s7_intro'), ['align' => 'both']);
        }
        $d->table(['#', t('brr.f_observation'), t('brr.f_source'), t('brr.f_treatment')], array_map(fn($i, $o) => [(string)($i + 1), $o['title'], $o['detail'], $o['extra']], array_keys($rows['dq'] ?? []), $rows['dq'] ?? []), [0.5, 5, 1.5, 3]);
        $d->heading($sec(8), 1)->para(t('brr.decisions_intro'))->table([t('brr.f_code'), t('brr.f_decision'), t('brr.f_dtype'), t('brr.f_owner'), t('brr.f_duration')],
            array_map(fn($o) => [$o['code'], $o['title'], $o['level'] ? t('enum.brdtype.' . $o['level']) : '', $o['detail'], $o['extra']], $rows['decision'] ?? []), [0.8, 5, 1.2, 2, 1.4]);
        $d->heading($sec(9), 1)->para($txt('s9_roadmap') ?: '—', ['align' => 'both']);
        // 10
        $d->heading($sec(10), 1);
        if ($txt('s10_intro') !== '') {
            $d->para($txt('s10_intro'), ['align' => 'both']);
        }
        $d->table([t('brr.f_priority'), t('brr.priority_meaning'), t('brr.priority_closure')], array_map(fn($p) => [t('enum.brprio.' . $p), t(sprintf('brr.prio_meaning_%s', $p)), t(sprintf('brr.prio_closure_%s', $p))], $tpl['priorities']), [1, 4, 4]);
        $byDept = [];
        foreach ($rows['recommendation'] ?? [] as $rec) {
            $byDept[$rec['department_id'] ?? 0][] = $rec;
        }
        $i = 0;
        foreach ($byDept as $recs) {
            $i++;
            $name = $recs[0]['dept_ar'] ? ($ar ? $recs[0]['dept_ar'] : $recs[0]['dept_en']) : '—';
            $refs = implode('، ', array_unique(array_filter(array_map(fn($x) => trim((string)$x['refs']), $recs))));
            $d->heading('10.' . $i . '  ' . $name, 2);
            if ($refs !== '') {
                $d->para(t('brr.linked_decisions') . ': ' . $refs, ['size' => 10]);
            }
            $d->table(['#', t('brr.f_action'), t('brr.f_output'), t('brr.f_duration'), t('brr.f_priority')], array_map(fn($k, $rr) => [(string)($k + 1), $rr['title'], $rr['detail'], $rr['extra'] ?: ($rr['days'] ? t('brr.days_n', ['n' => $rr['days']]) : ''), $rr['priority'] ? t('enum.brprio.' . $rr['priority']) : ''], array_keys($recs), $recs), [0.5, 5, 3, 1.4, 1]);
        }
        $d->box(t('brr.followup_title'), [$txt('s10_followup')]);
        $d->table([t('brr.prepared_by'), t('brr.approval')], [[t('brr.prepared_by_value'), trim(($r['signatory_title'] ?? '') . ' — ' . ($r['signatory_name'] ?? ''))], [t('rm.signature') . ': ……', t('rm.signature') . ': ……']]);
        // Annex
        $d->pageBreak()->heading($ar ? $tpl['sections'][11][0] : $tpl['sections'][11][1], 1)->heading(t('brr.annex_scale'), 2)
            ->table([t('brr.score'), t('brr.f_level'), t('brr.meaning')], array_map(fn($b) => [$b['min_score'] . ' – ' . $b['max_score'], $ar ? $b['label_ar'] : $b['label_en'], $ar ? $b['description_ar'] : $b['description_en']], BoardReport::bands()), [1.2, 1.2, 6])
            ->para(t('brr.scale_note'), ['size' => 10]);
        $ev = BoardReport::events($r['period_start'], $r['period_end']);
        $d->heading(t('brr.annex_events'), 2)->table(['#', t('incident.name'), t('incident.occurrence_date'), t('incident.category'), t('incident.net_loss'), t('incident.status')],
            array_map(fn($k, $e) => [(string)($k + 1), $e['title'], $e['occurrence_date'], $ar ? (string)$e['cat_ar'] : (string)$e['cat_en'], \App\Core\Money::format($e['net_loss']), enum_label('incident_status', $e['status'])], array_keys($ev), $ev), [0.5, 4, 1.4, 2, 1.6, 1.3]);
        $d->para(t('brr.end'), ['align' => 'center', 'bold' => true]);
        $this->audit('board_report.exported', 'board_report', $id);
        $d->send($r['code'] . '.docx');
    }
}
