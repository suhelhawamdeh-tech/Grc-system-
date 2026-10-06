<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Db;
use App\Core\Money;
use App\Core\Perm;
use App\Core\Request;
use App\Core\Scope;
use App\Core\Session;
use App\Core\Validator;
use App\Support\Csv;
use App\Support\Ref;
use App\Support\RiskFilter;
use App\Support\Scoring;
use App\Support\Upload;

final class RiskController extends Controller
{
    private const PER_PAGE = 25;
    public const TRACKED = ['title', 'description', 'category_id', 'causes', 'consequences', 'department_id', 'branch_id', 'owner_user_id',
        'identified_date', 'review_date', 'inh_likelihood', 'inh_impact', 'est_financial_impact', 'controls_description', 'control_owner_id',
        'control_assessment', 'res_likelihood', 'res_impact', 'res_rationale', 'treatment_decision', 'treatment_notes', 'status'];

    // ---------------------------------------------------------------- list
    public function index(): string
    {
        $u = $this->user();
        $f = RiskFilter::fromArray($_GET);
        $total = RiskFilter::count($f, $u);
        $page = min($this->page(), max(1, (int)ceil($total / self::PER_PAGE)));
        $rows = RiskFilter::fetch($f, $u, self::PER_PAGE, ($page - 1) * self::PER_PAGE);
        return $this->view('risks/index', [
            'title' => t('risk.register'), 'rows' => $rows, 'f' => $f, 'total' => $total, 'page' => $page, 'per' => self::PER_PAGE,
            'lists' => $this->lists($u),
        ]);
    }

    private function lists(array $u): array
    {
        $deps = Ref::departments(true);
        $brs = Ref::branches(true);
        if (Perm::scopeType($u['role']) === 'department') {
            $deps = array_filter($deps, static fn($d) => (int)$d['id'] === (int)$u['department_id']);
        }
        if (Perm::scopeType($u['role']) === 'branch') {
            $brs = array_filter($brs, static fn($b) => (int)$b['id'] === (int)$u['branch_id']);
        }
        return ['categories' => Ref::categories(true), 'departments' => $deps, 'branches' => $brs, 'users' => Ref::users(true), 'bands' => Ref::bands()];
    }

    // ---------------------------------------------------------------- show
    private function load(int $id): array
    {
        $u = $this->user();
        $r = Db::one(RiskFilter::SELECT . ' WHERE r.id = ?', [$id]);
        // Out-of-scope records look exactly like missing ones (no enumeration).
        if (!$r || !Scope::allows($u, $r)) {
            $this->notFound();
        }
        return $r;
    }

    public function show(int $id): string
    {
        $u = $this->user();
        $r = $this->load($id);
        $actions = Db::all('SELECT a.*, o.full_name AS owner_name FROM risk_actions a JOIN users o ON o.id = a.owner_user_id WHERE a.risk_id = ? ORDER BY a.deadline', [$id]);
        $history = Db::all('SELECT h.*, u.full_name FROM risk_history h LEFT JOIN users u ON u.id = h.user_id WHERE h.risk_id = ? ORDER BY h.id DESC LIMIT 100', [$id]);
        $attachments = Db::all('SELECT a.*, u.full_name FROM attachments a JOIN users u ON u.id = a.uploaded_by WHERE a.entity_type = ? AND a.entity_id = ? AND a.deleted_at IS NULL ORDER BY a.id DESC', ['risk', $id]);
        [$sw, $sp] = Scope::sql($u, 'i');
        $incidents = Db::all('SELECT i.id, i.incident_code, i.title, i.occurrence_date, i.net_loss, i.event_type FROM incidents i JOIN incident_risks ir ON ir.incident_id = i.id WHERE ir.risk_id = ? AND i.archived_at IS NULL AND ' . $sw . ' ORDER BY i.occurrence_date DESC', array_merge([$id], $sp));
        $controls = Perm::roleHas($u['role'], 'control.view') ? Db::all('SELECT c.id, c.code, c.name, c.is_key, c.last_result, c.last_tested_on, c.next_test_due FROM risk_controls rc JOIN controls c ON c.id = rc.control_id WHERE rc.risk_id = ? AND c.archived_at IS NULL ORDER BY c.code', [$id]) : null;
        $suggest = null;
        if ($r['est_financial_impact'] !== null) {
            $suggest = Scoring::impactForAmount(Money::fromDb($r['est_financial_impact']));
        }
        return $this->view('risks/show', [
            'title' => $r['risk_code'] . ' — ' . $r['title'], 'r' => $r, 'controls' => $controls, 'actions' => $actions, 'history' => $history, 'attachments' => $attachments,
            'incidents' => $incidents, 'suggest' => $suggest, 'users' => Ref::users(true),
        ]);
    }

