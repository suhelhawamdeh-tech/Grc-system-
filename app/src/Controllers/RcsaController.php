<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Db;
use App\Core\HttpException;
use App\Core\Perm;
use App\Core\Request;
use App\Core\Scope;
use App\Core\Validator;
use App\Support\Ref;

/** RCSA: templates (questions) -> assessments per department/branch -> submit -> review (approve / return). */
final class RcsaController extends Controller
{
    private const PER_PAGE = 25;
    private const RATINGS = ['effective', 'partially_effective', 'ineffective', 'not_applicable'];

    private function load(int $id): array
    {
        $u = $this->user();
        $a = Db::one('SELECT a.*, t.title AS t_title, d.name_en AS dept_en, d.name_ar AS dept_ar, b.name_en AS branch_en, b.name_ar AS branch_ar, u.full_name AS assessor_name, p.name_en AS p_en, p.name_ar AS p_ar, r.full_name AS reviewer_name
            FROM rcsa_assessments a JOIN rcsa_templates t ON t.id = a.template_id JOIN departments d ON d.id = a.department_id LEFT JOIN branches b ON b.id = a.branch_id JOIN users u ON u.id = a.assessor_user_id
            LEFT JOIN reporting_periods p ON p.id = a.period_id LEFT JOIN users r ON r.id = a.reviewer_user_id WHERE a.id = ?', [$id]);
        if (!$a || !Scope::allows($u, $a)) {
            $this->notFound();
        }
        return $a;
    }

    public function index(): string
    {
        $u = $this->user();
        [$sw, $sp] = Scope::sql($u, 'a');
        $f = ['status' => Request::str('status', true), 'department' => Request::int('department', true), 'overdue' => Request::str('overdue', true) === '1' ? '1' : ''];
        $w = [$sw];
        $p = $sp;
        if (in_array($f['status'], ['draft', 'submitted', 'approved', 'returned'], true)) {
            $w[] = 'a.status = ?';
            $p[] = $f['status'];
        }
        if ($f['department'] !== null) {
            $w[] = 'a.department_id = ?';
            $p[] = $f['department'];
        }
        if ($f['overdue'] === '1') {
            $w[] = "a.status IN ('draft','returned') AND a.due_date < ?";
            $p[] = date('Y-m-d');
        }
        $ws = implode(' AND ', $w);
        $total = (int)Db::val('SELECT COUNT(*) FROM rcsa_assessments a WHERE ' . $ws, $p);
        $page = min($this->page(), max(1, (int)ceil($total / self::PER_PAGE)));
        $rows = Db::all("SELECT a.*, t.title AS t_title, d.name_en AS dept_en, d.name_ar AS dept_ar, b.name_en AS branch_en, b.name_ar AS branch_ar, u.full_name AS assessor_name FROM rcsa_assessments a JOIN rcsa_templates t ON t.id = a.template_id JOIN departments d ON d.id = a.department_id LEFT JOIN branches b ON b.id = a.branch_id JOIN users u ON u.id = a.assessor_user_id WHERE $ws ORDER BY a.due_date DESC, a.id DESC LIMIT ? OFFSET ?", array_merge($p, [self::PER_PAGE, ($page - 1) * self::PER_PAGE]));
        return $this->view('rcsa/index', ['title' => t('rcsa.title'), 'rows' => $rows, 'f' => $f, 'total' => $total, 'page' => $page, 'per' => self::PER_PAGE, 'departments' => Ref::departments(true),
            'templates' => Db::all('SELECT t.*, (SELECT COUNT(*) FROM rcsa_questions q WHERE q.template_id = t.id) AS n_q FROM rcsa_templates t ORDER BY t.id DESC')]);
    }

    // ---- templates (rcsa.manage)
    public function templateForm(): string
    {
        return $this->view('rcsa/template_form', ['title' => t('rcsa.template_new'), 't' => [], 'existing' => null, 'questions' => [], 'cats' => Ref::categories(true)]);
    }

    public function templateEdit(int $id): string
    {
        $t = Db::one('SELECT * FROM rcsa_templates WHERE id = ?', [$id]) ?? $this->notFound();
        return $this->view('rcsa/template_form', ['title' => t('rcsa.template_edit'), 't' => $t, 'existing' => $t, 'questions' => Db::all('SELECT * FROM rcsa_questions WHERE template_id = ? ORDER BY sort_order, id', [$id]), 'cats' => Ref::categories(true)]);
    }

    public function templateSave(?int $id = null): never
    {
        $ex = $id ? (Db::one('SELECT * FROM rcsa_templates WHERE id = ?', [$id]) ?? $this->notFound()) : null;
        $v = new Validator($_POST);
        $v->required('title', 'rcsa.template_title')->max('title', 'rcsa.template_title', 250)->max('description', 'rcsa.description', 2000);
        $qs = [];
        $qEn = (array)($_POST['q_en'] ?? []);
        $qAr = (array)($_POST['q_ar'] ?? []);
        $qCat = (array)($_POST['q_cat'] ?? []);
        foreach ($qEn as $i => $en) {
            $en = trim((string)$en);
            $ar = trim((string)($qAr[$i] ?? ''));
            if ($en === '' && $ar === '') {
                continue;
            }
            if ($en === '' || $ar === '' || mb_strlen($en) > 500 || mb_strlen($ar) > 500) {
                $v->fail('questions', 'rcsa.err_question');
            }
            $cat = (int)($qCat[$i] ?? 0);
            $qs[] = [$en, $ar, $cat && isset(Ref::categories()[$cat]) ? $cat : null];
        }
        if (!$ex && !$qs) {
            $v->fail('questions', 'rcsa.err_no_questions');
        }
        $back = $ex ? url('rcsa/templates/' . $id . '/edit') : url('rcsa/templates/new');
        if (!$v->ok()) {
            $this->invalid($v->errors(), $back);
        }
        $now = date('Y-m-d H:i:s');
        $tid = Db::tx(function () use ($ex, $v, $qs, $now) {
            if ($ex) {
                Db::update('rcsa_templates', ['title' => $v->str('title'), 'description' => $v->str('description') ?: null, 'is_active' => !empty($_POST['is_active']) ? 1 : 0, 'updated_at' => $now], 'id = ?', [$ex['id']]);
                $tid = (int)$ex['id'];
            } else {
                $tid = Db::insert('rcsa_templates', ['title' => $v->str('title'), 'description' => $v->str('description') ?: null, 'is_active' => 1, 'created_by' => $this->user()['id'], 'created_at' => $now, 'updated_at' => $now]);
            }
            $order = (int)Db::val('SELECT COALESCE(MAX(sort_order),0) FROM rcsa_questions WHERE template_id = ?', [$tid]);
            foreach ($qs as [$en, $ar, $cat]) {
                Db::insert('rcsa_questions', ['template_id' => $tid, 'sort_order' => $order += 10, 'question_en' => $en, 'question_ar' => $ar, 'category_id' => $cat]);
            }
            return $tid;
        });
        // New questions on a template with existing assessments: add empty answer rows so drafts pick them up.
        foreach (Db::all("SELECT id FROM rcsa_assessments WHERE template_id = ? AND status IN ('draft','returned')", [$tid]) as $a) {
            Db::exec('INSERT IGNORE INTO rcsa_answers (assessment_id, question_id) SELECT ?, q.id FROM rcsa_questions q WHERE q.template_id = ?', [$a['id'], $tid]);
        }
        $this->audit($ex ? 'rcsa.template_updated' : 'rcsa.template_created', 'rcsa_template', $tid, ['questions_added' => count($qs)]);
        $this->redirect(url('rcsa'), 'admin.saved');
    }

    public function templateStore(): never
    {
        $this->templateSave(null);
    }

    public function templateUpdate(int $id): never
    {
        $this->templateSave($id);
    }

    // ---- launch (rcsa.manage)
    public function launchForm(): string
    {
        return $this->view('rcsa/launch', ['title' => t('rcsa.launch'), 'templates' => Db::all('SELECT * FROM rcsa_templates WHERE is_active = 1 ORDER BY title'), 'departments' => Ref::departments(true), 'branches' => Ref::branches(true), 'periods' => Ref::periods(true), 'users' => Ref::users(true)]);
    }

    public function launch(): never
    {
        $u = $this->user();
        $v = new Validator($_POST);
        $v->date('due_date', 'rcsa.due_date');
        $t = Request::int('template_id');
        $d = Request::int('department_id');
        $b = Request::int('branch_id');
        $per = Request::int('period_id');
        $as = Request::int('assessor_user_id');
        if ($t === null || !Db::val('SELECT 1 FROM rcsa_templates WHERE id = ? AND is_active = 1', [$t])) {
            $v->fail('template_id', 'validation.invalid_choice', ['field' => t('rcsa.template')]);
        }
        if ($d === null || !isset(Ref::departments(true)[$d])) {
            $v->fail('department_id', 'validation.invalid_choice', ['field' => t('risk.department')]);
        }
        if ($b !== null && !isset(Ref::branches(true)[$b])) {
            $v->fail('branch_id', 'validation.invalid_choice', ['field' => t('risk.branch')]);
        }
        if ($per !== null && !isset(Ref::periods()[$per])) {
            $v->fail('period_id', 'validation.invalid_choice', ['field' => t('dash.period')]);
        }
        if ($as === null || !isset(Ref::users(true)[$as])) {
            $v->fail('assessor_user_id', 'validation.invalid_choice', ['field' => t('rcsa.assessor')]);
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), url('rcsa/launch'));
        }
        $now = date('Y-m-d H:i:s');
        $id = Db::tx(function () use ($t, $d, $b, $per, $as, $v, $u, $now) {
            $id = Db::insert('rcsa_assessments', ['template_id' => $t, 'period_id' => $per, 'department_id' => $d, 'branch_id' => $b, 'assessor_user_id' => $as, 'due_date' => $v->str('due_date'), 'status' => 'draft', 'created_by' => $u['id'], 'created_at' => $now, 'updated_at' => $now]);
            Db::update('rcsa_assessments', ['code' => sprintf('RCSA-%04d', $id)], 'id = ?', [$id]);
            Db::exec('INSERT INTO rcsa_answers (assessment_id, question_id) SELECT ?, q.id FROM rcsa_questions q WHERE q.template_id = ?', [$id, $t]);
            return $id;
        });
        $this->audit('rcsa.launched', 'rcsa', $id, ['department' => $d, 'assessor' => $as]);
        $this->redirect(url('rcsa/' . $id), 'rcsa.launched');
    }

    // ---- respond / submit / review
    private function questions(int $id, int $tid): array
    {
        return Db::all('SELECT q.*, ans.rating, ans.comment, ans.risk_id FROM rcsa_questions q LEFT JOIN rcsa_answers ans ON ans.question_id = q.id AND ans.assessment_id = ? WHERE q.template_id = ? ORDER BY q.sort_order, q.id', [$id, $tid]);
    }

    private function canRespond(array $u, array $a): bool
    {
        if (!Perm::roleHas($u['role'], 'rcsa.respond') || !in_array($a['status'], ['draft', 'returned'], true)) {
            return false;
        }
        return (int)$a['assessor_user_id'] === (int)$u['id'] || ($u['role'] === 'dept_manager' && Scope::allows($u, $a));
    }

    public function show(int $id): string
    {
        $u = $this->user();
        $a = $this->load($id);
        $qs = $this->questions($id, (int)$a['template_id']);
        $summary = ['effective' => 0, 'partially_effective' => 0, 'ineffective' => 0, 'not_applicable' => 0, 'unanswered' => 0];
        foreach ($qs as $q) {
            $summary[$q['rating'] ?? 'unanswered']++;
        }
        [$rw, $rp] = Scope::sql($u, 'r');
        return $this->view('rcsa/show', ['title' => $a['code'] . ' — ' . $a['t_title'], 'a' => $a, 'qs' => $qs, 'summary' => $summary, 'canRespond' => $this->canRespond($u, $a), 'canReview' => Perm::roleHas($u['role'], 'rcsa.manage') && $a['status'] === 'submitted',
            'risks' => Db::all('SELECT r.id, r.risk_code, r.title FROM risks r WHERE r.archived_at IS NULL AND ' . $rw . ' ORDER BY r.id DESC LIMIT 300', $rp)]);
    }

    private function collectAnswers(int $id, array $a, array $u, bool $requireAll): array
    {
        $errors = [];
        $rows = [];
        [$rw, $rp] = Scope::sql($u, 'r');
        foreach ($this->questions($id, (int)$a['template_id']) as $q) {
            $qid = (int)$q['id'];
            $rating = (string)($_POST['rating'][$qid] ?? '');
            $comment = trim((string)($_POST['comment'][$qid] ?? ''));
            $risk = (int)($_POST['risk'][$qid] ?? 0) ?: null;
            if ($rating !== '' && !in_array($rating, self::RATINGS, true)) {
                $errors['rating_' . $qid] = t('validation.invalid_choice', ['field' => t('rcsa.rating')]);
            }
            if ($requireAll && $rating === '') {
                $errors['rating_' . $qid] = t('rcsa.err_unanswered');
            }
            if (in_array($rating, ['ineffective', 'partially_effective'], true) && $comment === '' && $requireAll) {
                $errors['comment_' . $qid] = t('rcsa.err_comment_required');
            }
            if (mb_strlen($comment) > 2000) {
                $errors['comment_' . $qid] = t('validation.max_length', ['field' => t('rcsa.comment'), 'max' => 2000]);
            }
            if ($risk !== null && !Db::val('SELECT 1 FROM risks r WHERE r.id = ? AND ' . $rw, array_merge([$risk], $rp))) {
                $errors['risk_' . $qid] = t('incident.err_bad_risk_link');
            }
            $rows[$qid] = [$rating !== '' ? $rating : null, $comment !== '' ? $comment : null, $risk];
        }
        return [$rows, $errors];
    }

    private function saveRows(int $id, array $rows): void
    {
        foreach ($rows as $qid => [$rating, $comment, $risk]) {
            Db::exec('INSERT INTO rcsa_answers (assessment_id, question_id, rating, comment, risk_id) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment), risk_id = VALUES(risk_id)', [$id, $qid, $rating, $comment, $risk]);
        }
    }

    public function save(int $id): never
    {
        $u = $this->user();
        $a = $this->load($id);
        if (!$this->canRespond($u, $a)) {
            throw new HttpException(403);
        }
        [$rows, $errors] = $this->collectAnswers($id, $a, $u, false);
        if ($errors) {
            $this->invalid($errors, url('rcsa/' . $id));
        }
        $this->saveRows($id, $rows);
        Db::update('rcsa_assessments', ['updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        $this->audit('rcsa.saved', 'rcsa', $id);
        $this->redirect(url('rcsa/' . $id), 'rcsa.saved');
    }

    public function submit(int $id): never
    {
        $u = $this->user();
        $a = $this->load($id);
        if (!$this->canRespond($u, $a)) {
            throw new HttpException(403);
        }
        [$rows, $errors] = $this->collectAnswers($id, $a, $u, true);
        if ($errors) {
            $this->invalid($errors, url('rcsa/' . $id));
        }
        $now = date('Y-m-d H:i:s');
        Db::tx(function () use ($id, $rows, $now) {
            $this->saveRows($id, $rows);
            Db::update('rcsa_assessments', ['status' => 'submitted', 'submitted_at' => $now, 'updated_at' => $now], 'id = ?', [$id]);
        });
        $this->audit('rcsa.submitted', 'rcsa', $id);
        $this->redirect(url('rcsa/' . $id), 'rcsa.submitted');
    }

    public function review(int $id): never
    {
        $u = $this->user();
        $a = $this->load($id);
        if ($a['status'] !== 'submitted') {
            $this->redirect(url('rcsa/' . $id), 'rcsa.err_not_submitted', 'error');
        }
        if ((int)$a['assessor_user_id'] === (int)$u['id']) {
            $this->redirect(url('rcsa/' . $id), 'rcsa.err_self_review', 'error');
        }
        $decision = Request::str('decision');
        $comment = Request::str('review_comment');
        if (!in_array($decision, ['approve', 'return'], true)) {
            $this->invalid(['decision' => t('validation.invalid_choice', ['field' => t('rcsa.decision')])], url('rcsa/' . $id));
        }
        if ($decision === 'return' && $comment === '') {
            $this->invalid(['review_comment' => t('rcsa.err_return_comment')], url('rcsa/' . $id));
        }
        Db::update('rcsa_assessments', ['status' => $decision === 'approve' ? 'approved' : 'returned', 'reviewer_user_id' => $u['id'], 'reviewed_at' => date('Y-m-d H:i:s'), 'review_comment' => $comment ?: null, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        $this->audit('rcsa.' . ($decision === 'approve' ? 'approved' : 'returned'), 'rcsa', $id);
        $this->redirect(url('rcsa/' . $id), $decision === 'approve' ? 'rcsa.approved_msg' : 'rcsa.returned_msg');
    }
}
