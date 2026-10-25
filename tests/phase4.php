<?php
// Release 4 tests: internal audit, anti-money laundering, T24 integration. Included by tests/run.php after phase3.php
// (shares $B, $pdo, $admin, $c[], $DEMO_PW, $ADMIN_PW, $work, $root).
use App\Support\AmlScoring;
use App\Support\AuditPlanner;
use App\Support\T24;

$q = fn(string $sql) => $pdo->query($sql)->fetchColumn();
$uid = fn(string $u) => (int)$q("SELECT id FROM users WHERE username = '$u'");
$today = date('Y-m-d');
$y = (int)date('Y');

section('Release 4 unit: audit priority and AML scoring');
check('audit risk factor maps residual 1-25 to 1-5 and unknown to 3', AuditPlanner::riskFactor(25) === 5 && AuditPlanner::riskFactor(1) === 1 && AuditPlanner::riskFactor(11) === 3 && AuditPlanner::riskFactor(null) === 3);
check('audit time factor: never 5, 3y 5, 2y 4, 1y 3, recent 1', AuditPlanner::timeFactor(null, '2026-01-01') === 5 && AuditPlanner::timeFactor('2022-06-01', '2026-01-01') === 5 && AuditPlanner::timeFactor('2023-06-01', '2026-01-01') === 4 && AuditPlanner::timeFactor('2024-06-01', '2026-01-01') === 3 && AuditPlanner::timeFactor('2025-06-01', '2026-01-01') === 1);
check('audit score is the weighted average (all 5 = 5, all 1 = 1)', AuditPlanner::score(array_fill_keys(array_keys(AuditPlanner::WEIGHTS), 5)) === 5.0 && AuditPlanner::score(array_fill_keys(array_keys(AuditPlanner::WEIGHTS), 1)) === 1.0);
check('audit score weights (risk 5, others 1 = 2.2)', AuditPlanner::score(['risk' => 5, 'materiality' => 1, 'regulatory' => 1, 'complexity' => 1, 'time' => 1, 'rating' => 1]) === 2.2);
check('audit priority bands 3.5 / 2.5', AuditPlanner::priority(3.5) === 'high' && AuditPlanner::priority(3.49) === 'medium' && AuditPlanner::priority(2.5) === 'medium' && AuditPlanner::priority(2.49) === 'low');
check('audit cycle: high yearly, medium two-yearly, never audited always due', AuditPlanner::due('high', '2025-03-01', 2026) && !AuditPlanner::due('medium', '2025-03-01', 2026) && AuditPlanner::due('medium', '2024-03-01', 2026) && AuditPlanner::due('low', null, 2026));
check('AML score weights 35/25/15/25 (all low = 1, all high = 3)', AmlScoring::score(['customer' => 1, 'product' => 1, 'channel' => 1, 'geography' => 1]) === 1.0 && AmlScoring::score(['customer' => 3, 'product' => 3, 'channel' => 3, 'geography' => 3]) === 3.0
    && AmlScoring::score(['customer' => 2, 'product' => 2, 'channel' => 2, 'geography' => 1]) === 1.75);
check('AML bands: 2.4 high, 1.7 medium', AmlScoring::rating(['customer' => 3, 'product' => 3, 'channel' => 1, 'geography' => 1], false, false) === 'medium' && AmlScoring::rating(['customer' => 3, 'product' => 3, 'channel' => 3, 'geography' => 2], false, false) === 'high'
    && AmlScoring::rating(['customer' => 1, 'product' => 2, 'channel' => 2, 'geography' => 1], false, false) === 'low');
check('AML rule: PEP or sanctions match is always high', AmlScoring::rating(['customer' => 1, 'product' => 1, 'channel' => 1, 'geography' => 1], true, false) === 'high' && AmlScoring::rating(['customer' => 1, 'product' => 1, 'channel' => 1, 'geography' => 1], false, true) === 'high');
check('AML rule: high-risk country or customer type never below medium', AmlScoring::rating(['customer' => 1, 'product' => 1, 'channel' => 1, 'geography' => 3], false, false) === 'medium' && AmlScoring::rating(['customer' => 3, 'product' => 1, 'channel' => 1, 'geography' => 1], false, false) === 'medium');
check('AML review cycle 12 / 24 / 36 months', AmlScoring::nextReview('high', '2026-01-31') === date('Y-m-d', strtotime('2026-01-31 +12 months')) && AmlScoring::nextReview('low', '2026-03-01') === '2029-03-01');
check('T24 field paths: nested lists and multi-values joined with |', T24::field(['a' => [['c' => '1'], ['c' => '2']]], 'a/*/c') === '1|2' && T24::field(['x' => ['A', 'B']], 'x') === 'A|B' && T24::field(['NAME.1' => ' X '], 'NAME.1') === 'X' && T24::field([], 'missing') === '');
check('T24 JSON records from IRIS body, data or plain list', count(T24::recordsFromJson('{"header":{},"body":[{"a":1},{"a":2}]}')) === 2 && count(T24::recordsFromJson('[{"a":1}]')) === 1 && T24::recordsFromJson('{"body":{"a":1}}') === null && T24::recordsFromJson('nope') === null);
check('T24 file parser detects pipe, semicolon and tab separators', T24::parseFile("A|B\n1|2\n")['rows'][0]['B'] === '2' && T24::parseFile("A;B\n1;2\n")['rows'][0]['B'] === '2' && T24::parseFile("A\tB\n1\t2\n")['rows'][0]['B'] === '2');

section('Release 4: access to internal audit, AML and integration pages');
$m4 = [
    '/ia' => ['auditor' => 200, 'risk' => 200, 'compliance' => 200, 'exec' => 200, 'retail' => 403, 'tripoli' => 403],
    '/ia/universe/new' => ['auditor' => 200, 'risk' => 403, 'exec' => 403],
    '/ia/findings' => ['auditor' => 200, 'retail' => 200, 'it' => 200, 'exec' => 200, 'tripoli' => 403],
    '/aml' => ['compliance' => 200, 'risk' => 200, 'exec' => 200, 'auditor' => 200, 'retail' => 403, 'tripoli' => 403],
    '/aml/customers/new' => ['compliance' => 200, 'risk' => 403, 'auditor' => 403, 'exec' => 403],
    '/aml/factors' => ['compliance' => 200, 'exec' => 200, 'retail' => 403],
    '/admin/integrations' => ['risk' => 403, 'compliance' => 403, 'auditor' => 403, 'exec' => 403],
];
foreach ($m4 as $p => $exp) foreach ($exp as $role => $code) { $r = $c[$role]->get($p); check("$role GET $p = $code", $r['code'] === $code, (string)$r['code']); }
check('admin opens integrations page', $admin->get('/admin/integrations')['code'] === 200);
foreach (['/ia', '/ia/universe', '/ia/plans', '/ia/findings', '/aml', '/aml/customers', '/aml/factors', '/aml/reports'] as $p) {
    $b = $c['auditor']->get($p)['body']; check("auditor page $p renders without PHP notices", !preg_match('/(Warning|Notice|Deprecated|Fatal error):/', $b) && str_contains($b, 'pill-tabs'));
}
$nav = $c['retail']->get('/')['body'];
check('department manager sees the audit findings link but not the plan or AML', str_contains($nav, 'href="/ia/findings"') && !str_contains($nav, 'href="/ia"') && !str_contains($nav, 'href="/aml"'));
check('admin menu shows the T24 integration link', str_contains($admin->get('/')['body'], 'href="/admin/integrations"'));

