<?php
declare(strict_types=1);

namespace App\Core;

final class Audit
{
    /** Keys whose values must never be stored in the audit log. */
    private const SECRET = '/pass|pwd|token|secret|hash|csrf|api_?key/i';

    public static function clean(mixed $v): mixed
    {
        if (is_array($v)) {
            $out = [];
            foreach ($v as $k => $x) {
                $out[$k] = is_string($k) && preg_match(self::SECRET, $k) ? '[redacted]' : self::clean($x);
            }
            return $out;
        }
        if (is_string($v) && mb_strlen($v) > 500) {
            return mb_substr($v, 0, 500) . '…';
        }
        return $v;
    }

    public static function log(string $action, ?string $entityType = null, ?int $entityId = null, ?array $detail = null, ?int $userId = null, ?string $username = null): void
    {
        try {
            $u = Auth::user();
            Db::insert('audit_log', [
                'user_id' => $userId ?? ($u['id'] ?? null),
                'username' => $username ?? ($u['username'] ?? null),
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'detail' => $detail ? json_encode(self::clean($detail), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'ip' => Request::ip(),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            error_log('audit failure: ' . $e->getMessage());
        }
    }
}
