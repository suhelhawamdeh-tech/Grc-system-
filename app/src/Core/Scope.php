<?php
declare(strict_types=1);

namespace App\Core;

/** Record-level (department / branch) access restrictions. Fails closed. */
final class Scope
{
    /** @return array{0:string,1:array} SQL predicate + params for a table alias with department_id, branch_id */
    public static function sql(array $user, string $alias): array
    {
        return match (Perm::scopeType($user['role'])) {
            'department' => ["$alias.department_id = ?", [(int)($user['department_id'] ?? 0)]],
            'branch' => ["$alias.branch_id = ?", [(int)($user['branch_id'] ?? 0)]],
            default => ['1=1', []],
        };
    }

    /** For tables that have only department_id (no branch): branch-scoped users see nothing. */
    public static function sqlDept(array $user, string $alias): array
    {
        return match (Perm::scopeType($user['role'])) {
            'department' => ["$alias.department_id = ?", [(int)($user['department_id'] ?? 0)]],
            'branch' => ['1 = 0', []],
            default => ['1=1', []],
        };
    }

    public static function allowsDept(array $user, array $row): bool
    {
        return match (Perm::scopeType($user['role'])) {
            'department' => (int)($user['department_id'] ?? 0) !== 0 && (int)($row['department_id'] ?? -1) === (int)$user['department_id'],
            'branch' => false,
            default => true,
        };
    }

    public static function allows(array $user, array $row): bool
    {
        return match (Perm::scopeType($user['role'])) {
            'department' => (int)($user['department_id'] ?? 0) !== 0 && (int)($row['department_id'] ?? -1) === (int)$user['department_id'],
            'branch' => (int)($user['branch_id'] ?? 0) !== 0 && isset($row['branch_id']) && (int)$row['branch_id'] === (int)$user['branch_id'],
            default => true,
        };
    }

    public static function restricted(array $user): bool
    {
        return Perm::scopeType($user['role']) !== null;
    }
}
