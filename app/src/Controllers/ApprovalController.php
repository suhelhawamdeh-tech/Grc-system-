<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Db;
use App\Core\Request;
use App\Support\Approvals;
use App\Support\Bia;

/** Approval inbox for deciders (maker-checker): pending requests plus the user's own requests. */
final class ApprovalController extends Controller
{
    private const LABEL = ['bia_process' => ['table' => 'bia_processes', 'url' => 'bia/'], 'policy_version' => ['table' => 'policy_versions', 'url' => 'policies/']];

    public function index(): string
    {
        $u = $this->user();
        $pending = Db::all("SELECT a.*, r.full_name AS requester FROM approvals a JOIN users r ON r.id = a.requested_by WHERE a.status = 'pending' ORDER BY a.requested_at");
        $mine = Db::all('SELECT a.*, d.full_name AS decider FROM approvals a LEFT JOIN users d ON d.id = a.decided_by WHERE a.requested_by = ? ORDER BY a.id DESC LIMIT 20', [$u['id']]);
        $decorate = static function (array $list): array {
            foreach ($list as &$a) {
                $t = self::LABEL[$a['entity_type']] ?? null;
                if ($a['entity_type'] === 'policy_version') {
                    $e = Db::one('SELECT p.id AS pid, p.code, p.title AS name, v.version_label FROM policy_versions v JOIN policies p ON p.id = v.policy_id WHERE v.id = ?', [$a['entity_id']]);
                    $a['label'] = $e ? $e['code'] . ' — ' . $e['name'] . ' (' . $e['version_label'] . ')' : 'policy_version #' . $a['entity_id'];
                    $a['link'] = $e ? url('policies/' . $e['pid']) : null;
                    continue;
                }
                $e = $t ? Db::one('SELECT code, name FROM ' . $t['table'] . ' WHERE id = ?', [$a['entity_id']]) : null;
                $a['label'] = $e ? $e['code'] . ' — ' . $e['name'] : $a['entity_type'] . ' #' . $a['entity_id'];
                $a['link'] = $t ? url($t['url'] . $a['entity_id']) : null;
            }
            return $list;
        };
        $pending = $decorate($pending);
        $mine = $decorate($mine);
        return $this->view('approvals/index', ['title' => t('appr.title'), 'pending' => $pending, 'mine' => $mine, 'me' => (int)$u['id']]);
    }

    public function decide(int $id): never
    {
        $u = $this->user();
        $a = Db::one('SELECT * FROM approvals WHERE id = ?', [$id]);
        $a || $this->notFound();
        $approve = Request::str('decision') === 'approve';
        $back = Request::str('back') === 'entity' && $a['entity_type'] === 'bia_process' ? url('bia/' . $a['entity_id']) : url('approvals');
        if ($a['entity_type'] === 'bia_process' && $approve && Bia::gaps((int)$a['entity_id'])) {
            $this->redirect($back, 'bia.err_gaps', 'error');
        }
        $err = Db::tx(function () use ($id, $u, $approve, $a) {
            $err = Approvals::decide($id, (int)$u['id'], $approve, Request::str('comment'));
            if ($err === null && $a['entity_type'] === 'policy_version') {
                $now = date('Y-m-d H:i:s');
                $v = Db::one('SELECT v.*, p.review_months FROM policy_versions v JOIN policies p ON p.id = v.policy_id WHERE v.id = ?', [$a['entity_id']]);
                if ($approve && $v) {
                    Db::update('policy_versions', ['is_current' => 0], 'policy_id = ?', [$v['policy_id']]);
                    Db::update('policy_versions', ['status' => 'approved', 'is_current' => 1, 'approved_by' => $u['full_name'], 'approval_date' => date('Y-m-d')], 'id = ?', [$v['id']]);
                    Db::update('policies', ['status' => 'approved', 'next_review_date' => date('Y-m-d', strtotime($v['effective_date'] . ' +' . (int)$v['review_months'] . ' months')), 'updated_at' => $now], 'id = ?', [$v['policy_id']]);
                } elseif ($v) {
                    Db::update('policy_versions', ['status' => 'rejected'], 'id = ?', [$v['id']]);
                }
            }
            if ($err === null && $a['entity_type'] === 'bia_process') {
                $now = date('Y-m-d H:i:s');
                Db::update('bia_processes', $approve ? ['status' => 'approved', 'approved_at' => $now, 'next_review_due' => date('Y-m-d', strtotime('+1 year')), 'updated_at' => $now] : ['status' => 'draft', 'updated_at' => $now], 'id = ?', [$a['entity_id']]);
            }
            return $err;
        });
        if ($err !== null) {
            $this->redirect($back, $err, 'error');
        }
        $this->audit($approve ? 'approval.approved' : 'approval.rejected', $a['entity_type'], (int)$a['entity_id'], ['approval_id' => $id]);
        $this->redirect($back, $approve ? 'appr.approved' : 'appr.rejected');
    }
}
