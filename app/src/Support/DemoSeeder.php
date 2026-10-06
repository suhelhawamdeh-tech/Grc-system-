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
        self::phase2($users, $dep, $cat, $riskIds, $now, $d, $br);
        return array_keys($users);
    }

    /** Synthetic Phase 2 records (KRIs, appetite, obligations, decisions, policies, RCSA). */
    private static function phase2(array $users, array $dep, array $cat, array $riskIds, string $now, callable $d, array $br = []): void
    {
        $code = static function (string $table, string $prefix, int $id, int $pad) {
            Db::update($table, ['code' => sprintf('%s-%0' . $pad . 'd', $prefix, $id)], 'id = ?', [$id]);
        };
        // Control library: a few controls linked to demo risks, with test history (synthetic)
        $ctls = [
            ['Dual approval for payments above the limit', 'preventive', 'manual', 'FIN', 'demo.risk', 1, 'effective', 40, 0],
            ['Daily cash reconciliation at branches', 'detective', 'manual', 'RET', 'demo.dept.retail', 1, 'partially_effective', 25, 3],
            ['Privileged access review', 'detective', 'semi_automated', 'IT', 'demo.dept.it', 1, 'ineffective', 15, 6],
            ['Automated limit check on large exposures', 'preventive', 'automated', 'COR', 'demo.risk', 0, null, null, null],
            ['Sharia screening of new products', 'preventive', 'manual', 'CMP', 'demo.compliance', 1, null, null, null],
        ];
        $riskList = array_values($riskIds);
        foreach ($ctls as $i => [$nm, $ty, $na, $dp, $own, $key, $res, $sample, $exc]) {
            $tested = $res ? date('Y-m-d', strtotime('-' . (30 + $i * 20) . ' days')) : null;
            $cid = Db::insert('controls', ['name' => $nm, 'description' => 'Synthetic control for demonstration.', 'control_type' => $ty, 'nature' => $na, 'frequency' => 'monthly', 'is_key' => $key, 'department_id' => $dep[$dp] ?? array_values($dep)[0], 'owner_user_id' => $users[$own],
                'test_frequency' => 'annual', 'next_test_due' => $tested ? date('Y-m-d', strtotime($tested . ' +1 year')) : date('Y-m-d', strtotime('+15 days')), 'last_tested_on' => $tested, 'last_result' => $res, 'created_by' => $users['demo.risk'], 'created_at' => $now, 'updated_at' => $now]);
            $code('controls', 'CTL', $cid, 4);
            if ($res) {
                Db::insert('control_tests', ['control_id' => $cid, 'test_type' => 'operating', 'test_date' => $tested, 'tester_user_id' => $users['demo.compliance'], 'sample_size' => $sample, 'exceptions' => $exc, 'result' => $res, 'conclusion' => $res === 'effective' ? null : 'Synthetic finding for demonstration.', 'created_at' => $now]);
            }
            foreach (array_slice($riskList, $i * 2, 2) as $rid) {
                Db::exec('INSERT IGNORE INTO risk_controls (risk_id, control_id, created_by, created_at) VALUES (?, ?, ?, ?)', [$rid, $cid, $users['demo.risk'], $now]);
            }
        }
        // KRIs with a few months of readings
        foreach ([
            ['Failed privileged logins per month', 'IT', 'demo.dept.it', 'count', 'higher_worse', 8, 15, [4, 6, 9, 12]],
            ['Overdue loans > 30 days (% of portfolio)', 'COR', 'demo.risk', '%', 'higher_worse', 4, 7, [3, 3.5, 4.5, 6]],
            ['Liquidity coverage ratio', 'FIN', 'demo.risk', '%', 'lower_worse', 130, 110, [160, 145, 128, 135]],
            ['Unreconciled cash items at branches', 'RET', 'demo.dept.retail', 'count', 'higher_worse', 10, 25, [3, 5, 4, 2]],
        ] as [$name, $dp, $own, $unit, $dir, $amber, $red, $vals]) {
            $kid = Db::insert('kris', ['name' => $name, 'description' => 'Synthetic indicator for demonstration.', 'department_id' => $dep[$dp], 'owner_user_id' => $users[$own], 'unit' => $unit, 'direction' => $dir,
                'amber_threshold' => Money::toDb((int)($amber * 1000)), 'red_threshold' => Money::toDb((int)($red * 1000)), 'frequency' => 'monthly', 'is_active' => 1, 'created_by' => $users['demo.risk'], 'created_at' => $now, 'updated_at' => $now]);
            $code('kris', 'KRI', $kid, 4);
            foreach ($vals as $i => $val) {
                $v = (int)round($val * 1000);
                Db::insert('kri_readings', ['kri_id' => $kid, 'reading_date' => date('Y-m-d', strtotime('first day of -' . (count($vals) - 1 - $i) . ' month')), 'value' => Money::toDb($v),
                    'status' => \App\Support\Kri::status($v, (int)($amber * 1000), (int)($red * 1000), $dir), 'comment' => null, 'entered_by' => $users['demo.risk'], 'created_at' => $now]);
            }
        }
        // Appetite (limits are demonstration values, not approved)
        foreach ([['Residual risk ceiling', 'No active risk should carry a residual score above 10 without explicit committee acceptance.', 'max_residual_score', 10000, null], ['Sharia non-compliance tolerance', 'Residual score for Sharia non-compliance risk must not exceed 8.', 'max_residual_score', 8000, 'ISL-SNC'], ['Annual operational net loss cap', 'Operational net losses should stay below the synthetic cap.', 'max_net_loss', 200000000, 'OP']] as [$t, $st, $m, $lim, $cc]) {
            $sid = Db::insert('appetite_statements', ['title' => $t, 'statement' => $st . ' (synthetic, unapproved)', 'metric' => $m, 'limit_value' => Money::toDb($lim), 'category_id' => $cc ? $cat[$cc] : null, 'is_active' => 1, 'created_by' => $users['demo.risk'], 'created_at' => $now, 'updated_at' => $now]);
            $code('appetite_statements', 'RAS', $sid, 3);
        }
        // Compliance obligations
        foreach ([['Quarterly AML/CFT report to the supervisor', 'Synthetic regulatory circular reference A-01', 'CMP', 'demo.compliance', 'quarterly', -12, 'in_progress'], ['Annual review of the Sharia governance framework', 'Synthetic Sharia governance standard S-04', 'ISL', 'demo.sharia', 'annual', 25, 'not_started'],
            ['Branch cash-handling attestation', 'Synthetic internal control standard IC-11', 'RET', 'demo.dept.retail', 'monthly', 6, 'compliant'], ['Information-security policy attestation', 'Synthetic ICT risk circular T-07', 'IT', 'demo.dept.it', 'annual', -30, 'non_compliant']] as [$t, $src, $dp, $own, $freq, $off, $st]) {
            $oid = Db::insert('compliance_obligations', ['title' => $t, 'source' => $src, 'description' => 'Synthetic obligation for demonstration.', 'department_id' => $dep[$dp], 'owner_user_id' => $users[$own], 'frequency' => $freq, 'due_date' => $d($off), 'status' => $st,
                'status_note' => in_array($st, ['non_compliant', 'compliant'], true) ? 'Synthetic status note.' : null, 'last_reviewed_on' => $st === 'not_started' ? null : $d(-20), 'created_by' => $users['demo.compliance'], 'created_at' => $now, 'updated_at' => $now]);
            $code('compliance_obligations', 'OBL', $oid, 4);
        }
        // Committee decisions and follow-ups
        foreach ([['board_risk', 'Approve revised liquidity contingency plan', -40, 'in_progress', -5], ['executive_risk', 'Escalate KYC remediation programme', -25, 'not_started', 20], ['sharia_board', 'Withdraw non-conforming financing template', -60, 'completed', -30]] as [$cm, $t, $off, $ast, $aoff]) {
            $did = Db::insert('committee_decisions', ['committee' => $cm, 'meeting_date' => $d($off), 'title' => $t, 'decision_text' => 'Synthetic committee decision text for demonstration.', 'status' => $ast === 'completed' ? 'implemented' : 'open', 'created_by' => $users['demo.risk'], 'created_at' => $now, 'updated_at' => $now]);
            $code('committee_decisions', 'DEC', $did, 4);
            Db::insert('decision_actions', ['decision_id' => $did, 'title' => 'Implement: ' . $t, 'owner_user_id' => $users['demo.risk'], 'deadline' => $d($aoff), 'status' => $ast, 'completed_on' => $ast === 'completed' ? $d(-31) : null, 'created_at' => $now, 'updated_at' => $now]);
        }
        // Policies with versions
        foreach ([['Credit Risk Policy', 'policy', null, 'v2.0', -400], ['Operational Loss Data Collection Procedure', 'procedure', 'RSK', 'v1.2', -120], ['Sharia Compliance Charter', 'charter', 'ISL', 'v1.0', -700]] as [$t, $cty, $dp, $ver, $off]) {
            $pid = Db::insert('policies', ['title' => $t, 'category' => $cty, 'department_id' => $dp ? $dep[$dp] : null, 'owner_user_id' => $users['demo.compliance'], 'status' => 'approved', 'review_months' => 12, 'next_review_date' => date('Y-m-d', strtotime($d($off) . ' +12 months')), 'created_by' => $users['demo.compliance'], 'created_at' => $now, 'updated_at' => $now]);
            $code('policies', 'POL', $pid, 3);
            Db::insert('policy_versions', ['policy_id' => $pid, 'version_label' => $ver, 'effective_date' => $d($off), 'approved_by' => 'Synthetic approving body', 'approval_date' => $d($off - 5), 'change_summary' => 'Synthetic change summary.', 'is_current' => 1, 'created_by' => $users['demo.compliance'], 'created_at' => $now]);
        }
        // RCSA template + one launched assessment
        $tid = Db::insert('rcsa_templates', ['title' => 'Branch operations self-assessment', 'description' => 'Synthetic questionnaire.', 'is_active' => 1, 'created_by' => $users['demo.risk'], 'created_at' => $now, 'updated_at' => $now]);
        foreach ([['Is dual control applied to cash and vault access?', 'هل تُطبَّق الرقابة الثنائية على النقد والخزنة؟'], ['Are customer due-diligence files complete and current?', 'هل ملفات العناية الواجبة بالعملاء مكتملة ومحدّثة؟'], ['Are incidents and near misses reported within 24 hours?', 'هل يتم الإبلاغ عن الحوادث وشبه الحوادث خلال 24 ساعة؟']] as $i => [$en, $ar]) {
            Db::insert('rcsa_questions', ['template_id' => $tid, 'sort_order' => ($i + 1) * 10, 'question_en' => $en, 'question_ar' => $ar]);
        }
        $aid = Db::insert('rcsa_assessments', ['template_id' => $tid, 'department_id' => $dep['RET'], 'branch_id' => $br['TRP'] ?? null, 'assessor_user_id' => $users['demo.branch.tripoli'], 'due_date' => $d(14), 'status' => 'draft', 'created_by' => $users['demo.risk'], 'created_at' => $now, 'updated_at' => $now]);
        $code('rcsa_assessments', 'RCSA', $aid, 4);
        Db::exec('INSERT INTO rcsa_answers (assessment_id, question_id) SELECT ?, q.id FROM rcsa_questions q WHERE q.template_id = ?', [$aid, $tid]);
    }
}
