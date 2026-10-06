<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Db;
use App\Core\Money;
use App\Core\Perm;
use App\Core\Request;
use App\Core\Scope;
use App\Core\Validator;
use App\Support\Ref;

/** Sharia compliance: Sharia Supervisory Board rulings, Sharia reviews, non-compliance events and income purification. */
final class ShariaController extends Controller
{
    private const PER_PAGE = 25;
    public const RULING_TYPES = ['fatwa', 'resolution', 'guideline'];
    public const RULING_STATUS = ['active', 'superseded', 'withdrawn'];
    public const REVIEW_TYPES = ['product', 'contract', 'internal_audit', 'other'];
    public const SEVERITIES = ['low', 'medium', 'high'];

    // ---------- hub ----------
    public function index(): string
    {
        $u = $this->user();
        [$w, $p] = Scope::sqlDept($u, 'e');
        $stats = Db::one("SELECT COUNT(*) AS total, SUM(e.status <> 'closed') AS open_n, SUM(e.status <> 'closed' AND e.severity = 'high') AS high_n, SUM(e.purification_status = 'pending') AS pending_n FROM sharia_events e WHERE $w", $p);
        $rulings = Db::val("SELECT COUNT(*) FROM sharia_rulings WHERE status = 'active'");
        $reviewsOpen = Db::val("SELECT COUNT(*) FROM sharia_reviews WHERE status <> 'completed'");
        $reviewsLate = Db::val("SELECT COUNT(*) FROM sharia_reviews WHERE status <> 'completed' AND planned_date < CURDATE()");
        $events = Db::all("SELECT e.*, d.name_en AS dept_en, d.name_ar AS dept_ar FROM sharia_events e JOIN departments d ON d.id = e.department_id WHERE e.status <> 'closed' AND $w ORDER BY FIELD(e.severity,'high','medium','low'), e.detected_on DESC LIMIT 8", $p);
        return $this->view('sharia/index', ['title' => t('sha.title'), 'stats' => $stats, 'rulings' => (int)$rulings, 'reviewsOpen' => (int)$reviewsOpen, 'reviewsLate' => (int)$reviewsLate, 'events' => $events]);
    }

    // ---------- rulings ----------
    private function ruling(int $id): array
    {
        $r = Db::one('SELECT r.* FROM sharia_rulings r WHERE r.id = ?', [$id]);
        $r || $this->notFound();
        return $r;
    }

    public function rulings(): string
    {
        $f = ['q' => Request::str('q', true), 'status' => Request::str('status', true), 'type' => Request::str('type', true)];
        $where = ['1=1'];
        $p = [];
        if ($f['q'] !== '') {
            $like = Db::like($f['q']);
            $where[] = '(r.title LIKE ? OR r.subject LIKE ? OR r.ruling_text LIKE ? OR r.code LIKE ? OR r.reference_no LIKE ?)';
            array_push($p, $like, $like, $like, $like, $like);
        }
        if (in_array($f['status'], self::RULING_STATUS, true)) {
            $where[] = 'r.status = ?';
            $p[] = $f['status'];
        }
        if (in_array($f['type'], self::RULING_TYPES, true)) {
            $where[] = 'r.ruling_type = ?';
            $p[] = $f['type'];
        }
        $w = implode(' AND ', $where);
        $total = (int)Db::val('SELECT COUNT(*) FROM sharia_rulings r WHERE ' . $w, $p);
        $page = min($this->page(), max(1, (int)ceil($total / self::PER_PAGE)));
        $rows = Db::all('SELECT r.* FROM sharia_rulings r WHERE ' . $w . ' ORDER BY r.issued_on DESC, r.id DESC LIMIT ? OFFSET ?', array_merge($p, [self::PER_PAGE, ($page - 1) * self::PER_PAGE]));
        return $this->view('sharia/rulings', ['title' => t('sha.rulings'), 'rows' => $rows, 'f' => $f, 'total' => $total, 'page' => $page, 'per' => self::PER_PAGE]);
    }

