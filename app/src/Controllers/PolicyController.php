<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Db;
use App\Core\Request;
use App\Core\Validator;
use App\Support\DocImporter;
use App\Support\Framework;
use App\Support\Ref;
use App\Support\Upload;

final class PolicyController extends Controller
{
    public const CATEGORIES = ['charter', 'framework', 'policy', 'manual', 'procedure', 'standard', 'methodology', 'tool', 'plan', 'template', 'report', 'sharia_resolution'];
    private const PER_PAGE = 25;

    public function index(): string
    {
        $f = ['q' => mb_substr(Request::str('q', true), 0, 100), 'status' => Request::str('status', true), 'category' => Request::str('category', true), 'review_overdue' => Request::str('review_overdue', true) === '1' ? '1' : '',
            'level' => Request::str('level', true), 'section' => Request::str('section', true), 'classification' => Request::str('classification', true)];
        $w = ['1=1'];
        $p = [];
        if ($f['q'] !== '') {
            $w[] = '(p.title LIKE ? OR p.code LIKE ? OR p.title_en LIKE ?)';
            $l = Db::like($f['q']);
            array_push($p, $l, $l, $l);
        }
        if (in_array($f['status'], ['draft', 'approved', 'retired'], true)) {
            $w[] = 'p.status = ?';
            $p[] = $f['status'];
        }
        if (in_array($f['category'], self::CATEGORIES, true)) {
            $w[] = 'p.category = ?';
            $p[] = $f['category'];
        }
        if (in_array((int)$f['level'], Framework::LEVELS, true)) {
            $w[] = 'p.doc_level = ?';
            $p[] = (int)$f['level'];
        }
        if (in_array($f['section'], Framework::SECTIONS, true)) {
            $w[] = 'p.rm_section = ?';
            $p[] = $f['section'];
        }
        if (in_array($f['classification'], Framework::CLASSIFICATIONS, true)) {
            $w[] = 'p.classification = ?';
            $p[] = $f['classification'];
        }
        if ($f['review_overdue'] === '1') {
            $w[] = "p.status <> 'retired' AND p.next_review_date < ?";
            $p[] = date('Y-m-d');
        }
        $ws = implode(' AND ', $w);
        $total = (int)Db::val('SELECT COUNT(*) FROM policies p WHERE ' . $ws, $p);
        $page = min($this->page(), max(1, (int)ceil($total / self::PER_PAGE)));
        $rows = Db::all("SELECT p.*, d.name_en AS dept_en, d.name_ar AS dept_ar, o.full_name AS owner_name, v.version_label AS cur_version, v.effective_date AS cur_effective FROM policies p LEFT JOIN departments d ON d.id = p.department_id JOIN users o ON o.id = p.owner_user_id LEFT JOIN policy_versions v ON v.policy_id = p.id AND v.is_current = 1 WHERE $ws ORDER BY COALESCE(p.doc_level, 9), p.code LIMIT ? OFFSET ?", array_merge($p, [self::PER_PAGE, ($page - 1) * self::PER_PAGE]));
        return $this->view('policies/index', ['title' => t('pol.title_plural'), 'rows' => $rows, 'f' => $f, 'total' => $total, 'page' => $page, 'per' => self::PER_PAGE]);
    }

    private function load(int $id): array
    {
        return Db::one('SELECT p.*, d.name_en AS dept_en, d.name_ar AS dept_ar, o.full_name AS owner_name FROM policies p LEFT JOIN departments d ON d.id = p.department_id JOIN users o ON o.id = p.owner_user_id WHERE p.id = ?', [$id]) ?? $this->notFound();
    }