section('Internal audit: universe and risk-based plan');
\App\Core\Db::init(['host' => $dbh, 'name' => $db1, 'user' => $dbu, 'pass' => $dbp]); // the planner and AML summary read the test database directly
$uc = fn() => (int)$q('SELECT COUNT(*) FROM audit_universe');
$n = $uc();
$uF = fn(array $o = []) => $o + ['name' => 'Payments operations', 'department_id' => (string)$q("SELECT id FROM departments WHERE code='OPS'"), 'materiality' => '5', 'regulatory' => '4', 'complexity' => '4', 'last_audit_date' => '', 'last_rating' => '', 'is_active' => '1', 'description' => ''];
$c['auditor']->post('/ia/universe', $uF(['name' => '']), '/ia/universe/new'); check('universe: name required', $uc() === $n);
$c['auditor']->post('/ia/universe', $uF(['materiality' => '6']), '/ia/universe/new'); check('universe: factor above 5 rejected', $uc() === $n);
$c['auditor']->post('/ia/universe', $uF(['last_audit_date' => date('Y-m-d', strtotime('+3 days'))]), '/ia/universe/new'); check('universe: future last audit rejected', $uc() === $n);
$c['auditor']->post('/ia/universe', $uF(['last_rating' => 'excellent']), '/ia/universe/new'); check('universe: unknown rating rejected', $uc() === $n);
check('universe: risk manager cannot add (403)', $c['risk']->post('/ia/universe', $uF(), '/ia')['code'] === 403 && $uc() === $n);
$c['auditor']->post('/ia/universe', $uF(), '/ia/universe/new'); $au = (int)$q('SELECT MAX(id) FROM audit_universe');
check('universe: entity created with AU code', $uc() === $n + 1 && preg_match('/^AU-\d{3}$/', (string)$q("SELECT code FROM audit_universe WHERE id=$au")) === 1);
$ub = $c['auditor']->get('/ia/universe')['body'];
check('universe page ranks by score and explains the factors', str_contains($ub, 'Payments operations') && str_contains($ub, 'class="meter') && substr_count($ub, '<details') >= 1);
$ranked = AuditPlanner::ranked($y + 1);
check('ranking is sorted by score, highest first', count($ranked) >= 7 && $ranked[0]['score'] >= end($ranked)['score']);
$newEnt = array_values(array_filter($ranked, fn($r) => (int)$r['id'] === $au))[0];
check('never-audited entity is due with time factor 5', $newEnt['due'] === true && $newEnt['factors']['time'] === 5);

$pc = fn() => (int)$q('SELECT COUNT(*) FROM audit_plans');
$np = $pc();
$c['auditor']->post('/ia/plans', ['plan_year' => (string)$y, 'title' => 'dup'], '/ia/plans'); check('plan: second plan for the same year rejected', $pc() === $np);
$c['auditor']->post('/ia/plans', ['plan_year' => '1999', 'title' => 'old'], '/ia/plans'); check('plan: year out of range rejected', $pc() === $np);
$ny = $y + 1;
$c['auditor']->post('/ia/plans', ['plan_year' => (string)$ny, 'title' => "Plan $ny"], '/ia/plans'); $plan = (int)$q("SELECT id FROM audit_plans WHERE plan_year=$ny");
check('plan: next-year plan created as draft', $plan > 0 && $q("SELECT status FROM audit_plans WHERE id=$plan") === 'draft');
$c['auditor']->post("/ia/plans/$plan/approve", ['approval_body' => 'Audit committee', 'approval_ref' => 'X', 'approved_on' => $today], "/ia/plans/$plan"); check('plan: empty plan cannot be approved', $q("SELECT status FROM audit_plans WHERE id=$plan") === 'draft');
$ec = fn() => (int)$q('SELECT COUNT(*) FROM audit_engagements');
$ne = $ec();
$eF = fn(array $o = []) => $o + ['universe_id' => (string)$au, 'title' => 'Payments audit', 'objective' => 'Check payment controls', 'scope' => '', 'lead_user_id' => (string)$uid('demo.auditor'), 'planned_start' => "$ny-03-01", 'planned_end' => "$ny-03-31"];
$c['auditor']->post("/ia/plans/$plan/engagements", $eF(['planned_start' => "$y-12-01"]), "/ia/plans/$plan"); check('engagement: dates outside the plan year rejected', $ec() === $ne);
$c['auditor']->post("/ia/plans/$plan/engagements", $eF(['lead_user_id' => (string)$uid('demo.risk')]), "/ia/plans/$plan"); check('engagement: non-auditor lead rejected', $ec() === $ne);
$c['auditor']->post("/ia/plans/$plan/engagements", $eF(['planned_end' => "$ny-02-01"]), "/ia/plans/$plan"); check('engagement: end before start rejected', $ec() === $ne);
$c['auditor']->post("/ia/plans/$plan/engagements", $eF(), "/ia/plans/$plan"); $eng = (int)$q('SELECT MAX(id) FROM audit_engagements');
check('engagement: added with IA-year-01 code and priority snapshot', $ec() === $ne + 1 && $q("SELECT code FROM audit_engagements WHERE id=$eng") === "IA-$ny-01" && (float)$q("SELECT priority_score FROM audit_engagements WHERE id=$eng") === (float)$newEnt['score']);
$c['auditor']->post("/ia/plans/$plan/engagements", $eF(), "/ia/plans/$plan"); check('engagement: same entity twice in a plan rejected', $ec() === $ne + 1);
$c['auditor']->post("/ia/engagements/$eng/start", [], "/ia/engagements/$eng"); check('engagement: cannot start before plan approval', $q("SELECT status FROM audit_engagements WHERE id=$eng") === 'planned');
$c['auditor']->post("/ia/plans/$plan/approve", ['approval_body' => 'Board audit committee', 'approval_ref' => 'AC/1', 'approved_on' => date('Y-m-d', strtotime('+1 day'))], "/ia/plans/$plan"); check('plan: future approval date rejected', $q("SELECT status FROM audit_plans WHERE id=$plan") === 'draft');
$c['auditor']->post("/ia/plans/$plan/approve", ['approval_body' => 'Board audit committee', 'approval_ref' => 'AC/1', 'approved_on' => $today], "/ia/plans/$plan");
check('plan: approval recorded with body, reference and recorder', $q("SELECT status FROM audit_plans WHERE id=$plan") === 'approved' && $q("SELECT approval_ref FROM audit_plans WHERE id=$plan") === 'AC/1' && (int)$q("SELECT recorded_by FROM audit_plans WHERE id=$plan") === $uid('demo.auditor'));
$c['auditor']->post("/ia/plans/$plan/engagements", $eF(['universe_id' => (string)$q('SELECT MIN(id) FROM audit_universe')]), "/ia/plans/$plan"); check('plan: approved plan is locked', $ec() === $ne + 1);
check('plan page shows coverage of high-priority entities', str_contains($c['exec']->get("/ia/plans/$plan")['body'], 'IA-' . $ny . '-01'));

