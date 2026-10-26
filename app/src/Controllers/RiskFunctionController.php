<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Db;
use App\Core\Perm;
use App\Core\Request;
use App\Core\Settings;
use App\Core\Validator;
use App\Support\Docx;
use App\Support\Framework;
use App\Support\Ref;
use App\Support\RiskFunction;

/**
 * The risk function's workbench (second line of defence): work calendar of recurring procedures, independent opinion
 * memos, limit breaches and exceptions, and the unified escalation log to the risk committee chair.
 */
final class RiskFunctionController extends Controller
{
    public const SUBJECTS = ['financing', 'rating', 'product', 'outsourcing', 'project', 'cloud', 'ai_tool', 'hedging', 'policy_review', 'exception', 'other'];
    public const OPINIONS = ['no_objection', 'conditional', 'reservation'];
    public const DECISIONS = ['approved', 'approved_conditions', 'rejected', 'withdrawn'];

    private function can(string $perm): bool
    {
        return Perm::roleHas($this->user()['role'], $perm);
    }

    private static function decimal(string $s): ?string
    {
        $s = str_replace([',', ' '], '', $s);
        return preg_match('/^-?\d{1,15}(\.\d{1,3})?$/', $s) ? $s : null;
    }

    // ------------------------------------------------------------------ home and work calendar

