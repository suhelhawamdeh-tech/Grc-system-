<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\Db;
use App\Core\Money;
use App\Core\Perm;
use App\Core\Scope;

/** CSV import: all rows are validated first; nothing is written unless every row is valid (all-or-nothing). */
final class Importer
{
    public const MAX_ROWS = 1000;
    public const TARGETS = [
        'risks' => ['title', 'description', 'category_code', 'department_code', 'owner_username', 'inh_likelihood', 'inh_impact', 'res_likelihood', 'res_impact', 'res_rationale', 'treatment_decision', 'treatment_notes', 'controls_description', 'identified_date'],
        'kri_readings' => ['kri_code', 'reading_date', 'value', 'comment'],
        'obligations' => ['title', 'source', 'department_code', 'owner_username', 'frequency', 'due_date', 'description'],
    ];
    public const PERM = ['risks' => 'risk.create', 'kri_readings' => 'kri.report', 'obligations' => 'compliance.manage'];

    /** @return array{rows:array,error:?string} rows are assoc arrays keyed by the template headers */
    public static function parse(string $raw, string $target): array
    {
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? '';
        if (!mb_check_encoding($raw, 'UTF-8')) {
            return ['rows' => [], 'error' => 'import.err_encoding'];
        }
        $first = strtok($raw, "\n") ?: '';
        $delim = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, $raw);
        rewind($fh);
        $head = fgetcsv($fh, 0, $delim, '"', '');
        if (!$head) {
            return ['rows' => [], 'error' => 'import.err_empty'];
        }
        $head = array_map(static fn($h) => strtolower(trim((string)$h)), $head);
        $need = self::TARGETS[$target];
        $required = ['risks' => ['title', 'description', 'category_code', 'department_code', 'owner_username', 'inh_likelihood', 'inh_impact', 'res_likelihood', 'res_impact'], 'kri_readings' => ['kri_code', 'reading_date', 'value'], 'obligations' => ['title', 'source', 'department_code', 'owner_username', 'frequency', 'due_date']][$target];
        if (array_diff($required, $head)) {
            return ['rows' => [], 'error' => 'import.err_headers'];
        }
        $rows = [];
        $line = 1;
        while (($r = fgetcsv($fh, 0, $delim, '"', '')) !== false) {
            $line++;
            if (count($r) === 1 && trim((string)$r[0]) === '') {
                continue;
            }
            $assoc = ['_line' => $line];
            foreach ($need as $col) {
                $i = array_search($col, $head, true);
                $assoc[$col] = $i === false ? '' : trim((string)($r[$i] ?? ''));
            }
            $rows[] = $assoc;
            if (count($rows) > self::MAX_ROWS) {
                return ['rows' => [], 'error' => 'import.err_too_many'];
            }
        }
        return ['rows' => $rows, 'error' => $rows ? null : 'import.err_empty'];
    }

    private static function date(string $s): bool
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $s);
        return $d && $d->format('Y-m-d') === $s && (int)$d->format('Y') >= 1990 && (int)$d->format('Y') <= 2100;
    }

    private static function byCode(array $set, string $code): ?array
    {
        foreach ($set as $r) {
            if (strcasecmp((string)$r['code'], $code) === 0) {
                return $r;
            }
        }
        return null;
    }

    private static function userByName(string $name): ?array
    {
        foreach (Ref::users(true) as $u) {
            if (strcasecmp($u['username'], $name) === 0) {
                return $u;
            }
        }
        return null;
    }

    /** @return array{rows:array,errors:array<int,string[]>} normalised rows ready to insert, and errors by line */
    public static function validate(string $target, array $raw, array $user): array
    {
        $out = [];
        $errors = [];
        $high = (int)(Db::val('SELECT MIN(min_score) FROM score_bands WHERE is_high = 1') ?? 26);
        $lv = ['l' => Ref::levels('likelihood'), 'i' => Ref::levels('impact')];
        foreach ($raw as $r) {
            $e = [];
            $n = ['_line' => $r['_line']];
            if ($target === 'risks') {
                if ($r['title'] === '' || mb_strlen($r['title']) > 250) {
                    $e[] = t('import.e_title');
                }
                if ($r['description'] === '' || mb_strlen($r['description']) > 5000) {
                    $e[] = t('import.e_description');
                }
                $cat = self::byCode(Ref::categories(true), $r['category_code']);
                $dep = self::byCode(Ref::departments(true), $r['department_code']);
                $own = self::userByName($r['owner_username']);
                $cat || $e[] = t('import.e_category', ['v' => $r['category_code']]);
                $dep || $e[] = t('import.e_department', ['v' => $r['department_code']]);
                $own || $e[] = t('import.e_owner', ['v' => $r['owner_username']]);
                if ($dep && !Scope::allows($user, ['department_id' => $dep['id'], 'branch_id' => null]) && Perm::scopeType($user['role']) !== null) {
                    $e[] = t('validation.out_of_scope');
                }
                $lvl = [];
                foreach (['inh_likelihood' => 'l', 'inh_impact' => 'i', 'res_likelihood' => 'l', 'res_impact' => 'i'] as $k => $sc) {
                    if (!preg_match('/^\d$/', $r[$k]) || !isset($lv[$sc][(int)$r[$k]])) {
                        $e[] = t('import.e_level', ['f' => $k]);
                    }
                    $lvl[$k] = (int)$r[$k];
                }
                $inh = $lvl['inh_likelihood'] * $lvl['inh_impact'];
                $res = $lvl['res_likelihood'] * $lvl['res_impact'];
                if (!$e && $res !== $inh && $r['res_rationale'] === '') {
                    $e[] = t('import.e_rationale');
                }
                $dec = $r['treatment_decision'] === '' ? 'mitigate' : strtolower($r['treatment_decision']);
                if (!in_array($dec, ['mitigate', 'accept', 'transfer', 'avoid'], true)) {
                    $e[] = t('import.e_treatment');
                } elseif (!$e && $dec === 'accept' && $res >= $high && $r['treatment_notes'] === '') {
                    $e[] = t('risk.err_accept_high_needs_notes');
                }
                $idt = $r['identified_date'] === '' ? date('Y-m-d') : $r['identified_date'];
                if (!self::date($idt) || $idt > date('Y-m-d')) {
                    $e[] = t('import.e_date', ['f' => 'identified_date']);
                }
                if (!$e) {
                    $n += ['title' => $r['title'], 'description' => $r['description'], 'category_id' => (int)$cat['id'], 'department_id' => (int)$dep['id'], 'owner_user_id' => (int)$own['id'], 'identified_date' => $idt,
                        'inh_likelihood' => $lvl['inh_likelihood'], 'inh_impact' => $lvl['inh_impact'], 'inh_score' => $inh, 'res_likelihood' => $lvl['res_likelihood'], 'res_impact' => $lvl['res_impact'], 'res_score' => $res,
                        'res_rationale' => $r['res_rationale'] ?: null, 'treatment_decision' => $dec, 'treatment_notes' => $r['treatment_notes'] ?: null, 'controls_description' => $r['controls_description'] ?: null];
                }
            } elseif ($target === 'kri_readings') {
                $k = $r['kri_code'] === '' ? null : Db::one('SELECT * FROM kris WHERE code = ?', [$r['kri_code']]);
                if (!$k || !Scope::allowsDept($user, $k)) {
                    $e[] = t('import.e_kri', ['v' => $r['kri_code']]);
                } elseif (!$k['is_active']) {
                    $e[] = t('kri.err_inactive');
                }
                if (!self::date($r['reading_date']) || $r['reading_date'] > date('Y-m-d')) {
                    $e[] = t('import.e_date', ['f' => 'reading_date']);
                }
                $val = Money::parse($r['value'], Money::decimals());
                if ($r['value'] === '' || $val === null) {
                    $e[] = t('import.e_value');
                }
                if (!$e && $k) {
                    if (($q = KriReadings::quality($k, (int)$val, $r['comment'])) !== null) {
                        $e[] = t($q);
                    }
                    $n += ['kri' => $k, 'value' => (int)$val, 'date' => $r['reading_date'], 'comment' => $r['comment']];
                }
            } else {
                if ($r['title'] === '' || mb_strlen($r['title']) > 250 || $r['source'] === '' || mb_strlen($r['source']) > 250) {
                    $e[] = t('import.e_title');
                }
                $dep = self::byCode(Ref::departments(true), $r['department_code']);
                $own = self::userByName($r['owner_username']);
                $dep || $e[] = t('import.e_department', ['v' => $r['department_code']]);
                $own || $e[] = t('import.e_owner', ['v' => $r['owner_username']]);
                $fr = strtolower($r['frequency']);
                if (!in_array($fr, ['once', 'monthly', 'quarterly', 'semiannual', 'annual'], true)) {
                    $e[] = t('import.e_frequency');
                }
                if (!self::date($r['due_date'])) {
                    $e[] = t('import.e_date', ['f' => 'due_date']);
                }
                if (!$e) {
                    $n += ['title' => $r['title'], 'source' => $r['source'], 'description' => $r['description'] ?: null, 'department_id' => (int)$dep['id'], 'owner_user_id' => (int)$own['id'], 'frequency' => $fr, 'due_date' => $r['due_date']];
                }
            }
            if ($e) {
                $errors[$r['_line']] = $e;
            } else {
                $out[] = $n;
            }
        }
        return ['rows' => $out, 'errors' => $errors];
    }

    public static function commit(string $target, array $rows, array $user): int
    {
        $now = date('Y-m-d H:i:s');
        Db::tx(function () use ($target, $rows, $user, $now) {
            foreach ($rows as $r) {
                if ($target === 'risks') {
                    $id = Db::insert('risks', ['title' => $r['title'], 'description' => $r['description'], 'category_id' => $r['category_id'], 'department_id' => $r['department_id'], 'branch_id' => null, 'owner_user_id' => $r['owner_user_id'], 'identified_date' => $r['identified_date'], 'review_date' => date('Y-m-d', strtotime('+1 year')),
                        'inh_likelihood' => $r['inh_likelihood'], 'inh_impact' => $r['inh_impact'], 'inh_score' => $r['inh_score'], 'controls_description' => $r['controls_description'], 'control_assessment' => 'not_assessed', 'res_likelihood' => $r['res_likelihood'], 'res_impact' => $r['res_impact'], 'res_score' => $r['res_score'],
                        'res_rationale' => $r['res_rationale'], 'treatment_decision' => $r['treatment_decision'], 'treatment_notes' => $r['treatment_notes'], 'status' => 'active', 'created_by' => $user['id'], 'created_at' => $now, 'updated_at' => $now]);
                    Db::update('risks', ['risk_code' => sprintf('RSK-%05d', $id)], 'id = ?', [$id]);
                    Db::insert('risk_history', ['risk_id' => $id, 'user_id' => $user['id'], 'event' => 'created', 'changes' => json_encode(['import' => true]), 'created_at' => $now]);
                    if ($r['treatment_decision'] === 'accept' && $r['res_score'] >= (int)(Db::val('SELECT MIN(min_score) FROM score_bands WHERE is_high = 1') ?? 26)) {
                        Db::update('risks', ['acceptance_status' => 'pending'], 'id = ?', [$id]);
                        Approvals::request('risk_acceptance', $id, (int)$user['id']);
                    }
                } elseif ($target === 'kri_readings') {
                    KriReadings::record($r['kri'], $r['value'], $r['date'], $r['comment'], (int)$user['id']);
                } else {
                    $id = Db::insert('compliance_obligations', ['title' => $r['title'], 'source' => $r['source'], 'description' => $r['description'], 'department_id' => $r['department_id'], 'owner_user_id' => $r['owner_user_id'], 'frequency' => $r['frequency'], 'due_date' => $r['due_date'], 'status' => 'not_started', 'created_by' => $user['id'], 'created_at' => $now, 'updated_at' => $now]);
                    Db::update('compliance_obligations', ['code' => sprintf('OBL-%04d', $id)], 'id = ?', [$id]);
                }
            }
        });
        return count($rows);
    }
}