    public function rulingShow(int $id): string
    {
        $r = $this->ruling($id);
        $att = Db::all("SELECT a.*, u.full_name FROM attachments a JOIN users u ON u.id = a.uploaded_by WHERE a.entity_type = 'sharia_ruling' AND a.entity_id = ? AND a.deleted_at IS NULL ORDER BY a.id DESC", [$id]);
        $events = Db::all('SELECT id, code, title, status FROM sharia_events WHERE ruling_id = ? ORDER BY id DESC LIMIT 20', [$id]);
        return $this->view('sharia/ruling_show', ['title' => $r['code'] . ' — ' . $r['title'], 'r' => $r, 'attachments' => $att, 'events' => $events]);
    }

    public function rulingForm(?int $id = null): string
    {
        $r = $id ? $this->ruling($id) : ['ruling_type' => 'resolution', 'status' => 'active', 'issued_on' => date('Y-m-d')];
        return $this->view('sharia/ruling_form', ['title' => t($id ? 'sha.ruling_edit' : 'sha.ruling_new'), 'r' => $r, 'existing' => $id ? $r : null]);
    }

    public function rulingNew(): string
    {
        return $this->rulingForm();
    }

    public function rulingEdit(int $id): string
    {
        return $this->rulingForm($id);
    }

    public function rulingStore(): never
    {
        $this->rulingSave(null);
    }

    public function rulingUpdate(int $id): never
    {
        $this->rulingSave($this->ruling($id));
    }

    private function rulingSave(?array $ex): never
    {
        $v = new Validator($_POST);
        $v->required('title', 'sha.r_title')->max('title', 'sha.r_title', 250)->required('subject', 'sha.r_subject')->max('subject', 'sha.r_subject', 250)->required('ruling_text', 'sha.r_text')->max('ruling_text', 'sha.r_text', 8000)
            ->max('reference_no', 'sha.r_ref', 60)->date('issued_on', 'sha.r_issued')->in('ruling_type', 'sha.r_type', self::RULING_TYPES)->in('status', 'sha.r_status', self::RULING_STATUS);
        $back = $ex ? url('sharia/rulings/' . $ex['id'] . '/edit') : url('sharia/rulings/new');
        if (!$v->ok()) {
            $this->invalid($v->errors(), $back);
        }
        $now = date('Y-m-d H:i:s');
        $d = ['title' => $v->str('title'), 'ruling_type' => $v->str('ruling_type'), 'subject' => $v->str('subject'), 'ruling_text' => $v->str('ruling_text'), 'reference_no' => $v->str('reference_no') ?: null, 'issued_on' => $v->str('issued_on'), 'status' => $v->str('status'), 'updated_at' => $now];
        if ($ex) {
            Db::update('sharia_rulings', $d, 'id = ?', [$ex['id']]);
            $this->audit('sharia.ruling_updated', 'sharia_ruling', (int)$ex['id'], ['changes' => $this->diff($ex, $d, ['title', 'ruling_type', 'subject', 'reference_no', 'issued_on', 'status', 'ruling_text'])]);
            $id = (int)$ex['id'];
        } else {
            $id = Db::insert('sharia_rulings', $d + ['created_by' => $this->user()['id'], 'created_at' => $now]);
            Db::update('sharia_rulings', ['code' => sprintf('SHR-%04d', $id)], 'id = ?', [$id]);
            $this->audit('sharia.ruling_created', 'sharia_ruling', $id, ['title' => $d['title']]);
        }
        $this->redirect(url('sharia/rulings/' . $id), 'admin.saved');
    }

    public function rulingUpload(int $id): never
    {
        $this->ruling($id);
        AttachmentController::handleUpload('sharia_ruling', $id, $this->user(), url('sharia/rulings/' . $id) . '#attachments');
    }