section('Internal audit: fieldwork, workpapers and independent review');
$c['auditor']->post("/ia/engagements/$eng/start", [], "/ia/engagements/$eng");
check('engagement: fieldwork started with actual start date', $q("SELECT status FROM audit_engagements WHERE id=$eng") === 'fieldwork' && $q("SELECT actual_start FROM audit_engagements WHERE id=$eng") === $today);
$c['auditor']->post("/ia/engagements/$eng/reporting", [], "/ia/engagements/$eng"); check('engagement: reporting refused without workpapers', $q("SELECT status FROM audit_engagements WHERE id=$eng") === 'fieldwork');
$ctlId = (int)$q('SELECT MIN(id) FROM controls');
$c['auditor']->post("/ia/engagements/$eng/workpapers", ['title' => '', 'procedure_text' => 'x'], "/ia/engagements/$eng"); check('workpaper: title required', !(int)$q("SELECT COUNT(*) FROM audit_workpapers WHERE engagement_id=$eng"));
$c['auditor']->post("/ia/engagements/$eng/workpapers", ['title' => 'Dual approval test', 'procedure_text' => 'Select 20 payments', 'control_id' => (string)$ctlId], "/ia/engagements/$eng"); $wp = (int)$q('SELECT MAX(id) FROM audit_workpapers');
check('workpaper: created as WP-01 linked to the control', $q("SELECT ref FROM audit_workpapers WHERE id=$wp") === 'WP-01' && (int)$q("SELECT control_id FROM audit_workpapers WHERE id=$wp") === $ctlId);
$c['auditor']->post("/ia/workpapers/$wp", ['sample_size' => '20', 'exceptions' => '21', 'conclusion' => 'ineffective', 'result_text' => 'r'], "/ia/workpapers/$wp"); check('workpaper: exceptions above sample rejected', $q("SELECT status FROM audit_workpapers WHERE id=$wp") === 'open');
$c['auditor']->post("/ia/workpapers/$wp", ['sample_size' => '20', 'exceptions' => '2', 'conclusion' => 'effective', 'result_text' => 'r'], "/ia/workpapers/$wp"); check('workpaper: "effective" with exceptions rejected', $q("SELECT status FROM audit_workpapers WHERE id=$wp") === 'open');
$c['auditor']->post("/ia/workpapers/$wp", ['sample_size' => '20', 'exceptions' => '3', 'conclusion' => 'partially_effective', 'result_text' => '3 payments approved by one person'], "/ia/workpapers/$wp");
check('workpaper: prepared by the auditor', $q("SELECT status FROM audit_workpapers WHERE id=$wp") === 'prepared' && (int)$q("SELECT prepared_by FROM audit_workpapers WHERE id=$wp") === $uid('demo.auditor'));
$c['auditor']->post("/ia/workpapers/$wp/review", ['decision' => 'approve', 'review_note' => ''], "/ia/workpapers/$wp"); check('workpaper: preparer cannot review own work (maker-checker)', $q("SELECT status FROM audit_workpapers WHERE id=$wp") === 'prepared');
check('auditor My tasks does not list own workpaper for review', !str_contains($c['auditor']->get('/my')['body'], 'Dual approval test'));
check('admin My tasks lists the workpaper to review', str_contains($admin->get('/my')['body'], 'Dual approval test'));
$admin->post("/ia/workpapers/$wp/review", ['decision' => 'return', 'review_note' => ''], "/ia/workpapers/$wp"); check('workpaper: returning needs a note', $q("SELECT status FROM audit_workpapers WHERE id=$wp") === 'prepared');
$admin->post("/ia/workpapers/$wp/review", ['decision' => 'return', 'review_note' => 'Attach the sample list'], "/ia/workpapers/$wp"); check('workpaper: returned to preparer', $q("SELECT status FROM audit_workpapers WHERE id=$wp") === 'open');
$c['auditor']->post("/ia/workpapers/$wp", ['sample_size' => '20', 'exceptions' => '3', 'conclusion' => 'partially_effective', 'result_text' => 'Sample list attached'], "/ia/workpapers/$wp");
$admin->post("/ia/workpapers/$wp/review", ['decision' => 'approve', 'review_note' => ''], "/ia/workpapers/$wp");
check('workpaper: approved by a second reviewer', $q("SELECT status FROM audit_workpapers WHERE id=$wp") === 'reviewed' && (int)$q("SELECT reviewed_by FROM audit_workpapers WHERE id=$wp") === (int)$q("SELECT id FROM users WHERE username='admin'"));
$r = $c['auditor']->post("/ia/workpapers/$wp/attachments", [], "/ia/workpapers/$wp", ['file' => ['sample.pdf', "%PDF-1.4\n%synthetic\n", 'application/pdf']]);
check('workpaper: evidence uploaded', (int)$q("SELECT COUNT(*) FROM attachments WHERE entity_type='audit_workpaper' AND entity_id=$wp") === 1);
$att = (int)$q("SELECT id FROM attachments WHERE entity_type='audit_workpaper' AND entity_id=$wp");
check('workpaper evidence: exec viewer may download, department manager may not', $c['exec']->get("/attachments/$att")['code'] === 200 && $c['retail']->get("/attachments/$att")['code'] === 404);

section('Internal audit: findings to closure');
$retId = (int)$q("SELECT id FROM departments WHERE code='RET'");
$fc = fn() => (int)$q('SELECT COUNT(*) FROM audit_findings');
$nf = $fc();
$fF = fn(array $o = []) => $o + ['title' => 'Single approval on payments', 'condition_text' => '3 of 20 payments approved by one person', 'criteria_text' => 'Payments policy', 'cause_text' => 'System allows it', 'effect_text' => 'Fraud risk', 'recommendation' => 'Enforce dual approval in the system',
    'severity' => 'high', 'department_id' => (string)$retId, 'owner_user_id' => (string)$uid('demo.dept.retail'), 'risk_id' => '', 'workpaper_id' => (string)$wp];
