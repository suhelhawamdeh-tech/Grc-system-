<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Perm;
use App\Core\Request;
use App\Support\Csv;
use App\Support\Importer;

/** Bulk CSV import with a mandatory preview: nothing is saved unless every row is valid. */
final class ImportController extends Controller
{
    private const TTL = 1800;

    private function targets(array $u): array
    {
        return array_values(array_filter(array_keys(Importer::TARGETS), fn($t) => Perm::roleHas($u['role'], Importer::PERM[$t])));
    }

    private function dir(): string
    {
        $d = Config::storagePath('cache');
        if (!is_dir($d)) {
            @mkdir($d, 0750, true);
        }
        foreach (glob($d . '/import-*.json') ?: [] as $f) {
            if (filemtime($f) < time() - self::TTL) {
                @unlink($f);
            }
        }
        return $d;
    }

    public function index(): string
    {
        $u = $this->user();
        return $this->view('import/index', ['title' => t('import.title'), 'targets' => $this->targets($u), 'preview' => null]);
    }

    public function template(): never
    {
        $u = $this->user();
        $t = Request::str('target', true);
        if (!in_array($t, $this->targets($u), true)) {
            $this->notFound();
        }
        $sample = ['risks' => [['Cash handling errors at branches', 'Differences found during counts', 'OP', 'RET', 'demo.risk', 3, 4, 2, 3, 'Dual control introduced', 'mitigate', '', 'Daily cash count', date('Y-m-d')]],
            'kri_readings' => [['KRI-0001', date('Y-m-d'), '4.5', 'Monthly figure']], 'obligations' => [['Submit quarterly liquidity report', 'Central bank instruction', 'FIN', 'demo.compliance', 'quarterly', date('Y-m-d', strtotime('+30 days')), '']]][$t];
        Csv::download('template-' . $t . '.csv', Importer::TARGETS[$t], $sample);
    }

    public function preview(): string
    {
        $u = $this->user();
        $t = Request::str('target');
        if (!in_array($t, $this->targets($u), true)) {
            $this->notFound();
        }
        $f = $_FILES['file'] ?? null;
        $err = null;
        if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
            $err = 'upload.none';
        } elseif ($f['size'] > 2 * 1024 * 1024 || $f['size'] <= 0) {
            $err = 'import.err_size';
        } elseif (!in_array(strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION)), ['csv', 'txt'], true)) {
            $err = 'import.err_type';
        }
        $parsed = $err ? ['rows' => [], 'error' => $err] : Importer::parse((string)file_get_contents($f['tmp_name']), $t);
        if ($parsed['error']) {
            $this->redirect(url('import'), $parsed['error'], 'error');
        }
        $v = Importer::validate($t, $parsed['rows'], $u);
        $token = null;
        if (!$v['errors']) {
            $token = bin2hex(random_bytes(16));
            file_put_contents($this->dir() . '/import-' . $token . '.json', json_encode(['user' => (int)$u['id'], 'target' => $t, 'rows' => $parsed['rows']], JSON_UNESCAPED_UNICODE));
        }
        $this->audit('import.previewed', 'import', null, ['target' => $t, 'rows' => count($parsed['rows']), 'errors' => count($v['errors'])]);
        return $this->view('import/index', ['title' => t('import.title'), 'targets' => $this->targets($u), 'preview' => ['target' => $t, 'total' => count($parsed['rows']), 'errors' => $v['errors'], 'sample' => array_slice($parsed['rows'], 0, 8), 'token' => $token]]);
    }

    public function commit(): never
    {
        $u = $this->user();
        $token = Request::str('token');
        $file = preg_match('/^[0-9a-f]{32}$/', $token) ? $this->dir() . '/import-' . $token . '.json' : '';
        $data = $file !== '' && is_file($file) ? json_decode((string)file_get_contents($file), true) : null;
        if (!is_array($data) || (int)$data['user'] !== (int)$u['id'] || !in_array($data['target'], $this->targets($u), true)) {
            $this->redirect(url('import'), 'import.err_token', 'error');
        }
        @unlink($file);
        $v = Importer::validate($data['target'], $data['rows'], $u);
        if ($v['errors']) {
            $this->redirect(url('import'), 'import.err_changed', 'error');
        }
        $n = Importer::commit($data['target'], $v['rows'], $u);
        $this->audit('import.committed', 'import', null, ['target' => $data['target'], 'rows' => $n]);
        $this->redirect(url('import'), 'import.done', 'success', ['n' => $n]);
    }
}
