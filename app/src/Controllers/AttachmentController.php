<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Db;
use App\Core\HttpException;
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

    /** Load attachment and verify the caller may access its parent record. */
    private function loadAuthorised(int $id): array
    {
        $u = $this->user();
        $a = Db::one('SELECT * FROM attachments WHERE id = ? AND deleted_at IS NULL', [$id]);
        if (!$a) {
            $this->notFound();
        }
        $parent = match ($a['entity_type']) {
            'risk' => Db::one('SELECT department_id, branch_id FROM risks WHERE id = ?', [$a['entity_id']]),
            'incident' => Db::one('SELECT department_id, branch_id FROM incidents WHERE id = ?', [$a['entity_id']]),
            default => null,
        };
        if (!$parent || !Scope::allows($u, $parent)) {
            $this->notFound();
        }
        $view = $a['entity_type'] === 'risk' ? 'risk.view' : 'incident.view';
        if (!\App\Core\Perm::roleHas($u['role'], $view)) {
            throw new HttpException(403);
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
        $u = $this->user();
        $a = $this->loadAuthorised($id);
        $edit = $a['entity_type'] === 'risk' ? 'risk.edit' : 'incident.edit';
        if (!\App\Core\Perm::roleHas($u['role'], $edit)) {
            throw new HttpException(403);
        }
        Db::update('attachments', ['deleted_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        $this->audit('attachment.deleted', $a['entity_type'], (int)$a['entity_id'], ['attachment_id' => $id]);
        $back = url(($a['entity_type'] === 'risk' ? 'risks/' : 'incidents/') . $a['entity_id']) . '#attachments';
        $this->redirect($back, 'upload.deleted');
    }
}