$c['auditor']->post("/ia/engagements/$eng/findings", $fF(['owner_user_id' => (string)$uid('demo.auditor')]), "/ia/engagements/$eng"); check('finding: an auditor cannot own a finding', $fc() === $nf);
$c['auditor']->post("/ia/engagements/$eng/findings", $fF(['recommendation' => '']), "/ia/engagements/$eng"); check('finding: recommendation required', $fc() === $nf);
$c['auditor']->post("/ia/engagements/$eng/findings", $fF(['severity' => 'critical']), "/ia/engagements/$eng"); check('finding: unknown severity rejected', $fc() === $nf);
$c['auditor']->post("/ia/engagements/$eng/findings", $fF(), "/ia/engagements/$eng"); $fid = (int)$q('SELECT MAX(id) FROM audit_findings');
check('finding: created as draft with AF code', $fc() === $nf + 1 && $q("SELECT status FROM audit_findings WHERE id=$fid") === 'draft' && preg_match('/^AF-\d{4}$/', (string)$q("SELECT code FROM audit_findings WHERE id=$fid")) === 1);
check('draft finding hidden from its owner and from viewers', $c['retail']->get("/ia/findings/$fid")['code'] === 404 && $c['exec']->get("/ia/findings/$fid")['code'] === 404 && !str_contains($c['retail']->get('/ia/findings')['body'], 'Single approval on payments'));
$c['auditor']->post("/ia/engagements/$eng/reporting", [], "/ia/engagements/$eng"); check('engagement: moved to reporting once every workpaper is reviewed', $q("SELECT status FROM audit_engagements WHERE id=$eng") === 'reporting');
$c['auditor']->post("/ia/engagements/$eng/issue", ['rating' => '', 'report_summary' => 'x'], "/ia/engagements/$eng"); check('issue: rating required', $q("SELECT status FROM audit_engagements WHERE id=$eng") === 'reporting');
$c['auditor']->post("/ia/engagements/$eng/issue", ['rating' => 'needs_improvement', 'report_summary' => 'Payments controls need improvement.'], "/ia/engagements/$eng");
check('issue: report issued, finding sent to management with 14 days to respond', $q("SELECT status FROM audit_engagements WHERE id=$eng") === 'issued' && $q("SELECT status FROM audit_findings WHERE id=$fid") === 'open' && $q("SELECT response_due FROM audit_findings WHERE id=$fid") === date('Y-m-d', strtotime('+14 days')));
check('issue: universe remembers the audit date and rating', $q("SELECT last_audit_date FROM audit_universe WHERE id=$au") === $today && $q("SELECT last_rating FROM audit_universe WHERE id=$au") === 'needs_improvement');
check('issued finding cannot be edited', str_contains((string)$c['auditor']->post("/ia/findings/$fid", $fF(['title' => 'changed']), "/ia/findings/$fid")['loc'], "/ia/findings/$fid") && $q("SELECT title FROM audit_findings WHERE id=$fid") === 'Single approval on payments');
check('owner sees the finding in My tasks', str_contains($c['retail']->get('/my')['body'], 'Single approval on payments'));
check('another department cannot open the finding', $c['it']->get("/ia/findings/$fid")['code'] === 404);
$rF = fn(array $o = []) => $o + ['management_response' => 'Agreed', 'agreed_action' => 'Configure dual approval', 'due_date' => date('Y-m-d', strtotime('+10 days'))];
$c['compliance']->post("/ia/findings/$fid/respond", $rF(), "/ia/findings/$fid"); check('only the owner can respond', $q("SELECT status FROM audit_findings WHERE id=$fid") === 'open');
$c['retail']->post("/ia/findings/$fid/respond", $rF(['due_date' => date('Y-m-d', strtotime('-1 day'))]), "/ia/findings/$fid"); check('response: past target date rejected', $q("SELECT status FROM audit_findings WHERE id=$fid") === 'open');
$c['retail']->post("/ia/findings/$fid/respond", $rF(['due_date' => date('Y-m-d', strtotime('+400 days'))]), "/ia/findings/$fid"); check('response: target more than a year away rejected', $q("SELECT status FROM audit_findings WHERE id=$fid") === 'open');
$c['retail']->post("/ia/findings/$fid/respond", $rF(['agreed_action' => '']), "/ia/findings/$fid"); check('response: agreed action required', $q("SELECT status FROM audit_findings WHERE id=$fid") === 'open');
$c['retail']->post("/ia/findings/$fid/respond", $rF(), "/ia/findings/$fid");
$due10 = date('Y-m-d', strtotime('+10 days'));
check('response: recorded with target date and original date', $q("SELECT status FROM audit_findings WHERE id=$fid") === 'responded' && $q("SELECT due_date FROM audit_findings WHERE id=$fid") === $due10 && $q("SELECT original_due_date FROM audit_findings WHERE id=$fid") === $due10);
$c['retail']->post("/ia/findings/$fid/attachments", [], "/ia/findings/$fid", ['file' => ['config.pdf', "%PDF-1.4\n%synthetic\n", 'application/pdf']]);
check('owner can attach evidence to the finding', (int)$q("SELECT COUNT(*) FROM attachments WHERE entity_type='audit_finding' AND entity_id=$fid") === 1);
$c['it']->post("/ia/findings/$fid/attachments", [], "/ia/findings/$fid", ['file' => ['x.pdf', "%PDF-1.4\n", 'application/pdf']]); check('other department cannot attach evidence', (int)$q("SELECT COUNT(*) FROM attachments WHERE entity_type='audit_finding' AND entity_id=$fid") === 1);
$c['auditor']->post("/ia/findings/$fid/extend", ['new_due_date' => date('Y-m-d', strtotime('+5 days')), 'reason' => 'x'], "/ia/findings/$fid"); check('extension: earlier date rejected', (int)$q("SELECT extension_count FROM audit_findings WHERE id=$fid") === 0);
$c['auditor']->post("/ia/findings/$fid/extend", ['new_due_date' => date('Y-m-d', strtotime('+20 days')), 'reason' => 'Vendor patch delayed'], "/ia/findings/$fid");
check('extension: counted, original date kept, logged', (int)$q("SELECT extension_count FROM audit_findings WHERE id=$fid") === 1 && $q("SELECT original_due_date FROM audit_findings WHERE id=$fid") === $due10 && (int)$q("SELECT COUNT(*) FROM audit_log WHERE action='ia.finding_extended' AND entity_id=$fid") === 1);
$c['retail']->post("/ia/findings/$fid/implemented", ['implemented_note' => ''], "/ia/findings/$fid"); check('implemented: note required', $q("SELECT status FROM audit_findings WHERE id=$fid") === 'responded');
$c['retail']->post("/ia/findings/$fid/implemented", ['implemented_note' => 'Dual approval enforced in the payment system'], "/ia/findings/$fid"); check('implemented: reported by owner', $q("SELECT status FROM audit_findings WHERE id=$fid") === 'implemented');
check('auditor My tasks lists the finding to verify', str_contains($c['auditor']->get('/my')['body'], 'Single approval on payments'));
$c['retail']->post("/ia/findings/$fid/verify", ['decision' => 'close', 'verification_note' => 'self'], "/ia/findings/$fid"); check('management cannot close its own finding (403)', $q("SELECT status FROM audit_findings WHERE id=$fid") === 'implemented');
$c['auditor']->post("/ia/findings/$fid/verify", ['decision' => 'return', 'verification_note' => 'Evidence covers one branch only'], "/ia/findings/$fid"); check('verification: returned to management', $q("SELECT status FROM audit_findings WHERE id=$fid") === 'responded');
$c['retail']->post("/ia/findings/$fid/implemented", ['implemented_note' => 'All branches configured'], "/ia/findings/$fid");
$c['auditor']->post("/ia/findings/$fid/verify", ['decision' => 'close', 'verification_note' => ''], "/ia/findings/$fid"); check('verification: note required', $q("SELECT status FROM audit_findings WHERE id=$fid") === 'implemented');
$c['auditor']->post("/ia/findings/$fid/verify", ['decision' => 'close', 'verification_note' => 'Checked in production'], "/ia/findings/$fid");
check('verification: finding closed with verifier and date', $q("SELECT status FROM audit_findings WHERE id=$fid") === 'closed' && (int)$q("SELECT verified_by FROM audit_findings WHERE id=$fid") === $uid('demo.auditor') && $q("SELECT closed_at FROM audit_findings WHERE id=$fid") !== null);
$ov = $c['auditor']->get('/ia/findings?overdue=1')['body'];
check('overdue filter lists the demo finding past its response date', str_contains($ov, 'Dual control at vault opening not evidenced'));
$dash = $c['auditor']->get('/')['body'];
check('dashboard shows audit tiles for the auditor', str_contains($dash, 'ia/findings?overdue=1'));
check('IA overview renders statistics', str_contains($c['exec']->get('/ia')['body'], 'class="kpis"'));

