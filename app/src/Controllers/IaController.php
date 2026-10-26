<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Db;
use App\Core\Perm;
use App\Core\Request;
use App\Core\Validator;
use App\Support\AuditPlanner;
use App\Support\Ref;

/**
 * Internal audit: audit universe, risk-based annual plan, engagements with workpapers (prepared and independently
 * reviewed), and findings followed up with management until the auditor verifies and closes them.
 */
final class IaController extends Controller
{
    private const RATINGS = ['satisfactory', 'needs_improvement', 'unsatisfactory'];
    private const SEVERITIES = ['high', 'medium', 'low'];
    private const CONCLUSIONS = ['effective', 'partially_effective', 'ineffective', 'not_applicable'];
    private const PER_PAGE = 25;

    // ------------------------------------------------------------------ overview

    public function index(): string
    {
        $today = date('Y-m-d');
        $year = (int)date('Y');
        $plan = Db::one('SELECT * FROM audit_plans WHERE plan_year = ?', [$year]);
        $eng = $plan ? array_column(Db::all('SELECT status, COUNT(*) AS n FROM audit_engagements WHERE plan_id = ? GROUP BY status', [$plan['id']]), 'n', 'status') : [];
        $fs = Db::all("SELECT severity, status, COUNT(*) AS n FROM audit_findings WHERE status <> 'draft' GROUP BY severity, status");
        $sev = [];
        foreach ($fs as $r) {
            if ($r['status'] !== 'closed') {
                $sev[$r['severity']] = ($sev[$r['severity']] ?? 0) + (int)$r['n'];
            }
        }
        $overdue = (int)Db::val("SELECT COUNT(*) FROM audit_findings WHERE (status = 'open' AND response_due < ?) OR (status = 'responded' AND due_date < ?)", [$today, $today]);
        $closed = (int)Db::val("SELECT COUNT(*) FROM audit_findings WHERE status = 'closed'");
        $issued = (int)Db::val("SELECT COUNT(*) FROM audit_findings WHERE status <> 'draft'");
        $onTime = (int)Db::val("SELECT COUNT(*) FROM audit_findings WHERE status = 'closed' AND (original_due_date IS NULL OR DATE(closed_at) <= original_due_date)");
        $ratings = array_column(Db::all("SELECT rating, COUNT(*) AS n FROM audit_engagements WHERE status = 'issued' AND YEAR(issued_at) = ? GROUP BY rating", [$year]), 'n', 'rating');
        return $this->view('ia/index', ['title' => t('ia.title'), 'plan' => $plan, 'eng' => $eng, 'sev' => $sev, 'overdue' => $overdue, 'closed' => $closed, 'issued' => $issued, 'onTime' => $onTime, 'ratings' => $ratings, 'year' => $year]);
    }

    // ------------------------------------------------------------------ audit universe

    public function universe(): string
    {
        $year = (int)date('Y');
        return $this->view('ia/universe', ['title' => t('ia.universe'), 'rows' => AuditPlanner::ranked($year), 'year' => $year,
            'inactive' => Db::all('SELECT u.*, d.name_en AS dept_en, d.name_ar AS dept_ar FROM audit_universe u LEFT JOIN departments d ON d.id = u.department_id WHERE u.is_active = 0 ORDER BY u.code')]);
    }

    public function universeNew(): string
    {
        return $this->view('ia/universe_form', ['title' => t('ia.universe_new'), 'u' => ['materiality' => 3, 'regulatory' => 3, 'complexity' => 3, 'is_active' => 1], 'existing' => null]);
    }

    public function universeEdit(int $id): string
    {
        $u = Db::one('SELECT * FROM audit_universe WHERE id = ?', [$id]) ?? $this->notFound();
        return $this->view('ia/universe_form', ['title' => t('ia.universe_edit'), 'u' => $u, 'existing' => $u]);
    }

    public function universeStore(): never
    {
        $this->universeSave(null);
    }

    public function universeUpdate(int $id): never
    {
        $this->universeSave(Db::one('SELECT * FROM audit_universe WHERE id = ?', [$id]) ?? $this->notFound());
    }

