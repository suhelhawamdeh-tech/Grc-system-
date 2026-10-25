<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\Db;
use App\Core\Money;
use App\Core\Settings;

/**
 * Integration with the Temenos T24 / Transact core banking system.
 *
 * Two ways in, same rules: a file exported from T24 (enquiry or Data Framework extract, CSV with a header row)
 * or the T24 REST API (JSON; records under "body" as in Temenos IRIS responses, or a plain list).
 * Field names are mapped per dataset in the settings, because every bank's T24 build uses its own local fields.
 * Every load is all-or-nothing and logged in integration_runs.
 */
final class T24
{
    public const SYSTEM = 't24';
    public const MAX_ROWS = 20000;
    public const MAX_PAGES = 200;

    /** dataset => target field => [default T24 field, required] */
    public const DATASETS = [
        'customers' => [
            'customer_ref' => ['CUSTOMER.ID', true], 'full_name' => ['NAME.1', true], 'sector' => ['SECTOR', false], 'residence' => ['RESIDENCE', false], 'nationality' => ['NATIONALITY', false],
            'products' => ['PRODUCTS', false], 'channels' => ['CHANNELS', false], 'pep' => ['PEP', false], 'branch' => ['BRANCH', false], 'since' => ['CUSTOMER.SINCE', false],
        ],
        'gl' => [
            'period' => ['PERIOD', true], 'account' => ['PL.CATEGORY', true], 'amount' => ['AMOUNT', true], 'description' => ['DESCRIPTION', false],
        ],
    ];

    /** @return array<string,string> target => source field for a dataset (settings override the defaults) */
    public static function mapping(string $dataset): array
    {
        $map = array_map(static fn($d) => $d[0], self::DATASETS[$dataset]);
        foreach (preg_split('/\R/', (string)Settings::get('t24_map_' . $dataset, '')) ?: [] as $line) {
            if (preg_match('/^\s*([a-z_]+)\s*=\s*(.+?)\s*$/', $line, $m) && isset($map[$m[1]])) {
                $map[$m[1]] = $m[2];
            }
        }
        return $map;
    }

    public static function mappingText(string $dataset): string
    {
        $out = [];
        foreach (self::mapping($dataset) as $k => $v) {
            $out[] = "$k=$v";
        }
        return implode("\n", $out);
    }

