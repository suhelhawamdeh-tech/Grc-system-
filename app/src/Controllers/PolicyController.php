<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Db;
use App\Core\Request;
use App\Core\Validator;
use App\Support\Ref;
use App\Support\Upload;

final class PolicyController extends Controller
{
    public const CATEGORIES = ['policy', 'procedure', 'standard', 'charter', 'sharia_resolution'];
    private const PER_PAGE = 25;

    public function index(): string
    {
        $f = ['q' => mb_substr(Request::str('q', true), 0, 100), 'status' => Request::str('status', true), 'category' => Request::str('category', true), 'review_overdue' => Request::str('review_overdue', true) === '1' ? '1' : ''];
        $w = ['1=1'];
        $p = [];
        if ($f['q'] !== '') {
            $w[] = '(p.title LIKE ? OR p.code LIKE ?)';
            $l = Db::like($f['q']);
            array_push($p, $l, $l);
        }
        if (in_array($f['status'], ['draft', 'approved', 'retired'], true)) {
            $w[] = 'p.status = ?';
            $p[] = $f['status'];
        }
        if (in_array($f['category'], self::CATEGORIES, true)) {
            $w[] = 'p.category = ?';
            $p[] = $f['category'];
        }
        if ($f['review_overdue'] === '1') {
            $w[] = "p.status <> 'retired' AND p.next_review_date < ?";
            $p[] = date('Y-m-d');
        }
        $ws = implode(' AND ', $w);
        $total = (int)Db::val('SELECT COUNT(*) FROM policies p WHERE ' . $ws, $p);
        $page = min($this->page(), max(1, (int)ceil($total / self::PER_PAGE)));
        $rows = Db::all("SELECT p.*, d.name_en AS dept_en, d.name_ar AS dept_ar, o.full_name AS owner_name, v.version_label AS cur_version, v.effective_date AS cur_effective FROM policies p LEFT JOIN departments d ON d.id = p.department_id JOIN users o ON o.id = p.owner_user_id LEFT JOIN policy_versions v ON v.policy_id = p.id AND v.is_current = 1 WHERE $ws ORDER BY p.code LIMIT ? OFFSET ?", array_merge($p, [self::PER_PAGE, ($page - 1) * self::PER_PAGE]));
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
        return $this->view('policies/show', ['title' => $p['code'] . ' — ' . $p['title'], 'p' => $p, 'versions' => $versions, 'atts' => $atts]);
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
        return ['departments' => Ref::departments(true), 'users' => Ref::users(true)];
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
        $v->required('title', 'pol.name')->max('title', 'pol.name', 250)->in('category', 'pol.category', self::CATEGORIES)->intRange('review_months', 'pol.review_months', 1, 60);
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
        $data = ['title' => $v->str('title'), 'category' => $v->str('category'), 'department_id' => $dept, 'owner_user_id' => $owner, 'status' => $ex ? $v->str('status') : 'draft', 'review_months' => (int)$v->str('review_months'), 'updated_at' => date('Y-m-d H:i:s')];
        if ($ex) {
            Db::update('policies', $data, 'id = ?', [$ex['id']]);
            $this->audit('policy.updated', 'policy', (int)$ex['id'], ['changes' => $this->diff($ex, $data, ['title', 'category', 'department_id', 'owner_user_id', 'status', 'review_months'])]);
            $id = (int)$ex['id'];
        } else {
            $id = Db::insert('policies', $data + ['created_by' => $this->user()['id'], 'created_at' => $data['updated_at']]);
            Db::update('policies', ['code' => sprintf('POL-%03d', $id)], 'id = ?', [$id]);
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
}
