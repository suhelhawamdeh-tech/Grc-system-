<?php
declare(strict_types=1);

namespace App\Core;

/** Role -> permission matrix. Enforced server-side by Router (route level) and controllers (object level). */
final class Perm
{
    public const ROLES = ['admin', 'risk_manager', 'compliance_officer', 'dept_manager', 'branch_liaison', 'exec_viewer', 'auditor'];

    private const READ = ['risk.view', 'incident.view', 'dashboard.view', 'export.run'];
    private const WORK = ['risk.view', 'risk.create', 'risk.edit', 'incident.view', 'incident.create', 'incident.edit', 'dashboard.view', 'export.run'];

    public static function matrix(): array
    {
        return [
            'admin' => ['*'],
            'risk_manager' => array_merge(self::WORK, ['risk.archive', 'incident.archive', 'scales.manage', 'ownership.transfer']),
            'compliance_officer' => array_merge(self::WORK, ['risk.archive', 'incident.archive']),
            'dept_manager' => array_merge(self::WORK, ['risk.archive', 'incident.archive']),
            'branch_liaison' => ['risk.view', 'risk.create', 'risk.edit', 'incident.view', 'incident.create', 'incident.edit', 'dashboard.view', 'export.run'],
            'exec_viewer' => self::READ,
            'auditor' => array_merge(self::READ, ['audit.view']),
        ];
    }

    public static function roleHas(string $role, string $perm): bool
    {
        $m = self::matrix()[$role] ?? [];
        return in_array('*', $m, true) || in_array($perm, $m, true);
    }

    /** Roles whose data access is limited to one department / one branch. */
    public static function scopeType(string $role): ?string
    {
        return match ($role) {
            'dept_manager' => 'department',
            'branch_liaison' => 'branch',
            default => null,
        };
    }
}
