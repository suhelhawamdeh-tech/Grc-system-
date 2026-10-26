<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Db;
use App\Core\Money;
use App\Core\Request;
use App\Core\Settings;

/** Reconciliation of operational losses in the incident register against the general ledger. */
final class ReconController extends Controller
{
    public function index(): string
    {
        $year = max(2000, min(2100, Request::int('year', true) ?: (int)date('Y')));
        $tol = Money::parse((string)(Settings::get('recon_tolerance', '0') ?: '0'), Money::decimals()) ?? 0;
        $inc = array_column(Db::all("SELECT DATE_FORMAT(occurrence_date, '%Y-%m') AS p, COALESCE(SUM(net_loss), 0) AS s, COUNT(*) AS n FROM incidents WHERE archived_at IS NULL AND event_type = 'incident' AND YEAR(occurrence_date) = ? GROUP BY p", [$year]), null, 'p');
        $gl = array_column(Db::all('SELECT period AS p, COALESCE(SUM(amount), 0) AS s, COUNT(*) AS n FROM gl_entries WHERE period LIKE ? GROUP BY period', ["$year-%"]), null, 'p');
        $rows = [];
        $last = $year < (int)date('Y') ? 12 : (int)date('n');
        for ($m = 1; $m <= $last; $m++) {
            $p = sprintf('%04d-%02d', $year, $m);
            $i = isset($inc[$p]) ? Money::fromDb($inc[$p]['s']) : 0;
            $g = isset($gl[$p]) ? Money::fromDb($gl[$p]['s']) : null;
            $diff = $g === null ? null : $i - $g;
            $rows[] = ['period' => $p, 'inc_n' => (int)($inc[$p]['n'] ?? 0), 'incidents' => $i, 'gl' => $g, 'gl_n' => (int)($gl[$p]['n'] ?? 0), 'diff' => $diff, 'state' => $g === null ? 'missing' : (abs($diff) <= $tol ? 'ok' : 'diff')];
        }
        return $this->view('recon/index', ['title' => t('recon.title'), 'rows' => $rows, 'year' => $year, 'tol' => $tol]);
    }

    public function deletePeriod(): never
    {
        $p = Request::str('period');
        if (!preg_match('/^\d{4}-\d{2}$/', $p)) {
            $this->notFound();
        }
        $n = Db::exec('DELETE FROM gl_entries WHERE period = ?', [$p]);
        $this->audit('recon.period_deleted', 'recon', null, ['period' => $p, 'rows' => $n]);
        $this->redirect(url('incidents/reconciliation', ['year' => substr($p, 0, 4)]), 'recon.deleted', 'success', ['p' => $p]);
    }
}
