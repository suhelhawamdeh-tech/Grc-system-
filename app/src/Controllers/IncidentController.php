<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Db;
use App\Core\Money;
use App\Core\Perm;
use App\Core\Settings;
use App\Core\Request;
use App\Core\Scope;
use App\Core\Validator;
use App\Support\Csv;
use App\Support\IncidentFilter;
use App\Support\IncidentPatterns;
use App\Support\Ref;

final class IncidentController extends Controller
{
    private const PER_PAGE = 25;
    private const TRACKED = ['event_type', 'title', 'description', 'occurrence_date', 'discovery_date', 'department_id', 'branch_id', 'category_id',
        'gross_loss', 'recoveries', 'net_loss', 'root_cause', 'corrective_actions', 'status', 'basel_event_type_id', 'business_line_id', 'product', 'system_name', 'recovery_source', 'non_financial_impact'];

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
        return ['categories' => Ref::categories(true), 'departments' => $deps, 'branches' => $brs];
    }

    private function load(int $id): array
    {
        $u = $this->user();
        $r = Db::one(IncidentFilter::SELECT . ' WHERE i.id = ?', [$id]);
        if (!$r || !Scope::allows($u, $r)) {
            $this->notFound();
        }
        return $r;
    }

    /** Risks the user may link an incident to. */
    private function linkableRisks(array $u): array
    {
        [$w, $p] = Scope::sql($u, 'r');
        return Db::all('SELECT r.id, r.risk_code, r.title FROM risks r WHERE r.archived_at IS NULL AND ' . $w . ' ORDER BY r.id DESC LIMIT 500', $p);
    }

    public function index(): string
    {
        $u = $this->user();
        $f = IncidentFilter::fromArray($_GET);
        $total = IncidentFilter::count($f, $u);
        $page = min($this->page(), max(1, (int)ceil($total / self::PER_PAGE)));
        return $this->view('incidents/index', [
            'title' => t('incident.title_plural'), 'rows' => IncidentFilter::fetch($f, $u, self::PER_PAGE, ($page - 1) * self::PER_PAGE),
            'f' => $f, 'total' => $total, 'page' => $page, 'per' => self::PER_PAGE, 'lists' => $this->lists($u), 'sums' => IncidentFilter::totals($f, $u),
            'risks' => Db::all('SELECT r.id, r.risk_code FROM risks r WHERE ' . Scope::sql($u, 'r')[0] . ' ORDER BY r.id DESC LIMIT 500', Scope::sql($u, 'r')[1]),
        ]);
    }

    public function show(int $id): string
    {
        $u = $this->user();
        $i = $this->load($id);
        [$w, $p] = Scope::sql($u, 'r');
        $risks = Db::all('SELECT r.id, r.risk_code, r.title FROM risks r JOIN incident_risks ir ON ir.risk_id = r.id WHERE ir.incident_id = ? AND ' . $w, array_merge([$id], $p));
        $atts = Db::all('SELECT a.*, u.full_name FROM attachments a JOIN users u ON u.id = a.uploaded_by WHERE a.entity_type = ? AND a.entity_id = ? AND a.deleted_at IS NULL ORDER BY a.id DESC', ['incident', $id]);
        $similar = $i['event_type'] === 'incident' ? IncidentPatterns::similar($u, $id, (int)$i['category_id'], (int)$i['department_id'], (string)$i['occurrence_date']) : [];
        $meta = Db::one('SELECT (SELECT name_en FROM basel_event_types WHERE id = ?) AS bt_en, (SELECT name_ar FROM basel_event_types WHERE id = ?) AS bt_ar, (SELECT name_en FROM business_lines WHERE id = ?) AS bl_en, (SELECT name_ar FROM business_lines WHERE id = ?) AS bl_ar', [$i['basel_event_type_id'], $i['basel_event_type_id'], $i['business_line_id'], $i['business_line_id']]);
        return $this->view('incidents/show', ['title' => $i['incident_code'] . ' — ' . $i['title'], 'i' => $i, 'risks' => $risks, 'attachments' => $atts, 'similar' => $similar, 'meta' => $meta]);
    }

    /** Categories and departments with repeated incidents in the last 12 months. */
    public function patterns(): string
    {
        $u = $this->user();
        [$w, $p] = Scope::sql($u, 'i');
        $rows = Db::all("SELECT c.name_en AS c_en, c.name_ar AS c_ar, d.name_en AS d_en, d.name_ar AS d_ar, i.category_id, i.department_id, COUNT(*) AS n, COALESCE(SUM(i.net_loss),0) AS net, MAX(i.occurrence_date) AS last_date FROM incidents i JOIN risk_categories c ON c.id = i.category_id JOIN departments d ON d.id = i.department_id WHERE i.archived_at IS NULL AND i.event_type = 'incident' AND i.occurrence_date >= DATE_SUB(CURDATE(), INTERVAL 365 DAY) AND $w GROUP BY i.category_id, i.department_id, c.name_en, c.name_ar, d.name_en, d.name_ar HAVING COUNT(*) >= 3 ORDER BY n DESC, net DESC LIMIT 50", $p);
        return $this->view('incidents/patterns', ['title' => t('incident.patterns'), 'rows' => $rows]);
    }

    public function create(): string
    {
        $u = $this->user();
        $d = ['event_type' => 'incident', 'occurrence_date' => date('Y-m-d'), 'discovery_date' => date('Y-m-d'), 'status' => 'open', 'gross_loss' => '0', 'recoveries' => '0'];
        if (Perm::scopeType($u['role']) === 'department') {
            $d['department_id'] = $u['department_id'];
        }
        if (Perm::scopeType($u['role']) === 'branch') {
            $d['branch_id'] = $u['branch_id'];
        }
        return $this->form($d, null, []);
    }

    public function edit(int $id): string
    {
        $i = $this->load($id);
        if ($i['archived_at'] !== null) {
            $this->redirect(url('incidents/' . $id), 'incident.err_archived_readonly', 'error');
        }
        $linked = array_column(Db::all('SELECT risk_id FROM incident_risks WHERE incident_id = ?', [$id]), 'risk_id');
        $i['gross_loss'] = Money::plain($i['gross_loss']);
        $i['recoveries'] = Money::plain($i['recoveries']);
        return $this->form($i, $i, $linked);
    }

    private function form(array $d, ?array $existing, array $linked): string
    {
        $u = $this->user();
        return $this->view('incidents/form', [
            'title' => $existing ? t('incident.edit') : t('incident.new'), 'i' => $d, 'existing' => $existing, 'lists' => $this->lists($u),
            'risks' => $this->linkableRisks($u), 'linked' => array_map('intval', $linked), 'basel' => Db::all('SELECT * FROM basel_event_types ORDER BY id'), 'lines' => Db::all('SELECT * FROM business_lines ORDER BY id'),
        ]);
    }

    public function store(): never
    {
        $u = $this->user();
        [$data, $links, $errors] = $this->collect($u, null);
        if ($errors) {
            $this->invalid($errors, url('incidents/new'));
        }
        $now = date('Y-m-d H:i:s');
        $id = Db::tx(function () use ($data, $links, $u, $now) {
            $id = Db::insert('incidents', $data + ['reported_by' => $u['id'], 'created_at' => $now, 'updated_at' => $now]);
            Db::update('incidents', ['incident_code' => sprintf('INC-%05d', $id)], 'id = ?', [$id]);
            foreach ($links as $rid) {
                Db::insert('incident_risks', ['incident_id' => $id, 'risk_id' => $rid]);
            }
            return $id;
        });
        $this->audit('incident.created', 'incident', $id, ['type' => $data['event_type'], 'gross' => $data['gross_loss'], 'recoveries' => $data['recoveries'], 'net' => $data['net_loss']]);
        $this->afterSave($id, $data, $links);
        $this->redirect(url('incidents/' . $id), 'incident.created');
    }

    public function update(int $id): never
    {
        $u = $this->user();
        $old = $this->load($id);
        if ($old['archived_at'] !== null) {
            $this->redirect(url('incidents/' . $id), 'incident.err_archived_readonly', 'error');
        }
        [$data, $links, $errors] = $this->collect($u, $old);
        if ($errors) {
            $this->invalid($errors, url('incidents/' . $id . '/edit'));
        }
        $changes = $this->diff($old, $data, self::TRACKED);
        $now = date('Y-m-d H:i:s');
        Db::tx(function () use ($id, $data, $links, $u, $now) {
            Db::update('incidents', $data + ['updated_by' => $u['id'], 'updated_at' => $now], 'id = ?', [$id]);
            // Only touch links to risks this user can see; links to out-of-scope risks are preserved.
            [$w, $p] = Scope::sql($u, 'r');
            Db::exec('DELETE ir FROM incident_risks ir JOIN risks r ON r.id = ir.risk_id WHERE ir.incident_id = ? AND ' . $w, array_merge([$id], $p));
            foreach ($links as $rid) {
                Db::exec('INSERT IGNORE INTO incident_risks (incident_id, risk_id) VALUES (?, ?)', [$id, $rid]);
            }
        });
        $this->audit('incident.updated', 'incident', $id, ['changes' => $changes]);
        $this->afterSave($id, $data + ['escalated_at' => $old['escalated_at']], $links);
        $this->redirect(url('incidents/' . $id), 'incident.updated');
    }

    /** @return array{0:array,1:int[],2:array} */
    private function collect(array $u, ?array $existing): array
    {
        $v = new Validator($_POST);
        $v->in('event_type', 'incident.type', ['incident', 'near_miss'])->required('title', 'incident.name')->max('title', 'incident.name', 250)
            ->required('description', 'incident.description')->max('description', 'incident.description', 5000)
            ->date('occurrence_date', 'incident.occurrence_date')->date('discovery_date', 'incident.discovery_date')
            ->max('root_cause', 'incident.root_cause', 5000)->max('corrective_actions', 'incident.corrective_actions', 5000)
            ->in('status', 'incident.status', ['open', 'investigating', 'closed']);

        $refId = static function (string $key, array $set, ?int $current = null): ?int {
            $val = Request::int($key);
            if ($val === null) {
                return null;
            }
            return isset($set[$val]) || $val === $current ? $val : -1;
        };
        $cat = $refId('category_id', Ref::categories(true), $existing['category_id'] ?? null);
        $dept = $refId('department_id', Ref::departments(true), $existing['department_id'] ?? null);
        $branch = $refId('branch_id', Ref::branches(true), $existing['branch_id'] ?? null);
        if ($cat === null || $cat === -1) {
            $v->fail('category_id', 'validation.invalid_choice', ['field' => t('incident.category')]);
        }
        if ($dept === null || $dept === -1) {
            $v->fail('department_id', 'validation.invalid_choice', ['field' => t('incident.department')]);
        }
        if ($branch === -1) {
            $v->fail('branch_id', 'validation.invalid_choice', ['field' => t('incident.branch')]);
        }
        $st = Perm::scopeType($u['role']);
        if ($st === 'department' && $dept !== (int)$u['department_id']) {
            $v->fail('department_id', 'validation.out_of_scope');
        }
        if ($st === 'branch' && $branch !== (int)$u['branch_id']) {
            $v->fail('branch_id', 'validation.out_of_scope');
        }

        $occ = $v->str('occurrence_date');
        $disc = $v->str('discovery_date');
        $today = date('Y-m-d');
        if ($occ !== '' && $occ > $today) {
            $v->fail('occurrence_date', 'validation.not_future', ['field' => t('incident.occurrence_date')]);
        }
        if ($disc !== '' && $disc > $today) {
            $v->fail('discovery_date', 'validation.not_future', ['field' => t('incident.discovery_date')]);
        }
        if ($occ !== '' && $disc !== '' && $disc < $occ) {
            $v->fail('discovery_date', 'validation.after_date', ['field' => t('incident.discovery_date'), 'other' => t('incident.occurrence_date')]);
        }

        // Exact integer arithmetic: net = gross - recoveries.
        $gross = Money::parse($v->str('gross_loss'), Money::decimals());
        $rec = Money::parse($v->str('recoveries'), Money::decimals());
        if ($gross === null) {
            $v->fail('gross_loss', 'validation.amount', ['field' => t('incident.gross_loss')]);
        }
        if ($rec === null) {
            $v->fail('recoveries', 'validation.amount', ['field' => t('incident.recoveries')]);
        }
        $net = 0;
        if ($gross !== null && $rec !== null) {
            if ($rec > $gross) {
                $v->fail('recoveries', 'incident.err_recoveries_exceed');
            }
            if ($v->str('event_type') === 'near_miss' && ($gross !== 0 || $rec !== 0)) {
                $v->fail('gross_loss', 'incident.err_near_miss_no_loss');
            }
            $net = $gross - $rec;
        }
        if ($v->str('status') === 'closed' && $v->str('root_cause') === '') {
            $v->fail('root_cause', 'incident.err_closed_needs_root_cause');
        }
        $basel = Request::int('basel_event_type_id');
        $bline = Request::int('business_line_id');
        if ($basel !== null && !Db::val('SELECT 1 FROM basel_event_types WHERE id = ?', [$basel])) {
            $v->fail('basel_event_type_id', 'validation.invalid_choice', ['field' => t('incident.basel_type')]);
        }
        if ($bline !== null && !Db::val('SELECT 1 FROM business_lines WHERE id = ?', [$bline])) {
            $v->fail('business_line_id', 'validation.invalid_choice', ['field' => t('incident.business_line')]);
        }
        $v->max('product', 'incident.product', 100)->max('system_name', 'incident.system_name', 100)->max('non_financial_impact', 'incident.non_financial', 2000);
        $src = $v->str('recovery_source');
        if ($src !== '' && !in_array($src, ['insurance', 'customer', 'employee', 'supplier', 'other'], true)) {
            $v->fail('recovery_source', 'validation.invalid_choice', ['field' => t('incident.recovery_source')]);
        } elseif ($rec !== null && $rec > 0 && $src === '') {
            $v->fail('recovery_source', 'incident.err_recovery_source');
        }
        if ($v->str('status') === 'closed') {
            if ($v->str('corrective_actions') === '') {
                $v->fail('corrective_actions', 'incident.err_closed_needs_actions');
            }
            if ($basel === null) {
                $v->fail('basel_event_type_id', 'incident.err_closed_needs_basel');
            }
        }

        // Linked risks must be ones the user can see.
        $links = [];
        $posted = $_POST['risk_ids'] ?? [];
        if (is_array($posted) && $posted) {
            $ids = array_values(array_unique(array_filter(array_map('intval', $posted))));
            if ($ids) {
                [$w, $p] = Scope::sql($u, 'r');
                $in = implode(',', array_fill(0, count($ids), '?'));
                $ok = array_map('intval', array_column(Db::all("SELECT r.id FROM risks r WHERE r.id IN ($in) AND $w", array_merge($ids, $p)), 'id'));
                if (count($ok) !== count($ids)) {
                    $v->fail('risk_ids', 'incident.err_bad_risk_link');
                }
                $links = $ok;
            }
        }

        $nz = static fn(string $s): ?string => $s === '' ? null : $s;
        $data = [
            'event_type' => $v->str('event_type'), 'title' => $v->str('title'), 'description' => $v->str('description'),
            'occurrence_date' => $occ, 'discovery_date' => $disc, 'department_id' => $dept, 'branch_id' => $branch, 'category_id' => $cat,
            'gross_loss' => Money::toDb($gross ?? 0), 'recoveries' => Money::toDb($rec ?? 0), 'net_loss' => Money::toDb($net),
            'root_cause' => $nz($v->str('root_cause')), 'corrective_actions' => $nz($v->str('corrective_actions')), 'status' => $v->str('status'),
            'basel_event_type_id' => $basel, 'business_line_id' => $bline, 'product' => $nz($v->str('product')), 'system_name' => $nz($v->str('system_name')),
            'recovery_source' => $src !== '' && ($rec ?? 0) > 0 ? $src : null, 'non_financial_impact' => $nz($v->str('non_financial_impact')),
        ];
        return [$data, $links, $v->errors()];
    }

    /** Escalation by loss threshold, recurrence detection and the resulting risk reassessment trigger. */
    private function afterSave(int $id, array $data, array $links): void
    {
        $now = date('Y-m-d H:i:s');
        $th = Money::parse((string)(Settings::get('incident_escalation_threshold', '') ?? ''), Money::decimals());
        $escalated = false;
        if ($th !== null && $th > 0 && empty($data['escalated_at']) && Money::fromDb($data['net_loss']) >= $th) {
            Db::update('incidents', ['escalated_at' => $now], 'id = ?', [$id]);
            $this->audit('incident.escalated', 'incident', $id, ['net_loss' => $data['net_loss'], 'threshold' => Money::toDb($th)]);
            $escalated = true;
        }
        $recurrence = $data['event_type'] === 'incident' ? IncidentPatterns::similarCount($id, (int)$data['category_id'], (int)$data['department_id'], (string)$data['occurrence_date']) : 0;
        if (($escalated || $recurrence >= 2) && $links) {
            foreach ($links as $rid) {
                Db::exec('UPDATE risks SET review_date = ? WHERE id = ? AND (review_date IS NULL OR review_date > ?)', [date('Y-m-d'), $rid, date('Y-m-d')]);
                Db::insert('risk_history', ['risk_id' => $rid, 'user_id' => $this->user()['id'], 'event' => 'reassessment_triggered', 'changes' => json_encode(['incident_id' => $id, 'reason' => $escalated ? 'escalation' : 'recurrence'], JSON_UNESCAPED_UNICODE), 'created_at' => $now]);
            }
            $this->audit('incident.reassessment_triggered', 'incident', $id, ['risks' => $links, 'reason' => $escalated ? 'escalation' : 'recurrence']);
        }
    }

    public function archive(int $id): never
    {
        $u = $this->user();
        $i = $this->load($id);
        if ($i['archived_at'] === null) {
            $now = date('Y-m-d H:i:s');
            Db::update('incidents', ['archived_at' => $now, 'archived_by' => $u['id'], 'updated_at' => $now, 'updated_by' => $u['id']], 'id = ?', [$id]);
            $this->audit('incident.archived', 'incident', $id);
        }
        $this->redirect(url('incidents/' . $id), 'incident.archived_msg');
    }

    public function restore(int $id): never
    {
        $u = $this->user();
        $i = $this->load($id);
        if ($i['archived_at'] !== null) {
            $now = date('Y-m-d H:i:s');
            Db::update('incidents', ['archived_at' => null, 'archived_by' => null, 'updated_at' => $now, 'updated_by' => $u['id']], 'id = ?', [$id]);
            $this->audit('incident.restored', 'incident', $id);
        }
        $this->redirect(url('incidents/' . $id), 'incident.restored');
    }

    public function upload(int $id): never
    {
        $u = $this->user();
        $this->load($id);
        AttachmentController::handleUpload('incident', $id, $u, url('incidents/' . $id) . '#attachments');
    }

    public function export(): never
    {
        $u = Auth::requirePerm('export.run');
        $f = IncidentFilter::fromArray($_GET);
        $count = IncidentFilter::count($f, $u);
        $this->audit('export.incidents', 'incident', null, ['rows' => $count, 'filters' => array_filter($f, static fn($x) => $x !== '' && $x !== null)]);
        $cur = \App\Core\Settings::get('currency_code', 'LYD');
        $h = [t('incident.id'), t('incident.type'), t('incident.name'), t('incident.description'), t('incident.occurrence_date'), t('incident.discovery_date'),
            t('incident.department'), t('incident.branch'), t('incident.category'), t('incident.gross_loss') . " ($cur)", t('incident.recoveries') . " ($cur)",
            t('incident.net_loss') . " ($cur)", t('incident.root_cause'), t('incident.corrective_actions'), t('incident.status'), t('incident.linked_risks'), t('incident.archived')];
        $gen = (function () use ($f, $u, $count) {
            for ($off = 0; $off < min($count, 50000); $off += 500) {
                $rows = IncidentFilter::fetch($f, $u, 500, $off);
                $links = [];
                if ($rows) {
                    $ids = array_column($rows, 'id');
                    $in = implode(',', array_fill(0, count($ids), '?'));
                    [$w, $p] = Scope::sql($u, 'r');
                    foreach (Db::all("SELECT ir.incident_id, r.risk_code FROM incident_risks ir JOIN risks r ON r.id = ir.risk_id WHERE ir.incident_id IN ($in) AND $w ORDER BY r.risk_code", array_merge($ids, $p)) as $l) {
                        $links[$l['incident_id']][] = $l['risk_code'];
                    }
                }
                foreach ($rows as $r) {
                    yield [$r['incident_code'], t('enum.event.' . $r['event_type']), $r['title'], $r['description'], $r['occurrence_date'], $r['discovery_date'],
                        lname($r, 'dept'), lname($r, 'branch'), lname($r, 'cat'), Money::plain($r['gross_loss']), Money::plain($r['recoveries']),
                        Money::plain($r['net_loss']), $r['root_cause'], $r['corrective_actions'], t('enum.incident_status.' . $r['status']),
                        implode(' ', $links[$r['id']] ?? []), $r['archived_at'] ? t('common.yes') : t('common.no')];
                }
            }
        })();
        Csv::download('incidents-' . date('Ymd-His') . '.csv', $h, $gen, Request::str('sep', true) === ';' ? ';' : ',');
    }

    public function printList(): string
    {
        $u = Auth::requirePerm('export.run');
        $f = IncidentFilter::fromArray($_GET);
        $count = IncidentFilter::count($f, $u);
        $this->audit('report.incidents_print', 'incident', null, ['rows' => $count]);
        return $this->view('incidents/print', ['title' => t('incident.title_plural'), 'rows' => IncidentFilter::fetch($f, $u, 500, 0), 'total' => $count, 'f' => $f, 'u' => $u, 'sums' => IncidentFilter::totals($f, $u)], 'layout/print');
    }
}