    // ---------- reviews ----------
    private function review(int $id): array
    {
        $r = Db::one('SELECT r.*, u.full_name AS reviewer_name, d.name_en AS dept_en, d.name_ar AS dept_ar FROM sharia_reviews r JOIN users u ON u.id = r.reviewer_user_id LEFT JOIN departments d ON d.id = r.department_id WHERE r.id = ?', [$id]);
        $r || $this->notFound();
        return $r;
    }

    public function reviews(): string
    {
        $f = ['status' => Request::str('status', true), 'late' => Request::str('late', true) === '1' ? '1' : ''];
        $where = ['1=1'];
        $p = [];
        if (in_array($f['status'], ['planned', 'in_progress', 'completed'], true)) {
            $where[] = 'r.status = ?';
            $p[] = $f['status'];
        }
        if ($f['late'] === '1') {
            $where[] = "r.status <> 'completed' AND r.planned_date < CURDATE()";
        }
        $w = implode(' AND ', $where);
        $total = (int)Db::val('SELECT COUNT(*) FROM sharia_reviews r WHERE ' . $w, $p);
        $page = min($this->page(), max(1, (int)ceil($total / self::PER_PAGE)));
        $rows = Db::all('SELECT r.*, u.full_name AS reviewer_name, d.name_en AS dept_en, d.name_ar AS dept_ar FROM sharia_reviews r JOIN users u ON u.id = r.reviewer_user_id LEFT JOIN departments d ON d.id = r.department_id WHERE ' . $w . ' ORDER BY r.planned_date DESC, r.id DESC LIMIT ? OFFSET ?', array_merge($p, [self::PER_PAGE, ($page - 1) * self::PER_PAGE]));
        return $this->view('sharia/reviews', ['title' => t('sha.reviews'), 'rows' => $rows, 'f' => $f, 'total' => $total, 'page' => $page, 'per' => self::PER_PAGE]);
    }

    public function reviewShow(int $id): string
    {
        $r = $this->review($id);
        return $this->view('sharia/review_show', ['title' => $r['code'] . ' — ' . $r['title'], 'r' => $r]);
    }

    public function reviewNew(): string
    {
        return $this->view('sharia/review_form', ['title' => t('sha.review_new'), 'r' => ['review_type' => 'product', 'status' => 'planned', 'planned_date' => date('Y-m-d')], 'existing' => null, 'lists' => ['departments' => Ref::departments(true), 'users' => Ref::users(true)]]);
    }

    public function reviewEdit(int $id): string
    {
        $r = $this->review($id);
        return $this->view('sharia/review_form', ['title' => t('sha.review_edit'), 'r' => $r, 'existing' => $r, 'lists' => ['departments' => Ref::departments(true), 'users' => Ref::users(true)]]);
    }

    public function reviewStore(): never
    {
        $this->reviewSave(null);
    }

    public function reviewUpdate(int $id): never
    {
        $this->reviewSave($this->review($id));
    }