    public function show(int $id): string
    {
        $p = $this->load($id);
        $versions = Db::all('SELECT v.*, u.full_name FROM policy_versions v JOIN users u ON u.id = v.created_by WHERE v.policy_id = ? ORDER BY v.effective_date DESC, v.id DESC', [$id]);
        $atts = [];
        foreach (Db::all("SELECT a.*, u.full_name FROM attachments a JOIN users u ON u.id = a.uploaded_by WHERE a.entity_type = 'policy_version' AND a.deleted_at IS NULL AND a.entity_id IN (SELECT id FROM policy_versions WHERE policy_id = ?) ORDER BY a.id DESC", [$id]) as $a) {
            $atts[$a['entity_id']][] = $a;
        }
        $children = Db::all('SELECT id, code, title, category, status FROM policies WHERE parent_id = ? ORDER BY code', [$id]);
        $parent = $p['parent_id'] ? Db::one('SELECT id, code, title FROM policies WHERE id = ?', [$p['parent_id']]) : null;
        $refs = [];
        foreach (array_filter(explode(',', (string)$p['refs'])) as $c) {
            $refs[$c] = Db::one('SELECT id, title FROM policies WHERE code = ?', [$c]);
        }
        $citedBy = $p['code'] ? Db::all('SELECT id, code, title FROM policies WHERE FIND_IN_SET(?, refs) > 0 ORDER BY code', [$p['code']]) : [];
        $u = $this->user();
        $filesOk = !in_array($p['classification'], Framework::RESTRICTED, true) || \App\Core\Perm::roleHas($u['role'], 'policy.manage') || \App\Core\Perm::roleHas($u['role'], 'kb.manage') || \App\Core\Perm::roleHas($u['role'], 'ia.manage');
        return $this->view('policies/show', ['title' => $p['code'] . ' — ' . $p['title'], 'p' => $p, 'versions' => $versions, 'atts' => $filesOk ? $atts : [], 'filesOk' => $filesOk,
            'children' => $children, 'parent' => $parent, 'refs' => $refs, 'citedBy' => $citedBy]);
    }

    public function create(): string
    {
        return $this->view('policies/form', ['title' => t('pol.new'), 'p' => ['category' => 'policy', 'review_months' => 12, 'owner_user_id' => $this->user()['id']], 'existing' => null, 'lists' => $this->lists()]);
    }

    public function edit(int $id): string
    {
        $p = $this->load($id);
        return $this->view('policies/form', ['title' => t('pol.edit'), 'p' => $p, 'existing' => $p, 'lists' => $this->lists()]);
    }

    private function lists(): array
    {
        return ['departments' => Ref::departments(true), 'users' => Ref::users(true), 'parents' => Db::all("SELECT id, code, title FROM policies WHERE status <> 'retired' ORDER BY COALESCE(doc_level, 9), code")];
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
        $v = new Validator($_POST);
        $v->required('title', 'pol.name')->max('title', 'pol.name', 250)->max('title_en', 'docs.title_en', 250)->in('category', 'pol.category', self::CATEGORIES)->intRange('review_months', 'pol.review_months', 1, 60)
            ->in('classification', 'docs.classification', Framework::CLASSIFICATIONS, false)->max('approval_body', 'docs.approval_body', 150);
        if ($v->str('doc_level') !== '') {
            $v->intRange('doc_level', 'docs.level', 1, 7);
        }
        if ($v->str('rm_section') !== '') {
            $v->in('rm_section', 'docs.section', Framework::SECTIONS);
        }
        $code = strtoupper($v->str('code'));
        if ($code !== '' && !preg_match('/^[A-Z0-9][A-Z0-9\-]{1,39}$/', $code)) {
            $v->fail('code', 'docs.err_code');
        } elseif ($code !== '' && Db::val('SELECT 1 FROM policies WHERE code = ? AND id <> ?', [$code, (int)($ex['id'] ?? 0)])) {
            $v->fail('code', 'docs.err_code_taken');
        }
        $parent = Request::int('parent_id');
        if ($parent !== null && ($parent === (int)($ex['id'] ?? 0) || !Db::val('SELECT 1 FROM policies WHERE id = ?', [$parent]) || ($ex && $this->isDescendant($parent, (int)$ex['id'])))) {
            $v->fail('parent_id', 'docs.err_parent');
        }
        if ($ex) {
            $v->in('status', 'pol.status', ['draft', 'approved', 'retired']);
        }
        $dept = Request::int('department_id');
        $owner = Request::int('owner_user_id');
        if ($dept !== null && !isset(Ref::departments()[$dept])) {
            $v->fail('department_id', 'validation.invalid_choice', ['field' => t('pol.department')]);
        }
        if ($owner === null || !isset(Ref::users(true)[$owner]) && $owner !== ($ex['owner_user_id'] ?? null)) {
            $v->fail('owner_user_id', 'validation.invalid_choice', ['field' => t('pol.owner')]);
        }
        $back = $ex ? url('policies/' . $ex['id'] . '/edit') : url('policies/new');
        if (!$v->ok()) {
            $this->invalid($v->errors(), $back);
        }
        $level = $v->str('doc_level') !== '' ? (int)$v->str('doc_level') : Framework::defaultLevel($v->str('category'), $code);
        $data = ['title' => $v->str('title'), 'title_en' => $v->str('title_en') ?: null, 'category' => $v->str('category'), 'doc_level' => $level, 'classification' => $v->str('classification') ?: ($ex['classification'] ?? 'internal'),
            'approval_body' => $v->str('approval_body') ?: null, 'parent_id' => $parent, 'rm_section' => $v->str('rm_section') ?: null, 'department_id' => $dept, 'owner_user_id' => $owner,
            'status' => $ex ? $v->str('status') : 'draft', 'review_months' => (int)$v->str('review_months'), 'updated_at' => date('Y-m-d H:i:s')];
        if ($code !== '') {
            $data['code'] = $code;
        }
        if ($ex) {
            Db::update('policies', $data, 'id = ?', [$ex['id']]);
            $this->audit('policy.updated', 'policy', (int)$ex['id'], ['changes' => $this->diff($ex, $data, ['code', 'title', 'title_en', 'category', 'doc_level', 'classification', 'approval_body', 'parent_id', 'rm_section', 'department_id', 'owner_user_id', 'status', 'review_months'])]);
            $id = (int)$ex['id'];
        } else {
            $id = Db::insert('policies', $data + ['created_by' => $this->user()['id'], 'created_at' => $data['updated_at']]);
            if ($code === '') {
                Db::update('policies', ['code' => sprintf('POL-%03d', $id)], 'id = ?', [$id]);
            }
            $this->audit('policy.created', 'policy', $id, ['title' => $data['title']]);
        }
        $this->redirect(url('policies/' . $id), 'admin.saved');
    }