    // ---------------------------------------------------------------- create / edit
    public function create(): string
    {
        $u = $this->user();
        $defaults = ['identified_date' => date('Y-m-d'), 'treatment_decision' => 'mitigate', 'control_assessment' => 'not_assessed', 'status' => 'active', 'owner_user_id' => $u['id']];
        if (Perm::scopeType($u['role']) === 'department') {
            $defaults['department_id'] = $u['department_id'];
        }
        if (Perm::scopeType($u['role']) === 'branch') {
            $defaults['branch_id'] = $u['branch_id'];
        }
        return $this->form($defaults, null);
    }

    public function edit(int $id): string
    {
        $r = $this->load($id);
        if ($r['archived_at'] !== null) {
            $this->redirect(url('risks/' . $id), 'risk.err_archived_readonly', 'error');
        }
        $data = $r;
        $data['est_financial_impact'] = $r['est_financial_impact'] !== null ? Money::plain($r['est_financial_impact']) : '';
        return $this->form($data, $r);
    }

    private function form(array $data, ?array $existing): string
    {
        $u = $this->user();
        return $this->view('risks/form', [
            'title' => $existing ? t('risk.edit') : t('risk.new'), 'r' => $data, 'existing' => $existing,
            'lists' => $this->lists($u), 'levelsL' => Ref::levels('likelihood'), 'levelsI' => Ref::levels('impact'),
        ]);
    }

    public function store(): never
    {
        $u = $this->user();
        [$data, $errors] = $this->collect($u, null);
        if ($errors) {
            $this->invalid($errors, url('risks/new'));
        }
        $now = date('Y-m-d H:i:s');
        $id = Db::tx(function () use ($data, $u, $now) {
            $id = Db::insert('risks', $data + ['created_by' => $u['id'], 'created_at' => $now, 'updated_at' => $now]);
            Db::update('risks', ['risk_code' => sprintf('RSK-%05d', $id)], 'id = ?', [$id]);
            Db::insert('risk_history', ['risk_id' => $id, 'user_id' => $u['id'], 'event' => 'created', 'changes' => null, 'created_at' => $now]);
            return $id;
        });
        $this->audit('risk.created', 'risk', $id, ['title' => $data['title'], 'inh_score' => $data['inh_score'], 'res_score' => $data['res_score']]);
        $this->redirect(url('risks/' . $id), 'risk.created');
    }

    public function update(int $id): never
    {
        $u = $this->user();
        $old = $this->load($id);
        if ($old['archived_at'] !== null) {
            $this->redirect(url('risks/' . $id), 'risk.err_archived_readonly', 'error');
        }
        [$data, $errors] = $this->collect($u, $old);
        if ($errors) {
            $this->invalid($errors, url('risks/' . $id . '/edit'));
        }
        $changes = $this->diff($old, $data, self::TRACKED);
        if (!$changes) {
            $this->redirect(url('risks/' . $id), 'risk.no_changes', 'info');
        }
        $now = date('Y-m-d H:i:s');
        Db::tx(function () use ($id, $data, $u, $now, $changes) {
            Db::update('risks', $data + ['updated_by' => $u['id'], 'updated_at' => $now], 'id = ?', [$id]);
            Db::insert('risk_history', ['risk_id' => $id, 'user_id' => $u['id'], 'event' => 'updated', 'changes' => json_encode($changes, JSON_UNESCAPED_UNICODE), 'created_at' => $now]);
        });
        $this->audit('risk.updated', 'risk', $id, ['fields' => array_keys($changes)]);
        $this->redirect(url('risks/' . $id), 'risk.updated');
    }