    public function home(): string
    {
        RiskFunction::ensure();
        $today = date('Y-m-d');
        $soon = date('Y-m-d', strtotime('+30 days'));
        $stats = [
            'tasks_due' => (int)Db::val("SELECT COUNT(*) FROM rm_tasks WHERE status = 'open' AND due_date <= ?", [$soon]),
            'tasks_overdue' => (int)Db::val("SELECT COUNT(*) FROM rm_tasks WHERE status = 'open' AND due_date < ?", [$today]),
            'opinions' => (int)Db::val("SELECT COUNT(*) FROM rm_opinions WHERE status IN ('draft','submitted','reviewed','returned')"),
            'breaches' => (int)Db::val("SELECT COUNT(*) FROM rm_breaches WHERE status IN ('open','plan_received')"),
            'escalations' => (int)Db::val("SELECT COUNT(*) FROM rm_escalations WHERE status <> 'closed'"),
            'contrary' => (int)Db::val("SELECT COUNT(*) FROM rm_opinions WHERE contrary = 1 AND decision_on >= ?", [date('Y-01-01')]),
        ];
        $tasks = Db::all("SELECT t.*, p.code, p.section, p.title_ar, p.title_en, p.frequency, u.full_name FROM rm_tasks t JOIN rm_procedures p ON p.id = t.procedure_id LEFT JOIN users u ON u.id = t.assignee_user_id
            WHERE t.status = 'open' AND t.due_date <= ? ORDER BY t.due_date, p.code LIMIT 25", [$soon]);
        $bySection = [];
        foreach (Db::all("SELECT p.section, SUM(t.status = 'open') AS open_n, SUM(t.status = 'open' AND t.due_date < ?) AS late, SUM(t.status = 'done') AS done FROM rm_tasks t JOIN rm_procedures p ON p.id = t.procedure_id GROUP BY p.section", [$today]) as $r) {
            $bySection[$r['section']] = $r;
        }
        $esc = Db::all("SELECT * FROM rm_escalations WHERE status <> 'closed' ORDER BY escalated_at DESC LIMIT 8");
        return $this->view('rm/home', ['title' => t('rm.title'), 'stats' => $stats, 'tasks' => $tasks, 'bySection' => $bySection, 'esc' => $esc]);
    }

    public function calendar(): string
    {
        RiskFunction::ensure();
        $f = ['section' => Request::str('section', true), 'status' => Request::str('status', true) ?: 'open', 'q' => mb_substr(Request::str('q', true), 0, 80)];
        $w = ['1=1'];
        $p = [];
        if (in_array($f['section'], Framework::SECTIONS, true)) {
            $w[] = 'p.section = ?';
            $p[] = $f['section'];
        }
        if ($f['status'] === 'overdue') {
            $w[] = "t.status = 'open' AND t.due_date < ?";
            $p[] = date('Y-m-d');
        } elseif (in_array($f['status'], ['open', 'done', 'not_applicable'], true)) {
            $w[] = 't.status = ?';
            $p[] = $f['status'];
        }
        if ($f['q'] !== '') {
            $w[] = '(p.title_ar LIKE ? OR p.title_en LIKE ? OR p.code LIKE ?)';
            $l = Db::like($f['q']);
            array_push($p, $l, $l, $l);
        }
        $rows = Db::all('SELECT t.*, p.code, p.section, p.title_ar, p.title_en, p.output_ar, p.output_en, p.frequency, p.approver, u.full_name, c.full_name AS done_name FROM rm_tasks t JOIN rm_procedures p ON p.id = t.procedure_id
            LEFT JOIN users u ON u.id = t.assignee_user_id LEFT JOIN users c ON c.id = t.completed_by WHERE ' . implode(' AND ', $w) . ' ORDER BY t.status = \'open\' DESC, t.due_date, p.code LIMIT 300', $p);
        $standing = Db::all("SELECT * FROM rm_procedures WHERE is_active = 1 AND frequency IN ('daily','weekly') ORDER BY section, code");
        return $this->view('rm/calendar', ['title' => t('rm.calendar'), 'rows' => $rows, 'f' => $f, 'standing' => $standing]);
    }

    public function completeTask(int $id): never
    {
        $u = $this->user();
        $t = Db::one('SELECT * FROM rm_tasks WHERE id = ?', [$id]) ?? $this->notFound();
        $v = new Validator($_POST);
        $v->in('result', 'rm.result', ['done', 'not_applicable'])->max('output_ref', 'rm.output_ref', 250)->max('note', 'rm.note', 2000);
        if ($v->str('result') === 'done' && $v->str('output_ref') === '') {
            $v->fail('output_ref', 'rm.err_output');
        }
        if ($v->str('result') === 'not_applicable' && $v->str('note') === '') {
            $v->fail('note', 'rm.err_na_reason');
        }
        if ($t['status'] !== 'open') {
            $v->fail('result', 'rm.err_closed');
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), url('rm/calendar') . '#t' . $id);
        }
        Db::update('rm_tasks', ['status' => $v->str('result'), 'output_ref' => $v->str('output_ref') ?: null, 'note' => $v->str('note') ?: null, 'completed_by' => $u['id'], 'completed_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        $this->audit('rm.task_' . $v->str('result'), 'rm_task', $id, ['period' => $t['period_key']]);
        $this->redirect(url('rm/calendar'), 'rm.task_saved');
    }

    public function procedures(): string
    {
        RiskFunction::ensure();
        $rows = Db::all('SELECT p.*, u.full_name, (SELECT COUNT(*) FROM rm_tasks t WHERE t.procedure_id = p.id AND t.status = \'done\') AS done_n FROM rm_procedures p LEFT JOIN users u ON u.id = p.assignee_user_id ORDER BY FIELD(p.section, \'office\', \'credit\', \'market\', \'operational\', \'infosec\', \'basel\'), p.code');
        // Procedures are carried out by the risk team (risk managers and administrators).
        $users = array_filter(Ref::users(true), fn($x) => in_array($x['role'], ['risk_manager', 'admin'], true));
        return $this->view('rm/procedures', ['title' => t('rm.procedures'), 'rows' => $rows, 'users' => $users]);
    }

    public function updateProcedure(int $id): never
    {
        $p = Db::one('SELECT * FROM rm_procedures WHERE id = ?', [$id]) ?? $this->notFound();
        $v = new Validator($_POST);
        $v->in('frequency', 'rm.frequency', RiskFunction::FREQUENCIES)->in('approver', 'rm.approver', RiskFunction::APPROVERS);
        if ($v->str('due_days') !== '') {
            $v->intRange('due_days', 'rm.due_days', 0, 180);
        }
        $assignee = Request::int('assignee_user_id');
        if ($assignee !== null && !isset(Ref::users(true)[$assignee])) {
            $v->fail('assignee_user_id', 'validation.invalid_choice', ['field' => t('rm.assignee')]);
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), url('rm/procedures') . '#p' . $id);
        }
        $data = ['frequency' => $v->str('frequency'), 'approver' => $v->str('approver'), 'due_days' => $v->str('due_days') !== '' ? (int)$v->str('due_days') : null, 'assignee_user_id' => $assignee,
            'is_active' => isset($_POST['is_active']) ? 1 : 0, 'updated_at' => date('Y-m-d H:i:s')];
        Db::update('rm_procedures', $data, 'id = ?', [$id]);
        if ($assignee !== null) {
            Db::exec("UPDATE rm_tasks SET assignee_user_id = ? WHERE procedure_id = ? AND status = 'open' AND assignee_user_id IS NULL", [$assignee, $id]);
        }
        $this->audit('rm.procedure_updated', 'rm_procedure', $id, ['changes' => $this->diff($p, $data, ['frequency', 'approver', 'due_days', 'assignee_user_id', 'is_active'])]);
        $this->redirect(url('rm/procedures') . '#p' . $id, 'admin.saved');
    }

    // ------------------------------------------------------------------ independent opinions

    public function opinions(): string
    {
        $f = ['status' => Request::str('status', true), 'section' => Request::str('section', true), 'subject' => Request::str('subject', true)];
        $w = ['1=1'];
        $p = [];
        if (in_array($f['status'], ['draft', 'submitted', 'reviewed', 'issued', 'decided', 'returned'], true)) {
            $w[] = 'o.status = ?';
            $p[] = $f['status'];
        } elseif ($f['status'] === 'contrary') {
            $w[] = 'o.contrary = 1';
        }
        if (in_array($f['section'], Framework::SECTIONS, true)) {
            $w[] = 'o.section = ?';
            $p[] = $f['section'];
        }
        if (in_array($f['subject'], self::SUBJECTS, true)) {
            $w[] = 'o.subject_type = ?';
            $p[] = $f['subject'];
        }
        $rows = Db::all('SELECT o.*, d.name_en AS dept_en, d.name_ar AS dept_ar, u.full_name FROM rm_opinions o LEFT JOIN departments d ON d.id = o.requesting_department_id JOIN users u ON u.id = o.prepared_by WHERE ' . implode(' AND ', $w) . ' ORDER BY o.id DESC LIMIT 300', $p);
        $kpi = Db::one("SELECT COUNT(*) AS n, AVG(DATEDIFF(DATE(issued_at), received_on)) AS days, SUM(contrary) AS contrary FROM rm_opinions WHERE issued_at IS NOT NULL AND received_on >= ?", [date('Y-01-01')]);
        return $this->view('rm/opinions', ['title' => t('rm.opinions'), 'rows' => $rows, 'f' => $f, 'kpi' => $kpi]);
    }

    private function opinion(int $id): array
    {
        return Db::one('SELECT o.*, d.name_en AS dept_en, d.name_ar AS dept_ar, pu.full_name AS prep_name, ru.full_name AS rev_name, iu.full_name AS iss_name FROM rm_opinions o LEFT JOIN departments d ON d.id = o.requesting_department_id
            JOIN users pu ON pu.id = o.prepared_by LEFT JOIN users ru ON ru.id = o.reviewed_by LEFT JOIN users iu ON iu.id = o.issued_by WHERE o.id = ?', [$id]) ?? $this->notFound();
    }

    public function opinionNew(): string
    {
        return $this->view('rm/opinion_form', ['title' => t('rm.opinion_new'), 'o' => ['section' => 'credit', 'subject_type' => 'financing', 'received_on' => date('Y-m-d')], 'existing' => null, 'departments' => Ref::departments(true)]);
    }

    public function opinionEdit(int $id): string
    {
        $o = $this->opinion($id);
        if (!in_array($o['status'], ['draft', 'returned'], true)) {
            $this->redirect(url('rm/opinions/' . $id), 'rm.err_locked', 'error');
        }
        return $this->view('rm/opinion_form', ['title' => t('rm.opinion_edit'), 'o' => $o, 'existing' => $o, 'departments' => Ref::departments(true)]);
    }

    public function opinionStore(): never
    {
        $this->opinionSave(null);
    }

    public function opinionUpdate(int $id): never
    {
        $o = $this->opinion($id);
        if (!in_array($o['status'], ['draft', 'returned'], true)) {
            $this->redirect(url('rm/opinions/' . $id), 'rm.err_locked', 'error');
        }
        $this->opinionSave($o);
    }

    private function opinionSave(?array $ex): never
    {
        $v = new Validator($_POST);
        $v->required('title', 'rm.subject')->max('title', 'rm.subject', 250)->in('section', 'docs.section', Framework::SECTIONS)->in('subject_type', 'rm.subject_type', self::SUBJECTS)
            ->max('request_ref', 'rm.request_ref', 80)->date('received_on', 'rm.received_on')->date('due_on', 'rm.due_on', false)->required('summary', 'rm.summary')->max('summary', 'rm.summary', 8000)
            ->max('key_risks', 'rm.key_risks', 8000)->max('limits_impact', 'rm.limits_impact', 4000)->in('opinion', 'rm.opinion', self::OPINIONS, false)->max('conditions', 'rm.conditions', 4000)->max('rationale', 'rm.rationale', 8000);
        if ($v->str('received_on') > date('Y-m-d')) {
            $v->fail('received_on', 'rm.err_future');
        }
        if ($v->str('opinion') === 'conditional' && $v->str('conditions') === '') {
            $v->fail('conditions', 'rm.err_conditions');
        }
        $dept = Request::int('requesting_department_id');
        if ($dept !== null && !isset(Ref::departments()[$dept])) {
            $v->fail('requesting_department_id', 'validation.invalid_choice', ['field' => t('rm.requesting_department')]);
        }
        $back = $ex ? url('rm/opinions/' . $ex['id'] . '/edit') : url('rm/opinions/new');
        if (!$v->ok()) {
            $this->invalid($v->errors(), $back);
        }
        $now = date('Y-m-d H:i:s');
        $data = ['title' => $v->str('title'), 'section' => $v->str('section'), 'subject_type' => $v->str('subject_type'), 'requesting_department_id' => $dept, 'request_ref' => $v->str('request_ref') ?: null,
            'received_on' => $v->str('received_on'), 'due_on' => $v->str('due_on') ?: null, 'summary' => $v->str('summary'), 'key_risks' => $v->str('key_risks') ?: null, 'limits_impact' => $v->str('limits_impact') ?: null,
            'opinion' => $v->str('opinion') ?: null, 'conditions' => $v->str('conditions') ?: null, 'rationale' => $v->str('rationale') ?: null, 'updated_at' => $now];
        if ($ex) {
            Db::update('rm_opinions', $data, 'id = ?', [$ex['id']]);
            $id = (int)$ex['id'];
            $this->audit('opinion.updated', 'rm_opinion', $id);
        } else {
            $id = Db::insert('rm_opinions', $data + ['status' => 'draft', 'prepared_by' => $this->user()['id'], 'created_at' => $now]);
            RiskFunction::code('rm_opinions', 'OP', $id);
            $this->audit('opinion.created', 'rm_opinion', $id, ['subject' => $data['subject_type']]);
        }
        $this->redirect(url('rm/opinions/' . $id), 'admin.saved');
    }

    public function opinionShow(int $id): string
    {
        $o = $this->opinion($id);
        $esc = $o['escalation_id'] ? Db::one('SELECT id, code, status FROM rm_escalations WHERE id = ?', [$o['escalation_id']]) : null;
        return $this->view('rm/opinion_show', ['title' => $o['code'] . ' — ' . $o['title'], 'o' => $o, 'esc' => $esc, 'uid' => (int)$this->user()['id']]);
    }

    /** Maker-checker steps: submit (preparer) → review (another analyst or the section head) → issue (risk director), with return for rework. */
    public function opinionStep(int $id): never
    {
        $u = $this->user();
        $o = $this->opinion($id);
        $step = Request::str('step');
        $now = date('Y-m-d H:i:s');
        $back = url('rm/opinions/' . $id);
        if ($step === 'submit') {
            if (!in_array($o['status'], ['draft', 'returned'], true) || (int)$o['prepared_by'] !== (int)$u['id']) {
                $this->redirect($back, 'rm.err_step', 'error');
            }
            if (!$o['opinion'] || !$o['rationale']) {
                $this->redirect($back, 'rm.err_incomplete', 'error');
            }
            Db::update('rm_opinions', ['status' => 'submitted', 'updated_at' => $now], 'id = ?', [$id]);
        } elseif ($step === 'review') {
            if ($o['status'] !== 'submitted' || (int)$o['prepared_by'] === (int)$u['id']) {
                $this->redirect($back, 'rm.err_four_eyes', 'error');
            }
            Db::update('rm_opinions', ['status' => 'reviewed', 'reviewed_by' => $u['id'], 'reviewed_at' => $now, 'updated_at' => $now], 'id = ?', [$id]);
        } elseif ($step === 'issue') {
            if ($o['status'] !== 'reviewed' || (int)$o['prepared_by'] === (int)$u['id'] || !$this->can('rm.approve')) {
                $this->redirect($back, 'rm.err_four_eyes', 'error');
            }
            Db::update('rm_opinions', ['status' => 'issued', 'issued_by' => $u['id'], 'issued_at' => $now, 'updated_at' => $now], 'id = ?', [$id]);
        } elseif ($step === 'return') {
            $note = trim((string)($_POST['note'] ?? ''));
            if (!in_array($o['status'], ['submitted', 'reviewed'], true) || (int)$o['prepared_by'] === (int)$u['id'] || $note === '' || mb_strlen($note) > 2000) {
                $this->redirect($back, 'rm.err_return', 'error');
            }
            Db::update('rm_opinions', ['status' => 'returned', 'reviewed_by' => null, 'reviewed_at' => null, 'decision_note' => null, 'updated_at' => $now], 'id = ?', [$id]);
            $this->audit('opinion.returned', 'rm_opinion', $id, ['note' => mb_substr($note, 0, 500)]);
            $this->redirect($back, 'rm.returned');
        } else {
            $this->notFound();
        }
        $this->audit('opinion.' . $step, 'rm_opinion', $id);
        $this->redirect($back, sprintf('rm.step_%s', $step));
    }

    /** Record the competent body's decision; a decision contrary to the opinion is escalated to the risk committee chair. */
    public function opinionDecide(int $id): never
    {
        $u = $this->user();
        $o = $this->opinion($id);
        $v = new Validator($_POST);
        $v->required('decision_body', 'rm.decision_body')->max('decision_body', 'rm.decision_body', 150)->in('decision', 'rm.decision', self::DECISIONS)->date('decision_on', 'rm.decision_on')->max('decision_note', 'rm.decision_note', 4000);
        $adopted = null;
        if ($o['opinion'] === 'conditional' && in_array($v->str('decision'), ['approved', 'approved_conditions'], true)) {
            $v->in('conditions_adopted', 'rm.conditions_adopted', ['0', '1']);
            $adopted = $v->str('conditions_adopted') === '1' ? 1 : 0;
        }
        if ($o['status'] !== 'issued') {
            $v->fail('decision', 'rm.err_not_issued');
        }
        if ($v->str('decision_on') !== '' && ($v->str('decision_on') > date('Y-m-d') || $v->str('decision_on') < substr((string)$o['issued_at'], 0, 10))) {
            $v->fail('decision_on', 'rm.err_decision_date');
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), url('rm/opinions/' . $id) . '#decision');
        }
        $contrary = RiskFunction::isContrary($o['opinion'], $v->str('decision'), $adopted);
        $now = date('Y-m-d H:i:s');
        Db::update('rm_opinions', ['decision_body' => $v->str('decision_body'), 'decision' => $v->str('decision'), 'conditions_adopted' => $adopted, 'decision_on' => $v->str('decision_on'),
            'decision_note' => $v->str('decision_note') ?: null, 'contrary' => $contrary ? 1 : 0, 'status' => 'decided', 'updated_at' => $now], 'id = ?', [$id]);
        if ($contrary) {
            $esc = RiskFunction::escalate('contrary_decision', 'rm_opinion', $id, t('rm.esc_contrary_title', ['code' => $o['code'], 'title' => $o['title']]),
                t('rm.esc_contrary_detail', ['opinion' => t('enum.rmop.' . $o['opinion']), 'body' => $v->str('decision_body'), 'decision' => t('enum.rmdec.' . $v->str('decision')), 'date' => $v->str('decision_on')]), (int)$u['id'], 'committee_chair', $o['section']);
            Db::update('rm_opinions', ['escalation_id' => $esc], 'id = ?', [$id]);
        }
        $this->audit('opinion.decided', 'rm_opinion', $id, ['decision' => $v->str('decision'), 'contrary' => $contrary]);
        $this->redirect(url('rm/opinions/' . $id), $contrary ? 'rm.decided_contrary' : 'rm.decided');
    }

    public function opinionPrint(int $id): string
    {
        $o = $this->opinion($id);
        return $this->view('rm/opinion_print', ['title' => t('rm.memo_title') . ' ' . $o['code'], 'o' => $o], 'layout/print');
    }

    public function opinionDocx(int $id): never
    {
        $o = $this->opinion($id);
        $d = $this->docx(t('rm.memo_title') . ' ' . $o['code']);
        $d->heading(t('rm.memo_title'), 1)->kv([t('rm.memo_no') => $o['code'], t('rm.received_on') => $o['received_on'], t('rm.request_ref') => $o['request_ref'] ?? '—',
            t('rm.requesting_department') => $o['dept_ar'] ? lname(['name_en' => $o['dept_en'], 'name_ar' => $o['dept_ar']]) : '—', t('docs.section') => t('enum.rmsec.' . $o['section']), t('rm.subject_type') => t('enum.rmsubj.' . $o['subject_type'])]);
        $d->heading(t('rm.subject'), 2)->para($o['title'], ['bold' => true])->heading(t('rm.summary'), 2)->para($o['summary']);
        if ($o['key_risks']) {
            $d->heading(t('rm.key_risks'), 2)->para($o['key_risks']);
        }
        if ($o['limits_impact']) {
            $d->heading(t('rm.limits_impact'), 2)->para($o['limits_impact']);
        }
        $d->heading(t('rm.opinion'), 2)->para($o['opinion'] ? t('enum.rmop.' . $o['opinion']) : '—', ['bold' => true]);
        if ($o['conditions']) {
            $d->heading(t('rm.conditions'), 3)->para($o['conditions']);
        }
        $d->heading(t('rm.rationale'), 3)->para($o['rationale'] ?? '—')->box(t('rm.memo_warning_title'), [t('rm.memo_warning')]);
        $d->table([t('rm.prepared_by'), t('rm.reviewed_by'), t('rm.issued_by')], [[$o['prep_name'], $o['rev_name'] ?? '—', $o['iss_name'] ?? '—'], [t('rm.signature'), t('rm.signature'), t('rm.signature')]]);
        $this->audit('opinion.exported', 'rm_opinion', $id);
        $d->send($o['code'] . '.docx');
    }

    private function docx(string $title): Docx
    {
        $org = lname(['name_en' => Settings::get('org_name_en'), 'name_ar' => Settings::get('org_name_ar')]);
        return new Docx(locale() === 'ar', $title, $org . ' — ' . t('rm.title'), t('report.confidential') . ' — ' . date('Y-m-d'));
    }

    // ------------------------------------------------------------------ limit breaches and exceptions

    /** Department managers see only their department's breaches and may submit the treatment plan. */
    private function breachScope(array $u): array
    {
        if ($this->can('rm.view')) {
            return ['1=1', []];
        }
        return ['b.owner_department_id = ?', [(int)($u['department_id'] ?? 0)]];
    }

    public function breaches(): string
    {
        $u = $this->user();
        [$sw, $sp] = $this->breachScope($u);
        $f = ['kind' => Request::str('kind', true), 'status' => Request::str('status', true), 'section' => Request::str('section', true)];
        $w = [$sw];
        $p = $sp;
        if (in_array($f['kind'], ['breach', 'exception'], true)) {
            $w[] = 'b.kind = ?';
            $p[] = $f['kind'];
        }
        if (in_array($f['status'], ['open', 'plan_received', 'resolved', 'closed'], true)) {
            $w[] = 'b.status = ?';
            $p[] = $f['status'];
        }
        if (in_array($f['section'], Framework::SECTIONS, true)) {
            $w[] = 'b.section = ?';
            $p[] = $f['section'];
        }
        $rows = Db::all('SELECT b.*, d.name_en AS dept_en, d.name_ar AS dept_ar FROM rm_breaches b LEFT JOIN departments d ON d.id = b.owner_department_id WHERE ' . implode(' AND ', $w) . ' ORDER BY b.status IN (\'open\',\'plan_received\') DESC, b.detected_at DESC LIMIT 300', $p);
        return $this->view('rm/breaches', ['title' => t('rm.breaches'), 'rows' => $rows, 'f' => $f]);
    }

    private function breach(int $id): array
    {
        $u = $this->user();
        [$sw, $sp] = $this->breachScope($u);
        return Db::one("SELECT b.*, d.name_en AS dept_en, d.name_ar AS dept_ar, k.code AS kri_code, k.name AS kri_name, c.full_name AS creator FROM rm_breaches b LEFT JOIN departments d ON d.id = b.owner_department_id
            LEFT JOIN kris k ON k.id = b.kri_id JOIN users c ON c.id = b.created_by WHERE b.id = ? AND $sw", array_merge([$id], $sp)) ?? $this->notFound();
    }

    public function breachNew(): string
    {
        $kind = Request::str('kind', true) === 'exception' ? 'exception' : 'breach';
        return $this->view('rm/breach_form', ['title' => t($kind === 'exception' ? 'rm.exception_new' : 'rm.breach_new'), 'b' => ['kind' => $kind, 'section' => 'credit', 'limit_type' => 'internal', 'limit_tier' => 'policy', 'detected_at' => date('Y-m-d H:i')],
            'departments' => Ref::departments(true), 'kris' => Db::all('SELECT id, code, name FROM kris WHERE is_active = 1 ORDER BY code')]);
    }

    public function breachStore(): never
    {
        $u = $this->user();
        $v = new Validator($_POST);
        $v->in('kind', 'rm.kind', ['breach', 'exception'])->in('section', 'docs.section', Framework::SECTIONS)->in('limit_type', 'rm.limit_type', ['regulatory', 'internal'])->in('limit_tier', 'rm.limit_tier', ['board', 'policy', 'operational'])
            ->required('limit_name', 'rm.limit_name')->max('limit_name', 'rm.limit_name', 250)->max('subject', 'rm.breach_subject', 250)->max('unit', 'rm.unit', 20)->required('detected_at', 'rm.detected_at')
            ->max('cause', 'rm.cause', 4000)->date('plan_due', 'rm.plan_due', false)->max('approver_body', 'rm.approver_body', 150)->date('expires_on', 'rm.expires_on', false);
        $limit = $v->str('limit_value') !== '' ? self::decimal($v->str('limit_value')) : null;
        $actual = $v->str('actual_value') !== '' ? self::decimal($v->str('actual_value')) : null;
        if ($v->str('limit_value') !== '' && $limit === null) {
            $v->fail('limit_value', 'rm.err_number');
        }
        if ($v->str('actual_value') !== '' && $actual === null) {
            $v->fail('actual_value', 'rm.err_number');
        }
        $detected = strtotime(str_replace('T', ' ', $v->str('detected_at')));
        if ($v->str('detected_at') !== '' && (!$detected || $detected > time() + 300)) {
            $v->fail('detected_at', 'rm.err_future');
        }
        // No exception can be granted from a regulatory limit.
        if ($v->str('kind') === 'exception' && $v->str('limit_type') === 'regulatory') {
            $v->fail('limit_type', 'rm.err_regulatory_exception');
        }
        if ($v->str('kind') === 'exception') {
            $v->in('risk_opinion', 'rm.opinion', self::OPINIONS);
        }
        $dept = Request::int('owner_department_id');
        if ($dept === null || !isset(Ref::departments()[$dept])) {
            $v->fail('owner_department_id', 'validation.invalid_choice', ['field' => t('rm.owner_department')]);
        }
        $kri = Request::int('kri_id');
        if ($kri !== null && !Db::val('SELECT 1 FROM kris WHERE id = ?', [$kri])) {
            $v->fail('kri_id', 'validation.invalid_choice', ['field' => t('rm.kri')]);
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), url('rm/breaches/new') . ($v->str('kind') === 'exception' ? '?kind=exception' : ''));
        }
        $now = date('Y-m-d H:i:s');
        $material = isset($_POST['material']) ? 1 : 0;
        $data = ['kind' => $v->str('kind'), 'section' => $v->str('section'), 'limit_type' => $v->str('limit_type'), 'limit_tier' => $v->str('limit_tier'), 'limit_name' => $v->str('limit_name'), 'kri_id' => $kri,
            'subject' => $v->str('subject') ?: null, 'limit_value' => $limit, 'actual_value' => $actual, 'unit' => $v->str('unit') ?: null, 'detected_at' => date('Y-m-d H:i:s', $detected), 'cause' => $v->str('cause') ?: null,
            'owner_department_id' => $dept, 'plan_due' => $v->str('plan_due') ?: null, 'material' => $material, 'risk_opinion' => $v->str('risk_opinion') ?: null, 'approver_body' => $v->str('approver_body') ?: null,
            'expires_on' => $v->str('expires_on') ?: null, 'status' => 'open', 'created_by' => $u['id'], 'created_at' => $now, 'updated_at' => $now];
        $id = Db::insert('rm_breaches', $data);
        $code = RiskFunction::code('rm_breaches', $data['kind'] === 'exception' ? 'EXC' : 'BR', $id);
        // A regulatory breach, or a material internal one, goes to the risk committee chair at once.
        if ($data['kind'] === 'breach' && ($data['limit_type'] === 'regulatory' || $material)) {
            $esc = RiskFunction::escalate($data['limit_type'] === 'regulatory' ? 'regulatory_breach' : 'material_breach', 'rm_breach', $id, t('rm.esc_breach_title', ['code' => $code, 'name' => $data['limit_name']]),
                t('rm.esc_breach_detail', ['limit' => $limit ?? '—', 'actual' => $actual ?? '—', 'unit' => $data['unit'] ?? '', 'subject' => $data['subject'] ?? '—']), (int)$u['id'], 'committee_chair', $data['section']);
            Db::update('rm_breaches', ['escalation_id' => $esc], 'id = ?', [$id]);
        }
        $this->audit('breach.created', 'rm_breach', $id, ['kind' => $data['kind'], 'type' => $data['limit_type'], 'material' => $material]);
        $this->redirect(url('rm/breaches/' . $id), 'admin.saved');
    }

    public function breachShow(int $id): string
    {
        $b = $this->breach($id);
        $esc = $b['escalation_id'] ? Db::one('SELECT id, code, status FROM rm_escalations WHERE id = ?', [$b['escalation_id']]) : null;
        return $this->view('rm/breach_show', ['title' => $b['code'] . ' — ' . $b['limit_name'], 'b' => $b, 'esc' => $esc, 'excess' => RiskFunction::excessPct($b['limit_value'], $b['actual_value'])]);
    }

    /** The owning department's treatment plan (risk team, or the department manager for their own department). */
    public function breachPlan(int $id): never
    {
        if (!$this->can('rm.work') && !$this->can('rm.respond')) {
            throw new \App\Core\HttpException(403);
        }
        $b = $this->breach($id);
        $v = new Validator($_POST);
        $v->required('plan_text', 'rm.plan_text')->max('plan_text', 'rm.plan_text', 6000);
        if (!in_array($b['status'], ['open', 'plan_received'], true) || $b['kind'] !== 'breach') {
            $v->fail('plan_text', 'rm.err_closed');
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), url('rm/breaches/' . $id) . '#plan');
        }
        Db::update('rm_breaches', ['plan_text' => $v->str('plan_text'), 'plan_received_on' => date('Y-m-d'), 'status' => 'plan_received', 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        $this->audit('breach.plan_received', 'rm_breach', $id);
        $this->redirect(url('rm/breaches/' . $id), 'rm.plan_saved');
    }

    public function breachResolve(int $id): never
    {
        $b = $this->breach($id);
        $v = new Validator($_POST);
        $v->required('resolution_note', 'rm.resolution_note')->max('resolution_note', 'rm.resolution_note', 4000);
        if ($b['kind'] === 'exception') {
            $v->in('decision', 'rm.decision', ['approved', 'rejected'])->date('decision_on', 'rm.decision_on');
        }
        if (in_array($b['status'], ['resolved', 'closed'], true)) {
            $v->fail('resolution_note', 'rm.err_closed');
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), url('rm/breaches/' . $id) . '#resolve');
        }
        $data = ['resolution_note' => $v->str('resolution_note'), 'resolved_on' => date('Y-m-d'), 'status' => 'resolved', 'updated_at' => date('Y-m-d H:i:s')];
        if ($b['kind'] === 'exception') {
            $data += ['decision' => $v->str('decision'), 'decision_on' => $v->str('decision_on')];
        }
        Db::update('rm_breaches', $data, 'id = ?', [$id]);
        $this->audit('breach.resolved', 'rm_breach', $id, ['decision' => $data['decision'] ?? null]);
        $this->redirect(url('rm/breaches/' . $id), 'admin.saved');
    }

    public function breachPrint(int $id): string
    {
        $b = $this->breach($id);
        return $this->view('rm/breach_print', ['title' => t('rm.notice_title') . ' ' . $b['code'], 'b' => $b, 'excess' => RiskFunction::excessPct($b['limit_value'], $b['actual_value'])], 'layout/print');
    }

    public function breachDocx(int $id): never
    {
        $b = $this->breach($id);
        $ex = RiskFunction::excessPct($b['limit_value'], $b['actual_value']);
        $d = $this->docx(t('rm.notice_title') . ' ' . $b['code']);
        $d->heading(t($b['kind'] === 'exception' ? 'rm.exception_title' : 'rm.notice_title'), 1)->kv([t('rm.notice_no') => $b['code'], t('rm.detected_at') => $b['detected_at'], t('rm.limit_type') => t('enum.rmlt.' . $b['limit_type']),
            t('rm.limit_tier') => t('enum.rmtier.' . $b['limit_tier']), t('rm.limit_name') => $b['limit_name'], t('rm.breach_subject') => $b['subject'] ?? '—', t('rm.limit_value') => ($b['limit_value'] ?? '—') . ' ' . ($b['unit'] ?? ''),
            t('rm.actual_value') => ($b['actual_value'] ?? '—') . ' ' . ($b['unit'] ?? ''), t('rm.excess') => $ex !== null ? $ex . '%' : '—', t('rm.cause') => $b['cause'] ?? '—',
            t('rm.owner_department') => lname(['name_en' => $b['dept_en'], 'name_ar' => $b['dept_ar']]), t('rm.plan_due') => $b['plan_due'] ?? '—',
            t('rm.escalate_chair') => ($b['limit_type'] === 'regulatory' || $b['material']) ? t('common.yes') : t('common.no')]);
        $d->box(t('rm.notice_warning_title'), [t('rm.notice_warning')]);
        $d->table([t('rm.section_head'), t('rm.director')], [['', ''], [t('rm.signature'), t('rm.signature')]]);
        $this->audit('breach.exported', 'rm_breach', $id);
        $d->send($b['code'] . '.docx');
    }

    // ------------------------------------------------------------------ escalation log

    public function escalations(): string
    {
        $f = ['status' => Request::str('status', true), 'source' => Request::str('source', true)];
        $w = ['1=1'];
        $p = [];
        if (in_array($f['status'], ['open', 'responded', 'closed'], true)) {
            $w[] = 'e.status = ?';
            $p[] = $f['status'];
        }
        if (in_array($f['source'], RiskFunction::ESC_SOURCES, true)) {
            $w[] = 'e.source_type = ?';
            $p[] = $f['source'];
        }
        $rows = Db::all('SELECT e.*, u.full_name FROM rm_escalations e JOIN users u ON u.id = e.escalated_by WHERE ' . implode(' AND ', $w) . ' ORDER BY e.status = \'closed\', e.escalated_at DESC LIMIT 300', $p);
        return $this->view('rm/escalations', ['title' => t('rm.escalations'), 'rows' => $rows, 'f' => $f]);
    }

    private function escalation(int $id): array
    {
        return Db::one('SELECT e.*, u.full_name FROM rm_escalations e JOIN users u ON u.id = e.escalated_by WHERE e.id = ?', [$id]) ?? $this->notFound();
    }

    public function escalationNew(): string
    {
        return $this->view('rm/escalation_form', ['title' => t('rm.escalation_new'), 'e' => ['source_type' => 'other', 'escalated_to' => 'committee_chair']]);
    }

    public function escalationStore(): never
    {
        $u = $this->user();
        $v = new Validator($_POST);
        $v->in('source_type', 'rm.source_type', RiskFunction::ESC_SOURCES)->in('escalated_to', 'rm.escalated_to', RiskFunction::ESC_TO)->required('title', 'rm.esc_subject')->max('title', 'rm.esc_subject', 250)
            ->required('detail', 'rm.esc_detail')->max('detail', 'rm.esc_detail', 8000)->max('ceo_excluded_reason', 'rm.ceo_excluded_reason', 250);
        if ($v->str('section') !== '') {
            $v->in('section', 'docs.section', Framework::SECTIONS);
        }
        $excluded = isset($_POST['ceo_excluded']);
        if ($excluded && $v->str('ceo_excluded_reason') === '') {
            $v->fail('ceo_excluded_reason', 'rm.err_ceo_reason');
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), url('rm/escalations/new'));
        }
        $id = RiskFunction::escalate($v->str('source_type'), null, null, $v->str('title'), $v->str('detail'), (int)$u['id'], $v->str('escalated_to'), $v->str('section') ?: null, $excluded ? $v->str('ceo_excluded_reason') : null);
        $this->redirect(url('rm/escalations/' . $id), 'rm.escalated');
    }

    public function escalationShow(int $id): string
    {
        $e = $this->escalation($id);
        $src = null;
        if ($e['source_entity'] === 'rm_opinion') {
            $src = ['url' => url('rm/opinions/' . $e['source_id']), 'code' => Db::val('SELECT code FROM rm_opinions WHERE id = ?', [$e['source_id']])];
        } elseif ($e['source_entity'] === 'rm_breach') {
            $src = ['url' => url('rm/breaches/' . $e['source_id']), 'code' => Db::val('SELECT code FROM rm_breaches WHERE id = ?', [$e['source_id']])];
        } elseif ($e['source_entity'] === 'kri_reading') {
            $kid = (int)Db::val('SELECT kri_id FROM kri_readings WHERE id = ?', [$e['source_id']]);
            $src = ['url' => url('kris/' . $kid), 'code' => Db::val('SELECT code FROM kris WHERE id = ?', [$kid])];
        } elseif ($e['source_entity'] === 'icaap_cycle') {
            $src = ['url' => url('icaap/' . $e['source_id']), 'code' => 'ICAAP ' . Db::val('SELECT year FROM icaap_cycles WHERE id = ?', [$e['source_id']])];
        }
        return $this->view('rm/escalation_show', ['title' => $e['code'] . ' — ' . $e['title'], 'e' => $e, 'src' => $src]);
    }

    public function escalationRespond(int $id): never
    {
        $e = $this->escalation($id);
        $v = new Validator($_POST);
        $v->required('response', 'rm.esc_response')->max('response', 'rm.esc_response', 6000)->date('response_on', 'rm.response_on');
        if ($v->str('response_on') > date('Y-m-d') || $v->str('response_on') < substr($e['escalated_at'], 0, 10)) {
            $v->fail('response_on', 'rm.err_decision_date');
        }
        if ($e['status'] === 'closed') {
            $v->fail('response', 'rm.err_closed');
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), url('rm/escalations/' . $id) . '#respond');
        }
        Db::update('rm_escalations', ['response' => $v->str('response'), 'response_on' => $v->str('response_on'), 'status' => 'responded', 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        $this->audit('escalation.responded', 'rm_escalation', $id);
        $this->redirect(url('rm/escalations/' . $id), 'admin.saved');
    }

    public function escalationClose(int $id): never
    {
        $e = $this->escalation($id);
        $v = new Validator($_POST);
        $v->required('outcome', 'rm.esc_outcome')->max('outcome', 'rm.esc_outcome', 6000);
        if ($e['status'] !== 'responded') {
            $v->fail('outcome', 'rm.err_close_needs_response');
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), url('rm/escalations/' . $id) . '#close');
        }
        Db::update('rm_escalations', ['outcome' => $v->str('outcome'), 'status' => 'closed', 'closed_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        $this->audit('escalation.closed', 'rm_escalation', $id);
        $this->redirect(url('rm/escalations/' . $id), 'admin.saved');
    }

    public function escalationDocx(int $id): never
    {
        $e = $this->escalation($id);
        $d = $this->docx(t('rm.esc_notice_title') . ' ' . $e['code']);
        $d->heading(t('rm.esc_notice_title'), 1)->kv([t('rm.notice_no') => $e['code'], t('rm.escalated_at') => $e['escalated_at'], t('rm.escalated_to') => t('enum.rmescto.' . $e['escalated_to']),
            t('rm.source_type') => t('enum.rmsrc.' . $e['source_type']), t('docs.section') => $e['section'] ? t('enum.rmsec.' . $e['section']) : '—',
            t('rm.ceo_informed') => $e['ceo_informed'] ? t('common.yes') : t('rm.ceo_not_informed', ['r' => (string)$e['ceo_excluded_reason']])]);
        $d->heading(t('rm.esc_subject'), 2)->para($e['title'], ['bold' => true])->heading(t('rm.esc_detail'), 2)->para($e['detail']);
        if ($e['response']) {
            $d->heading(t('rm.esc_response'), 2)->para($e['response']);
        }
        $d->box(t('rm.esc_warning_title'), [t('rm.esc_warning')])->table([t('rm.director')], [[''], [t('rm.signature')]]);
        $this->audit('escalation.exported', 'rm_escalation', $id);
        $d->send($e['code'] . '.docx');
    }
}
