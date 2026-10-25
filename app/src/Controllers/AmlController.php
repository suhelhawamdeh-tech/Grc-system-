<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Db;
use App\Core\Perm;
use App\Core\Request;
use App\Core\Validator;
use App\Support\AmlScoring;
use App\Support\Csv;
use App\Support\Ref;

/**
 * Anti-money laundering (risk-based approach): risk factors in four dimensions (customers, products and services,
 * delivery channels, geographic areas), customer risk rating with due-diligence level and periodic review,
 * the enterprise-wide picture with the controls that mitigate each dimension, and regulatory reports.
 */
final class AmlController extends Controller
{
    private const RATINGS = ['low', 'medium', 'high'];
    private const REPORT_TYPES = ['str', 'ctr', 'periodic', 'ewra', 'other'];
    private const PER_PAGE = 25;

    // ------------------------------------------------------------------ overview (enterprise-wide risk assessment)

    public function index(): string
    {
        $today = date('Y-m-d');
        $byRating = array_column(Db::all("SELECT rating, COUNT(*) AS n FROM aml_customers WHERE status = 'active' GROUP BY rating"), 'n', 'rating');
        $stats = [
            'customers' => array_sum(array_map('intval', $byRating)),
            'high' => (int)($byRating['high'] ?? 0),
            'pep' => (int)Db::val("SELECT COUNT(*) FROM aml_customers WHERE status = 'active' AND is_pep = 1"),
            'sanctions' => (int)Db::val("SELECT COUNT(*) FROM aml_customers WHERE status = 'active' AND sanctions_match = 1"),
            'review_overdue' => (int)Db::val("SELECT COUNT(*) FROM aml_customers WHERE status = 'active' AND next_review_due < ?", [$today]),
            'reports_overdue' => (int)Db::val("SELECT COUNT(*) FROM aml_reports r WHERE r.status = 'draft' AND r.due_date < ? AND " . $this->reportScope('r')[0], [$today]),
        ];
        $controls = Perm::roleHas($this->user()['role'], 'aml.manage') ? Db::all('SELECT id, code, name FROM controls WHERE archived_at IS NULL ORDER BY code LIMIT 500') : [];
        return $this->view('aml/index', ['title' => t('aml.title'), 'dims' => AmlScoring::dimensionSummary(), 'byRating' => $byRating, 'stats' => $stats, 'controls' => $controls]);
    }

    public function linkControl(): never
    {
        $dim = Request::str('dimension');
        $ctl = Request::int('control_id');
        if (!in_array($dim, AmlScoring::DIMENSIONS, true) || $ctl === null || !Db::val('SELECT 1 FROM controls WHERE id = ? AND archived_at IS NULL', [$ctl])) {
            $this->redirect(url('aml') . '#controls', 'aml.err_control', 'error');
        }
        Db::exec('INSERT IGNORE INTO aml_dimension_controls (dimension, control_id, created_by, created_at) VALUES (?, ?, ?, ?)', [$dim, $ctl, $this->user()['id'], date('Y-m-d H:i:s')]);
        $this->audit('aml.control_linked', 'aml_dimension', null, ['dimension' => $dim, 'control_id' => $ctl]);
        $this->redirect(url('aml') . '#controls', 'aml.control_linked');
    }

    public function unlinkControl(): never
    {
        $dim = Request::str('dimension');
        $ctl = (int)Request::int('control_id');
        Db::exec('DELETE FROM aml_dimension_controls WHERE dimension = ? AND control_id = ?', [$dim, $ctl]);
        $this->audit('aml.control_unlinked', 'aml_dimension', null, ['dimension' => $dim, 'control_id' => $ctl]);
        $this->redirect(url('aml') . '#controls', 'aml.control_unlinked');
    }

    // ------------------------------------------------------------------ risk factors

    public function factors(): string
    {
        $rows = Db::all("SELECT f.*, (SELECT COUNT(*) FROM aml_customers c WHERE c.customer_type_id = f.id OR c.geography_id = f.id) + (SELECT COUNT(*) FROM aml_customer_factors x WHERE x.factor_id = f.id) AS used FROM aml_factors f ORDER BY FIELD(f.dimension, 'customer', 'product', 'channel', 'geography'), f.risk_level DESC, f.code");
        $by = [];
        foreach ($rows as $r) {
            $by[$r['dimension']][] = $r;
        }
        return $this->view('aml/factors', ['title' => t('aml.factors'), 'by' => $by]);
    }