    /** Validate POST and build the DB row. @return array{0:array,1:array} */
    private function collect(array $u, ?array $existing): array
    {
        $v = new Validator($_POST);
        $v->required('title', 'risk.title')->max('title', 'risk.title', 250)
            ->required('description', 'risk.description')->max('description', 'risk.description', 5000)
            ->max('causes', 'risk.causes', 5000)->max('consequences', 'risk.consequences', 5000)
            ->max('controls_description', 'risk.controls', 5000)->max('res_rationale', 'risk.res_rationale', 5000)
            ->max('treatment_notes', 'risk.treatment_notes', 5000)
            ->date('identified_date', 'risk.identified_date')->date('review_date', 'risk.review_date', false)
            ->intRange('inh_likelihood', 'risk.likelihood', 1, 5)->intRange('inh_impact', 'risk.impact', 1, 5)
            ->intRange('res_likelihood', 'risk.likelihood', 1, 5)->intRange('res_impact', 'risk.impact', 1, 5)
            ->in('control_assessment', 'risk.control_assessment', ['not_assessed', 'effective', 'partially_effective', 'ineffective'])
            ->in('treatment_decision', 'risk.treatment', ['mitigate', 'accept', 'transfer', 'avoid'])
            ->in('status', 'risk.status', ['active', 'closed']);

        $refId = static function (string $key, array $set, ?int $current = null): ?int {
            $val = Request::int($key);
            if ($val === null) {
                return null;
            }
            return isset($set[$val]) || $val === $current ? $val : -1;
        };
        $category = $refId('category_id', Ref::categories(true), $existing['category_id'] ?? null);
        $dept = $refId('department_id', Ref::departments(true), $existing['department_id'] ?? null);
        $branch = $refId('branch_id', Ref::branches(true), $existing['branch_id'] ?? null);
        $owner = $refId('owner_user_id', Ref::users(true), $existing['owner_user_id'] ?? null);
        $ctrlOwner = $refId('control_owner_id', Ref::users(true), $existing['control_owner_id'] ?? null);
        if ($category === null || $category === -1) {
            $v->fail('category_id', 'validation.invalid_choice', ['field' => t('risk.category')]);
        }
        if ($dept === null || $dept === -1) {
            $v->fail('department_id', 'validation.invalid_choice', ['field' => t('risk.department')]);
        }
        if ($branch === -1) {
            $v->fail('branch_id', 'validation.invalid_choice', ['field' => t('risk.branch')]);
        }
        if ($owner === null || $owner === -1) {
            $v->fail('owner_user_id', 'validation.invalid_choice', ['field' => t('risk.owner')]);
        }
        if ($ctrlOwner === -1) {
            $v->fail('control_owner_id', 'validation.invalid_choice', ['field' => t('risk.control_owner')]);
        }
        // Server-side scope: restricted roles cannot create/move risks outside their department / branch.
        $st = Perm::scopeType($u['role']);
        if ($st === 'department' && $dept !== (int)$u['department_id']) {
            $v->fail('department_id', 'validation.out_of_scope');
        }
        if ($st === 'branch' && $branch !== (int)$u['branch_id']) {
            $v->fail('branch_id', 'validation.out_of_scope');
        }

        $est = null;
        if ($v->str('est_financial_impact') !== '') {
            $est = Money::parse($v->str('est_financial_impact'), Money::decimals());
            if ($est === null) {
                $v->fail('est_financial_impact', 'validation.amount', ['field' => t('risk.est_financial_impact')]);
            }
        }
        $identified = $v->str('identified_date');
        $review = $v->str('review_date');
        if ($identified !== '' && $identified > date('Y-m-d')) {
            $v->fail('identified_date', 'validation.not_future', ['field' => t('risk.identified_date')]);
        }
        if ($review !== '' && $identified !== '' && $review < $identified) {
            $v->fail('review_date', 'validation.after_date', ['field' => t('risk.review_date'), 'other' => t('risk.identified_date')]);
        }

        $il = (int)$v->str('inh_likelihood');
        $ii = (int)$v->str('inh_impact');
        $rl = (int)$v->str('res_likelihood');
        $ri = (int)$v->str('res_impact');
        $inh = Scoring::score($il, $ii);
        $res = Scoring::score($rl, $ri);
        // Residual is assessed explicitly. When it differs from inherent, the assessor must say why.
        $levelErr = array_intersect_key($v->errors(), array_flip(['inh_likelihood', 'inh_impact', 'res_likelihood', 'res_impact']));
        if (!$levelErr && $res !== $inh && $v->str('res_rationale') === '') {
            $v->fail('res_rationale', 'risk.err_rationale_required');
        }
        if (!$levelErr && $v->str('treatment_decision') === 'accept' && $res >= $this->highThreshold() && $v->str('treatment_notes') === '') {
            $v->fail('treatment_notes', 'risk.err_accept_high_needs_notes');
        }

        $nz = static fn(string $s): ?string => $s === '' ? null : $s;
        $data = [
            'title' => $v->str('title'), 'description' => $v->str('description'), 'category_id' => $category, 'causes' => $nz($v->str('causes')),
            'consequences' => $nz($v->str('consequences')), 'department_id' => $dept, 'branch_id' => $branch, 'owner_user_id' => $owner,
            'identified_date' => $identified, 'review_date' => $nz($review),
            'inh_likelihood' => $il, 'inh_impact' => $ii, 'inh_score' => $inh,
            'est_financial_impact' => $est !== null ? Money::toDb($est) : null,
            'controls_description' => $nz($v->str('controls_description')), 'control_owner_id' => $ctrlOwner, 'control_assessment' => $v->str('control_assessment'),
            'res_likelihood' => $rl, 'res_impact' => $ri, 'res_score' => $res, 'res_rationale' => $nz($v->str('res_rationale')),
            'treatment_decision' => $v->str('treatment_decision'), 'treatment_notes' => $nz($v->str('treatment_notes')), 'status' => $v->str('status'),
        ];
        return [$data, $v->errors()];
    }