    private function reviewSave(?array $ex): never
    {
        $v = new Validator($_POST);
        $v->required('title', 'sha.v_title')->max('title', 'sha.v_title', 250)->max('scope', 'sha.v_scope', 3000)->max('summary', 'sha.v_summary', 6000)->date('planned_date', 'sha.v_planned')->date('completed_on', 'sha.v_completed', false)
            ->in('review_type', 'sha.v_type', self::REVIEW_TYPES)->in('status', 'sha.v_status', ['planned', 'in_progress', 'completed']);
        $dept = Request::int('department_id');
        $rev = Request::int('reviewer_user_id');
        if ($dept !== null && !isset(Ref::departments(true)[$dept]) && $dept !== ($ex['department_id'] ?? null)) {
            $v->fail('department_id', 'validation.invalid_choice', ['field' => t('sha.v_dept')]);
        }
        if ($rev === null || !isset(Ref::users(true)[$rev]) && $rev !== ($ex['reviewer_user_id'] ?? null)) {
            $v->fail('reviewer_user_id', 'validation.invalid_choice', ['field' => t('sha.v_reviewer')]);
        }
        $status = $v->str('status');
        if ($status === 'completed') {
            if ($v->str('summary') === '') {
                $v->fail('summary', 'sha.err_summary_required');
            }
            if ($v->str('completed_on') === '') {
                $v->fail('completed_on', 'validation.required', ['field' => t('sha.v_completed')]);
            }
        }
        if ($v->str('completed_on') !== '' && $v->str('completed_on') > date('Y-m-d')) {
            $v->fail('completed_on', 'validation.not_future', ['field' => t('sha.v_completed')]);
        }
        $back = $ex ? url('sharia/reviews/' . $ex['id'] . '/edit') : url('sharia/reviews/new');
        if (!$v->ok()) {
            $this->invalid($v->errors(), $back);
        }
        $now = date('Y-m-d H:i:s');
        $d = ['title' => $v->str('title'), 'review_type' => $v->str('review_type'), 'scope' => $v->str('scope') ?: null, 'department_id' => $dept, 'reviewer_user_id' => $rev, 'planned_date' => $v->str('planned_date'),
            'completed_on' => $status === 'completed' ? $v->str('completed_on') : null, 'status' => $status, 'summary' => $v->str('summary') ?: null, 'updated_at' => $now];
        if ($ex) {
            Db::update('sharia_reviews', $d, 'id = ?', [$ex['id']]);
            $this->audit('sharia.review_updated', 'sharia_review', (int)$ex['id'], ['changes' => $this->diff($ex, $d, ['title', 'review_type', 'department_id', 'reviewer_user_id', 'planned_date', 'completed_on', 'status'])]);
            $id = (int)$ex['id'];
        } else {
            $id = Db::insert('sharia_reviews', $d + ['created_by' => $this->user()['id'], 'created_at' => $now]);
            Db::update('sharia_reviews', ['code' => sprintf('SRV-%04d', $id)], 'id = ?', [$id]);
            $this->audit('sharia.review_created', 'sharia_review', $id, ['title' => $d['title']]);
        }
        $this->redirect(url('sharia/reviews/' . $id), 'admin.saved');
    }

    // ---------- non-compliance events ----------
    private function event(int $id): array
    {
        $e = Db::one('SELECT e.*, d.name_en AS dept_en, d.name_ar AS dept_ar, u.full_name AS reporter_name, r.code AS ruling_code, r.title AS ruling_title FROM sharia_events e JOIN departments d ON d.id = e.department_id JOIN users u ON u.id = e.reported_by LEFT JOIN sharia_rulings r ON r.id = e.ruling_id WHERE e.id = ?', [$id]);
        if (!$e || !Scope::allowsDept($this->user(), $e)) {
            $this->notFound();
        }
        return $e;
    }

    public function events(): string
    {
        $u = $this->user();
        [$sw, $sp] = Scope::sqlDept($u, 'e');
        $f = ['status' => Request::str('status', true), 'severity' => Request::str('severity', true), 'purification' => Request::str('purification', true) === '1' ? '1' : '', 'open' => Request::str('open', true) === '1' ? '1' : ''];
        $where = [$sw];
        $p = $sp;
        if (in_array($f['status'], ['reported', 'under_review', 'decided', 'closed'], true)) {
            $where[] = 'e.status = ?';
            $p[] = $f['status'];
        }
        if (in_array($f['severity'], self::SEVERITIES, true)) {
            $where[] = 'e.severity = ?';
            $p[] = $f['severity'];
        }
        if ($f['purification'] === '1') {
            $where[] = "e.purification_status = 'pending'";
        }
        if ($f['open'] === '1') {
            $where[] = "e.status <> 'closed'";
        }
        $w = implode(' AND ', $where);
        $total = (int)Db::val('SELECT COUNT(*) FROM sharia_events e WHERE ' . $w, $p);
        $page = min($this->page(), max(1, (int)ceil($total / self::PER_PAGE)));
        $rows = Db::all('SELECT e.*, d.name_en AS dept_en, d.name_ar AS dept_ar FROM sharia_events e JOIN departments d ON d.id = e.department_id WHERE ' . $w . ' ORDER BY e.detected_on DESC, e.id DESC LIMIT ? OFFSET ?', array_merge($p, [self::PER_PAGE, ($page - 1) * self::PER_PAGE]));
        return $this->view('sharia/events', ['title' => t('sha.events'), 'rows' => $rows, 'f' => $f, 'total' => $total, 'page' => $page, 'per' => self::PER_PAGE]);
    }

