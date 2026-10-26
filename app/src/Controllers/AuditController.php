<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Db;
use App\Core\Request;
use App\Support\Csv;

final class AuditController extends Controller
{
    private const PER_PAGE = 50;

    private function filter(): array
    {
        $where = ['1=1'];
        $p = [];
        $f = ['action' => Request::str('action', true), 'user' => Request::str('user', true), 'from' => Request::str('from', true), 'to' => Request::str('to', true), 'entity' => Request::str('entity', true)];
        if ($f['action'] !== '') {
            $where[] = 'a.action LIKE ?';
            $p[] = Db::like($f['action']);
        }
        if ($f['user'] !== '') {
            $where[] = 'a.username LIKE ?';
            $p[] = Db::like($f['user']);
        }
        if ($f['entity'] !== '') {
            $where[] = 'a.entity_type = ?';
            $p[] = $f['entity'];
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['from'])) {
            $where[] = 'a.created_at >= ?';
            $p[] = $f['from'] . ' 00:00:00';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $f['to'])) {
            $where[] = 'a.created_at <= ?';
            $p[] = $f['to'] . ' 23:59:59';
        }
        return [$f, implode(' AND ', $where), $p];
    }

    public function index(): string
    {
        [$f, $w, $p] = $this->filter();
        $total = (int)Db::val('SELECT COUNT(*) FROM audit_log a WHERE ' . $w, $p);
        $page = min($this->page(), max(1, (int)ceil($total / self::PER_PAGE)));
        $rows = Db::all('SELECT a.* FROM audit_log a WHERE ' . $w . ' ORDER BY a.id DESC LIMIT ? OFFSET ?', array_merge($p, [self::PER_PAGE, ($page - 1) * self::PER_PAGE]));
        return $this->view('audit/index', ['title' => t('nav.audit'), 'rows' => $rows, 'f' => $f, 'total' => $total, 'page' => $page, 'per' => self::PER_PAGE]);
    }

    public function export(): never
    {
        [$f, $w, $p] = $this->filter();
        $this->audit('export.audit', 'audit', null, ['filters' => array_filter($f)]);
        $rows = Db::all('SELECT a.* FROM audit_log a WHERE ' . $w . ' ORDER BY a.id DESC LIMIT 50000', $p);
        $gen = (static function () use ($rows) {
            foreach ($rows as $r) {
                yield [$r['id'], $r['created_at'], $r['username'], $r['action'], $r['entity_type'], $r['entity_id'], $r['detail'], $r['ip']];
            }
        })();
        Csv::download('audit-log-' . date('Ymd-His') . '.csv', ['ID', t('audit.time'), t('audit.user'), t('audit.action'), t('audit.entity'), t('audit.entity_id'), t('audit.detail'), 'IP'], $gen, Request::str('sep', true) === ';' ? ';' : ',');
    }
}