    private function highThreshold(): int
    {
        return (int)(Db::val('SELECT MIN(min_score) FROM score_bands WHERE is_high = 1') ?? 26);
    }

    // ---------------------------------------------------------------- archive
    public function archive(int $id): never
    {
        $u = $this->user();
        $r = $this->load($id);
        if ($r['archived_at'] === null) {
            $now = date('Y-m-d H:i:s');
            Db::update('risks', ['archived_at' => $now, 'archived_by' => $u['id'], 'updated_at' => $now, 'updated_by' => $u['id']], 'id = ?', [$id]);
            Db::insert('risk_history', ['risk_id' => $id, 'user_id' => $u['id'], 'event' => 'archived', 'changes' => null, 'created_at' => $now]);
            $this->audit('risk.archived', 'risk', $id);
        }
        $this->redirect(url('risks/' . $id), 'risk.archived_msg');
    }

    public function restore(int $id): never
    {
        $u = $this->user();
        $r = $this->load($id);
        if ($r['archived_at'] !== null) {
            $now = date('Y-m-d H:i:s');
            Db::update('risks', ['archived_at' => null, 'archived_by' => null, 'updated_at' => $now, 'updated_by' => $u['id']], 'id = ?', [$id]);
            Db::insert('risk_history', ['risk_id' => $id, 'user_id' => $u['id'], 'event' => 'restored', 'changes' => null, 'created_at' => $now]);
            $this->audit('risk.restored', 'risk', $id);
        }
        $this->redirect(url('risks/' . $id), 'risk.restored');
    }