    public function eventShow(int $id): string
    {
        $e = $this->event($id);
        $att = Db::all("SELECT a.*, u.full_name FROM attachments a JOIN users u ON u.id = a.uploaded_by WHERE a.entity_type = 'sharia_event' AND a.entity_id = ? AND a.deleted_at IS NULL ORDER BY a.id DESC", [$id]);
        return $this->view('sharia/event_show', ['title' => $e['code'] . ' — ' . $e['title'], 'e' => $e, 'attachments' => $att, 'canManage' => Perm::roleHas($this->user()['role'], 'sharia.manage')]);
    }

    private function eventLists(): array
    {
        return ['departments' => Ref::departments(true), 'rulings' => Db::all("SELECT id, code, title FROM sharia_rulings WHERE status = 'active' ORDER BY issued_on DESC LIMIT 300")];
    }

    public function eventNew(): string
    {
        $u = $this->user();
        return $this->view('sharia/event_form', ['title' => t('sha.event_new'), 'e' => ['severity' => 'medium', 'detected_on' => date('Y-m-d'), 'department_id' => $u['department_id'] ?? ''], 'existing' => null, 'lists' => $this->eventLists()]);
    }

    public function eventEdit(int $id): string
    {
        $e = $this->event($id);
        return $this->view('sharia/event_form', ['title' => t('sha.event_edit'), 'e' => $e, 'existing' => $e, 'lists' => $this->eventLists()]);
    }

    public function eventStore(): never
    {
        $this->eventSave(null);
    }

    public function eventUpdate(int $id): never
    {
        $this->eventSave($this->event($id));
    }

    private function eventSave(?array $ex): never
    {
        $u = $this->user();
        $v = new Validator($_POST);
        $v->required('title', 'sha.e_title')->max('title', 'sha.e_title', 250)->required('description', 'sha.e_desc')->max('description', 'sha.e_desc', 6000)->date('detected_on', 'sha.e_detected')->in('severity', 'sha.e_severity', self::SEVERITIES);
        if ($v->str('detected_on') > date('Y-m-d')) {
            $v->fail('detected_on', 'validation.not_future', ['field' => t('sha.e_detected')]);
        }
        $dept = Request::int('department_id');
        $ruling = Request::int('ruling_id');
        if ($dept === null || !isset(Ref::departments(true)[$dept]) && $dept !== ($ex['department_id'] ?? null)) {
            $v->fail('department_id', 'validation.invalid_choice', ['field' => t('sha.e_dept')]);
        } elseif (!Scope::allowsDept($u, ['department_id' => $dept])) {
            $v->fail('department_id', 'validation.out_of_scope');
        }
        if ($ruling !== null && !Db::val('SELECT 1 FROM sharia_rulings WHERE id = ?', [$ruling])) {
            $v->fail('ruling_id', 'validation.invalid_choice', ['field' => t('sha.e_ruling')]);
        }
        $back = $ex ? url('sharia/events/' . $ex['id'] . '/edit') : url('sharia/events/new');
        if (!$v->ok()) {
            $this->invalid($v->errors(), $back);
        }
        $now = date('Y-m-d H:i:s');
        $sev = $v->str('severity');
        $d = ['title' => $v->str('title'), 'description' => $v->str('description'), 'detected_on' => $v->str('detected_on'), 'department_id' => $dept, 'ruling_id' => $ruling, 'severity' => $sev, 'updated_at' => $now];
        if ($ex) {
            if ($ex['status'] === 'closed') {
                $this->redirect(url('sharia/events/' . $ex['id']), 'risk.err_archived_readonly', 'error');
            }
            if ($sev === 'high' && !$ex['escalated_at']) {
                $d['escalated_at'] = $now;
            }
            Db::update('sharia_events', $d, 'id = ?', [$ex['id']]);
            $this->audit('sharia.event_updated', 'sharia_event', (int)$ex['id'], ['changes' => $this->diff($ex, $d, ['title', 'detected_on', 'department_id', 'ruling_id', 'severity'])]);
            $id = (int)$ex['id'];
        } else {
            $id = Db::insert('sharia_events', $d + ['status' => 'reported', 'escalated_at' => $sev === 'high' ? $now : null, 'reported_by' => $u['id'], 'created_at' => $now]);
            Db::update('sharia_events', ['code' => sprintf('SNC-%04d', $id)], 'id = ?', [$id]);
            $this->audit('sharia.event_reported', 'sharia_event', $id, ['severity' => $sev, 'escalated' => $sev === 'high']);
        }
        $this->redirect(url('sharia/events/' . $id), $sev === 'high' ? 'sha.reported_escalated' : 'sha.reported');
    }