    /** Validate mapping text from the settings form. @return ?string error key */
    public static function checkMapping(string $dataset, string $text): ?string
    {
        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            if (trim($line) === '') {
                continue;
            }
            if (!preg_match('/^\s*([a-z_]+)\s*=\s*([^=\r\n]{1,120})\s*$/', $line, $m) || !isset(self::DATASETS[$dataset][$m[1]])) {
                return 't24.err_mapping';
            }
        }
        return null;
    }

    // ------------------------------------------------------------------ reading records

    /** Parse a T24 export (CSV / pipe / semicolon / tab separated, header in the first row). @return array{rows:array,error:?string} */
    public static function parseFile(string $raw): array
    {
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? '';
        if (!mb_check_encoding($raw, 'UTF-8')) {
            return ['rows' => [], 'error' => 'import.err_encoding'];
        }
        $first = strtok($raw, "\n") ?: '';
        $counts = [',' => substr_count($first, ','), ';' => substr_count($first, ';'), '|' => substr_count($first, '|'), "\t" => substr_count($first, "\t")];
        arsort($counts);
        $delim = (string)array_key_first($counts);
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, $raw);
        rewind($fh);
        $head = fgetcsv($fh, 0, $delim, '"', '');
        if (!$head || count($head) < 2) {
            return ['rows' => [], 'error' => 'import.err_empty'];
        }
        $head = array_map(static fn($h) => trim((string)$h), $head);
        $rows = [];
        while (($r = fgetcsv($fh, 0, $delim, '"', '')) !== false) {
            if (count($r) === 1 && trim((string)$r[0]) === '') {
                continue;
            }
            $assoc = [];
            foreach ($head as $i => $h) {
                $assoc[$h] = trim((string)($r[$i] ?? ''));
            }
            $rows[] = $assoc;
            if (count($rows) > self::MAX_ROWS) {
                return ['rows' => [], 'error' => 't24.err_too_many'];
            }
        }
        return ['rows' => $rows, 'error' => $rows ? null : 'import.err_empty'];
    }

    /** Records from a JSON payload: {"body": [...]} (IRIS style), {"data": [...]} or a plain list. */
    public static function recordsFromJson(string $json): ?array
    {
        $d = json_decode($json, true);
        if (!is_array($d)) {
            return null;
        }
        $list = $d['body'] ?? $d['data'] ?? $d;
        return is_array($list) && array_is_list($list) ? $list : null;
    }

    /**
     * Read one field from a record. "a/b" walks nested objects, "*" walks every element of a list,
     * and lists of values are joined with "|" (so multi-value T24 fields work in both files and JSON).
     */
    public static function field(array $rec, string $path): string
    {
        $vals = [$rec];
        foreach (explode('/', $path) as $seg) {
            $next = [];
            foreach ($vals as $v) {
                if ($seg === '*' && is_array($v)) {
                    array_push($next, ...array_values($v));
                } elseif (is_array($v) && array_key_exists($seg, $v)) {
                    $next[] = $v[$seg];
                }
            }
            $vals = $next;
        }
        $flat = [];
        array_walk_recursive($vals, static function ($x) use (&$flat) {
            if ($x !== null && $x !== '') {
                $flat[] = is_bool($x) ? ($x ? 'Y' : 'N') : (string)$x;
            }
        });
        return trim(implode('|', $flat));
    }

    /** Map raw records to target fields. */
    public static function mapRecords(string $dataset, array $records): array
    {
        $map = self::mapping($dataset);
        $out = [];
        foreach ($records as $i => $rec) {
            $row = ['_line' => $i + 1];
            foreach ($map as $target => $src) {
                $row[$target] = is_array($rec) ? self::field($rec, $src) : '';
            }
            $out[] = $row;
        }
        return $out;
    }

    // ------------------------------------------------------------------ API

    /** Base URL must be http(s); credentials are stored encrypted. */
    public static function apiConfig(): array
    {
        $secret = (string)Settings::get('t24_api_secret', '');
        return ['base' => rtrim((string)Settings::get('t24_api_base', ''), '/'), 'user' => (string)Settings::get('t24_api_user', ''), 'secret' => $secret !== '' ? (Crypto::decrypt($secret) ?? '') : '',
            'customers_path' => (string)Settings::get('t24_customers_path', '/api/v1.0.0/party/customers'), 'gl_path' => (string)Settings::get('t24_gl_path', '/api/v1.0.0/accounts/glBalances'),
            'page_size' => max(1, min(1000, (int)Settings::get('t24_page_size', '200'))), 'timeout' => max(5, min(300, (int)Settings::get('t24_timeout', '30')))];
    }

    /** @return array{ok:bool,status:int,body:string,error:?string} */
    private static function httpGet(string $url, array $cfg): array
    {
        $headers = ['Accept: application/json'];
        if ($cfg['user'] !== '') {
            $headers[] = 'Authorization: Basic ' . base64_encode($cfg['user'] . ':' . $cfg['secret']);
        } elseif ($cfg['secret'] !== '') {
            $headers[] = 'Authorization: Bearer ' . $cfg['secret'];
        }
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => $cfg['timeout'], CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2]);
            $body = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $err = $body === false ? curl_error($ch) : null;
            return ['ok' => $body !== false && $status === 200, 'status' => $status, 'body' => (string)$body, 'error' => $err];
        }
        $ctx = stream_context_create(['http' => ['method' => 'GET', 'header' => implode("\r\n", $headers), 'timeout' => $cfg['timeout'], 'ignore_errors' => true, 'follow_location' => 0]]);
        $body = @file_get_contents($url, false, $ctx);
        $status = 0;
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
                $status = (int)$m[1];
            }
        }
        return ['ok' => $body !== false && $status === 200, 'status' => $status, 'body' => (string)$body, 'error' => $body === false ? 'connection failed' : null];
    }

    /**
     * Fetch every page of a dataset from the API (page_size / page_start, as used by Temenos IRIS APIs).
     * @return array{records:?array,error:?string}
     */
    public static function fetch(string $dataset, ?int $limit = null): array
    {
        $cfg = self::apiConfig();
        if (!preg_match('#^https?://[^/\s]+#i', $cfg['base'])) {
            return ['records' => null, 'error' => t('t24.err_no_api')];
        }
        $path = $dataset === 'gl' ? $cfg['gl_path'] : $cfg['customers_path'];
        $size = $limit ?? $cfg['page_size'];
        $all = [];
        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $url = $cfg['base'] . '/' . ltrim($path, '/') . (str_contains($path, '?') ? '&' : '?') . http_build_query(['page_size' => $size, 'page_start' => $page]);
            $r = self::httpGet($url, $cfg);
            if (!$r['ok']) {
                return ['records' => null, 'error' => t('t24.err_http', ['s' => $r['status'] ?: '—']) . ($r['error'] ? ' (' . mb_strimwidth($r['error'], 0, 200, '…') . ')' : '')];
            }
            $recs = self::recordsFromJson($r['body']);
            if ($recs === null) {
                return ['records' => null, 'error' => t('t24.err_json')];
            }
            array_push($all, ...$recs);
            if ($limit !== null || count($recs) < $size) {
                break;
            }
            if (count($all) > self::MAX_ROWS) {
                return ['records' => null, 'error' => t('t24.err_too_many')];
            }
        }
        return ['records' => $all, 'error' => null];
    }

    // ------------------------------------------------------------------ loading

    /**
     * Validate and load mapped records. Nothing is written unless every row is valid.
     * @return array run row (id, status, rows_read, rows_created, rows_updated, error_text)
     */
    public static function load(string $dataset, array $records, string $mode, ?string $fileName, int $userId, ?int $runBy): array
    {
        $started = date('Y-m-d H:i:s');
        $rows = self::mapRecords($dataset, $records);
        $errors = [];
        if (!$rows) {
            $errors[] = t('import.err_empty');
        }
        $missing = [];
        foreach (self::DATASETS[$dataset] as $target => [, $required]) {
            if ($required && $rows && !array_filter(array_column($rows, $target), static fn($v) => $v !== '')) {
                $missing[] = $target . ' ← ' . self::mapping($dataset)[$target];
            }
        }
        if ($missing) {
            $errors[] = t('t24.err_fields_missing', ['f' => implode(', ', $missing)]);
        }
        $created = $updated = 0;
        if (!$errors) {
            $prepared = $dataset === 'gl' ? self::prepareGl($rows, $errors) : self::prepareCustomers($rows, $errors);
            if (!$errors) {
                [$created, $updated] = $dataset === 'gl' ? self::commitGl($prepared, $userId) : self::commitCustomers($prepared, $userId);
            }
        }
        $run = ['system_code' => self::SYSTEM, 'dataset' => $dataset, 'mode' => $mode, 'file_name' => $fileName !== null ? mb_substr($fileName, 0, 200) : null, 'status' => $errors ? 'failed' : 'success',
            'rows_read' => count($rows), 'rows_created' => $created, 'rows_updated' => $updated, 'error_text' => $errors ? implode("\n", array_slice($errors, 0, 50)) . (count($errors) > 50 ? "\n… +" . (count($errors) - 50) : '') : null,
            'run_by' => $runBy, 'started_at' => $started, 'finished_at' => date('Y-m-d H:i:s')];
        $run['id'] = Db::insert('integration_runs', $run);
        return $run;
    }

    /** Log a run that failed before any record could be read (connection, JSON, file). */
    public static function logFailure(string $dataset, string $mode, ?string $fileName, string $error, ?int $runBy): array
    {
        $now = date('Y-m-d H:i:s');
        $run = ['system_code' => self::SYSTEM, 'dataset' => $dataset, 'mode' => $mode, 'file_name' => $fileName, 'status' => 'failed', 'rows_read' => 0, 'rows_created' => 0, 'rows_updated' => 0, 'error_text' => $error, 'run_by' => $runBy, 'started_at' => $now, 'finished_at' => $now];
        $run['id'] = Db::insert('integration_runs', $run);
        return $run;
    }

    private static function date(string $s): ?string
    {
        foreach (['!Ymd', '!Y-m-d'] as $fmt) {
            $d = \DateTimeImmutable::createFromFormat($fmt, $s);
            if ($d && $d->format(substr($fmt, 1)) === $s) {
                return $d->format('Y-m-d');
            }
        }
        return null;
    }

    private static function prepareCustomers(array $rows, array &$errors): array
    {
        $branches = array_column(Db::all('SELECT id, code FROM branches'), 'id', 'code');
        $seen = [];
        $out = [];
        AmlScoring::resetCache();
        foreach ($rows as $r) {
            $e = [];
            $ref = $r['customer_ref'];
            if ($ref === '' || mb_strlen($ref) > 40) {
                $e[] = t('t24.e_ref');
            } elseif (isset($seen[$ref])) {
                $e[] = t('t24.e_duplicate', ['v' => $ref]);
            }
            $seen[$ref] = true;
            if ($r['full_name'] === '' || mb_strlen($r['full_name']) > 200) {
                $e[] = t('t24.e_name');
            }
            $type = AmlScoring::factorForCode('customer', $r['sector']);
            if (!$type) {
                $e[] = t('t24.e_no_default', ['d' => t('enum.amld.customer')]);
            }
            $geo = null;
            foreach ([$r['residence'], $r['nationality']] as $code) {
                $g = AmlScoring::factorForCode('geography', $code);
                if ($g && (!$geo || (int)$g['risk_level'] > (int)$geo['risk_level'])) {
                    $geo = $g;
                }
            }
            if (!$geo) {
                $e[] = t('t24.e_no_default', ['d' => t('enum.amld.geography')]);
            }
            $factors = [];
            foreach (['product' => 'products', 'channel' => 'channels'] as $dim => $col) {
                $codes = array_filter(array_map('trim', preg_split('/[|;,]/', $r[$col]) ?: []), static fn($c) => $c !== '');
                $ids = [];
                foreach ($codes ?: [''] as $code) {
                    $f = AmlScoring::factorForCode($dim, $code);
                    if ($f) {
                        $ids[(int)$f['id']] = true;
                    }
                }
                if (!$ids) {
                    $e[] = t('t24.e_no_default', ['d' => t('enum.amld.' . $dim)]);
                }
                $factors = array_merge($factors, array_keys($ids));
            }
            $since = null;
            if ($r['since'] !== '') {
                $since = self::date($r['since']);
                if ($since === null || $since > date('Y-m-d')) {
                    $e[] = t('t24.e_date', ['v' => $r['since']]);
                }
            }
            if ($e) {
                $errors[] = t('import.line') . ' ' . ($r['_line'] + 1) . ': ' . implode(' ', $e);
                continue;
            }
            $out[] = ['customer_ref' => $ref, 'full_name' => $r['full_name'], 'customer_type_id' => (int)$type['id'], 'geography_id' => (int)$geo['id'], 'factors' => array_values(array_unique($factors)),
                'is_pep' => in_array(strtoupper($r['pep']), ['Y', 'YES', 'TRUE', '1'], true) ? 1 : 0, 'branch_id' => $branches[$r['branch']] ?? null, 'onboarded_on' => $since];
        }
        return $out;
    }

    private static function commitCustomers(array $rows, int $userId): array
    {
        return Db::tx(function () use ($rows, $userId) {
            $now = date('Y-m-d H:i:s');
            $created = $updated = 0;
            foreach ($rows as $r) {
                $factors = $r['factors'];
                unset($r['factors']);
                $id = (int)Db::val('SELECT id FROM aml_customers WHERE customer_ref = ?', [$r['customer_ref']]);
                $isNew = $id === 0;
                $d = $r + ['source' => 't24', 'updated_at' => $now];
                if (!$isNew) {
                    if ($d['branch_id'] === null) {
                        unset($d['branch_id']);
                    }
                    Db::update('aml_customers', $d, 'id = ?', [$id]);
                    Db::exec('DELETE FROM aml_customer_factors WHERE customer_id = ?', [$id]);
                    $updated++;
                } else {
                    $id = Db::insert('aml_customers', $d + ['score' => 1, 'calc_rating' => 'low', 'rating' => 'low', 'cdd_level' => 'simplified', 'next_review_due' => date('Y-m-d'), 'status' => 'active', 'created_by' => $userId, 'created_at' => $now]);
                    $created++;
                }
                foreach ($factors as $fid) {
                    Db::exec('INSERT INTO aml_customer_factors (customer_id, factor_id) VALUES (?, ?)', [$id, $fid]);
                }
                $res = AmlScoring::recalc($id);
                if ($isNew) {
                    // First rating sets the review cycle; for existing customers recalc() only brings it forward when the rating rises.
                    Db::update('aml_customers', ['next_review_due' => AmlScoring::nextReview($res['rating'], date('Y-m-d'))], 'id = ?', [$id]);
                }
            }
            return [$created, $updated];
        });
    }

    private static function prepareGl(array $rows, array &$errors): array
    {
        $out = [];
        foreach ($rows as $r) {
            $e = [];
            $p = preg_replace('/[^0-9]/', '', $r['period']) ?? '';
            $period = strlen($p) >= 6 ? substr($p, 0, 4) . '-' . substr($p, 4, 2) : '';
            $d = $period !== '' ? \DateTimeImmutable::createFromFormat('!Y-m', $period) : false;
            if (!$d || $d->format('Y-m') !== $period || (int)substr($period, 0, 4) < 1990 || $period > date('Y-m')) {
                $e[] = t('import.e_period');
            }
            if ($r['account'] === '' || mb_strlen($r['account']) > 40) {
                $e[] = t('import.e_account');
            }
            // T24 exports often show P&L debits as negative numbers: the loss amount is the absolute value.
            $am = Money::parse(ltrim(str_replace([' ', "\u{00A0}"], '', $r['amount']), '-'), Money::decimals());
            if ($r['amount'] === '' || $am === null) {
                $e[] = t('validation.amount', ['field' => 'amount']);
            }
            if ($e) {
                $errors[] = t('import.line') . ' ' . ($r['_line'] + 1) . ': ' . implode(' ', $e);
                continue;
            }
            $out[] = ['period' => $period, 'account' => $r['account'], 'amount' => $am, 'description' => $r['description'] !== '' ? mb_substr($r['description'], 0, 250) : null];
        }
        return $out;
    }

    /** Periods present in the load replace what was stored for them, so a re-run never double counts. */
    private static function commitGl(array $rows, int $userId): array
    {
        return Db::tx(function () use ($rows, $userId) {
            $now = date('Y-m-d H:i:s');
            $periods = array_values(array_unique(array_column($rows, 'period')));
            $replaced = 0;
            foreach ($periods as $p) {
                $replaced += Db::exec('DELETE FROM gl_entries WHERE period = ?', [$p]);
            }
            foreach ($rows as $r) {
                Db::insert('gl_entries', ['period' => $r['period'], 'account' => $r['account'], 'description' => $r['description'], 'amount' => Money::toDb($r['amount']), 'imported_by' => $userId, 'imported_at' => $now]);
            }
            return [count($rows), $replaced];
        });
    }
}