    // ---------------------------------------------------------------- treatment actions
    public function addAction(int $id): never
    {
        $u = $this->user();
        $r = $this->load($id);
        if ($r['archived_at'] !== null) {
            $this->redirect(url('risks/' . $id), 'risk.err_archived_readonly', 'error');
        }
        $v = new Validator($_POST);
        $v->required('title', 'action.title')->max('title', 'action.title', 250)->max('description', 'action.description', 2000)->date('deadline', 'action.deadline')
            ->in('status', 'action.status', ['not_started', 'in_progress', 'completed', 'cancelled']);
        $owner = Request::int('owner_user_id');
        if ($owner === null || !isset(Ref::users(true)[$owner])) {
            $v->fail('owner_user_id', 'validation.invalid_choice', ['field' => t('action.owner')]);
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), url('risks/' . $id) . '#actions');
        }
        $now = date('Y-m-d H:i:s');
        $status = $v->str('status');
        $aid = Db::insert('risk_actions', [
            'risk_id' => $id, 'title' => $v->str('title'), 'description' => $v->str('description') ?: null, 'owner_user_id' => $owner,
            'deadline' => $v->str('deadline'), 'status' => $status, 'completed_on' => $status === 'completed' ? date('Y-m-d') : null,
            'created_by' => $u['id'], 'created_at' => $now, 'updated_at' => $now,
        ]);
        Db::insert('risk_history', ['risk_id' => $id, 'user_id' => $u['id'], 'event' => 'action_added', 'changes' => json_encode(['action' => $v->str('title')], JSON_UNESCAPED_UNICODE), 'created_at' => $now]);
        $this->audit('risk.action_added', 'risk', $id, ['action_id' => $aid]);
        $this->redirect(url('risks/' . $id) . '#actions', 'action.added');
    }

    public function updateAction(int $id, int $aid): never
    {
        $u = $this->user();
        $this->load($id);
        $a = Db::one('SELECT * FROM risk_actions WHERE id = ? AND risk_id = ?', [$aid, $id]);
        if (!$a) {
            $this->notFound();
        }
        $v = new Validator($_POST);
        $v->date('deadline', 'action.deadline')->in('status', 'action.status', ['not_started', 'in_progress', 'completed', 'cancelled']);
        $owner = Request::int('owner_user_id');
        if ($owner === null || (!isset(Ref::users(true)[$owner]) && $owner !== (int)$a['owner_user_id'])) {
            $v->fail('owner_user_id', 'validation.invalid_choice', ['field' => t('action.owner')]);
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), url('risks/' . $id) . '#actions');
        }
        $status = $v->str('status');
        $new = ['deadline' => $v->str('deadline'), 'status' => $status, 'owner_user_id' => $owner];
        $ch = $this->diff($a, $new, ['deadline', 'status', 'owner_user_id']);
        if ($ch) {
            $now = date('Y-m-d H:i:s');
            Db::update('risk_actions', $new + ['completed_on' => $status === 'completed' ? ($a['completed_on'] ?? date('Y-m-d')) : null, 'updated_at' => $now], 'id = ?', [$aid]);
            Db::insert('risk_history', ['risk_id' => $id, 'user_id' => $u['id'], 'event' => 'action_updated', 'changes' => json_encode(['action' => $a['title']] + $ch, JSON_UNESCAPED_UNICODE), 'created_at' => $now]);
            $this->audit('risk.action_updated', 'risk', $id, ['action_id' => $aid, 'fields' => array_keys($ch)]);
        }
        $this->redirect(url('risks/' . $id) . '#actions', 'action.updated');
    }

    // ---------------------------------------------------------------- attachments
    public function upload(int $id): never
    {
        $u = $this->user();
        $this->load($id);
        AttachmentController::handleUpload('risk', $id, $u, url('risks/' . $id) . '#attachments');
    }

    // ---------------------------------------------------------------- reports
    public function export(): never
    {
        $u = Auth::requirePerm('export.run');
        $f = RiskFilter::fromArray($_GET);
        $count = RiskFilter::count($f, $u);
        $this->audit('export.risks', 'risk', null, ['rows' => $count, 'filters' => array_filter($f, static fn($x) => $x !== '' && $x !== null)]);
        $h = [t('risk.id'), t('risk.title'), t('risk.description'), t('risk.category'), t('risk.department'), t('risk.branch'), t('risk.owner'),
            t('risk.identified_date'), t('risk.review_date'), t('risk.inh_likelihood'), t('risk.inh_impact'), t('risk.inh_score'), t('risk.inh_band'),
            t('risk.est_financial_impact') . ' (' . \App\Core\Settings::get('currency_code', 'LYD') . ')', t('risk.controls'), t('risk.control_owner'), t('risk.control_assessment'),
            t('risk.res_likelihood'), t('risk.res_impact'), t('risk.res_score'), t('risk.res_band'), t('risk.res_rationale'),
            t('risk.treatment'), t('risk.status'), t('risk.archived'), t('risk.open_actions'), t('risk.next_deadline')];
        $gen = (function () use ($f, $u, $count) {
            $users = Ref::users();
            for ($off = 0; $off < min($count, 50000); $off += 500) {
                $rows = RiskFilter::fetch($f, $u, 500, $off);
                $ids = array_column($rows, 'id');
                $act = [];
                if ($ids) {
                    $in = implode(',', array_fill(0, count($ids), '?'));
                    foreach (Db::all("SELECT risk_id, COUNT(*) n, MIN(deadline) d FROM risk_actions WHERE status IN ('not_started','in_progress') AND risk_id IN ($in) GROUP BY risk_id", $ids) as $a) {
                        $act[$a['risk_id']] = $a;
                    }
                }
                foreach ($rows as $r) {
                    yield [
                        $r['risk_code'], $r['title'], $r['description'], lname($r, 'cat'), lname($r, 'dept'), lname($r, 'branch'), $r['owner_name'],
                        $r['identified_date'], $r['review_date'], (int)$r['inh_likelihood'], (int)$r['inh_impact'], (int)$r['inh_score'], lname(Ref::band((int)$r['inh_score']), 'label'),
                        $r['est_financial_impact'] !== null ? Money::plain($r['est_financial_impact']) : '', $r['controls_description'],
                        $r['control_owner_id'] ? ($users[(int)$r['control_owner_id']]['full_name'] ?? '') : '', t('enum.control.' . $r['control_assessment']),
                        (int)$r['res_likelihood'], (int)$r['res_impact'], (int)$r['res_score'], lname(Ref::band((int)$r['res_score']), 'label'), $r['res_rationale'],
                        t('enum.treatment.' . $r['treatment_decision']), t('enum.risk_status.' . $r['status']), $r['archived_at'] ? t('common.yes') : t('common.no'),
                        (int)($act[$r['id']]['n'] ?? 0), $act[$r['id']]['d'] ?? '',
                    ];
                }
            }
        })();
        Csv::download('risk-register-' . date('Ymd-His') . '.csv', $h, $gen, Request::str('sep', true) === ';' ? ';' : ',');
    }

    public function printList(): string
    {
        $u = Auth::requirePerm('export.run');
        $f = RiskFilter::fromArray($_GET);
        $count = RiskFilter::count($f, $u);
        $rows = RiskFilter::fetch($f, $u, 500, 0);
        $this->audit('report.risks_print', 'risk', null, ['rows' => $count]);
        return $this->view('risks/print', ['title' => t('risk.register'), 'rows' => $rows, 'total' => $count, 'f' => $f, 'u' => $u, 'lists' => $this->lists($u)], 'layout/print');
    }

    public function printOne(int $id): string
    {
        Auth::requirePerm('export.run');
        $r = $this->load($id);
        $actions = Db::all('SELECT a.*, o.full_name AS owner_name FROM risk_actions a JOIN users o ON o.id = a.owner_user_id WHERE a.risk_id = ? ORDER BY a.deadline', [$id]);
        $this->audit('report.risk_print', 'risk', $id);
        return $this->view('risks/print_one', ['title' => $r['risk_code'], 'r' => $r, 'actions' => $actions, 'users' => Ref::users()], 'layout/print');
    }
}
