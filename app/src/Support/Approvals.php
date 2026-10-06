<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\Db;

/** Generic maker-checker log: one pending request per record; the decider must differ from the requester. */
final class Approvals
{
    public static function pending(string $type, int $id): ?array
    {
        return Db::one("SELECT * FROM approvals WHERE entity_type = ? AND entity_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1", [$type, $id]);
    }

    public static function request(string $type, int $id, int $userId): int
    {
        if ($p = self::pending($type, $id)) {
            return (int)$p['id'];
        }
        return Db::insert('approvals', ['entity_type' => $type, 'entity_id' => $id, 'status' => 'pending', 'requested_by' => $userId, 'requested_at' => date('Y-m-d H:i:s')]);
    }

    /** @return string|null error translation key, null on success */
    public static function decide(int $approvalId, int $userId, bool $approve, string $comment): ?string
    {
        $a = Db::one('SELECT * FROM approvals WHERE id = ?', [$approvalId]);
        if (!$a || $a['status'] !== 'pending') {
            return 'appr.err_state';
        }
        if ((int)$a['requested_by'] === $userId) {
            return 'appr.err_self';
        }
        if (!$approve && trim($comment) === '') {
            return 'appr.err_comment';
        }
        Db::update('approvals', ['status' => $approve ? 'approved' : 'rejected', 'decided_by' => $userId, 'decided_at' => date('Y-m-d H:i:s'), 'comment' => trim($comment) ?: null], 'id = ?', [$approvalId]);
        return null;
    }
}