section('AML: customer risk rating');
$fac = array_column($pdo->query('SELECT id, code FROM aml_factors')->fetchAll(), 'id', 'code');
$cc = fn() => (int)$q('SELECT COUNT(*) FROM aml_customers');
$n = $cc();
$cF = fn(array $o = []) => $o + ['customer_ref' => 'T-5001', 'full_name' => 'Test Exchange House', 'customer_type_id' => (string)$fac['CUS-CASH'], 'geography_id' => (string)$fac['GEO-NEIGH'], 'products' => [(string)$fac['PRD-FX']], 'channels' => [(string)$fac['CHN-AGENT']], 'onboarded_on' => '2024-01-10', 'branch_id' => ''];
$c['compliance']->post('/aml/customers', $cF(['products' => []]), '/aml/customers/new'); check('customer: at least one product required', $cc() === $n);
$c['compliance']->post('/aml/customers', $cF(['channels' => []]), '/aml/customers/new'); check('customer: at least one channel required', $cc() === $n);
$c['compliance']->post('/aml/customers', $cF(['customer_ref' => '900101']), '/aml/customers/new'); check('customer: duplicate customer number rejected', $cc() === $n);
$c['compliance']->post('/aml/customers', $cF(['geography_id' => (string)$fac['PRD-CUR']]), '/aml/customers/new'); check('customer: factor from the wrong dimension rejected', $cc() === $n);
$c['compliance']->post('/aml/customers', $cF(['onboarded_on' => date('Y-m-d', strtotime('+2 days'))]), '/aml/customers/new'); check('customer: future onboarding date rejected', $cc() === $n);
check('customer: risk manager cannot create (403)', $c['risk']->post('/aml/customers', $cF(), '/aml')['code'] === 403);
$c['compliance']->post('/aml/customers', $cF(), '/aml/customers/new'); $cu = (int)$q("SELECT id FROM aml_customers WHERE customer_ref='T-5001'");
check('customer: cash business, FX, agent, neighbouring country = 2.75 high, enhanced CDD, review in 12 months', (float)$q("SELECT score FROM aml_customers WHERE id=$cu") === 2.75 && $q("SELECT rating FROM aml_customers WHERE id=$cu") === 'high'
    && $q("SELECT cdd_level FROM aml_customers WHERE id=$cu") === 'enhanced' && $q("SELECT next_review_due FROM aml_customers WHERE id=$cu") === date('Y-m-d', strtotime('+12 months')));
$c['compliance']->post('/aml/customers', $cF(['customer_ref' => 'T-5002', 'full_name' => 'Test Resident', 'customer_type_id' => (string)$fac['CUS-IND-RES'], 'geography_id' => (string)$fac['GEO-HIGH'], 'products' => [(string)$fac['PRD-CUR']], 'channels' => [(string)$fac['CHN-BRANCH']]]), '/aml/customers/new');
check('customer: high-risk country lifts a 1.5 score to medium (rule)', (float)$q("SELECT score FROM aml_customers WHERE customer_ref='T-5002'") === 1.5 && $q("SELECT rating FROM aml_customers WHERE customer_ref='T-5002'") === 'medium');
$c['compliance']->post('/aml/customers', $cF(['customer_ref' => 'T-5003', 'full_name' => 'Test Official', 'customer_type_id' => (string)$fac['CUS-IND-RES'], 'geography_id' => (string)$fac['GEO-LY'], 'products' => [(string)$fac['PRD-CUR']], 'channels' => [(string)$fac['CHN-BRANCH']], 'is_pep' => '1']), '/aml/customers/new');
$pep = (int)$q("SELECT id FROM aml_customers WHERE customer_ref='T-5003'");
check('customer: politically exposed person rated high despite a score of 1', (float)$q("SELECT score FROM aml_customers WHERE id=$pep") === 1.0 && $q("SELECT rating FROM aml_customers WHERE id=$pep") === 'high');
$show = $c['exec']->get("/aml/customers/$cu")['body'];
check('customer page explains the rating dimension by dimension', str_contains($show, 'class="data"') && substr_count($show, '%</td>') === 4);
$c['compliance']->post("/aml/customers/$pep/override", ['override_rating' => 'low', 'override_reason' => 'Long-standing customer with low activity'], "/aml/customers/$pep"); check('override: PEP cannot be rated below high', $q("SELECT rating FROM aml_customers WHERE id=$pep") === 'high');
$c['compliance']->post("/aml/customers/$cu/override", ['override_rating' => 'medium', 'override_reason' => 'short'], "/aml/customers/$cu"); check('override: reason of at least 20 characters required', $q("SELECT rating FROM aml_customers WHERE id=$cu") === 'high');
$c['compliance']->post("/aml/customers/$cu/override", ['override_rating' => 'medium', 'override_reason' => 'Licensed exchange house supervised by the central bank'], "/aml/customers/$cu");
check('override: effective rating medium, calculated stays high, logged', $q("SELECT rating FROM aml_customers WHERE id=$cu") === 'medium' && $q("SELECT calc_rating FROM aml_customers WHERE id=$cu") === 'high' && $q("SELECT cdd_level FROM aml_customers WHERE id=$cu") === 'standard' && (int)$q("SELECT COUNT(*) FROM audit_log WHERE action='aml.rating_overridden' AND entity_id=$cu") === 1);
$c['compliance']->post("/aml/customers/$cu/override", ['override_rating' => '', 'override_reason' => ''], "/aml/customers/$cu"); check('override: cleared back to the calculated rating', $q("SELECT rating FROM aml_customers WHERE id=$cu") === 'high' && $q("SELECT override_rating FROM aml_customers WHERE id=$cu") === null);
$sanc = (int)$q("SELECT id FROM aml_customers WHERE sanctions_match = 1 LIMIT 1");
$c['compliance']->post("/aml/customers/$sanc/review", ['note' => 'All checked'], "/aml/customers/$sanc"); check('review: refused while a sanctions match is open', !(int)$q("SELECT COUNT(*) FROM aml_customer_reviews WHERE customer_id=$sanc"));
$c['compliance']->post("/aml/customers/$cu/review", ['note' => ''], "/aml/customers/$cu"); check('review: note required', !(int)$q("SELECT COUNT(*) FROM aml_customer_reviews WHERE customer_id=$cu"));
$c['compliance']->post("/aml/customers/$cu/review", ['note' => 'Documents and source of funds checked'], "/aml/customers/$cu");
check('review: recorded and next review set from the rating', (int)$q("SELECT COUNT(*) FROM aml_customer_reviews WHERE customer_id=$cu") === 1 && $q("SELECT last_review_on FROM aml_customers WHERE id=$cu") === $today && $q("SELECT next_review_due FROM aml_customers WHERE id=$cu") === date('Y-m-d', strtotime('+12 months')));
check('compliance My tasks lists overdue customer reviews', str_contains($c['compliance']->get('/my')['body'], 'Synthetic Exchange Shop'));
$c['compliance']->post('/aml/factors/' . $fac['PRD-FX'], ['name_en' => 'Foreign currency cash exchange', 'name_ar' => 'صرف العملات الأجنبية نقداً', 'risk_level' => '1', 't24_code' => 'TT', 'is_active' => '1'], '/aml/factors/' . $fac['PRD-FX'] . '/edit');
check('factor: lowering a level re-rates the customers that use it', (float)$q("SELECT score FROM aml_customers WHERE id=$cu") === 2.25 && $q("SELECT rating FROM aml_customers WHERE id=$cu") === 'medium');
$c['compliance']->post('/aml/factors/' . $fac['PRD-FX'], ['name_en' => 'Foreign currency cash exchange', 'name_ar' => 'صرف العملات الأجنبية نقداً', 'risk_level' => '3', 't24_code' => 'TT', 'is_active' => '1'], '/aml/factors/' . $fac['PRD-FX'] . '/edit');
check('factor: raising it back re-rates high and brings the review forward', $q("SELECT rating FROM aml_customers WHERE id=$cu") === 'high' && $q("SELECT next_review_due FROM aml_customers WHERE id=$cu") <= date('Y-m-d', strtotime('+30 days')));
$c['compliance']->post('/aml/factors/' . $fac['PRD-FX'], ['name_en' => 'x', 'name_ar' => 'س', 'risk_level' => '3', 't24_code' => 'TT'], '/aml/factors/' . $fac['PRD-FX'] . '/edit'); check('factor: in-use factor cannot be deactivated', (int)$q('SELECT is_active FROM aml_factors WHERE id=' . $fac['PRD-FX']) === 1);
$c['compliance']->post('/aml/factors', ['dimension' => 'geography', 'code' => 'bad code', 'name_en' => 'x', 'name_ar' => 'س', 'risk_level' => '3', 'is_active' => '1'], '/aml/factors/new'); check('factor: code format enforced', !$q("SELECT 1 FROM aml_factors WHERE code='bad code'"));
$c['compliance']->post('/aml/factors', ['dimension' => 'geography', 'code' => 'GEO-TEST', 'name_en' => 'Test country', 'name_ar' => 'دولة اختبار', 'risk_level' => '3', 't24_code' => 'ZZ', 'is_active' => '1'], '/aml/factors/new'); check('factor: new geography factor created', (int)$q("SELECT risk_level FROM aml_factors WHERE code='GEO-TEST'") === 3);
$exp = $c['exec']->get('/aml/customers/export?rating=high')['body'];
check('CSV export (exec viewer) has the rating columns and only filtered rows', str_contains($exp, 'customer_ref') && str_contains($exp, 'T-5001') && !str_contains($exp, 'T-5002'));

