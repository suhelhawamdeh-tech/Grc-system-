<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Db;
use App\Core\HttpException;
use App\Core\Perm;
use App\Core\Response;
use App\Core\Scope;
use App\Core\Session;
use App\Support\Upload;

final class AttachmentController extends Controller
{
    public static function handleUpload(string $type, int $entityId, array $user, string $back): never
    {
        $f = $_FILES['file'] ?? null;
        if (!is_array($f) || !is_string($f['name'] ?? null)) {
            Session::message('error', t('upload.none'));
            Response::redirect($back);
        }
        if ($err = Upload::validate($f)) {
            Session::message('error', t($err, ['max' => '5 MB', 'types' => implode(', ', array_keys(Upload::TYPES))]));
            Response::redirect($back);
        }
        $id = Upload::store($f, $type, $entityId, (int)$user['id']);
        Audit::log('attachment.uploaded', $type, $entityId, ['attachment_id' => $id, 'name' => $f['name'], 'size' => $f['size']]);
        Session::message('success', t('upload.done'));
        Response::redirect($back);
    }

    /**
     * Parent-record access rule per attachment type: [view permission, edit permission(s), scope callable].
     * Returns the parent row (with department/branch columns where relevant) or null.
     */
    public static function parentAccess(array $user, string $type, int $id, bool $forEdit): bool
    {
        $role = $user['role'];
        $can = static fn(string ...$perms): bool => (bool)array_filter($perms, static fn($p) => Perm::roleHas($role, $p));
        switch ($type) {
            case 'risk':
            case 'incident':
                $tbl = $type === 'risk' ? 'risks' : 'incidents';
                $row = Db::one("SELECT department_id, branch_id FROM $tbl WHERE id = ?", [$id]);
                return $row && Scope::allows($user, $row) && $can($type . ($forEdit ? '.edit' : '.view'));
            case 'obligation':
                $row = Db::one('SELECT department_id FROM compliance_obligations WHERE id = ?', [$id]);
                return $row && Scope::allowsDept($user, $row) && ($forEdit ? $can('compliance.manage', 'compliance.update') : $can('compliance.view'));
            case 'control_test':
                $row = Db::one('SELECT c.department_id FROM control_tests t JOIN controls c ON c.id = t.control_id WHERE t.id = ?', [$id]);
                return $row && Scope::allowsDept($user, $row) && ($forEdit ? $can('control.test') : $can('control.view'));
            case 'circular':
                if ($forEdit) {
                    return $can('circular.manage') && Db::val('SELECT 1 FROM regulatory_circulars WHERE id = ?', [$id]);
                }
                if (!$can('circular.view')) {
                    return false;
                }
                if (Perm::scopeType($role) === 'department') {
                    return (bool)Db::val("SELECT 1 FROM circular_distribution d JOIN regulatory_circulars c ON c.id = d.circular_id WHERE c.id = ? AND c.status <> 'draft' AND d.department_id = ?", [$id, (int)($user['department_id'] ?? 0)]);
                }
                return (bool)Db::val(Perm::roleHas($role, 'circular.manage') ? 'SELECT 1 FROM regulatory_circulars WHERE id = ?' : "SELECT 1 FROM regulatory_circulars WHERE id = ? AND status <> 'draft'", [$id]);
            case 'sharia_ruling':
                return Db::val('SELECT 1 FROM sharia_rulings WHERE id = ?', [$id]) && ($forEdit ? $can('sharia.manage') : $can('sharia.view'));
            case 'sharia_event':
                $row = Db::one('SELECT department_id FROM sharia_events WHERE id = ?', [$id]);
                return $row && Scope::allowsDept($user, $row) && ($forEdit ? $can('sharia.report') : $can('sharia.view'));
            case 'audit_workpaper':
                return Db::val('SELECT 1 FROM audit_workpapers WHERE id = ?', [$id]) && ($forEdit ? $can('ia.manage') : $can('ia.view'));
            case 'audit_finding':
                $f = Db::one('SELECT status, department_id, owner_user_id FROM audit_findings WHERE id = ?', [$id]);
                if (!$f) {
                    return false;
                }
                if ($can('ia.manage')) {
                    return true;
                }
                if ($forEdit || $f['status'] === 'draft') {
                    return false;
                }
                return $can('ia.view') || ($can('ia.findings') && ((int)$f['department_id'] === (int)($user['department_id'] ?? 0) || (int)$f['owner_user_id'] === (int)$user['id']));
            case 'aml_report':
                $r = Db::one('SELECT confidential FROM aml_reports WHERE id = ?', [$id]);
                if (!$r) {
                    return false;
                }
                if ($forEdit || (int)$r['confidential'] === 1) {
                    return $can('aml.manage');
                }
                return $can('aml.view');
            case 'policy_version':
                $row = Db::one('SELECT id FROM policy_versions WHERE id = ?', [$id]);
                return $row && ($forEdit ? $can('policy.manage') : $can('policy.view'));
            default:
                return false;
        }
    }

    private function loadAuthorised(int $id, bool $forEdit = false): array
    {
        $u = $this->user();
        $a = Db::one('SELECT * FROM attachments WHERE id = ? AND deleted_at IS NULL', [$id]);
        // Missing, out-of-scope and not-permitted all look the same (404) to avoid enumeration.
        if (!$a || !self::parentAccess($u, $a['entity_type'], (int)$a['entity_id'], $forEdit)) {
            $this->notFound();
        }
        return $a;
    }

    public function download(int $id): never
    {
        $a = $this->loadAuthorised($id);
        $path = Upload::path($a);
        if (!$path) {
            $this->notFound();
        }
        $this->audit('attachment.downloaded', $a['entity_type'], (int)$a['entity_id'], ['attachment_id' => $id]);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        $ascii = preg_replace('/[^A-Za-z0-9._-]/', '_', $a['original_name']);
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($a['original_name']));
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
    }

    public function delete(int $id): never
    {
        $a = $this->loadAuthorised($id, true);
        Db::update('attachments', ['deleted_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        $this->audit('attachment.deleted', $a['entity_type'], (int)$a['entity_id'], ['attachment_id' => $id]);
        $base = match ($a['entity_type']) {
            'risk' => 'risks/' . $a['entity_id'],
            'incident' => 'incidents/' . $a['entity_id'],
            'obligation' => 'compliance/' . $a['entity_id'],
            'circular' => 'circulars/' . $a['entity_id'],
            'sharia_ruling' => 'sharia/rulings/' . $a['entity_id'],
            'sharia_event' => 'sharia/events/' . $a['entity_id'],
            'control_test' => 'controls/' . (int)Db::val('SELECT control_id FROM control_tests WHERE id = ?', [$a['entity_id']]),
            'audit_workpaper' => 'ia/workpapers/' . $a['entity_id'],
            'audit_finding' => 'ia/findings/' . $a['entity_id'],
            'aml_report' => 'aml/reports/' . $a['entity_id'],
            default => 'policies/' . (int)Db::val('SELECT policy_id FROM policy_versions WHERE id = ?', [$a['entity_id']]),
        };
        $this->redirect(url($base) . '#attachments', 'upload.deleted');
    }
}
