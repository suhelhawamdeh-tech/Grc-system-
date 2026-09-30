<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\Db;
use App\Core\Money;

/**
 * Synthetic demonstration data ONLY. No real people, customers, accounts or institutions.
 * Passwords are supplied by the caller (never hard-coded); demo users must change them at first login.
 */
final class DemoSeeder
{
    public static function run(string $demoPassword, int $adminId): array
    {
        $now = date('Y-m-d H:i:s');
        $hash = password_hash($demoPassword, PASSWORD_DEFAULT);
        mt_srand(20240501); // deterministic

        $dep = [];
        foreach ([
            ['RET', 'Retail Banking', 'الخدمات المصرفية للأفراد'], ['COR', 'Corporate Banking', 'الخدمات المصرفية للشركات'], ['FIN', 'Finance', 'الشؤون المالية'],
            ['IT', 'Information Technology', 'تقنية المعلومات'], ['OPS', 'Operations', 'العمليات'], ['CMP', 'Compliance', 'الامتثال'],
            ['RSK', 'Risk Management', 'إدارة المخاطر'], ['ISL', 'Islamic Finance and Sharia Coordination', 'الصيرفة الإسلامية والتنسيق الشرعي'],
        ] as [$c, $en, $ar]) {
            $dep[$c] = Db::insert('departments', ['code' => $c, 'name_en' => $en, 'name_ar' => $ar, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        }
        $br = [];
        foreach ([['TRP', 'Tripoli Main Branch', 'فرع طرابلس الرئيسي', 'Tripoli'], ['BNG', 'Benghazi Branch', 'فرع بنغازي', 'Benghazi'], ['MSR', 'Misrata Branch', 'فرع مصراتة', 'Misrata'],
            ['SBH', 'Sabha Branch', 'فرع سبها', 'Sabha'], ['ZAW', 'Zawiya Branch', 'فرع الزاوية', 'Zawiya']] as [$c, $en, $ar, $city]) {
            $br[$c] = Db::insert('branches', ['code' => $c, 'name_en' => $en, 'name_ar' => $ar, 'city' => $city, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        }
        $y = (int)date('Y');
        foreach ([1, 2, 3, 4] as $q) {
            $sm = ($q - 1) * 3 + 1;
            Db::insert('reporting_periods', ['name_en' => "Q$q $y", 'name_ar' => "الربع $q $y", 'start_date' => sprintf('%d-%02d-01', $y, $sm), 'end_date' => date('Y-m-t', strtotime(sprintf('%d-%02d-01', $y, $sm + 2))), 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
        }
        Db::insert('reporting_periods', ['name_en' => "Full year $y", 'name_ar' => "السنة الكاملة $y", 'start_date' => "$y-01-01", 'end_date' => "$y-12-31", 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);

        $users = [];
        foreach ([
            ['demo.risk', 'Demo Risk Manager / مدير المخاطر (تجريبي)', 'risk_manager', 'RSK', null],
            ['demo.compliance', 'Demo Compliance Officer / مسؤول الامتثال (تجريبي)', 'compliance_officer', 'CMP', null],
            ['demo.dept.retail', 'Demo Retail Dept Manager / مدير إدارة الأفراد (تجريبي)', 'dept_manager', 'RET', null],
            ['demo.dept.it', 'Demo IT Dept Manager / مدير تقنية المعلومات (تجريبي)', 'dept_manager', 'IT', null],
            ['demo.branch.tripoli', 'Demo Tripoli Liaison / ضابط ارتباط طرابلس (تجريبي)', 'branch_liaison', 'RET', 'TRP'],
            ['demo.branch.benghazi', 'Demo Benghazi Liaison / ضابط ارتباط بنغازي (تجريبي)', 'branch_liaison', 'RET', 'BNG'],
            ['demo.exec', 'Demo Executive Viewer / عضو الإدارة التنفيذية (تجريبي)', 'exec_viewer', null, null],
            ['demo.auditor', 'Demo Auditor / مدقق (تجريبي)', 'auditor', null, null],
            ['demo.sharia', 'Demo Sharia Coordinator / منسق شرعي (تجريبي)', 'dept_manager', 'ISL', null],
        ] as [$un, $name, $role, $d, $b]) {
            $users[$un] = Db::insert('users', ['username' => $un, 'email' => $un . '@example.invalid', 'full_name' => $name, 'password_hash' => $hash, 'role' => $role,
                'department_id' => $d ? $dep[$d] : null, 'branch_id' => $b ? $br[$b] : null, 'is_active' => 1, 'must_change_password' => 1, 'created_at' => $now, 'updated_at' => $now]);
        }

        $cat = [];
        foreach (Db::all('SELECT id, code FROM risk_categories') as $c) {
            $cat[$c['code']] = (int)$c['id'];
        }
        $d = static fn(int $days) => date('Y-m-d', strtotime(($days >= 0 ? '+' : '') . $days . ' days'));

        // [title, desc, cat, dept, branch, owner, identL, identI, resL, resI, control, treat, rationale, estAmount]
        $risks = [
            ['Delayed loan repayment monitoring', 'Overdue instalments are not escalated within policy timelines in several branches.', 'CR', 'COR', null, 'demo.risk', 4, 4, 3, 3, 'partially_effective', 'mitigate', 'Weekly overdue report introduced but not yet enforced in all branches.', '2500000'],
            ['Concentration in a single real-estate developer', 'Large exposure to one counterparty group relative to capital.', 'CR', 'COR', null, 'demo.risk', 3, 5, 3, 4, 'partially_effective', 'mitigate', 'Limit approved; exposure not yet reduced.', '9000000'],
            ['Core banking system outage', 'Unplanned downtime of the core banking platform during business hours.', 'OP', 'IT', null, 'demo.dept.it', 3, 4, 2, 3, 'effective', 'mitigate', 'DR site tested in the last 6 months; recovery within 4 hours demonstrated.', '800000'],
            ['Unauthorised access to customer data', 'Excessive privileged access rights and weak review of user entitlements.', 'CYB', 'IT', null, 'demo.dept.it', 4, 5, 3, 4, 'partially_effective', 'mitigate', 'Quarterly access review started; backlog remains.', '4000000'],
            ['Cash handling discrepancies at branch tills', 'Recurring till differences and weak dual-control on cash vault.', 'OP', 'RET', 'TRP', 'demo.branch.tripoli', 4, 3, 2, 2, 'effective', 'mitigate', 'Dual control and daily reconciliation now enforced.', '60000'],
            ['Counterfeit notes accepted over the counter', 'Insufficient detection equipment at counters.', 'FRD', 'RET', 'BNG', 'demo.branch.benghazi', 3, 3, 3, 2, 'partially_effective', 'mitigate', 'Detectors procured for half of the counters.', '45000'],
            ['Incomplete customer due diligence files', 'Sample review found missing beneficial-owner documentation for corporate accounts.', 'AML', 'CMP', null, 'demo.compliance', 4, 5, 3, 4, 'partially_effective', 'mitigate', 'Remediation project under way; 40% of files completed.', '3000000'],
            ['Late regulatory returns submission', 'Manual compilation leads to occasional late filings to the supervisor.', 'CMP', 'FIN', null, 'demo.compliance', 3, 3, 2, 2, 'effective', 'mitigate', 'Calendar and maker-checker introduced.', '100000'],
            ['Third-party payment processor failure', 'Dependency on a single outsourced card-processing provider.', 'TPR', 'OPS', null, 'demo.risk', 2, 4, 2, 4, 'ineffective', 'transfer', 'No alternative provider contracted; SLA penalties only.', '1500000'],
            ['Negative publicity from service outages', 'Public complaints on social media following repeated ATM downtime.', 'REP', 'RET', null, 'demo.dept.retail', 3, 3, 3, 3, 'not_assessed', 'accept', 'Accepted pending customer-service review.', null],
            ['Liquidity shortfall in stressed withdrawals', 'Stress test shows thin liquid-asset buffer under a severe withdrawal scenario.', 'LQ', 'FIN', null, 'demo.risk', 2, 5, 2, 4, 'partially_effective', 'mitigate', 'Contingency funding plan drafted, not tested.', '20000000'],
            ['Foreign-currency position mismatch', 'Open FX position exceeds internal limits on several days.', 'MR', 'FIN', null, 'demo.risk', 3, 3, 2, 3, 'effective', 'mitigate', 'Daily limit monitoring with escalation implemented.', '700000'],
            ['Product contract not aligned with Sharia board resolution', 'A financing contract template deviates from a recent Sharia board resolution on ownership before sale.', 'ISL-SNC', 'ISL', null, 'demo.sharia', 3, 5, 2, 4, 'partially_effective', 'mitigate', 'Template withdrawn; existing contracts under review by the Sharia supervisory board.', '5000000'],
            ['Displaced commercial risk on investment accounts', 'Pressure to forgo part of the bank share of profit to keep investment account returns competitive.', 'ISL-DCR', 'ISL', null, 'demo.risk', 3, 4, 3, 3, 'not_assessed', 'mitigate', 'Profit equalisation reserve policy under review.', '6000000'],
            ['Equity investment valuation uncertainty', 'Unlisted equity participations valued using stale assumptions.', 'ISL-EIR', 'FIN', null, 'demo.risk', 3, 4, 3, 4, 'ineffective', 'mitigate', 'Independent valuation not yet commissioned.', '8000000'],
            ['Ijarah asset residual value decline', 'Leased vehicle fleet may be worth less than projected at lease end.', 'ISL-ACR', 'COR', null, 'demo.risk', 3, 3, 2, 3, 'partially_effective', 'mitigate', 'Residual value review each year.', '1200000'],
            ['Rate of return volatility for savers', 'Changes in market yields may cause a gap between expected and paid returns.', 'ISL-ROR', 'ISL', null, 'demo.sharia', 2, 3, 2, 3, 'effective', 'accept', 'Within appetite; monitored monthly.', null],
            ['Weak segregation of duties in payments release', 'Same officer can create and release outgoing transfers in two systems.', 'FRD', 'OPS', null, 'demo.compliance', 4, 5, 2, 5, 'ineffective', 'mitigate', 'Compensating manual review exists but design flaw persists until system change.', '6000000'],
            ['Branch premises fire safety', 'Fire drills and equipment servicing overdue at one branch.', 'OP', 'OPS', 'SBH', 'demo.risk', 2, 4, 2, 3, 'partially_effective', 'mitigate', 'Servicing scheduled.', '300000'],
            ['Legacy policy documents not updated', 'Several key policies exceed their review date.', 'LEG', 'CMP', null, 'demo.compliance', 3, 2, 2, 2, 'effective', 'mitigate', 'Policy calendar in place.', null],
        ];
        $riskIds = [];
        foreach ($risks as $i => [$title, $desc, $c, $dp, $b, $own, $il, $ii, $rl, $ri, $ctl, $tr, $why, $est]) {
            $identified = $d(-(200 - $i * 7));
            $id = Db::insert('risks', [
                'title' => $title, 'description' => $desc, 'category_id' => $cat[$c], 'causes' => 'Synthetic demonstration cause for: ' . $title, 'consequences' => 'Synthetic demonstration consequence: financial loss, regulatory or reputational effect.',
                'department_id' => $dep[$dp], 'branch_id' => $b ? $br[$b] : null, 'owner_user_id' => $users[$own], 'identified_date' => $identified, 'review_date' => $d($i % 4 === 0 ? -10 : 30 + $i * 6),
                'inh_likelihood' => $il, 'inh_impact' => $ii, 'inh_score' => $il * $ii, 'est_financial_impact' => $est, 'controls_description' => 'Synthetic description of existing controls.',
                'control_owner_id' => $users[$own], 'control_assessment' => $ctl, 'res_likelihood' => $rl, 'res_impact' => $ri, 'res_score' => $rl * $ri,
                'res_rationale' => $why, 'treatment_decision' => $tr, 'treatment_notes' => $tr === 'accept' ? 'Accepted by the risk owner within delegated authority (synthetic).' : null,
                'status' => 'active', 'created_by' => $users[$own], 'created_at' => $now, 'updated_at' => $now,
            ]);
            Db::update('risks', ['risk_code' => sprintf('RSK-%05d', $id)], 'id = ?', [$id]);
            Db::insert('risk_history', ['risk_id' => $id, 'user_id' => $users[$own], 'event' => 'created', 'changes' => null, 'created_at' => $now]);
            $riskIds[$i] = $id;
            if ($tr !== 'accept') {
                $off = ($i % 5) - 2; // some overdue, some upcoming
                $status = $i % 3 === 0 ? 'in_progress' : ($i % 7 === 0 ? 'completed' : 'not_started');
                Db::insert('risk_actions', ['risk_id' => $id, 'title' => 'Synthetic treatment action ' . ($i + 1), 'description' => 'Implement the agreed control improvement.', 'owner_user_id' => $users[$own],
                    'deadline' => $d($off * 20 + ($off < 0 ? -5 : 25)), 'status' => $status, 'completed_on' => $status === 'completed' ? $d(-3) : null, 'created_by' => $users[$own], 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        $incTitles = [
            ['ATM cash-out discrepancy', 'OP'], ['Phishing email led to credential compromise', 'CYB'], ['Duplicate customer debit', 'OP'], ['Forged cheque presented', 'FRD'],
            ['Delayed submission of regulatory report', 'CMP'], ['Payment sent to wrong beneficiary', 'OP'], ['Card processor downtime', 'TPR'], ['Till shortage identified at close of day', 'OP'],
            ['Unauthorised system access attempt', 'CYB'], ['Contract executed with outdated template', 'ISL-SNC'], ['Near miss: transfer stopped by maker-checker', 'OP'],
            ['Near miss: suspicious document detected', 'FRD'], ['Near miss: wrong limit loaded in FX system', 'MR'], ['Customer complaint escalated publicly', 'REP'], ['Fire alarm malfunction during drill', 'OP'],
        ];
        foreach ($incTitles as $i => [$title, $c]) {
            $near = str_starts_with($title, 'Near miss');
            $gross = $near ? 0 : (mt_rand(1, 400) * 250 + 500) * 1000; // thousandths
            $rec = $near ? 0 : (int)floor($gross * mt_rand(0, 60) / 100 / 1000) * 1000;
            $dp = array_values($dep)[$i % count($dep)];
            $brn = $i % 3 === 2 ? null : array_values($br)[$i % count($br)];
            $occ = $d(-mt_rand(3, 150));
            $id = Db::insert('incidents', [
                'event_type' => $near ? 'near_miss' : 'incident', 'title' => $title, 'description' => 'Synthetic demonstration incident record.',
                'occurrence_date' => $occ, 'discovery_date' => date('Y-m-d', strtotime($occ . ' +' . mt_rand(0, 4) . ' days')), 'department_id' => $dp, 'branch_id' => $brn, 'category_id' => $cat[$c],
                'gross_loss' => Money::toDb($gross), 'recoveries' => Money::toDb($rec), 'net_loss' => Money::toDb($gross - $rec),
                'root_cause' => $i % 4 === 0 ? null : 'Synthetic root cause: control gap or human error.', 'corrective_actions' => 'Synthetic corrective action.', 'status' => ['open', 'investigating', 'closed'][$i % 3] === 'closed' && $i % 4 === 0 ? 'investigating' : ['open', 'investigating', 'closed'][$i % 3],
                'reported_by' => $users['demo.risk'], 'created_at' => $now, 'updated_at' => $now,
            ]);
            Db::update('incidents', ['incident_code' => sprintf('INC-%05d', $id)], 'id = ?', [$id]);
            foreach ($risks as $ri => $rk) {
                if ($rk[2] === $c && $ri % 2 === 0) {
                    Db::exec('INSERT IGNORE INTO incident_risks (incident_id, risk_id) VALUES (?, ?)', [$id, $riskIds[$ri]]);
                    break;
                }
            }
        }
        return array_keys($users);
    }
}