    private function universeSave(?array $ex): never
    {
        $v = new Validator($_POST);
        $v->required('name', 'ia.u_name')->max('name', 'ia.u_name', 200)->max('description', 'ia.u_description', 4000)
            ->intRange('materiality', 'ia.materiality', 1, 5)->intRange('regulatory', 'ia.regulatory', 1, 5)->intRange('complexity', 'ia.complexity', 1, 5)
            ->date('last_audit_date', 'ia.last_audit', false)->in('last_rating', 'ia.last_rating', self::RATINGS, false);
        $dept = Request::int('department_id');
        if ($dept !== null && !isset(Ref::departments()[$dept])) {
            $v->fail('department_id', 'validation.invalid_choice', ['field' => t('ia.department')]);
        }
        if ($v->str('last_audit_date') > date('Y-m-d')) {
            $v->fail('last_audit_date', 'validation.not_future', ['field' => t('ia.last_audit')]);
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), $ex ? url('ia/universe/' . $ex['id'] . '/edit') : url('ia/universe/new'));
        }
        $now = date('Y-m-d H:i:s');
        $d = ['name' => $v->str('name'), 'department_id' => $dept, 'description' => $v->str('description') ?: null, 'materiality' => (int)$v->str('materiality'), 'regulatory' => (int)$v->str('regulatory'), 'complexity' => (int)$v->str('complexity'),
            'last_audit_date' => $v->str('last_audit_date') ?: null, 'last_rating' => $v->str('last_rating') ?: null, 'is_active' => isset($_POST['is_active']) ? 1 : 0, 'updated_at' => $now];
        if ($ex) {
            Db::update('audit_universe', $d, 'id = ?', [$ex['id']]);
            $this->audit('ia.universe_updated', 'audit_universe', (int)$ex['id'], ['changes' => $this->diff($ex, $d, array_keys($d))]);
        } else {
            $id = Db::insert('audit_universe', $d + ['created_by' => $this->user()['id'], 'created_at' => $now]);
            Db::update('audit_universe', ['code' => sprintf('AU-%03d', $id)], 'id = ?', [$id]);
            $this->audit('ia.universe_created', 'audit_universe', $id, ['name' => $d['name']]);
        }
        $this->redirect(url('ia/universe'), 'admin.saved');
    }

    // ------------------------------------------------------------------ plans

    public function plans(): string
    {
        $rows = Db::all("SELECT p.*, (SELECT COUNT(*) FROM audit_engagements e WHERE e.plan_id = p.id AND e.status <> 'cancelled') AS n, (SELECT COUNT(*) FROM audit_engagements e WHERE e.plan_id = p.id AND e.status = 'issued') AS done FROM audit_plans p ORDER BY p.plan_year DESC");
        return $this->view('ia/plans', ['title' => t('ia.plans'), 'rows' => $rows]);
    }

    public function planStore(): never
    {
        $v = new Validator($_POST);
        $v->intRange('plan_year', 'ia.plan_year', 2000, 2100)->required('title', 'ia.plan_title')->max('title', 'ia.plan_title', 200);
        if ($v->ok() && Db::val('SELECT 1 FROM audit_plans WHERE plan_year = ?', [(int)$v->str('plan_year')])) {
            $v->fail('plan_year', 'ia.err_plan_exists');
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), url('ia/plans'));
        }
        $now = date('Y-m-d H:i:s');
        $id = Db::insert('audit_plans', ['plan_year' => (int)$v->str('plan_year'), 'title' => $v->str('title'), 'status' => 'draft', 'created_by' => $this->user()['id'], 'created_at' => $now, 'updated_at' => $now]);
        $this->audit('ia.plan_created', 'audit_plan', $id, ['year' => (int)$v->str('plan_year')]);
        $this->redirect(url('ia/plans/' . $id), 'admin.saved');
    }

    public function planShow(int $id): string
    {
        $p = Db::one('SELECT p.*, u.full_name AS recorder FROM audit_plans p LEFT JOIN users u ON u.id = p.recorded_by WHERE p.id = ?', [$id]) ?? $this->notFound();
        $eng = Db::all('SELECT e.*, u.code AS u_code, u.name AS u_name, l.full_name AS lead FROM audit_engagements e JOIN audit_universe u ON u.id = e.universe_id JOIN users l ON l.id = e.lead_user_id WHERE e.plan_id = ? ORDER BY e.planned_start, e.id', [$id]);
        $inPlan = array_map('intval', array_column(array_filter($eng, fn($e) => $e['status'] !== 'cancelled'), 'universe_id'));
        $ranked = AuditPlanner::ranked((int)$p['plan_year']);
        $dueHigh = array_filter($ranked, fn($r) => $r['due'] && $r['priority'] === 'high');
        $covered = array_filter($dueHigh, fn($r) => in_array((int)$r['id'], $inPlan, true));
        $candidates = array_values(array_filter($ranked, fn($r) => !in_array((int)$r['id'], $inPlan, true)));
        return $this->view('ia/plan_show', ['title' => t('ia.plan') . ' ' . $p['plan_year'], 'p' => $p, 'eng' => $eng, 'candidates' => $candidates,
            'coverage' => [count($covered), count($dueHigh)], 'leads' => $this->auditors()]);
    }

    /** Active users who can lead an audit (internal auditors and administrators). */
    private function auditors(): array
    {
        return array_filter(Ref::users(true), fn($u) => Perm::roleHas($u['role'], 'ia.manage'));
    }

    public function engagementStore(int $id): never
    {
        $p = Db::one('SELECT * FROM audit_plans WHERE id = ?', [$id]) ?? $this->notFound();
        $back = url('ia/plans/' . $id) . '#add';
        if ($p['status'] !== 'draft') {
            $this->redirect(url('ia/plans/' . $id), 'ia.err_plan_locked', 'error');
        }
        $v = new Validator($_POST);
        $v->required('universe_id', 'ia.entity')->required('title', 'ia.e_title')->max('title', 'ia.e_title', 250)->required('objective', 'ia.objective')->max('objective', 'ia.objective', 4000)->max('scope', 'ia.scope', 4000)
            ->required('lead_user_id', 'ia.lead')->date('planned_start', 'ia.planned_start')->date('planned_end', 'ia.planned_end');
        $uid = Request::int('universe_id');
        $ranked = array_column(AuditPlanner::ranked((int)$p['plan_year']), null, 'id');
        if ($uid !== null && !isset($ranked[$uid])) {
            $v->fail('universe_id', 'validation.invalid_choice', ['field' => t('ia.entity')]);
        } elseif ($uid !== null && Db::val("SELECT 1 FROM audit_engagements WHERE plan_id = ? AND universe_id = ? AND status <> 'cancelled'", [$id, $uid])) {
            $v->fail('universe_id', 'ia.err_in_plan');
        }
        $lead = Request::int('lead_user_id');
        if ($lead !== null && !isset($this->auditors()[$lead])) {
            $v->fail('lead_user_id', 'ia.err_lead');
        }
        $y = (string)$p['plan_year'];
        foreach (['planned_start', 'planned_end'] as $f) {
            if ($v->str($f) !== '' && substr($v->str($f), 0, 4) !== $y) {
                $v->fail($f, 'ia.err_outside_year', ['y' => $y]);
            }
        }
        if ($v->str('planned_end') !== '' && $v->str('planned_end') < $v->str('planned_start')) {
            $v->fail('planned_end', 'validation.after_date', ['field' => t('ia.planned_end'), 'other' => t('ia.planned_start')]);
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), $back);
        }
        $now = date('Y-m-d H:i:s');
        $eid = Db::insert('audit_engagements', ['plan_id' => $id, 'universe_id' => $uid, 'title' => $v->str('title'), 'objective' => $v->str('objective'), 'scope' => $v->str('scope') ?: null, 'lead_user_id' => $lead,
            'planned_start' => $v->str('planned_start'), 'planned_end' => $v->str('planned_end'), 'priority_score' => $ranked[$uid]['score'], 'status' => 'planned', 'created_by' => $this->user()['id'], 'created_at' => $now, 'updated_at' => $now]);
        $seq = (int)Db::val('SELECT COUNT(*) FROM audit_engagements WHERE plan_id = ? AND id <= ?', [$id, $eid]);
        Db::update('audit_engagements', ['code' => sprintf('IA-%s-%02d', $y, $seq)], 'id = ?', [$eid]);
        $this->audit('ia.engagement_planned', 'audit_engagement', $eid, ['plan' => $id, 'universe' => $uid]);
        $this->redirect(url('ia/plans/' . $id), 'ia.engagement_added');
    }

    /** Record the audit committee's approval of the plan. The plan is then locked. */
    public function planApprove(int $id): never
    {
        $p = Db::one('SELECT * FROM audit_plans WHERE id = ?', [$id]) ?? $this->notFound();
        if ($p['status'] !== 'draft') {
            $this->redirect(url('ia/plans/' . $id), 'ia.err_plan_locked', 'error');
        }
        $v = new Validator($_POST);
        $v->required('approval_body', 'ia.approval_body')->max('approval_body', 'ia.approval_body', 150)->max('approval_ref', 'ia.approval_ref', 80)->date('approved_on', 'ia.approved_on')->max('notes', 'ia.notes', 4000);
        if ($v->str('approved_on') > date('Y-m-d')) {
            $v->fail('approved_on', 'validation.not_future', ['field' => t('ia.approved_on')]);
        }
        if (!(int)Db::val("SELECT COUNT(*) FROM audit_engagements WHERE plan_id = ? AND status <> 'cancelled'", [$id])) {
            $v->fail('approval_body', 'ia.err_plan_empty');
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), url('ia/plans/' . $id) . '#approve');
        }
        Db::update('audit_plans', ['status' => 'approved', 'approval_body' => $v->str('approval_body'), 'approval_ref' => $v->str('approval_ref') ?: null, 'approved_on' => $v->str('approved_on'), 'notes' => $v->str('notes') ?: null,
            'recorded_by' => $this->user()['id'], 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        $this->audit('ia.plan_approved', 'audit_plan', $id, ['body' => $v->str('approval_body'), 'ref' => $v->str('approval_ref'), 'on' => $v->str('approved_on')]);
        $this->redirect(url('ia/plans/' . $id), 'ia.plan_approved');
    }

    // ------------------------------------------------------------------ engagements

    private function engagement(int $id): array
    {
        return Db::one('SELECT e.*, p.plan_year, p.status AS plan_status, u.code AS u_code, u.name AS u_name, u.department_id AS u_dept, l.full_name AS lead FROM audit_engagements e JOIN audit_plans p ON p.id = e.plan_id JOIN audit_universe u ON u.id = e.universe_id JOIN users l ON l.id = e.lead_user_id WHERE e.id = ?', [$id]) ?? $this->notFound();
    }

    public function engagementShow(int $id): string
    {
        $e = $this->engagement($id);
        $wps = Db::all('SELECT w.*, c.code AS ctl_code, pu.full_name AS preparer, ru.full_name AS reviewer FROM audit_workpapers w LEFT JOIN controls c ON c.id = w.control_id LEFT JOIN users pu ON pu.id = w.prepared_by LEFT JOIN users ru ON ru.id = w.reviewed_by WHERE w.engagement_id = ? ORDER BY w.ref', [$id]);
        $fd = Db::all('SELECT f.*, o.full_name AS owner FROM audit_findings f JOIN users o ON o.id = f.owner_user_id WHERE f.engagement_id = ? ORDER BY FIELD(f.severity, \'high\', \'medium\', \'low\'), f.id', [$id]);
        if (!can('ia.manage')) {
            $fd = array_values(array_filter($fd, fn($f) => $f['status'] !== 'draft'));
        }
        $controls = Db::all('SELECT id, code, name FROM controls WHERE archived_at IS NULL ORDER BY code LIMIT 500');
        return $this->view('ia/engagement_show', ['title' => $e['code'] . ' — ' . $e['title'], 'e' => $e, 'wps' => $wps, 'findings' => $fd, 'controls' => $controls]);
    }

    private function transition(int $id, string $from, string $action): array
    {
        $e = $this->engagement($id);
        if (!in_array($e['status'], (array)explode('|', $from), true)) {
            $this->redirect(url('ia/engagements/' . $id), 'ia.err_state', 'error');
        }
        return $e;
    }

    public function engagementStart(int $id): never
    {
        $e = $this->transition($id, 'planned', 'start');
        if ($e['plan_status'] !== 'approved') {
            $this->redirect(url('ia/engagements/' . $id), 'ia.err_plan_not_approved', 'error');
        }
        Db::update('audit_engagements', ['status' => 'fieldwork', 'actual_start' => date('Y-m-d'), 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        $this->audit('ia.fieldwork_started', 'audit_engagement', $id);
        $this->redirect(url('ia/engagements/' . $id), 'ia.started');
    }

    public function engagementReporting(int $id): never
    {
        $this->transition($id, 'fieldwork', 'reporting');
        $n = (int)Db::val('SELECT COUNT(*) FROM audit_workpapers WHERE engagement_id = ?', [$id]);
        $open = (int)Db::val("SELECT COUNT(*) FROM audit_workpapers WHERE engagement_id = ? AND status <> 'reviewed'", [$id]);
        if ($n === 0 || $open > 0) {
            $this->redirect(url('ia/engagements/' . $id), 'ia.err_wp_unreviewed', 'error');
        }
        Db::update('audit_engagements', ['status' => 'reporting', 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        $this->audit('ia.reporting_started', 'audit_engagement', $id);
        $this->redirect(url('ia/engagements/' . $id), 'ia.moved_reporting');
    }

    /** Issue the report: findings go to management for a response within 14 days; the universe remembers the date and rating. */
    public function engagementIssue(int $id): never
    {
        $e = $this->transition($id, 'reporting', 'issue');
        $v = new Validator($_POST);
        $v->in('rating', 'ia.rating', self::RATINGS)->required('report_summary', 'ia.report_summary')->max('report_summary', 'ia.report_summary', 8000);
        if (!$v->ok()) {
            $this->invalid($v->errors(), url('ia/engagements/' . $id) . '#issue');
        }
        $today = date('Y-m-d');
        $now = date('Y-m-d H:i:s');
        $n = Db::tx(function () use ($id, $e, $v, $today, $now) {
            Db::update('audit_engagements', ['status' => 'issued', 'rating' => $v->str('rating'), 'report_summary' => $v->str('report_summary'), 'issued_at' => $now, 'actual_end' => $today, 'updated_at' => $now], 'id = ?', [$id]);
            Db::update('audit_universe', ['last_audit_date' => $today, 'last_rating' => $v->str('rating'), 'updated_at' => $now], 'id = ?', [$e['universe_id']]);
            return Db::exec("UPDATE audit_findings SET status = 'open', response_due = ?, updated_at = ? WHERE engagement_id = ? AND status = 'draft'", [date('Y-m-d', strtotime('+14 days')), $now, $id]);
        });
        $this->audit('ia.report_issued', 'audit_engagement', $id, ['rating' => $v->str('rating'), 'findings' => $n]);
        $this->redirect(url('ia/engagements/' . $id), 'ia.issued', 'success', ['n' => $n]);
    }

    public function engagementCancel(int $id): never
    {
        $this->transition($id, 'planned|fieldwork', 'cancel');
        $v = new Validator($_POST);
        $v->required('cancel_reason', 'ia.cancel_reason')->max('cancel_reason', 'ia.cancel_reason', 2000);
        if (!$v->ok()) {
            $this->invalid($v->errors(), url('ia/engagements/' . $id) . '#cancel');
        }
        Db::update('audit_engagements', ['status' => 'cancelled', 'cancel_reason' => $v->str('cancel_reason'), 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        $this->audit('ia.engagement_cancelled', 'audit_engagement', $id, ['reason' => $v->str('cancel_reason')]);
        $this->redirect(url('ia/engagements/' . $id), 'ia.cancelled');
    }

    // ------------------------------------------------------------------ workpapers

    public function workpaperStore(int $id): never
    {
        $this->transition($id, 'fieldwork', 'workpaper');
        $v = new Validator($_POST);
        $v->required('title', 'ia.wp_title')->max('title', 'ia.wp_title', 250)->required('procedure_text', 'ia.procedure')->max('procedure_text', 'ia.procedure', 8000);
        $ctl = Request::int('control_id');
        if ($ctl !== null && !Db::val('SELECT 1 FROM controls WHERE id = ? AND archived_at IS NULL', [$ctl])) {
            $v->fail('control_id', 'validation.invalid_choice', ['field' => t('ia.control')]);
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), url('ia/engagements/' . $id) . '#add-wp');
        }
        $now = date('Y-m-d H:i:s');
        $seq = (int)Db::val('SELECT COUNT(*) FROM audit_workpapers WHERE engagement_id = ?', [$id]) + 1;
        $wid = Db::insert('audit_workpapers', ['engagement_id' => $id, 'ref' => sprintf('WP-%02d', $seq), 'title' => $v->str('title'), 'procedure_text' => $v->str('procedure_text'), 'control_id' => $ctl, 'status' => 'open', 'created_by' => $this->user()['id'], 'created_at' => $now, 'updated_at' => $now]);
        $this->audit('ia.workpaper_created', 'audit_workpaper', $wid, ['engagement' => $id]);
        $this->redirect(url('ia/workpapers/' . $wid), 'admin.saved');
    }

    private function workpaper(int $id): array
    {
        return Db::one('SELECT w.*, e.code AS e_code, e.title AS e_title, e.status AS e_status, c.code AS ctl_code, c.name AS ctl_name, pu.full_name AS preparer, ru.full_name AS reviewer FROM audit_workpapers w JOIN audit_engagements e ON e.id = w.engagement_id LEFT JOIN controls c ON c.id = w.control_id LEFT JOIN users pu ON pu.id = w.prepared_by LEFT JOIN users ru ON ru.id = w.reviewed_by WHERE w.id = ?', [$id]) ?? $this->notFound();
    }

    public function workpaperShow(int $id): string
    {
        $w = $this->workpaper($id);
        $att = Db::all("SELECT a.*, u.full_name FROM attachments a JOIN users u ON u.id = a.uploaded_by WHERE a.entity_type = 'audit_workpaper' AND a.entity_id = ? AND a.deleted_at IS NULL ORDER BY a.id DESC", [$id]);
        return $this->view('ia/workpaper_show', ['title' => $w['ref'] . ' — ' . $w['title'], 'w' => $w, 'attachments' => $att, 'me' => (int)$this->user()['id']]);
    }

    /** The auditor records the test performed and the conclusion; the workpaper then waits for review by another auditor. */
    public function workpaperSave(int $id): never
    {
        $w = $this->workpaper($id);
        if ($w['e_status'] !== 'fieldwork' || $w['status'] === 'reviewed') {
            $this->redirect(url('ia/workpapers/' . $id), 'ia.err_state', 'error');
        }
        $v = new Validator($_POST);
        $v->intRange('sample_size', 'ia.sample', 0, 100000)->intRange('exceptions', 'ia.exceptions', 0, 100000)->in('conclusion', 'ia.conclusion', self::CONCLUSIONS)->required('result_text', 'ia.result')->max('result_text', 'ia.result', 8000);
        if ($v->ok() && (int)$v->str('exceptions') > (int)$v->str('sample_size')) {
            $v->fail('exceptions', 'ia.err_exceptions');
        }
        if ($v->ok() && $v->str('conclusion') === 'effective' && (int)$v->str('exceptions') > 0) {
            $v->fail('conclusion', 'ia.err_effective_with_exceptions');
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), url('ia/workpapers/' . $id) . '#result');
        }
        $now = date('Y-m-d H:i:s');
        Db::update('audit_workpapers', ['sample_size' => (int)$v->str('sample_size'), 'exceptions' => (int)$v->str('exceptions'), 'conclusion' => $v->str('conclusion'), 'result_text' => $v->str('result_text'), 'status' => 'prepared',
            'prepared_by' => $this->user()['id'], 'prepared_at' => $now, 'reviewed_by' => null, 'reviewed_at' => null, 'updated_at' => $now], 'id = ?', [$id]);
        $this->audit('ia.workpaper_prepared', 'audit_workpaper', $id, ['conclusion' => $v->str('conclusion'), 'exceptions' => (int)$v->str('exceptions')]);
        $this->redirect(url('ia/workpapers/' . $id), 'ia.wp_prepared');
    }

    /** Independent review (maker-checker): the reviewer must not be the auditor who prepared the workpaper. */
    public function workpaperReview(int $id): never
    {
        $w = $this->workpaper($id);
        $u = $this->user();
        if ($w['e_status'] !== 'fieldwork' || $w['status'] !== 'prepared') {
            $this->redirect(url('ia/workpapers/' . $id), 'ia.err_state', 'error');
        }
        if ((int)$w['prepared_by'] === (int)$u['id']) {
            $this->redirect(url('ia/workpapers/' . $id), 'ia.err_self_review', 'error');
        }
        $v = new Validator($_POST);
        $v->in('decision', 'ia.decision', ['approve', 'return'])->max('review_note', 'ia.review_note', 4000);
        if ($v->str('decision') === 'return' && $v->str('review_note') === '') {
            $v->fail('review_note', 'ia.err_note_required');
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), url('ia/workpapers/' . $id) . '#review');
        }
        $ok = $v->str('decision') === 'approve';
        Db::update('audit_workpapers', ['status' => $ok ? 'reviewed' : 'open', 'reviewed_by' => $ok ? $u['id'] : null, 'reviewed_at' => $ok ? date('Y-m-d H:i:s') : null, 'review_note' => $v->str('review_note') ?: null, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        $this->audit($ok ? 'ia.workpaper_reviewed' : 'ia.workpaper_returned', 'audit_workpaper', $id, ['note' => $v->str('review_note')]);
        $this->redirect(url('ia/workpapers/' . $id), $ok ? 'ia.wp_reviewed' : 'ia.wp_returned');
    }

    public function workpaperUpload(int $id): never
    {
        $w = $this->workpaper($id);
        if ($w['e_status'] !== 'fieldwork') {
            $this->redirect(url('ia/workpapers/' . $id), 'ia.err_state', 'error');
        }
        AttachmentController::handleUpload('audit_workpaper', $id, $this->user(), url('ia/workpapers/' . $id) . '#attachments');
    }

    // ------------------------------------------------------------------ findings

    /** Who may see which findings: auditors all; other viewers everything issued; department managers their department or their own. */
    public static function findingScope(array $u, string $a = 'f'): array
    {
        if (Perm::roleHas($u['role'], 'ia.manage')) {
            return ['1 = 1', []];
        }
        if (Perm::roleHas($u['role'], 'ia.view')) {
            return ["$a.status <> 'draft'", []];
        }
        return ["$a.status <> 'draft' AND ($a.department_id = ? OR $a.owner_user_id = ?)", [(int)($u['department_id'] ?? 0), (int)$u['id']]];
    }

    private function finding(int $id): array
    {
        [$w, $p] = self::findingScope($this->user());
        return Db::one("SELECT f.*, e.code AS e_code, e.title AS e_title, e.status AS e_status, o.full_name AS owner, vu.full_name AS verifier, d.name_en AS dept_en, d.name_ar AS dept_ar, r.risk_code, r.title AS risk_title, w.ref AS wp_ref
            FROM audit_findings f JOIN audit_engagements e ON e.id = f.engagement_id JOIN users o ON o.id = f.owner_user_id JOIN departments d ON d.id = f.department_id LEFT JOIN users vu ON vu.id = f.verified_by LEFT JOIN risks r ON r.id = f.risk_id LEFT JOIN audit_workpapers w ON w.id = f.workpaper_id
            WHERE f.id = ? AND $w", array_merge([$id], $p)) ?? $this->notFound();
    }

    public function findings(): string
    {
        $u = $this->user();
        [$w, $p] = self::findingScope($u);
        $f = ['status' => Request::str('status', true), 'severity' => Request::str('severity', true), 'overdue' => Request::str('overdue', true) === '1' ? '1' : '', 'department' => Request::int('department', true)];
        $where = [$w];
        if (in_array($f['status'], ['draft', 'open', 'responded', 'implemented', 'closed'], true)) {
            $where[] = 'f.status = ?';
            $p[] = $f['status'];
        } elseif ($f['status'] === 'not_closed') {
            $where[] = "f.status NOT IN ('closed','draft')";
        }
        if (in_array($f['severity'], self::SEVERITIES, true)) {
            $where[] = 'f.severity = ?';
            $p[] = $f['severity'];
        }
        if ($f['overdue'] === '1') {
            $where[] = "((f.status = 'open' AND f.response_due < CURDATE()) OR (f.status = 'responded' AND f.due_date < CURDATE()))";
        }
        if ($f['department']) {
            $where[] = 'f.department_id = ?';
            $p[] = $f['department'];
        }
        $wsql = implode(' AND ', $where);
        $total = (int)Db::val("SELECT COUNT(*) FROM audit_findings f WHERE $wsql", $p);
        $page = min($this->page(), max(1, (int)ceil($total / self::PER_PAGE)));
        $rows = Db::all("SELECT f.*, e.code AS e_code, o.full_name AS owner, d.name_en AS dept_en, d.name_ar AS dept_ar FROM audit_findings f JOIN audit_engagements e ON e.id = f.engagement_id JOIN users o ON o.id = f.owner_user_id JOIN departments d ON d.id = f.department_id
            WHERE $wsql ORDER BY FIELD(f.status, 'open', 'responded', 'implemented', 'draft', 'closed'), FIELD(f.severity, 'high', 'medium', 'low'), COALESCE(f.due_date, f.response_due), f.id LIMIT ? OFFSET ?", array_merge($p, [self::PER_PAGE, ($page - 1) * self::PER_PAGE]));
        return $this->view('ia/findings', ['title' => t('ia.findings'), 'rows' => $rows, 'f' => $f, 'total' => $total, 'page' => $page, 'per' => self::PER_PAGE]);
    }

    public function findingShow(int $id): string
    {
        $f = $this->finding($id);
        $att = Db::all("SELECT a.*, u.full_name FROM attachments a JOIN users u ON u.id = a.uploaded_by WHERE a.entity_type = 'audit_finding' AND a.entity_id = ? AND a.deleted_at IS NULL ORDER BY a.id DESC", [$id]);
        return $this->view('ia/finding_show', ['title' => $f['code'] . ' — ' . $f['title'], 'f' => $f, 'attachments' => $att, 'me' => (int)$this->user()['id']]);
    }

    public function findingNew(int $id): string
    {
        $e = $this->engagement($id);
        if (!in_array($e['status'], ['fieldwork', 'reporting'], true)) {
            $this->redirect(url('ia/engagements/' . $id), 'ia.err_state', 'error');
        }
        return $this->view('ia/finding_form', ['title' => t('ia.finding_new'), 'e' => $e, 'f' => ['department_id' => $e['u_dept'], 'severity' => 'medium'], 'existing' => null] + $this->findingOptions($id));
    }

    public function findingEdit(int $id): string
    {
        $f = $this->finding($id);
        if ($f['status'] !== 'draft') {
            $this->redirect(url('ia/findings/' . $id), 'ia.err_finding_locked', 'error');
        }
        return $this->view('ia/finding_form', ['title' => t('ia.finding_edit'), 'e' => $this->engagement((int)$f['engagement_id']), 'f' => $f, 'existing' => $f] + $this->findingOptions((int)$f['engagement_id']));
    }

    private function findingOptions(int $engagementId): array
    {
        return ['owners' => $this->owners(), 'risks' => Db::all('SELECT id, risk_code, title FROM risks WHERE archived_at IS NULL ORDER BY risk_code LIMIT 1000'),
            'wps' => Db::all('SELECT id, ref, title FROM audit_workpapers WHERE engagement_id = ? ORDER BY ref', [$engagementId])];
    }

    /** Management users who can own and answer a finding (never the auditors themselves). */
    private function owners(): array
    {
        return array_filter(Ref::users(true), fn($u) => Perm::roleHas($u['role'], 'ia.respond') && !Perm::roleHas($u['role'], 'ia.manage'));
    }

    public function findingStore(int $id): never
    {
        $e = $this->engagement($id);
        if (!in_array($e['status'], ['fieldwork', 'reporting'], true)) {
            $this->redirect(url('ia/engagements/' . $id), 'ia.err_state', 'error');
        }
        $this->findingSave($e, null);
    }

    public function findingUpdate(int $id): never
    {
        $f = $this->finding($id);
        if ($f['status'] !== 'draft') {
            $this->redirect(url('ia/findings/' . $id), 'ia.err_finding_locked', 'error');
        }
        $this->findingSave($this->engagement((int)$f['engagement_id']), $f);
    }

    private function findingSave(array $e, ?array $ex): never
    {
        $v = new Validator($_POST);
        $v->required('title', 'ia.f_title')->max('title', 'ia.f_title', 250)->required('condition_text', 'ia.condition')->max('condition_text', 'ia.condition', 8000)->max('criteria_text', 'ia.criteria', 4000)->max('cause_text', 'ia.cause', 4000)
            ->max('effect_text', 'ia.effect', 4000)->required('recommendation', 'ia.recommendation')->max('recommendation', 'ia.recommendation', 4000)->in('severity', 'ia.severity', self::SEVERITIES)->required('department_id', 'ia.department')->required('owner_user_id', 'ia.owner');
        $dept = Request::int('department_id');
        if ($dept !== null && !isset(Ref::departments(true)[$dept])) {
            $v->fail('department_id', 'validation.invalid_choice', ['field' => t('ia.department')]);
        }
        $owner = Request::int('owner_user_id');
        if ($owner !== null && !isset($this->owners()[$owner])) {
            $v->fail('owner_user_id', 'ia.err_owner');
        }
        $risk = Request::int('risk_id');
        if ($risk !== null && !Db::val('SELECT 1 FROM risks WHERE id = ? AND archived_at IS NULL', [$risk])) {
            $v->fail('risk_id', 'validation.invalid_choice', ['field' => t('ia.risk')]);
        }
        $wp = Request::int('workpaper_id');
        if ($wp !== null && !Db::val('SELECT 1 FROM audit_workpapers WHERE id = ? AND engagement_id = ?', [$wp, $e['id']])) {
            $v->fail('workpaper_id', 'validation.invalid_choice', ['field' => t('ia.workpaper')]);
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), $ex ? url('ia/findings/' . $ex['id'] . '/edit') : url('ia/engagements/' . $e['id'] . '/findings/new'));
        }
        $now = date('Y-m-d H:i:s');
        $d = ['title' => $v->str('title'), 'condition_text' => $v->str('condition_text'), 'criteria_text' => $v->str('criteria_text') ?: null, 'cause_text' => $v->str('cause_text') ?: null, 'effect_text' => $v->str('effect_text') ?: null,
            'recommendation' => $v->str('recommendation'), 'severity' => $v->str('severity'), 'department_id' => $dept, 'owner_user_id' => $owner, 'risk_id' => $risk, 'workpaper_id' => $wp, 'updated_at' => $now];
        if ($ex) {
            Db::update('audit_findings', $d, 'id = ?', [$ex['id']]);
            $this->audit('ia.finding_updated', 'audit_finding', (int)$ex['id'], ['changes' => $this->diff($ex, $d, ['title', 'severity', 'department_id', 'owner_user_id', 'risk_id'])]);
            $id = (int)$ex['id'];
        } else {
            $id = Db::insert('audit_findings', $d + ['engagement_id' => $e['id'], 'status' => 'draft', 'created_by' => $this->user()['id'], 'created_at' => $now]);
            Db::update('audit_findings', ['code' => sprintf('AF-%04d', $id)], 'id = ?', [$id]);
            $this->audit('ia.finding_created', 'audit_finding', $id, ['engagement' => $e['id'], 'severity' => $d['severity']]);
        }
        $this->redirect(url('ia/findings/' . $id), 'admin.saved');
    }

    /** Management response: the owner agrees an action and a target date. */
    public function findingRespond(int $id): never
    {
        $f = $this->finding($id);
        $this->ownerOnly($f, 'open');
        $v = new Validator($_POST);
        $v->required('management_response', 'ia.mgmt_response')->max('management_response', 'ia.mgmt_response', 4000)->required('agreed_action', 'ia.agreed_action')->max('agreed_action', 'ia.agreed_action', 4000)->date('due_date', 'ia.due_date');
        if ($v->str('due_date') !== '' && $v->str('due_date') < date('Y-m-d')) {
            $v->fail('due_date', 'ia.err_due_past');
        } elseif ($v->str('due_date') > date('Y-m-d', strtotime('+1 year'))) {
            $v->fail('due_date', 'ia.err_due_far');
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), url('ia/findings/' . $id) . '#respond');
        }
        Db::update('audit_findings', ['status' => 'responded', 'management_response' => $v->str('management_response'), 'agreed_action' => $v->str('agreed_action'), 'due_date' => $v->str('due_date'),
            'original_due_date' => $f['original_due_date'] ?? $v->str('due_date'), 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        $this->audit('ia.finding_responded', 'audit_finding', $id, ['due' => $v->str('due_date')]);
        $this->redirect(url('ia/findings/' . $id), 'ia.responded');
    }

    public function findingImplemented(int $id): never
    {
        $f = $this->finding($id);
        $this->ownerOnly($f, 'responded');
        $v = new Validator($_POST);
        $v->required('implemented_note', 'ia.implemented_note')->max('implemented_note', 'ia.implemented_note', 4000);
        if (!$v->ok()) {
            $this->invalid($v->errors(), url('ia/findings/' . $id) . '#implement');
        }
        Db::update('audit_findings', ['status' => 'implemented', 'implemented_note' => $v->str('implemented_note'), 'implemented_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        $this->audit('ia.finding_implemented', 'audit_finding', $id);
        $this->redirect(url('ia/findings/' . $id), 'ia.implemented');
    }

    private function ownerOnly(array $f, string $status): void
    {
        if ((int)$f['owner_user_id'] !== (int)$this->user()['id']) {
            $this->redirect(url('ia/findings/' . $f['id']), 'ia.err_not_owner', 'error');
        }
        if ($f['status'] !== $status) {
            $this->redirect(url('ia/findings/' . $f['id']), 'ia.err_state', 'error');
        }
    }

    /** The auditor checks the evidence: close the finding, or send it back to management. */
    public function findingVerify(int $id): never
    {
        $f = $this->finding($id);
        if ($f['status'] !== 'implemented') {
            $this->redirect(url('ia/findings/' . $id), 'ia.err_state', 'error');
        }
        $v = new Validator($_POST);
        $v->in('decision', 'ia.decision', ['close', 'return'])->required('verification_note', 'ia.verification_note')->max('verification_note', 'ia.verification_note', 4000);
        if (!$v->ok()) {
            $this->invalid($v->errors(), url('ia/findings/' . $id) . '#verify');
        }
        $close = $v->str('decision') === 'close';
        $now = date('Y-m-d H:i:s');
        Db::update('audit_findings', ['status' => $close ? 'closed' : 'responded', 'verification_note' => $v->str('verification_note'), 'verified_by' => $close ? $this->user()['id'] : null, 'closed_at' => $close ? $now : null, 'updated_at' => $now], 'id = ?', [$id]);
        $this->audit($close ? 'ia.finding_closed' : 'ia.finding_returned', 'audit_finding', $id, ['note' => $v->str('verification_note')]);
        $this->redirect(url('ia/findings/' . $id), $close ? 'ia.closed' : 'ia.returned');
    }

    /** Agree a later target date with management (counted, and kept in the audit log). */
    public function findingExtend(int $id): never
    {
        $f = $this->finding($id);
        if ($f['status'] !== 'responded') {
            $this->redirect(url('ia/findings/' . $id), 'ia.err_state', 'error');
        }
        $v = new Validator($_POST);
        $v->date('new_due_date', 'ia.new_due_date')->required('reason', 'ia.extend_reason')->max('reason', 'ia.extend_reason', 2000);
        if ($v->str('new_due_date') !== '' && $v->str('new_due_date') <= (string)$f['due_date']) {
            $v->fail('new_due_date', 'ia.err_extend_later');
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), url('ia/findings/' . $id) . '#extend');
        }
        Db::update('audit_findings', ['due_date' => $v->str('new_due_date'), 'extension_count' => (int)$f['extension_count'] + 1, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        $this->audit('ia.finding_extended', 'audit_finding', $id, ['from' => $f['due_date'], 'to' => $v->str('new_due_date'), 'reason' => $v->str('reason')]);
        $this->redirect(url('ia/findings/' . $id), 'ia.extended');
    }

    public function findingUpload(int $id): never
    {
        $f = $this->finding($id);
        $u = $this->user();
        $auditor = Perm::roleHas($u['role'], 'ia.manage');
        if (!$auditor && ((int)$f['owner_user_id'] !== (int)$u['id'] || !in_array($f['status'], ['open', 'responded'], true))) {
            $this->redirect(url('ia/findings/' . $id), 'ia.err_not_owner', 'error');
        }
        AttachmentController::handleUpload('audit_finding', $id, $u, url('ia/findings/' . $id) . '#attachments');
    }
}