section('AML: controls and confidential reports');
$c['compliance']->post('/aml/controls', ['dimension' => 'geography', 'control_id' => (string)$ctlId], '/aml'); check('control linked to the geography dimension', (int)$q("SELECT COUNT(*) FROM aml_dimension_controls WHERE dimension='geography' AND control_id=$ctlId") === 1);
check('risk manager cannot link AML controls (403)', $c['risk']->post('/aml/controls', ['dimension' => 'customer', 'control_id' => (string)$ctlId], '/aml')['code'] === 403);
$sum = AmlScoring::dimensionSummary();
check('enterprise view: geography now has a control effectiveness and residual below inherent', $sum['geography']['effectiveness'] !== null && ($sum['geography']['effectiveness'] === 0 || $sum['geography']['residual'] < $sum['geography']['inherent']));
$c['compliance']->post('/aml/controls/remove', ['dimension' => 'geography', 'control_id' => (string)$ctlId], '/aml'); check('control unlinked', !(int)$q("SELECT COUNT(*) FROM aml_dimension_controls WHERE dimension='geography' AND control_id=$ctlId"));
$rc = fn() => (int)$q('SELECT COUNT(*) FROM aml_reports');
$n = $rc();
$rF2 = fn(array $o = []) => $o + ['report_type' => 'str', 'title' => 'Structuring by test customer', 'customer_id' => (string)$cu, 'recipient' => 'Financial intelligence unit', 'due_date' => date('Y-m-d', strtotime('+2 days')), 'summary' => 'Repeated deposits just under the threshold'];
$c['compliance']->post('/aml/reports', $rF2(['customer_id' => '']), '/aml/reports/new'); check('STR: customer required', $rc() === $n);
$c['compliance']->post('/aml/reports', $rF2(), '/aml/reports/new'); $str = (int)$q('SELECT MAX(id) FROM aml_reports');
check('STR: created confidential with AMLR code', $rc() === $n + 1 && (int)$q("SELECT confidential FROM aml_reports WHERE id=$str") === 1 && preg_match('/^AMLR-\d{4}$/', (string)$q("SELECT code FROM aml_reports WHERE id=$str")) === 1);
check('STR: hidden from executives and auditors (no tipping off)', $c['exec']->get("/aml/reports/$str")['code'] === 404 && $c['auditor']->get("/aml/reports/$str")['code'] === 404 && !str_contains($c['exec']->get('/aml/reports')['body'], 'Structuring by test customer') && !str_contains($c['exec']->get("/aml/customers/$cu")['body'], 'Structuring by test customer'));
check('STR: visible to the compliance officer and in My tasks', $c['compliance']->get("/aml/reports/$str")['code'] === 200 && str_contains($c['compliance']->get('/my')['body'], 'Structuring by test customer'));
check('STR: details kept out of the audit log', !(int)$q("SELECT COUNT(*) FROM audit_log WHERE detail LIKE '%Repeated deposits%' OR detail LIKE '%Structuring by test%'"));
$c['compliance']->post("/aml/reports/$str/attachments", [], "/aml/reports/$str", ['file' => ['str.pdf', "%PDF-1.4\n%synthetic\n", 'application/pdf']]);
$sa = (int)$q("SELECT id FROM attachments WHERE entity_type='aml_report' AND entity_id=$str");
check('STR attachment: compliance can download, executive cannot', $sa > 0 && $c['compliance']->get("/attachments/$sa")['code'] === 200 && $c['exec']->get("/attachments/$sa")['code'] === 404);
$c['compliance']->post("/aml/reports/$str/submit", ['submitted_on' => date('Y-m-d', strtotime('+1 day')), 'reference_no' => 'FIU-1'], "/aml/reports/$str"); check('submission: future date rejected', $q("SELECT status FROM aml_reports WHERE id=$str") === 'draft');
$c['compliance']->post("/aml/reports/$str/submit", ['submitted_on' => $today, 'reference_no' => 'FIU-1'], "/aml/reports/$str");
check('submission recorded with reference', $q("SELECT status FROM aml_reports WHERE id=$str") === 'submitted' && $q("SELECT reference_no FROM aml_reports WHERE id=$str") === 'FIU-1');
$c['compliance']->post("/aml/reports/$str", $rF2(['title' => 'changed']), "/aml/reports/$str"); check('submitted report is locked', $q("SELECT title FROM aml_reports WHERE id=$str") === 'Structuring by test customer');
$c['compliance']->post('/aml/reports', $rF2(['report_type' => 'periodic', 'customer_id' => '', 'title' => 'Quarterly statistics Q-test']), '/aml/reports/new'); $per = (int)$q('SELECT MAX(id) FROM aml_reports');
check('periodic report is not confidential and visible to executives', (int)$q("SELECT confidential FROM aml_reports WHERE id=$per") === 0 && $c['exec']->get("/aml/reports/$per")['code'] === 200);
$ab = $c['risk']->get('/aml')['body'];
check('AML overview shows the four dimensions and the rules', substr_count($ab, 'class="dim-card') === 4 && str_contains($ab, 'id="controls"'));
check('dashboard shows AML tiles for the compliance officer', str_contains($c['compliance']->get('/')['body'], 'aml/customers?review=1'));

