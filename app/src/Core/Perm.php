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
        // Phase 2 module permissions
        $p2read = ['kri.view', 'appetite.view', 'compliance.view', 'committee.view', 'policy.view', 'rcsa.view', 'control.view', 'sharia.view', 'bia.view', 'circular.view', 'governance.view', 'recon.view', 'strategy.view', 'ia.view', 'ia.findings', 'aml.view'];
        return [
            'admin' => ['*'],
            'risk_manager' => array_merge(self::WORK, $p2read, ['risk.archive', 'incident.archive', 'scales.manage', 'ownership.transfer', 'kri.report', 'kri.manage', 'appetite.manage', 'committee.manage', 'policy.manage', 'rcsa.manage', 'control.manage', 'control.test', 'sharia.report', 'bia.manage', 'approval.decide', 'governance.manage', 'recon.manage', 'strategy.manage', 'ia.respond']),
            'compliance_officer' => array_merge(self::WORK, $p2read, ['risk.archive', 'incident.archive', 'kri.report', 'compliance.manage', 'committee.manage', 'policy.manage', 'control.manage', 'control.test', 'sharia.report', 'sharia.manage', 'bia.manage', 'approval.decide', 'circular.manage', 'governance.manage', 'recon.manage', 'ia.respond', 'aml.manage']),
            'dept_manager' => array_merge(self::WORK, ['risk.archive', 'incident.archive', 'kri.view', 'kri.report', 'compliance.view', 'compliance.update', 'policy.view', 'rcsa.view', 'rcsa.respond', 'control.view', 'sharia.view', 'sharia.report', 'bia.view', 'bia.manage', 'circular.view', 'circular.ack', 'ia.findings', 'ia.respond']),
            'branch_liaison' => ['risk.view', 'risk.create', 'risk.edit', 'incident.view', 'incident.create', 'incident.edit', 'dashboard.view', 'export.run', 'policy.view', 'rcsa.view', 'rcsa.respond'],
            'exec_viewer' => array_merge(self::READ, $p2read),
            'auditor' => array_merge(self::READ, $p2read, ['audit.view', 'ia.manage']),
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