    public function factorEdit(int $id): string
    {
        $f = Db::one('SELECT * FROM aml_factors WHERE id = ?', [$id]) ?? $this->notFound();
        return $this->view('aml/factor_form', ['title' => t('aml.factor_edit'), 'f' => $f, 'existing' => $f]);
    }

    public function factorNew(): string
    {
        return $this->view('aml/factor_form', ['title' => t('aml.factor_new'), 'f' => ['dimension' => Request::str('dimension', true), 'risk_level' => 2, 'is_active' => 1], 'existing' => null]);
    }

    public function factorStore(): never
    {
        $this->factorSave(null);
    }

    public function factorUpdate(int $id): never
    {
        $this->factorSave(Db::one('SELECT * FROM aml_factors WHERE id = ?', [$id]) ?? $this->notFound());
    }

    private function factorSave(?array $ex): never
    {
        $v = new Validator($_POST);
        if (!$ex) {
            $v->in('dimension', 'aml.dimension', AmlScoring::DIMENSIONS)->required('code', 'aml.f_code')->max('code', 'aml.f_code', 30);
            if ($v->str('code') !== '' && !preg_match('/^[A-Z0-9][A-Z0-9_-]*$/', $v->str('code'))) {
                $v->fail('code', 'aml.err_code_format');
            } elseif ($v->str('code') !== '' && Db::val('SELECT 1 FROM aml_factors WHERE code = ?', [$v->str('code')])) {
                $v->fail('code', 'aml.err_code_taken');
            }
        }
        $v->required('name_en', 'aml.f_name_en')->max('name_en', 'aml.f_name_en', 150)->required('name_ar', 'aml.f_name_ar')->max('name_ar', 'aml.f_name_ar', 150)->intRange('risk_level', 'aml.level', 1, 3)->max('t24_code', 'aml.t24_code', 60);
        $dim = $ex['dimension'] ?? $v->str('dimension');
        $active = isset($_POST['is_active']) ? 1 : 0;
        $default = isset($_POST['is_default']) ? 1 : 0;
        if ($default && !$active) {
            $v->fail('is_default', 'aml.err_default_inactive');
        }
        if ($ex && !$active && (int)Db::val('SELECT (SELECT COUNT(*) FROM aml_customers c WHERE c.customer_type_id = ? OR c.geography_id = ?) + (SELECT COUNT(*) FROM aml_customer_factors x WHERE x.factor_id = ?)', [$ex['id'], $ex['id'], $ex['id']])) {
            $v->fail('is_active', 'aml.err_factor_in_use');
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), $ex ? url('aml/factors/' . $ex['id'] . '/edit') : url('aml/factors/new', ['dimension' => $dim]));
        }
        $now = date('Y-m-d H:i:s');
        $d = ['name_en' => $v->str('name_en'), 'name_ar' => $v->str('name_ar'), 'risk_level' => (int)$v->str('risk_level'), 't24_code' => $v->str('t24_code') ?: null, 'is_default' => $default, 'is_active' => $active, 'updated_at' => $now];
        $n = Db::tx(function () use ($ex, $d, $dim, $v, $now) {
            if ($d['is_default']) {
                Db::exec('UPDATE aml_factors SET is_default = 0 WHERE dimension = ?', [$dim]);
            }
            if ($ex) {
                Db::update('aml_factors', $d, 'id = ?', [$ex['id']]);
                $id = (int)$ex['id'];
            } else {
                $id = Db::insert('aml_factors', $d + ['dimension' => $dim, 'code' => $v->str('code'), 'created_at' => $now]);
            }
            $n = 0;
            if ($ex && (int)$ex['risk_level'] !== $d['risk_level']) {
                // A new risk level changes the rating of every customer that uses this factor.
                foreach (Db::all('SELECT id FROM aml_customers WHERE customer_type_id = ? OR geography_id = ? UNION SELECT customer_id FROM aml_customer_factors WHERE factor_id = ?', [$id, $id, $id]) as $c) {
                    AmlScoring::recalc((int)$c['id']);
                    $n++;
                }
            }
            return [$id, $n];
        });
        AmlScoring::resetCache();
        $this->audit($ex ? 'aml.factor_updated' : 'aml.factor_created', 'aml_factor', $n[0], $ex ? ['changes' => $this->diff($ex, $d, ['name_en', 'name_ar', 'risk_level', 't24_code', 'is_default', 'is_active']), 'customers_rescored' => $n[1]] : ['code' => $v->str('code')]);
        $this->redirect(url('aml/factors'), $n[1] ? 'aml.factor_rescored' : 'admin.saved', 'success', ['n' => $n[1]]);
    }

    // ------------------------------------------------------------------ customers

    private function customerFilter(): array
    {
        $f = ['q' => Request::str('q', true), 'rating' => Request::str('rating', true), 'pep' => Request::str('pep', true) === '1' ? '1' : '', 'review' => Request::str('review', true) === '1' ? '1' : '', 'sanctions' => Request::str('sanctions', true) === '1' ? '1' : '',
            'source' => Request::str('source', true), 'status' => Request::str('status', true) === 'exited' ? 'exited' : 'active'];
        $w = ['c.status = ?'];
        $p = [$f['status']];
        if ($f['q'] !== '') {
            $like = Db::like($f['q']);
            $w[] = '(c.customer_ref LIKE ? OR c.full_name LIKE ?)';
            array_push($p, $like, $like);
        }
        if (in_array($f['rating'], self::RATINGS, true)) {
            $w[] = 'c.rating = ?';
            $p[] = $f['rating'];
        }
        if ($f['pep'] === '1') {
            $w[] = 'c.is_pep = 1';
        }
        if ($f['sanctions'] === '1') {
            $w[] = 'c.sanctions_match = 1';
        }
        if ($f['review'] === '1') {
            $w[] = 'c.next_review_due < CURDATE()';
        }
        if (in_array($f['source'], ['manual', 't24'], true)) {
            $w[] = 'c.source = ?';
            $p[] = $f['source'];
        }
        return [$f, implode(' AND ', $w), $p];
    }

    public function customers(): string
    {
        [$f, $w, $p] = $this->customerFilter();
        $total = (int)Db::val("SELECT COUNT(*) FROM aml_customers c WHERE $w", $p);
        $page = min($this->page(), max(1, (int)ceil($total / self::PER_PAGE)));
        $rows = Db::all("SELECT c.*, t.name_en AS type_en, t.name_ar AS type_ar, g.name_en AS geo_en, g.name_ar AS geo_ar FROM aml_customers c JOIN aml_factors t ON t.id = c.customer_type_id JOIN aml_factors g ON g.id = c.geography_id
            WHERE $w ORDER BY FIELD(c.rating, 'high', 'medium', 'low'), c.score DESC, c.customer_ref LIMIT ? OFFSET ?", array_merge($p, [self::PER_PAGE, ($page - 1) * self::PER_PAGE]));
        return $this->view('aml/customers', ['title' => t('aml.customers'), 'rows' => $rows, 'f' => $f, 'total' => $total, 'page' => $page, 'per' => self::PER_PAGE]);
    }

    public function export(): never
    {
        [, $w, $p] = $this->customerFilter();
        $rows = Db::all("SELECT c.*, t.code AS type_code, g.code AS geo_code FROM aml_customers c JOIN aml_factors t ON t.id = c.customer_type_id JOIN aml_factors g ON g.id = c.geography_id WHERE $w ORDER BY c.customer_ref LIMIT 50000", $p);
        $this->audit('aml.customers_exported', 'aml_customer', null, ['rows' => count($rows)]);
        $gen = (static function () use ($rows) {
            foreach ($rows as $r) {
                yield [$r['customer_ref'], $r['full_name'], $r['type_code'], $r['geo_code'], $r['is_pep'] ? 'Y' : 'N', $r['sanctions_match'] ? 'Y' : 'N', $r['score'], $r['calc_rating'], $r['override_rating'] ?? '', $r['rating'], $r['cdd_level'], $r['last_review_on'] ?? '', $r['next_review_due'], $r['source']];
            }
        })();
        Csv::download('aml-customers-' . date('Ymd-His') . '.csv', ['customer_ref', 'full_name', 'customer_type', 'geography', 'pep', 'sanctions_match', 'score', 'calculated_rating', 'override_rating', 'rating', 'cdd_level', 'last_review', 'next_review', 'source'], $gen, Request::str('sep', true) === ';' ? ';' : ',');
    }

    private function customer(int $id): array
    {
        return Db::one('SELECT c.*, t.name_en AS type_en, t.name_ar AS type_ar, t.risk_level AS type_level, g.name_en AS geo_en, g.name_ar AS geo_ar, g.risk_level AS geo_level, b.name_en AS branch_en, b.name_ar AS branch_ar, o.full_name AS override_name
            FROM aml_customers c JOIN aml_factors t ON t.id = c.customer_type_id JOIN aml_factors g ON g.id = c.geography_id LEFT JOIN branches b ON b.id = c.branch_id LEFT JOIN users o ON o.id = c.override_by WHERE c.id = ?', [$id]) ?? $this->notFound();
    }

    public function customerShow(int $id): string
    {
        $c = $this->customer($id);
        $fx = Db::all('SELECT f.* FROM aml_customer_factors x JOIN aml_factors f ON f.id = x.factor_id WHERE x.customer_id = ? ORDER BY f.dimension, f.risk_level DESC', [$id]);
        $reviews = Db::all('SELECT r.*, u.full_name FROM aml_customer_reviews r JOIN users u ON u.id = r.reviewed_by WHERE r.customer_id = ? ORDER BY r.reviewed_on DESC, r.id DESC', [$id]);
        [$rw, $rp] = $this->reportScope('r');
        $reports = Db::all("SELECT r.* FROM aml_reports r WHERE r.customer_id = ? AND $rw ORDER BY r.due_date DESC", array_merge([$id], $rp));
        return $this->view('aml/customer_show', ['title' => $c['customer_ref'] . ' — ' . $c['full_name'], 'c' => $c, 'fx' => $fx, 'levels' => AmlScoring::levelsFor($id), 'reviews' => $reviews, 'reports' => $reports]);
    }

    private function formData(): array
    {
        $by = [];
        foreach (Db::all('SELECT * FROM aml_factors WHERE is_active = 1 ORDER BY risk_level, code') as $f) {
            $by[$f['dimension']][$f['id']] = $f;
        }
        return ['factors' => $by, 'branches' => Ref::branches(true)];
    }

    public function customerNew(): string
    {
        return $this->view('aml/customer_form', ['title' => t('aml.customer_new'), 'c' => [], 'sel' => [], 'existing' => null] + $this->formData());
    }

    public function customerEdit(int $id): string
    {
        $c = $this->customer($id);
        $sel = array_map('intval', array_column(Db::all('SELECT factor_id FROM aml_customer_factors WHERE customer_id = ?', [$id]), 'factor_id'));
        return $this->view('aml/customer_form', ['title' => t('aml.customer_edit'), 'c' => $c, 'sel' => $sel, 'existing' => $c] + $this->formData());
    }

    public function customerStore(): never
    {
        $this->customerSave(null);
    }

    public function customerUpdate(int $id): never
    {
        $this->customerSave($this->customer($id));
    }

    private function customerSave(?array $ex): never
    {
        $v = new Validator($_POST);
        $v->required('customer_ref', 'aml.customer_ref')->max('customer_ref', 'aml.customer_ref', 40)->required('full_name', 'aml.full_name')->max('full_name', 'aml.full_name', 200)->date('onboarded_on', 'aml.onboarded_on', false);
        $fd = $this->formData()['factors'];
        $type = Request::int('customer_type_id');
        $geo = Request::int('geography_id');
        if ($type === null || !isset($fd['customer'][$type])) {
            $v->fail('customer_type_id', 'validation.required', ['field' => t('aml.customer_type')]);
        }
        if ($geo === null || !isset($fd['geography'][$geo])) {
            $v->fail('geography_id', 'validation.required', ['field' => t('aml.geography')]);
        }
        $pick = static fn(string $k, string $dim) => array_values(array_intersect(array_map('intval', (array)($_POST[$k] ?? [])), array_keys($fd[$dim] ?? [])));
        $products = $pick('products', 'product');
        $channels = $pick('channels', 'channel');
        if (!$products) {
            $v->fail('products', 'aml.err_pick_product');
        }
        if (!$channels) {
            $v->fail('channels', 'aml.err_pick_channel');
        }
        $branch = Request::int('branch_id');
        if ($branch !== null && !isset(Ref::branches(true)[$branch])) {
            $v->fail('branch_id', 'validation.invalid_choice', ['field' => t('aml.branch')]);
        }
        if ($v->str('customer_ref') !== '' && Db::val('SELECT 1 FROM aml_customers WHERE customer_ref = ? AND id <> ?', [$v->str('customer_ref'), $ex['id'] ?? 0])) {
            $v->fail('customer_ref', 'aml.err_ref_taken');
        }
        if ($v->str('onboarded_on') > date('Y-m-d')) {
            $v->fail('onboarded_on', 'validation.not_future', ['field' => t('aml.onboarded_on')]);
        }
        $status = $ex ? (Request::str('status') === 'exited' ? 'exited' : 'active') : 'active';
        if (!$v->ok()) {
            $this->invalid($v->errors(), $ex ? url('aml/customers/' . $ex['id'] . '/edit') : url('aml/customers/new'));
        }
        $now = date('Y-m-d H:i:s');
        $d = ['customer_ref' => $v->str('customer_ref'), 'full_name' => $v->str('full_name'), 'customer_type_id' => $type, 'geography_id' => $geo, 'branch_id' => $branch, 'is_pep' => isset($_POST['is_pep']) ? 1 : 0,
            'sanctions_match' => isset($_POST['sanctions_match']) ? 1 : 0, 'onboarded_on' => $v->str('onboarded_on') ?: null, 'status' => $status, 'updated_at' => $now];
        $res = Db::tx(function () use ($ex, $d, $products, $channels, $now) {
            if ($ex) {
                Db::update('aml_customers', $d, 'id = ?', [$ex['id']]);
                $id = (int)$ex['id'];
                Db::exec('DELETE FROM aml_customer_factors WHERE customer_id = ?', [$id]);
            } else {
                $id = Db::insert('aml_customers', $d + ['source' => 'manual', 'score' => 1, 'calc_rating' => 'low', 'rating' => 'low', 'cdd_level' => 'simplified', 'next_review_due' => date('Y-m-d'), 'created_by' => $this->user()['id'], 'created_at' => $now]);
            }
            foreach (array_merge($products, $channels) as $fid) {
                Db::exec('INSERT INTO aml_customer_factors (customer_id, factor_id) VALUES (?, ?)', [$id, $fid]);
            }
            if (!$ex) {
                // First rating sets the review cycle from today.
                $r = AmlScoring::recalc($id);
                Db::update('aml_customers', ['next_review_due' => AmlScoring::nextReview($r['rating'], date('Y-m-d'))], 'id = ?', [$id]);
                return [$id, $r];
            }
            return [$id, AmlScoring::recalc($id)];
        });
        [$id, $r] = $res;
        $this->audit($ex ? 'aml.customer_updated' : 'aml.customer_created', 'aml_customer', $id, ['rating' => $r['rating'], 'score' => $r['score']] + ($ex ? ['changes' => $this->diff($ex, $d, ['full_name', 'customer_type_id', 'geography_id', 'is_pep', 'sanctions_match', 'status'])] : []));
        $this->redirect(url('aml/customers/' . $id), 'aml.customer_saved', 'success', ['r' => t('enum.amlr.' . $r['rating'])]);
    }

    /** Periodic review: confirms the due diligence is current and restarts the review cycle. */
    public function customerReview(int $id): never
    {
        $c = $this->customer($id);
        $v = new Validator($_POST);
        $v->required('note', 'aml.review_note')->max('note', 'aml.review_note', 4000);
        if ($c['status'] !== 'active') {
            $v->fail('note', 'aml.err_exited');
        }
        if ((int)$c['sanctions_match'] === 1) {
            $v->fail('note', 'aml.err_sanctions_open');
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), url('aml/customers/' . $id) . '#review');
        }
        $today = date('Y-m-d');
        Db::tx(function () use ($id, $c, $v, $today) {
            Db::insert('aml_customer_reviews', ['customer_id' => $id, 'reviewed_on' => $today, 'rating' => $c['rating'], 'note' => $v->str('note'), 'reviewed_by' => $this->user()['id'], 'created_at' => date('Y-m-d H:i:s')]);
            Db::update('aml_customers', ['last_review_on' => $today, 'next_review_due' => AmlScoring::nextReview($c['rating'], $today), 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        });
        $this->audit('aml.customer_reviewed', 'aml_customer', $id, ['rating' => $c['rating']]);
        $this->redirect(url('aml/customers/' . $id), 'aml.reviewed');
    }

    /** Manual override of the calculated rating, always with a written reason; cleared by choosing "use calculated". */
    public function customerOverride(int $id): never
    {
        $c = $this->customer($id);
        $v = new Validator($_POST);
        $rating = Request::str('override_rating');
        if ($rating !== '' && !in_array($rating, self::RATINGS, true)) {
            $v->fail('override_rating', 'validation.invalid_choice', ['field' => t('aml.override')]);
        }
        if ($rating !== '') {
            $v->required('override_reason', 'aml.override_reason')->max('override_reason', 'aml.override_reason', 2000);
            if (mb_strlen($v->str('override_reason')) < 20) {
                $v->fail('override_reason', 'aml.err_reason_short');
            }
            if (((int)$c['is_pep'] === 1 || (int)$c['sanctions_match'] === 1) && $rating !== 'high') {
                $v->fail('override_rating', 'aml.err_override_rule');
            }
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), url('aml/customers/' . $id) . '#override');
        }
        Db::update('aml_customers', ['override_rating' => $rating ?: null, 'override_reason' => $rating ? $v->str('override_reason') : null, 'override_by' => $rating ? $this->user()['id'] : null], 'id = ?', [$id]);
        $r = AmlScoring::recalc($id);
        $this->audit('aml.rating_overridden', 'aml_customer', $id, ['from' => $c['rating'], 'to' => $r['rating'], 'calculated' => $r['calc'], 'reason' => $v->str('override_reason')]);
        $this->redirect(url('aml/customers/' . $id), 'aml.override_saved');
    }

    // ------------------------------------------------------------------ reports

    /** Suspicious transaction reports are confidential (no tipping off): only the AML team sees them. */
    private function reportScope(string $a): array
    {
        return Perm::roleHas($this->user()['role'], 'aml.manage') ? ['1 = 1', []] : ["$a.confidential = 0", []];
    }

    public function reports(): string
    {
        [$w, $p] = $this->reportScope('r');
        $f = ['type' => Request::str('type', true), 'status' => Request::str('status', true), 'overdue' => Request::str('overdue', true) === '1' ? '1' : ''];
        if (in_array($f['type'], self::REPORT_TYPES, true)) {
            $w .= ' AND r.report_type = ?';
            $p[] = $f['type'];
        }
        if (in_array($f['status'], ['draft', 'submitted'], true)) {
            $w .= ' AND r.status = ?';
            $p[] = $f['status'];
        }
        if ($f['overdue'] === '1') {
            $w .= " AND r.status = 'draft' AND r.due_date < CURDATE()";
        }
        $rows = Db::all("SELECT r.*, c.customer_ref FROM aml_reports r LEFT JOIN aml_customers c ON c.id = r.customer_id WHERE $w ORDER BY r.status = 'submitted', r.due_date LIMIT 500", $p);
        return $this->view('aml/reports', ['title' => t('aml.reports'), 'rows' => $rows, 'f' => $f]);
    }

    private function report(int $id): array
    {
        [$w, $p] = $this->reportScope('r');
        return Db::one("SELECT r.*, c.customer_ref, c.full_name, u.full_name AS submitter FROM aml_reports r LEFT JOIN aml_customers c ON c.id = r.customer_id LEFT JOIN users u ON u.id = r.submitted_by WHERE r.id = ? AND $w", array_merge([$id], $p)) ?? $this->notFound();
    }

    public function reportShow(int $id): string
    {
        $r = $this->report($id);
        $att = Db::all("SELECT a.*, u.full_name FROM attachments a JOIN users u ON u.id = a.uploaded_by WHERE a.entity_type = 'aml_report' AND a.entity_id = ? AND a.deleted_at IS NULL ORDER BY a.id DESC", [$id]);
        return $this->view('aml/report_show', ['title' => $r['code'] . ' — ' . $r['title'], 'r' => $r, 'attachments' => $att]);
    }

    public function reportNew(): string
    {
        $cid = Request::int('customer', true);
        return $this->view('aml/report_form', ['title' => t('aml.report_new'), 'r' => ['customer_id' => $cid, 'recipient' => t('aml.default_recipient')], 'existing' => null, 'customers' => $this->customerOptions()]);
    }

    public function reportEdit(int $id): string
    {
        $r = $this->report($id);
        if ($r['status'] !== 'draft') {
            $this->redirect(url('aml/reports/' . $id), 'aml.err_report_locked', 'error');
        }
        return $this->view('aml/report_form', ['title' => t('aml.report_edit'), 'r' => $r, 'existing' => $r, 'customers' => $this->customerOptions()]);
    }

    private function customerOptions(): array
    {
        return Db::all("SELECT id, customer_ref, full_name FROM aml_customers WHERE status = 'active' ORDER BY customer_ref LIMIT 2000");
    }

    public function reportStore(): never
    {
        $this->reportSave(null);
    }

    public function reportUpdate(int $id): never
    {
        $r = $this->report($id);
        if ($r['status'] !== 'draft') {
            $this->redirect(url('aml/reports/' . $id), 'aml.err_report_locked', 'error');
        }
        $this->reportSave($r);
    }

    private function reportSave(?array $ex): never
    {
        $v = new Validator($_POST);
        $v->in('report_type', 'aml.report_type', self::REPORT_TYPES)->required('title', 'aml.r_title')->max('title', 'aml.r_title', 250)->required('recipient', 'aml.recipient')->max('recipient', 'aml.recipient', 150)
            ->date('due_date', 'aml.r_due')->required('summary', 'aml.r_summary')->max('summary', 'aml.r_summary', 8000);
        $cid = Request::int('customer_id');
        if ($cid !== null && !Db::val('SELECT 1 FROM aml_customers WHERE id = ?', [$cid])) {
            $v->fail('customer_id', 'validation.invalid_choice', ['field' => t('aml.customer')]);
        }
        if ($v->str('report_type') === 'str' && $cid === null) {
            $v->fail('customer_id', 'aml.err_str_customer');
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), $ex ? url('aml/reports/' . $ex['id'] . '/edit') : url('aml/reports/new'));
        }
        $now = date('Y-m-d H:i:s');
        $type = $v->str('report_type');
        $d = ['report_type' => $type, 'title' => $v->str('title'), 'customer_id' => $cid, 'recipient' => $v->str('recipient'), 'due_date' => $v->str('due_date'), 'summary' => $v->str('summary'),
            'confidential' => $type === 'str' || isset($_POST['confidential']) ? 1 : 0, 'updated_at' => $now];
        if ($ex) {
            Db::update('aml_reports', $d, 'id = ?', [$ex['id']]);
            $id = (int)$ex['id'];
        } else {
            $id = Db::insert('aml_reports', $d + ['status' => 'draft', 'created_by' => $this->user()['id'], 'created_at' => $now]);
            Db::update('aml_reports', ['code' => sprintf('AMLR-%04d', $id)], 'id = ?', [$id]);
        }
        // Confidential report details stay out of the audit log text.
        $this->audit($ex ? 'aml.report_updated' : 'aml.report_created', 'aml_report', $id, ['type' => $type]);
        $this->redirect(url('aml/reports/' . $id), 'admin.saved');
    }

    public function reportSubmit(int $id): never
    {
        $r = $this->report($id);
        if ($r['status'] !== 'draft') {
            $this->redirect(url('aml/reports/' . $id), 'aml.err_report_locked', 'error');
        }
        $v = new Validator($_POST);
        $v->date('submitted_on', 'aml.submitted_on')->max('reference_no', 'aml.reference', 80);
        if ($v->str('submitted_on') > date('Y-m-d')) {
            $v->fail('submitted_on', 'validation.not_future', ['field' => t('aml.submitted_on')]);
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), url('aml/reports/' . $id) . '#submit');
        }
        Db::update('aml_reports', ['status' => 'submitted', 'submitted_on' => $v->str('submitted_on'), 'reference_no' => $v->str('reference_no') ?: null, 'submitted_by' => $this->user()['id'], 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        $this->audit('aml.report_submitted', 'aml_report', $id, ['on' => $v->str('submitted_on')]);
        $this->redirect(url('aml/reports/' . $id), 'aml.submitted');
    }

    public function reportUpload(int $id): never
    {
        $this->report($id);
        AttachmentController::handleUpload('aml_report', $id, $this->user(), url('aml/reports/' . $id) . '#attachments');
    }
}