section('T24 integration: settings and file loads');
$admin->post('/admin/integrations/settings', ['api_base' => 'ftp://t24.local', 'api_user' => '', 'customers_path' => '', 'gl_path' => '', 'page_size' => '200', 'timeout' => '30', 'map_customers' => '', 'map_gl' => ''], '/admin/integrations'); check('settings: non-http address rejected', (string)$q("SELECT setting_value FROM settings WHERE setting_key='t24_api_base'") === '');
$admin->post('/admin/integrations/settings', ['api_base' => 'https://user:pw@t24.local', 'api_user' => '', 'customers_path' => '', 'gl_path' => '', 'page_size' => '200', 'timeout' => '30', 'map_customers' => '', 'map_gl' => ''], '/admin/integrations'); check('settings: credentials inside the URL rejected', (string)$q("SELECT setting_value FROM settings WHERE setting_key='t24_api_base'") === '');
$admin->post('/admin/integrations/settings', ['api_base' => '', 'api_user' => '', 'customers_path' => '', 'gl_path' => '', 'page_size' => '200', 'timeout' => '30', 'map_customers' => "unknown_field=X", 'map_gl' => ''], '/admin/integrations'); check('settings: mapping to an unknown platform field rejected', $q("SELECT setting_value FROM settings WHERE setting_key='t24_map_customers'") === false);
$csv = "CUSTOMER.ID|NAME.1|SECTOR|RESIDENCE|NATIONALITY|PRODUCTS|CHANNELS|PEP|BRANCH|CUSTOMER.SINCE\n800001|File Customer One|1001|LY|LY|1001|BRANCH|N|TRP|20200115\n800002|File Customer Two|2002|TN|EG|FT;TT|AGENT|N||20210301\n800003|File Customer Three|1001|LY|LY|1001|MOBILE|Y||\n800004|File Customer Four|9999|XX|XX|55555|KIOSK|N||\n";
$ir = fn() => (int)$q('SELECT COUNT(*) FROM integration_runs');
$n = $ir(); $nc = $cc();
$admin->post('/admin/integrations/upload', ['dataset' => 'customers'], '/admin/integrations', ['file' => ['customers.csv', $csv, 'text/csv']]);
check('file load: run logged as success', $ir() === $n + 1 && $q('SELECT status FROM integration_runs ORDER BY id DESC LIMIT 1') === 'success' && (int)$q('SELECT rows_created FROM integration_runs ORDER BY id DESC LIMIT 1') === 4);
check('file load: customers created from T24 with source t24', $cc() === $nc + 4 && $q("SELECT source FROM aml_customers WHERE customer_ref='800001'") === 't24' && (int)$q("SELECT branch_id FROM aml_customers WHERE customer_ref='800001'") === (int)$q("SELECT id FROM branches WHERE code='TRP'"));
check('file load: codes mapped to factors and rated (cash business abroad = high)', $q("SELECT rating FROM aml_customers WHERE customer_ref='800002'") === 'high' && (float)$q("SELECT score FROM aml_customers WHERE customer_ref='800002'") === 2.75);
check('file load: PEP flag Y rated high', $q("SELECT rating FROM aml_customers WHERE customer_ref='800003'") === 'high' && (int)$q("SELECT is_pep FROM aml_customers WHERE customer_ref='800003'") === 1);
check('file load: unknown codes fall back to the default factors', (int)$q("SELECT customer_type_id FROM aml_customers WHERE customer_ref='800004'") === (int)$fac['CUS-IND-RES'] && (int)$q("SELECT geography_id FROM aml_customers WHERE customer_ref='800004'") === (int)$fac['GEO-LY']);
check('file load: onboarding date converted from YYYYMMDD', $q("SELECT onboarded_on FROM aml_customers WHERE customer_ref='800001'") === '2020-01-15');
$c800 = (int)$q("SELECT id FROM aml_customers WHERE customer_ref='800001'");
$c['compliance']->post("/aml/customers/$c800/override", ['override_rating' => 'medium', 'override_reason' => 'Manual review found cash activity'], "/aml/customers/$c800");
$admin->post('/admin/integrations/upload', ['dataset' => 'customers'], '/admin/integrations', ['file' => ['customers.csv', str_replace('File Customer One|1001', 'File Customer One Renamed|1001', $csv), 'text/csv']]);
check('reload: existing customers updated, not duplicated', $cc() === $nc + 4 && (int)$q('SELECT rows_updated FROM integration_runs ORDER BY id DESC LIMIT 1') === 4 && $q("SELECT full_name FROM aml_customers WHERE id=$c800") === 'File Customer One Renamed');
check('reload: manual override kept', $q("SELECT override_rating FROM aml_customers WHERE id=$c800") === 'medium' && $q("SELECT rating FROM aml_customers WHERE id=$c800") === 'medium');
$bad = $csv . "800001|Duplicate|1001|LY|LY|1001|BRANCH|N||\n800009||1001|LY|LY|1001|BRANCH|N||20991301\n";
$before = $q("SELECT updated_at FROM aml_customers WHERE id=$c800");
$admin->post('/admin/integrations/upload', ['dataset' => 'customers'], '/admin/integrations', ['file' => ['bad.csv', $bad, 'text/csv']]);
$run = (int)$q('SELECT MAX(id) FROM integration_runs');
check('bad file: whole load rejected, nothing changed', $q("SELECT status FROM integration_runs WHERE id=$run") === 'failed' && $cc() === $nc + 4 && $q("SELECT updated_at FROM aml_customers WHERE id=$c800") === $before);
$err = (string)$q("SELECT error_text FROM integration_runs WHERE id=$run");
check('bad file: errors name the line and the problem (duplicate, missing name, bad date)', str_contains($err, '800001') && substr_count($err, "\n") >= 1);
check('run page shows the errors', str_contains($admin->get("/admin/integrations/runs/$run")['body'], '800001'));
$admin->post('/admin/integrations/upload', ['dataset' => 'customers'], '/admin/integrations', ['file' => ['wrong.csv', "ID,NAME\n1,x\n", 'text/csv']]);
check('file with unmapped columns rejected with a clear message', $q('SELECT status FROM integration_runs ORDER BY id DESC LIMIT 1') === 'failed' && str_contains((string)$q('SELECT error_text FROM integration_runs ORDER BY id DESC LIMIT 1'), 'CUSTOMER.ID'));
$prev = date('Y-m', strtotime('first day of last month'));
$gl = "PERIOD,PL.CATEGORY,AMOUNT,DESCRIPTION\n" . str_replace('-', '', $prev) . ",69001,-1250.500,cash shortage\n" . str_replace('-', '', $prev) . "15,69002,300,fraud\n";
$admin->post('/admin/integrations/upload', ['dataset' => 'gl'], '/admin/integrations', ['file' => ['gl.csv', $gl, 'text/csv']]);
$glSum = fn() => (string)$q("SELECT COALESCE(SUM(amount),0) FROM gl_entries WHERE period='$prev'");
check('GL load: negative debit taken as loss, YYYYMMDD period accepted', $q('SELECT status FROM integration_runs ORDER BY id DESC LIMIT 1') === 'success' && $glSum() === '1550.500');
$admin->post('/admin/integrations/upload', ['dataset' => 'gl'], '/admin/integrations', ['file' => ['gl.csv', $gl, 'text/csv']]);
check('GL reload replaces the period instead of doubling it', $glSum() === '1550.500' && (int)$q('SELECT rows_updated FROM integration_runs ORDER BY id DESC LIMIT 1') === 2);
check('reconciliation page shows the loaded period', str_contains($c['risk']->get('/incidents/reconciliation?year=' . substr($prev, 0, 4))['body'], $prev));
$json = json_encode(['header' => [], 'body' => [['cid' => '810001', 'name' => 'Json Customer', 'sector' => '2001', 'res' => 'LY', 'accts' => [['cat' => '21070'], ['cat' => '1001']], 'chans' => ['INTERNET']]]]);
$admin->post('/admin/integrations/settings', ['api_base' => '', 'api_user' => '', 'customers_path' => '', 'gl_path' => '', 'page_size' => '2', 'timeout' => '30', 'map_customers' => "customer_ref=cid\nfull_name=name\nsector=sector\nresidence=res\nnationality=res\nproducts=accts/*/cat\nchannels=chans", 'map_gl' => ''], '/admin/integrations');
$admin->post('/admin/integrations/upload', ['dataset' => 'customers'], '/admin/integrations', ['file' => ['customers.json', $json, 'application/json']]);
check('JSON load with nested mapping (accts/*/cat): trade finance mapped', $q("SELECT status FROM integration_runs ORDER BY id DESC LIMIT 1") === 'success' && (int)$q("SELECT COUNT(*) FROM aml_customer_factors x JOIN aml_customers c ON c.id = x.customer_id WHERE c.customer_ref='810001' AND x.factor_id=" . $fac['PRD-TRADE']) === 1);
$tpl = $admin->get('/admin/integrations/sample/customers')['body'];
check('template download uses the current mapping', str_contains($tpl, 'cid') && str_contains($tpl, 'accts/*/cat'));
check('integration pages closed to non-admins (403)', $c['compliance']->post('/admin/integrations/upload', ['dataset' => 'customers'], '/aml', ['file' => ['c.csv', $csv, 'text/csv']])['code'] === 403);

