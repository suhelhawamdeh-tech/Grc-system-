<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Db;
use App\Core\Request;
use App\Core\Settings;
use App\Core\Validator;
use App\Support\Crypto;
use App\Support\Csv;
use App\Support\T24;

/** Administrator page for the T24 core-banking link: connection settings, field mapping, manual loads and the run log. */
final class IntegrationController extends Controller
{
    public function index(): string
    {
        $runs = Db::all('SELECT r.*, u.full_name FROM integration_runs r LEFT JOIN users u ON u.id = r.run_by ORDER BY r.id DESC LIMIT 30');
        $cfg = T24::apiConfig();
        return $this->view('integrations/index', ['title' => t('t24.title'), 'runs' => $runs, 'cfg' => $cfg, 'hasSecret' => (string)Settings::get('t24_api_secret', '') !== '',
            'maps' => ['customers' => T24::mappingText('customers'), 'gl' => T24::mappingText('gl')]]);
    }

    public function saveSettings(): never
    {
        $v = new Validator($_POST);
        $v->max('api_base', 't24.api_base', 300)->max('api_user', 't24.api_user', 120)->max('customers_path', 't24.customers_path', 300)->max('gl_path', 't24.gl_path', 300)
            ->intRange('page_size', 't24.page_size', 1, 1000)->intRange('timeout', 't24.timeout', 5, 300);
        $base = rtrim($v->str('api_base'), '/');
        if ($base !== '' && (!preg_match('~^https?://[^/\s?#@]+(/[^\s?#]*)?$~i', $base))) {
            $v->fail('api_base', 't24.err_url');
        }
        foreach (['customers_path', 'gl_path'] as $f) {
            if ($v->str($f) !== '' && !preg_match('#^/[^\s]*$#', $v->str($f))) {
                $v->fail($f, 't24.err_path');
            }
        }
        foreach (['customers', 'gl'] as $ds) {
            $err = T24::checkMapping($ds, (string)($_POST['map_' . $ds] ?? ''));
            if ($err) {
                $v->fail('map_' . $ds, $err);
            }
        }
        if (!$v->ok()) {
            $this->invalid($v->errors(), url('admin/integrations') . '#settings');
        }
        Settings::set('t24_api_base', $base);
        Settings::set('t24_api_user', $v->str('api_user'));
        Settings::set('t24_customers_path', $v->str('customers_path') ?: '/api/v1.0.0/party/customers');
        Settings::set('t24_gl_path', $v->str('gl_path') ?: '/api/v1.0.0/accounts/glBalances');
        Settings::set('t24_page_size', $v->str('page_size'));
        Settings::set('t24_timeout', $v->str('timeout'));
        foreach (['customers', 'gl'] as $ds) {
            Settings::set('t24_map_' . $ds, trim(str_replace("\r", '', (string)($_POST['map_' . $ds] ?? ''))));
        }
        $secretChanged = false;
        if (isset($_POST['clear_secret'])) {
            Settings::set('t24_api_secret', '');
            $secretChanged = true;
        } elseif (($s = (string)($_POST['api_secret'] ?? '')) !== '') {
            Settings::set('t24_api_secret', Crypto::encrypt($s));
            $secretChanged = true;
        }
        // The secret itself is never written to the audit log.
        $this->audit('t24.settings_updated', 'integration', null, ['base' => $base, 'user' => $v->str('api_user'), 'secret_changed' => $secretChanged]);
        $this->redirect(url('admin/integrations'), 'admin.saved');
    }

    public function test(): never
    {
        $r = T24::fetch('customers', 1);
        $this->audit('t24.connection_tested', 'integration', null, ['ok' => $r['error'] === null]);
        if ($r['error'] !== null) {
            $this->redirect(url('admin/integrations'), 't24.test_failed', 'error', ['e' => $r['error']]);
        }
        $this->redirect(url('admin/integrations'), 't24.test_ok', 'success', ['n' => count($r['records'])]);
    }