    /** Move the event into Sharia review. */
    public function eventReview(int $id): never
    {
        $e = $this->event($id);
        if ($e['status'] !== 'reported') {
            $this->redirect(url('sharia/events/' . $id), 'sha.err_state', 'error');
        }
        Db::update('sharia_events', ['status' => 'under_review', 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        $this->audit('sharia.event_review', 'sharia_event', $id);
        $this->redirect(url('sharia/events/' . $id), 'admin.saved');
    }

    /** Record the Sharia Supervisory Board decision and any impermissible income to be purified. */
    public function eventDecide(int $id): never
    {
        $e = $this->event($id);
        $back = url('sharia/events/' . $id) . '#decision';
        if (!in_array($e['status'], ['reported', 'under_review'], true)) {
            $this->redirect(url('sharia/events/' . $id), 'sha.err_state', 'error');
        }
        $v = new Validator($_POST);
        $v->required('decision_text', 'sha.e_decision')->max('decision_text', 'sha.e_decision', 6000)->date('decided_on', 'sha.e_decided_on');
        if ($v->str('decided_on') > date('Y-m-d')) {
            $v->fail('decided_on', 'validation.not_future', ['field' => t('sha.e_decided_on')]);
        } elseif ($v->str('decided_on') !== '' && $v->str('decided_on') < $e['detected_on']) {
            $v->fail('decided_on', 'validation.after_date', ['field' => t('sha.e_decided_on'), 'other' => t('sha.e_detected')]);
        }
        $raw = $v->str('impermissible_income');
        $inc = $raw === '' ? 0 : Money::parse($raw, Money::decimals());
        if ($inc === null || $inc < 0) {
            $v->fail('impermissible_income', 'validation.amount', ['field' => t('sha.e_income')]);
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), $back);
        }
        Db::update('sharia_events', ['status' => 'decided', 'decision_text' => $v->str('decision_text'), 'decided_on' => $v->str('decided_on'), 'impermissible_income' => Money::toDb((int)$inc), 'purification_status' => $inc > 0 ? 'pending' : 'not_required', 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        $this->audit('sharia.event_decided', 'sharia_event', $id, ['impermissible_income' => Money::toDb((int)$inc)]);
        $this->redirect(url('sharia/events/' . $id), $inc > 0 ? 'sha.decided_purify' : 'sha.decided');
    }

    /** Record that impermissible income was transferred to charity. */
    public function eventPurify(int $id): never
    {
        $e = $this->event($id);
        if ($e['status'] !== 'decided' || $e['purification_status'] !== 'pending') {
            $this->redirect(url('sharia/events/' . $id), 'sha.err_state', 'error');
        }
        $v = new Validator($_POST);
        $v->date('purified_on', 'sha.e_purified_on')->required('beneficiary', 'sha.e_beneficiary')->max('beneficiary', 'sha.e_beneficiary', 250);
        if ($v->str('purified_on') > date('Y-m-d')) {
            $v->fail('purified_on', 'validation.not_future', ['field' => t('sha.e_purified_on')]);
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), url('sharia/events/' . $id) . '#purification');
        }
        Db::update('sharia_events', ['purification_status' => 'purified', 'purified_on' => $v->str('purified_on'), 'beneficiary' => $v->str('beneficiary'), 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        $this->audit('sharia.event_purified', 'sharia_event', $id, ['amount' => $e['impermissible_income']]);
        $this->redirect(url('sharia/events/' . $id), 'sha.purified');
    }

    public function eventClose(int $id): never
    {
        $e = $this->event($id);
        if ($e['status'] !== 'decided' || $e['purification_status'] === 'pending') {
            $this->redirect(url('sharia/events/' . $id), 'sha.err_close', 'error');
        }
        Db::update('sharia_events', ['status' => 'closed', 'closed_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        $this->audit('sharia.event_closed', 'sharia_event', $id);
        $this->redirect(url('sharia/events/' . $id), 'sha.closed');
    }

    public function eventUpload(int $id): never
    {
        $this->event($id);
        AttachmentController::handleUpload('sharia_event', $id, $this->user(), url('sharia/events/' . $id) . '#attachments');
    }

    // ---------- annual report ----------
    public function report(): string
    {
        $u = $this->user();
        $year = Request::int('year', true) ?: (int)date('Y');
        $year = max(2000, min(2100, $year));
        [$w, $p] = Scope::sqlDept($u, 'e');
        $from = "$year-01-01";
        $to = "$year-12-31";
        $ev = Db::all("SELECT e.severity, e.status, COUNT(*) AS n, SUM(e.impermissible_income) AS income, SUM(IF(e.purification_status = 'purified', e.impermissible_income, 0)) AS purified FROM sharia_events e WHERE e.detected_on BETWEEN ? AND ? AND $w GROUP BY e.severity, e.status", array_merge([$from, $to], $p));
        $tot = Db::one("SELECT COUNT(*) AS n, COALESCE(SUM(e.impermissible_income),0) AS income, COALESCE(SUM(IF(e.purification_status = 'purified', e.impermissible_income, 0)),0) AS purified, COALESCE(SUM(IF(e.purification_status = 'pending', e.impermissible_income, 0)),0) AS pending FROM sharia_events e WHERE e.detected_on BETWEEN ? AND ? AND $w", array_merge([$from, $to], $p));
        $bySev = Db::all("SELECT e.severity, COUNT(*) AS n FROM sharia_events e WHERE e.detected_on BETWEEN ? AND ? AND $w GROUP BY e.severity", array_merge([$from, $to], $p));
        $reviews = Db::one("SELECT COUNT(*) AS total, SUM(status = 'completed') AS done FROM sharia_reviews WHERE planned_date BETWEEN ? AND ?", [$from, $to]);
        $rulings = (int)Db::val('SELECT COUNT(*) FROM sharia_rulings WHERE issued_on BETWEEN ? AND ?', [$from, $to]);
        $activeRulings = (int)Db::val("SELECT COUNT(*) FROM sharia_rulings WHERE status = 'active'");
        return $this->view('sharia/report', ['title' => t('sha.report'), 'year' => $year, 'tot' => $tot, 'bySev' => array_column($bySev, 'n', 'severity'), 'reviews' => $reviews, 'rulings' => $rulings, 'activeRulings' => $activeRulings]);
    }
}