    /** New immutable version; the previous current version is kept as history. Optional document upload. */
    public function addVersion(int $id): never
    {
        $u = $this->user();
        $p = $this->load($id);
        $v = new Validator($_POST);
        $v->required('version_label', 'pol.version')->max('version_label', 'pol.version', 30)->date('effective_date', 'pol.effective_date')->date('approval_date', 'pol.approval_date', false)
            ->max('approved_by', 'pol.approved_by', 150)->max('change_summary', 'pol.change_summary', 3000);
        if ($v->str('version_label') !== '' && Db::val('SELECT 1 FROM policy_versions WHERE policy_id = ? AND version_label = ?', [$id, $v->str('version_label')])) {
            $v->fail('version_label', 'pol.err_version_exists');
        }
        $f = $_FILES['file'] ?? null;
        $hasFile = is_array($f) && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if ($hasFile && ($err = Upload::validate($f))) {
            $v->fail('file', $err, ['max' => '5 MB', 'types' => implode(', ', array_keys(Upload::TYPES))]);
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), url('policies/' . $id) . '#versions');
        }
        $now = date('Y-m-d H:i:s');
        $viaApproval = isset($_POST['needs_approval']);
        $vid = Db::tx(function () use ($id, $v, $u, $now, $p, $viaApproval) {
            if ($viaApproval) {
                $vid = Db::insert('policy_versions', ['policy_id' => $id, 'version_label' => $v->str('version_label'), 'effective_date' => $v->str('effective_date'), 'approved_by' => null, 'approval_date' => null, 'change_summary' => $v->str('change_summary') ?: null, 'is_current' => 0, 'status' => 'pending', 'created_by' => $u['id'], 'created_at' => $now]);
                \App\Support\Approvals::request('policy_version', $vid, (int)$u['id']);
                return $vid;
            }
            Db::update('policy_versions', ['is_current' => 0], 'policy_id = ?', [$id]);
            $vid = Db::insert('policy_versions', ['policy_id' => $id, 'version_label' => $v->str('version_label'), 'effective_date' => $v->str('effective_date'), 'approved_by' => $v->str('approved_by') ?: null,
                'approval_date' => $v->str('approval_date') ?: null, 'change_summary' => $v->str('change_summary') ?: null, 'is_current' => 1, 'created_by' => $u['id'], 'created_at' => $now]);
            $approved = $v->str('approval_date') !== '';
            Db::update('policies', ['status' => $approved ? 'approved' : $p['status'], 'next_review_date' => date('Y-m-d', strtotime($v->str('effective_date') . ' +' . (int)$p['review_months'] . ' months')), 'updated_at' => $now], 'id = ?', [$id]);
            return $vid;
        });
        if ($hasFile) {
            Upload::store($f, 'policy_version', $vid, (int)$u['id']);
        }
        $this->audit('policy.version_added', 'policy', $id, ['version' => $v->str('version_label'), 'file' => $hasFile]);
        $this->redirect(url('policies/' . $id) . '#versions', $viaApproval ? 'pol.version_pending' : 'pol.version_added');
    }

    public function upload(int $id, int $vid): never
    {
        $u = $this->user();
        $this->load($id);
        Db::one('SELECT id FROM policy_versions WHERE id = ? AND policy_id = ?', [$vid, $id]) ?? $this->notFound();
        AttachmentController::handleUpload('policy_version', $vid, $u, url('policies/' . $id) . '#versions');
    }

    private function isDescendant(int $candidate, int $of): bool
    {
        for ($i = 0, $cur = $candidate; $cur && $i < 20; $i++) {
            if ($cur === $of) {
                return true;
            }
            $cur = (int)Db::val('SELECT parent_id FROM policies WHERE id = ?', [$cur]);
        }
        return false;
    }

    /** The document hierarchy of the risk framework: levels 1-7, parent/child links and references to documents not yet registered. */
    public function framework(): string
    {
        $rows = Db::all("SELECT p.id, p.code, p.title, p.title_en, p.category, p.doc_level, p.classification, p.status, p.parent_id, p.rm_section, p.next_review_date, p.approval_body, v.version_label FROM policies p
            LEFT JOIN policy_versions v ON v.policy_id = p.id AND v.is_current = 1 WHERE p.status <> 'retired' ORDER BY COALESCE(p.doc_level, 9), p.code");
        $byLevel = [];
        foreach ($rows as $r) {
            $byLevel[(int)($r['doc_level'] ?? 0)][] = $r;
        }
        $stats = ['total' => count($rows), 'draft' => count(array_filter($rows, fn($r) => $r['status'] === 'draft')), 'approved' => count(array_filter($rows, fn($r) => $r['status'] === 'approved')),
            'review_due' => count(array_filter($rows, fn($r) => $r['next_review_date'] && $r['next_review_date'] < date('Y-m-d')))];
        return $this->view('policies/framework', ['title' => t('docs.framework'), 'byLevel' => $byLevel, 'missing' => DocImporter::missingReferences(), 'stats' => $stats]);
    }

    /** Upload one or more bank documents; each is read, registered, versioned and indexed in the reference library. */
    public function import(): never
    {
        $u = $this->user();
        $files = $_FILES['files'] ?? null;
        $back = url('policies/framework');
        if (!is_array($files) || !is_array($files['name'] ?? null)) {
            $this->redirect($back, 'upload.none', 'error');
        }
        $ok = $fail = [];
        foreach (array_keys($files['name']) as $i) {
            $f = ['name' => (string)$files['name'][$i], 'type' => (string)$files['type'][$i], 'tmp_name' => (string)$files['tmp_name'][$i], 'error' => (int)$files['error'][$i], 'size' => (int)$files['size'][$i]];
            if ($f['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $err = Upload::validate($f) ?? DocImporter::check($f['tmp_name'], $f['name']);
            if ($err !== null) {
                $fail[] = $f['name'] . ': ' . t($err, ['max' => '5 MB', 'types' => 'docx, xlsx, pdf']);
                continue;
            }
            try {
                $r = DocImporter::import($f['tmp_name'], $f['name'], (int)$u['id'], true);
                $ok[] = $r['code'] . ' (' . t(sprintf('docs.action_%s', $r['action'])) . ', ' . t('docs.n_passages', ['n' => $r['passages']]) . ')';
            } catch (\Throwable $e) {
                $fail[] = $f['name'] . ': ' . t('docs.err_read');
            }
        }
        if (!$ok && !$fail) {
            $this->redirect($back, 'upload.none', 'error');
        }
        if ($ok) {
            \App\Core\Session::message('success', t('docs.imported', ['list' => implode('، ', $ok)]));
        }
        if ($fail) {
            \App\Core\Session::message('error', t('docs.import_failed', ['list' => implode('؛ ', $fail)]));
        }
        $this->redirect($back);
    }
}