    public function upload(): never
    {
        $ds = Request::str('dataset');
        if (!isset(T24::DATASETS[$ds])) {
            $this->redirect(url('admin/integrations') . '#load', 't24.err_dataset', 'error');
        }
        $f = $_FILES['file'] ?? null;
        if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name']) || (int)$f['size'] > 20 * 1024 * 1024) {
            $this->redirect(url('admin/integrations') . '#load', 't24.err_file', 'error');
        }
        $name = basename((string)$f['name']);
        $raw = (string)file_get_contents($f['tmp_name']);
        $u = $this->user();
        if (preg_match('/\.json$/i', $name)) {
            $recs = T24::recordsFromJson($raw);
            $run = $recs === null ? T24::logFailure($ds, 'file', $name, t('t24.err_json'), (int)$u['id']) : T24::load($ds, $recs, 'file', $name, (int)$u['id'], (int)$u['id']);
        } else {
            $p = T24::parseFile($raw);
            $run = $p['error'] ? T24::logFailure($ds, 'file', $name, t($p['error']), (int)$u['id']) : T24::load($ds, $p['rows'], 'file', $name, (int)$u['id'], (int)$u['id']);
        }
        $this->finish($run);
    }

    public function pull(): never
    {
        $ds = Request::str('dataset');
        if (!isset(T24::DATASETS[$ds])) {
            $this->redirect(url('admin/integrations') . '#load', 't24.err_dataset', 'error');
        }
        $u = $this->user();
        $r = T24::fetch($ds);
        $run = $r['error'] !== null ? T24::logFailure($ds, 'api', null, $r['error'], (int)$u['id']) : T24::load($ds, $r['records'], 'api', null, (int)$u['id'], (int)$u['id']);
        $this->finish($run);
    }

    private function finish(array $run): never
    {
        $this->audit('t24.load_' . $run['status'], 'integration', (int)$run['id'], ['dataset' => $run['dataset'], 'mode' => $run['mode'], 'read' => $run['rows_read'], 'created' => $run['rows_created'], 'updated' => $run['rows_updated']]);
        if ($run['status'] === 'success') {
            $this->redirect(url('admin/integrations/runs/' . $run['id']), 't24.load_ok', 'success', ['c' => $run['rows_created'], 'u' => $run['rows_updated']]);
        }
        $this->redirect(url('admin/integrations/runs/' . $run['id']), 't24.load_failed', 'error');
    }

    public function run(int $id): string
    {
        $r = Db::one('SELECT r.*, u.full_name FROM integration_runs r LEFT JOIN users u ON u.id = r.run_by WHERE r.id = ?', [$id]) ?? $this->notFound();
        return $this->view('integrations/run', ['title' => t('t24.run') . ' #' . $id, 'r' => $r]);
    }

    /** Example file with the currently mapped column names, so the T24 team knows exactly what to export. */
    public function sample(): never
    {
        $dataset = (string)$this->extra;
        if (!isset(T24::DATASETS[$dataset])) {
            $this->notFound();
        }
        $map = T24::mapping($dataset);
        $ex = $dataset === 'gl'
            ? [['PERIOD' => date('Ym', strtotime('first day of last month')), 'PL.CATEGORY' => '69001', 'AMOUNT' => '-1250.500', 'DESCRIPTION' => 'Operational loss - cash shortage']]
            : [['CUSTOMER.ID' => '100234', 'NAME.1' => 'Example Trading Co', 'SECTOR' => '2001', 'RESIDENCE' => 'LY', 'NATIONALITY' => 'LY', 'PRODUCTS' => '1001|21051', 'CHANNELS' => 'BRANCH|INTERNET', 'PEP' => 'N', 'BRANCH' => 'TRP', 'CUSTOMER.SINCE' => '20190315']];
        $gen = (static function () use ($ex, $map) {
            foreach ($ex as $row) {
                yield array_map(static fn($src) => $row[$src] ?? '', array_values($map));
            }
        })();
        Csv::download('t24-' . $dataset . '-template.csv', array_values($map), $gen);
    }
}