section('T24 integration: API and scheduled job');
@mkdir("$work/t24"); [$t24srv] = startServer("$work/t24", 8097, [], "$root/tests/fake_t24.php");
$admin->post('/admin/integrations/settings', ['api_base' => 'http://127.0.0.1:8097', 'api_user' => 't24user', 'api_secret' => 'wrong-pass', 'customers_path' => '/api/v1.0.0/party/customers', 'gl_path' => '/api/v1.0.0/accounts/glBalances', 'page_size' => '2', 'timeout' => '10',
    'map_customers' => "customer_ref=customerId\nfull_name=customerName\nsector=sector\nresidence=residence\nnationality=nationality\nproducts=accounts/*/category\nchannels=channels\npep=pep", 'map_gl' => "period=period\naccount=plCategory\namount=amount\ndescription=narrative"], '/admin/integrations');
$stored = (string)$q("SELECT setting_value FROM settings WHERE setting_key='t24_api_secret'");
check('secret stored encrypted, never in clear, never shown', $stored !== '' && !str_contains($stored, 'wrong-pass') && !str_contains($admin->get('/admin/integrations')['body'], 'wrong-pass') && !(int)$q("SELECT COUNT(*) FROM audit_log WHERE detail LIKE '%wrong-pass%'"));
$r = $admin->post('/admin/integrations/test', [], '/admin/integrations'); $pg = $admin->get('/admin/integrations')['body'];
check('connection test with a wrong password reports status 401', str_contains($pg, '401'));
$n = $ir(); $admin->post('/admin/integrations/pull', ['dataset' => 'customers'], '/admin/integrations');
check('failed API pull is logged and changes nothing', $ir() === $n + 1 && $q('SELECT status FROM integration_runs ORDER BY id DESC LIMIT 1') === 'failed' && !$q("SELECT 1 FROM aml_customers WHERE customer_ref='700001'"));
$admin->post('/admin/integrations/settings', ['api_base' => 'http://127.0.0.1:8097', 'api_user' => 't24user', 'api_secret' => 't24pass', 'customers_path' => '/api/v1.0.0/party/customers', 'gl_path' => '/api/v1.0.0/accounts/glBalances', 'page_size' => '2', 'timeout' => '10',
    'map_customers' => "customer_ref=customerId\nfull_name=customerName\nsector=sector\nresidence=residence\nnationality=nationality\nproducts=accounts/*/category\nchannels=channels\npep=pep", 'map_gl' => "period=period\naccount=plCategory\namount=amount\ndescription=narrative"], '/admin/integrations');
$admin->post('/admin/integrations/test', [], '/admin/integrations'); check('connection test succeeds with the right password', str_contains($admin->get('/admin/integrations')['body'], 'الاتصال يعمل'));
$admin->post('/admin/integrations/pull', ['dataset' => 'customers'], '/admin/integrations');
check('API pull reads every page (5 records, page size 2) and rates them', $q('SELECT status FROM integration_runs ORDER BY id DESC LIMIT 1') === 'success' && (int)$q('SELECT rows_read FROM integration_runs ORDER BY id DESC LIMIT 1') === 5 && (int)$q("SELECT COUNT(*) FROM aml_customers WHERE customer_ref LIKE '7000%'") === 5);
check('API pull: PEP and high-risk products rated high', $q("SELECT rating FROM aml_customers WHERE customer_ref='700003'") === 'high' && $q("SELECT rating FROM aml_customers WHERE customer_ref='700002'") === 'high' && $q("SELECT mode FROM integration_runs ORDER BY id DESC LIMIT 1") === 'api');
$admin->post('/admin/integrations/pull', ['dataset' => 'gl'], '/admin/integrations');
check('API pull of the loss ledger', $q('SELECT status FROM integration_runs ORDER BY id DESC LIMIT 1') === 'success' && (int)$q("SELECT COUNT(*) FROM gl_entries WHERE account='69100'") === 1);
$cli = fn(string $args) => (function () use ($args, $work) { exec('GRC_CONFIG_FILE=' . escapeshellarg("$work/cfg/config.php") . ' php ' . escapeshellarg("$work/root/bin/t24-sync.php") . " $args 2>&1", $o, $rc); return [$rc, implode("\n", $o)]; })();
[$rc] = $cli(''); check('CLI without arguments exits 2 (usage)', $rc === 2);
[$rc, $out] = $cli('--dataset=customers --api'); check('CLI API load exits 0', $rc === 0 && str_contains($out, 'read=5'));
file_put_contents("$work/t24-cli.csv", "CUSTOMER.ID,NAME.1,SECTOR,RESIDENCE,NATIONALITY,PRODUCTS,CHANNELS,PEP\n820001,Cli Customer,1001,LY,LY,1001,BRANCH,N\n");
$admin->post('/admin/integrations/settings', ['api_base' => 'http://127.0.0.1:8097', 'api_user' => 't24user', 'api_secret' => '', 'customers_path' => '/api/v1.0.0/party/customers', 'gl_path' => '/api/v1.0.0/accounts/glBalances', 'page_size' => '2', 'timeout' => '10', 'map_customers' => '', 'map_gl' => ''], '/admin/integrations');
check('empty secret field keeps the stored secret', (string)$q("SELECT setting_value FROM settings WHERE setting_key='t24_api_secret'") !== '');
[$rc, $out] = $cli('--dataset=customers --file=' . escapeshellarg("$work/t24-cli.csv"));
check('CLI file load exits 0 and logs a scheduled run', $rc === 0 && str_contains($out, 'status=success') && $q("SELECT mode FROM integration_runs ORDER BY id DESC LIMIT 1") === 'cli' && $q("SELECT run_by FROM integration_runs ORDER BY id DESC LIMIT 1") === null && (bool)$q("SELECT 1 FROM aml_customers WHERE customer_ref='820001'"));
file_put_contents("$work/t24-bad.csv", "CUSTOMER.ID,NAME.1\n,\n");
[$rc] = $cli('--dataset=customers --file=' . escapeshellarg("$work/t24-bad.csv")); check('CLI rejected load exits 1', $rc === 1);
proc_terminate($t24srv);
$runsPage = $admin->get('/admin/integrations')['body'];
check('run history lists file, API and scheduled loads', str_contains($runsPage, 'class="data"') && substr_count($runsPage, 'admin/integrations/runs/') >= 10);
