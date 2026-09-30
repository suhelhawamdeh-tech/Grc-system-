<?php
// Phase 2 tests. Included by tests/run.php (shares $B, $pdo, $admin, $c[], $DEMO_PW, $ADMIN_PW, $pdf, helper closures).
section('Phase 2 unit: KRI status logic');
use App\Support\Kri;
check('KRI higher-is-worse boundaries', Kri::status(4999, 5000, 10000, 'higher_worse') === 'green' && Kri::status(5000, 5000, 10000, 'higher_worse') === 'amber' && Kri::status(9999, 5000, 10000, 'higher_worse') === 'amber' && Kri::status(10000, 5000, 10000, 'higher_worse') === 'red');
check('KRI lower-is-worse boundaries', Kri::status(20001, 20000, 10000, 'lower_worse') === 'green' && Kri::status(20000, 20000, 10000, 'lower_worse') === 'amber' && Kri::status(10000, 20000, 10000, 'lower_worse') === 'red' && Kri::status(0, 20000, 10000, 'lower_worse') === 'red');
check('KRI threshold ordering validation', Kri::thresholdsValid(5, 10, 'higher_worse') && !Kri::thresholdsValid(10, 5, 'higher_worse') && Kri::thresholdsValid(20, 10, 'lower_worse') && !Kri::thresholdsValid(10, 20, 'lower_worse'));
check('KRI cycle start (monthly/quarterly)', Kri::cycleStart('monthly', '2026-08-17') === '2026-08-01' && Kri::cycleStart('quarterly', '2026-08-17') === '2026-07-01' && Kri::cycleStart('quarterly', '2026-02-01') === '2026-01-01');

$roles2 = ['risk' => $c['risk'], 'compliance' => $c['compliance'], 'retail' => $c['retail'], 'it' => $c['it'], 'tripoli' => $c['tripoli'], 'exec' => $c['exec'], 'auditor' => $c['auditor']];
$admin2 = new Client($B); $admin2->login('admin', $ADMIN_PW);
$deptRet = $retail; $deptIt = $itd;
$uid = fn($n) => (int)$pdo->query("SELECT id FROM users WHERE username='$n'")->fetchColumn();

section('Phase 2 authorization matrix');
$m2 = [
    '/kris' => ['risk' => 200, 'compliance' => 200, 'retail' => 200, 'tripoli' => 403, 'exec' => 200, 'auditor' => 200],
    '/kris/new' => ['risk' => 200, 'compliance' => 403, 'retail' => 403, 'exec' => 403, 'auditor' => 403],
    '/appetite' => ['risk' => 200, 'compliance' => 200, 'retail' => 403, 'tripoli' => 403, 'exec' => 200, 'auditor' => 200],
    '/appetite/new' => ['risk' => 200, 'compliance' => 403, 'exec' => 403, 'auditor' => 403],
    '/compliance' => ['risk' => 200, 'compliance' => 200, 'retail' => 200, 'tripoli' => 403, 'exec' => 200, 'auditor' => 200],
    '/compliance/new' => ['risk' => 403, 'compliance' => 200, 'retail' => 403, 'exec' => 403],
    '/committees' => ['risk' => 200, 'compliance' => 200, 'retail' => 403, 'tripoli' => 403, 'exec' => 200, 'auditor' => 200],
    '/committees/new' => ['risk' => 200, 'compliance' => 200, 'exec' => 403, 'auditor' => 403],
    '/policies' => ['risk' => 200, 'retail' => 200, 'tripoli' => 200, 'exec' => 200, 'auditor' => 200],
    '/policies/new' => ['risk' => 200, 'compliance' => 200, 'retail' => 403, 'tripoli' => 403, 'exec' => 403],
    '/rcsa' => ['risk' => 200, 'compliance' => 200, 'retail' => 200, 'tripoli' => 200, 'exec' => 200, 'auditor' => 200],
    '/rcsa/launch' => ['risk' => 200, 'compliance' => 403, 'retail' => 403, 'tripoli' => 403, 'exec' => 403],
    '/rcsa/templates/new' => ['risk' => 200, 'retail' => 403],
    '/compliance/export' => ['exec' => 200, 'auditor' => 200, 'tripoli' => 403],
];
foreach ($m2 as $p => $exp) foreach ($exp as $role => $code) { $r = $roles2[$role]->get($p); check("P2 $role GET $p = $code", $r['code'] === $code, (string)$r['code']); }
foreach (['/kris', '/appetite', '/compliance', '/committees', '/policies', '/rcsa'] as $p) check("anonymous $p -> login", str_contains((string)(new Client($B))->get($p)['loc'], '/login'));

section('Phase 2: KRIs');
$kf = fn(array $o = []) => $o + ['name' => 'Failed logins per day', 'description' => 'd', 'department_id' => $deptIt, 'owner_user_id' => $uid('demo.dept.it'), 'unit' => 'count', 'frequency' => 'monthly', 'direction' => 'higher_worse', 'amber_threshold' => '5', 'red_threshold' => '10'];
$n = (int)$pdo->query('SELECT COUNT(*) FROM kris')->fetchColumn();
$c['risk']->post('/kris', $kf(['red_threshold' => '3']), '/kris/new'); check('KRI: red below amber (higher-is-worse) rejected', (int)$pdo->query('SELECT COUNT(*) FROM kris')->fetchColumn() === $n);
$c['risk']->post('/kris', $kf(['amber_threshold' => 'abc']), '/kris/new'); check('KRI: non-numeric threshold rejected', (int)$pdo->query('SELECT COUNT(*) FROM kris')->fetchColumn() === $n);
$c['risk']->post('/kris', $kf(['department_id' => 99999]), '/kris/new'); check('KRI: bad department rejected', (int)$pdo->query('SELECT COUNT(*) FROM kris')->fetchColumn() === $n);
$c['risk']->post('/kris', $kf(), '/kris/new'); $kid = (int)$pdo->query('SELECT MAX(id) FROM kris')->fetchColumn(); check('KRI created with code KRI-0000', $kid > 0 && preg_match('/^KRI-\d{4}$/', $pdo->query("SELECT code FROM kris WHERE id=$kid")->fetchColumn()) === 1);
$c['risk']->post('/kris', $kf(['name' => 'Liquidity coverage', 'department_id' => $deptRet, 'owner_user_id' => $uid('demo.dept.retail'), 'direction' => 'lower_worse', 'amber_threshold' => '120', 'red_threshold' => '100', 'unit' => '%']), '/kris/new'); $kid2 = (int)$pdo->query('SELECT MAX(id) FROM kris')->fetchColumn();
$rd = fn(Client $cl, int $id, string $val, array $o = []) => $cl->post("/kris/$id/readings", $o + ['reading_date' => date('Y-m-d'), 'value' => $val], "/kris/$id");
$st = fn(int $id) => $pdo->query("SELECT status FROM kri_readings WHERE kri_id=$id ORDER BY id DESC LIMIT 1")->fetchColumn();
$rd($c['risk'], $kid, '3'); check('reading 3 -> green', $st($kid) === 'green');
$rd($c['risk'], $kid, '6.5'); check('reading 6.5 -> amber', $st($kid) === 'amber');
$rd($c['risk'], $kid, '10'); check('reading 10 -> red', $st($kid) === 'red');
$cnt = (int)$pdo->query("SELECT COUNT(*) FROM kri_readings WHERE kri_id=$kid")->fetchColumn();
$rd($c['risk'], $kid, 'x'); $rd($c['risk'], $kid, '-1'); $rd($c['risk'], $kid, '5', ['reading_date' => '2999-01-01']); $rd($c['risk'], $kid, '5', ['reading_date' => 'nope']);
check('invalid readings (non-numeric, negative, future, bad date) rejected', (int)$pdo->query("SELECT COUNT(*) FROM kri_readings WHERE kri_id=$kid")->fetchColumn() === $cnt);
$rd($c['risk'], $kid2, '90'); check('lower-is-worse reading 90 -> red', $st($kid2) === 'red');
check('KRI list shows alert filter results', str_contains($c['risk']->get('/kris?alert=1')['body'], "/kris/$kid\"") && str_contains($c['risk']->get('/kris?status=red')['body'], "/kris/$kid\"") && !str_contains($c['risk']->get('/kris?status=green')['body'], "/kris/$kid\""));
check('KRI missing-reading filter: KRI with no readings flagged', (function () use ($c, $pdo, $deptIt, $uid) { $c['risk']->post('/kris', ['name' => 'No readings KRI', 'department_id' => $deptIt, 'owner_user_id' => $uid('demo.dept.it'), 'frequency' => 'monthly', 'direction' => 'higher_worse', 'amber_threshold' => '1', 'red_threshold' => '2', 'unit' => ''], '/kris/new'); $id = (int)$pdo->query('SELECT MAX(id) FROM kris')->fetchColumn(); return str_contains($c['risk']->get('/kris?missing=1')['body'], "/kris/$id\""); })());
// scope
check('IT manager can open IT KRI, retail manager gets 404', $c['it']->get("/kris/$kid")['code'] === 200 && $c['retail']->get("/kris/$kid")['code'] === 404);
check('retail list excludes IT KRI, includes own', !str_contains($c['retail']->get('/kris')['body'], "/kris/$kid\"") && str_contains($c['retail']->get('/kris')['body'], "/kris/$kid2\""));
$cnt = (int)$pdo->query('SELECT COUNT(*) FROM kri_readings')->fetchColumn(); $rd($c['retail'], $kid, '1'); check('retail manager cannot add reading to IT KRI (404)', (int)$pdo->query('SELECT COUNT(*) FROM kri_readings')->fetchColumn() === $cnt);
$rd($c['retail'], $kid2, '150'); check('retail manager can report own-department KRI (green)', $st($kid2) === 'green');
check('exec viewer cannot add readings (403)', $rd($c['exec'], $kid, '1')['code'] === 403 && $c['exec']->get("/kris/$kid")['code'] === 200);
$c['risk']->post("/kris/$kid", $kf(['amber_threshold' => '8', 'red_threshold' => '12', 'is_active' => '1']), "/kris/$kid/edit");
check('editing thresholds re-evaluates stored reading statuses', $pdo->query("SELECT status FROM kri_readings WHERE kri_id=$kid AND value=10")->fetchColumn() === 'amber' && $pdo->query("SELECT status FROM kri_readings WHERE kri_id=$kid AND value=6.5")->fetchColumn() === 'green');
$c['risk']->post("/kris/$kid", $kf(['is_active' => '']), "/kris/$kid/edit"); check('inactive KRI rejects new readings', $rd($c['risk'], $kid, '1') && $pdo->query("SELECT COUNT(*) FROM kri_readings WHERE kri_id=$kid")->fetchColumn() == 3);
$c['risk']->post("/kris/$kid", $kf(['is_active' => '1', 'amber_threshold' => '5', 'red_threshold' => '10']), "/kris/$kid/edit");
check('KRI detail page renders trend and readings', str_contains($c['risk']->get("/kris/$kid")['body'], 'class="cols"'));

section('Phase 2: risk appetite and breaches');
$af = fn(array $o = []) => $o + ['title' => 'Residual risk ceiling', 'statement' => 'No active risk may exceed a residual score of 10 without Board acceptance.', 'metric' => 'max_residual_score', 'limit_value' => '10', 'approved_by' => 'Board Risk Committee (test)', 'approved_on' => date('Y-m-d')];
$n = (int)$pdo->query('SELECT COUNT(*) FROM appetite_statements')->fetchColumn();
$c['risk']->post('/appetite', $af(['limit_value' => '30']), '/appetite/new'); $c['risk']->post('/appetite', $af(['limit_value' => 'x']), '/appetite/new'); $c['risk']->post('/appetite', $af(['metric' => 'bogus']), '/appetite/new');
check('appetite: invalid limits/metric rejected', (int)$pdo->query('SELECT COUNT(*) FROM appetite_statements')->fetchColumn() === $n);
$c['risk']->post('/appetite', $af(), '/appetite/new'); $sid = (int)$pdo->query('SELECT MAX(id) FROM appetite_statements')->fetchColumn();
check('appetite statement created (code RAS-000)', preg_match('/^RAS-\d{3}$/', $pdo->query("SELECT code FROM appetite_statements WHERE id=$sid")->fetchColumn()) === 1);
$over = (int)$pdo->query("SELECT COUNT(*) FROM risks WHERE archived_at IS NULL AND status='active' AND res_score > 10")->fetchColumn();
$pg = $c['risk']->get('/appetite')['body']; check("appetite page shows live measure ($over risks above limit) from DB", $over > 0 && str_contains($pg, 'badge danger') && str_contains($pg, (string)$over));
$c['risk']->post('/appetite/evaluate', [], '/appetite'); $bc = (int)$pdo->query("SELECT COUNT(*) FROM appetite_breaches WHERE statement_id=$sid AND source='auto'")->fetchColumn();
check('evaluate opens exactly one auto breach', $bc === 1);
$c['risk']->post('/appetite/evaluate', [], '/appetite'); check('re-evaluating does not duplicate an open breach', (int)$pdo->query("SELECT COUNT(*) FROM appetite_breaches WHERE statement_id=$sid")->fetchColumn() === 1);
$bid = (int)$pdo->query("SELECT id FROM appetite_breaches WHERE statement_id=$sid")->fetchColumn();
$c['risk']->post("/appetite/breaches/$bid", ['status' => 'resolved', 'resolution_note' => ''], '/appetite'); check('resolving a breach without a note rejected', $pdo->query("SELECT status FROM appetite_breaches WHERE id=$bid")->fetchColumn() === 'open');
$c['risk']->post("/appetite/breaches/$bid", ['status' => 'escalated', 'resolution_note' => ''], '/appetite'); check('escalating a breach works', $pdo->query("SELECT status FROM appetite_breaches WHERE id=$bid")->fetchColumn() === 'escalated');
$c['risk']->post('/appetite/evaluate', [], '/appetite'); check('escalated breach still counts as open (no duplicate)', (int)$pdo->query("SELECT COUNT(*) FROM appetite_breaches WHERE statement_id=$sid")->fetchColumn() === 1);
$c['risk']->post("/appetite/breaches/$bid", ['status' => 'accepted', 'resolution_note' => 'Accepted by committee (test)'], '/appetite'); check('accepting with a note sets resolved_on', $pdo->query("SELECT resolved_on FROM appetite_breaches WHERE id=$bid")->fetchColumn() === date('Y-m-d'));
$c['risk']->post("/appetite/$sid/breaches", ['detected_on' => date('Y-m-d'), 'description' => 'Manual breach test'], '/appetite'); check('manual breach recorded', (int)$pdo->query("SELECT COUNT(*) FROM appetite_breaches WHERE statement_id=$sid AND source='manual'")->fetchColumn() === 1);
$c['risk']->post('/appetite', $af(['title' => 'Loss cap tiny', 'metric' => 'max_net_loss', 'limit_value' => '1']), '/appetite/new'); $sid2 = (int)$pdo->query('SELECT MAX(id) FROM appetite_statements')->fetchColumn();
$c['risk']->post('/appetite', $af(['title' => 'Loss cap huge', 'metric' => 'max_net_loss', 'limit_value' => '999,999,999,999']), '/appetite/new'); $sid3 = (int)$pdo->query('SELECT MAX(id) FROM appetite_statements')->fetchColumn();
$pg = $c['risk']->get('/appetite')['body']; check('net-loss statement: tiny cap breached, huge cap within appetite', (bool)preg_match('/Loss cap tiny.*?badge danger/s', $pg) && (bool)preg_match('/Loss cap huge.*?badge ok/s', $pg));
check('appetite: exec sees but cannot modify (403)', $c['exec']->get('/appetite')['code'] === 200 && $c['exec']->post('/appetite/evaluate', [], '/appetite')['code'] === 403 && $c['exec']->post("/appetite/breaches/$bid", ['status' => 'open'], '/appetite')['code'] === 403);
check('appetite: dept manager no access (403)', $c['retail']->get('/appetite')['code'] === 403);

section('Phase 2: compliance obligations');
$of = fn(array $o = []) => $o + ['title' => 'Submit quarterly AML return', 'source' => 'Central Bank circular (test ref 12/2026)', 'description' => 'd', 'department_id' => $deptRet, 'owner_user_id' => $uid('demo.dept.retail'), 'frequency' => 'quarterly', 'due_date' => date('Y-m-d', strtotime('-3 days'))];
$n = (int)$pdo->query('SELECT COUNT(*) FROM compliance_obligations')->fetchColumn();
$c['compliance']->post('/compliance', $of(['due_date' => 'bad']), '/compliance/new'); $c['compliance']->post('/compliance', $of(['source' => '']), '/compliance/new'); $c['compliance']->post('/compliance', $of(['frequency' => 'hourly']), '/compliance/new');
check('obligation: invalid input rejected', (int)$pdo->query('SELECT COUNT(*) FROM compliance_obligations')->fetchColumn() === $n);
$c['compliance']->post('/compliance', $of(), '/compliance/new'); $oid = (int)$pdo->query('SELECT MAX(id) FROM compliance_obligations')->fetchColumn();
$c['compliance']->post('/compliance', $of(['title' => 'IT-owned obligation', 'department_id' => $deptIt, 'owner_user_id' => $uid('demo.dept.it'), 'frequency' => 'once']), '/compliance/new'); $oid2 = (int)$pdo->query('SELECT MAX(id) FROM compliance_obligations')->fetchColumn();
$c['compliance']->post('/compliance', $of(['title' => '=HYPERLINK("http://evil","x")', 'department_id' => $deptRet]), '/compliance/new');
check('obligation created (code OBL-0000)', preg_match('/^OBL-\d{4}$/', $pdo->query("SELECT code FROM compliance_obligations WHERE id=$oid")->fetchColumn()) === 1);
check('retail manager sees own-dept obligation, not IT one (404)', $c['retail']->get("/compliance/$oid")['code'] === 200 && $c['retail']->get("/compliance/$oid2")['code'] === 404 && !str_contains($c['retail']->get('/compliance')['body'], "/compliance/$oid2\""));
check('retail manager cannot edit definition (403)', $c['retail']->get("/compliance/$oid/edit")['code'] === 403 && $c['retail']->post("/compliance/$oid", $of(['title' => 'hacked']), "/compliance/$oid")['code'] === 403);
check('overdue filter lists overdue obligation', str_contains($c['retail']->get('/compliance?overdue=1')['body'], "/compliance/$oid\""));
$sst = fn(Client $cl, int $id, array $o) => $cl->post("/compliance/$id/status", $o, "/compliance/$id");
$sst($c['retail'], $oid, ['status' => 'non_compliant', 'status_note' => '']); check('non-compliant without note rejected', $pdo->query("SELECT status FROM compliance_obligations WHERE id=$oid")->fetchColumn() === 'not_started');
$sst($c['retail'], $oid, ['status' => 'compliant', 'status_note' => '']); check('compliant without evidence or note rejected', $pdo->query("SELECT status FROM compliance_obligations WHERE id=$oid")->fetchColumn() === 'not_started');
$sst($c['retail'], $oid2, ['status' => 'compliant', 'status_note' => 'x']); check('retail manager cannot update foreign-department obligation', $pdo->query("SELECT status FROM compliance_obligations WHERE id=$oid2")->fetchColumn() === 'not_started');
$upl = fn(Client $cl, int $id, string $name, string $content, string $mime) => $cl->post("/compliance/$id/attachments", [], "/compliance/$id", ['file' => [$name, $content, $mime]]);
$a0 = (int)$pdo->query("SELECT COUNT(*) FROM attachments WHERE entity_type='obligation'")->fetchColumn();
$upl($c['retail'], $oid, 'evil.php', '<?php echo 1;', 'application/x-php'); check('evidence upload: .php rejected', (int)$pdo->query("SELECT COUNT(*) FROM attachments WHERE entity_type='obligation'")->fetchColumn() === $a0);
$upl($c['retail'], $oid, 'proof.pdf', $pdf, 'application/pdf'); check('evidence upload: pdf accepted for own department', (int)$pdo->query("SELECT COUNT(*) FROM attachments WHERE entity_type='obligation'")->fetchColumn() === $a0 + 1);
$upl($c['retail'], $oid2, 'x.pdf', $pdf, 'application/pdf'); check('evidence upload to foreign-department obligation refused', (int)$pdo->query("SELECT COUNT(*) FROM attachments WHERE entity_type='obligation'")->fetchColumn() === $a0 + 1);
check('exec viewer cannot upload evidence (403)', $upl($c['exec'], $oid, 'x.pdf', $pdf, 'application/pdf')['code'] === 403);
$sst($c['retail'], $oid, ['status' => 'compliant', 'status_note' => '']); check('compliant accepted once evidence exists; last_reviewed set', $pdo->query("SELECT status FROM compliance_obligations WHERE id=$oid")->fetchColumn() === 'compliant' && $pdo->query("SELECT last_reviewed_on FROM compliance_obligations WHERE id=$oid")->fetchColumn() === date('Y-m-d'));
$aid = (int)$pdo->query("SELECT MAX(id) FROM attachments WHERE entity_type='obligation'")->fetchColumn();
check('evidence download: in-scope roles OK, out-of-scope 404, anonymous -> login', $c['auditor']->get("/attachments/$aid")['code'] === 200 && $c['it']->get("/attachments/$aid")['code'] === 404 && $c['tripoli']->get("/attachments/$aid")['code'] === 404 && str_contains((string)(new Client($B))->get("/attachments/$aid")['loc'], '/login'));
$before = $pdo->query("SELECT due_date FROM compliance_obligations WHERE id=$oid")->fetchColumn();
$c['compliance']->post("/compliance/$oid/roll", [], "/compliance/$oid"); $after = $pdo->query("SELECT due_date, status FROM compliance_obligations WHERE id=$oid")->fetch();
check('roll-forward moves quarterly due date +3 months and resets status', $after['due_date'] === date('Y-m-d', strtotime("$before +3 months")) && $after['status'] === 'not_started');
$c['compliance']->post("/compliance/$oid2/roll", [], "/compliance/$oid2"); check('one-off obligation cannot be rolled', $pdo->query("SELECT status FROM compliance_obligations WHERE id=$oid2")->fetchColumn() === 'not_started');
check('retail manager cannot roll/archive (403)', $c['retail']->post("/compliance/$oid/roll", [], "/compliance/$oid")['code'] === 403 && $c['retail']->post("/compliance/$oid/archive", [], "/compliance/$oid")['code'] === 403);
$c['compliance']->post("/compliance/$oid2/archive", [], "/compliance/$oid2"); check('archive hides from default list', !str_contains($c['compliance']->get('/compliance')['body'], "/compliance/$oid2\"") && str_contains($c['compliance']->get('/compliance?archived=1')['body'], "/compliance/$oid2\""));
$csv = $c['retail']->get('/compliance/export'); $rows = array_map('str_getcsv', explode("\r\n", trim(substr($csv['body'], 3))));
check('compliance CSV: BOM, scoped to department, formula-safe', str_starts_with($csv['body'], "\xEF\xBB\xBF") && !str_contains($csv['body'], 'IT-owned obligation') && in_array("'=HYPERLINK(\"http://evil\",\"x\")", array_column($rows, 1), true));
$od = (int)$pdo->query("SELECT COUNT(*) FROM compliance_obligations o WHERE o.archived_at IS NULL AND o.status IN ('not_started','in_progress','non_compliant') AND o.due_date < CURDATE()")->fetchColumn();
preg_match_all('#<div class="v"[^>]*>([^<]+)</div>#', $admin2->get('/')['body'], $mm);
check('dashboard tile: overdue obligations = DB', in_array((string)$od, $mm[1], true), "db=$od");

section('Phase 2: committee decisions');
$df = fn(array $o = []) => $o + ['committee' => 'board_risk', 'meeting_date' => date('Y-m-d', strtotime('-20 days')), 'title' => 'Approve revised liquidity limits', 'decision_text' => 'The committee approved …', 'status' => 'open'];
$n = (int)$pdo->query('SELECT COUNT(*) FROM committee_decisions')->fetchColumn();
$c['risk']->post('/committees', $df(['committee' => 'nope']), '/committees/new'); $c['risk']->post('/committees', $df(['decision_text' => '']), '/committees/new'); $c['risk']->post('/committees', $df(['meeting_date' => '2999-01-01']), '/committees/new');
check('decision: invalid input rejected', (int)$pdo->query('SELECT COUNT(*) FROM committee_decisions')->fetchColumn() === $n);
$c['risk']->post('/committees', $df(), '/committees/new'); $did = (int)$pdo->query('SELECT MAX(id) FROM committee_decisions')->fetchColumn(); check('decision recorded (DEC-0000)', preg_match('/^DEC-\d{4}$/', $pdo->query("SELECT code FROM committee_decisions WHERE id=$did")->fetchColumn()) === 1);
$c['risk']->post("/committees/$did/actions", ['title' => 'Update ALM policy', 'owner_user_id' => $uid('demo.risk'), 'deadline' => date('Y-m-d', strtotime('-2 days')), 'status' => 'in_progress'], "/committees/$did");
$c['risk']->post("/committees/$did/actions", ['title' => '', 'owner_user_id' => $uid('demo.risk'), 'deadline' => date('Y-m-d'), 'status' => 'open'], "/committees/$did");
check('follow-up action added; invalid one rejected', (int)$pdo->query("SELECT COUNT(*) FROM decision_actions WHERE decision_id=$did")->fetchColumn() === 1);
check('overdue filter + list badge', str_contains($c['risk']->get('/committees?overdue=1')['body'], "/committees/$did\"") && str_contains($c['exec']->get("/committees/$did")['body'], 'badge danger'));
$daid = (int)$pdo->query("SELECT MAX(id) FROM decision_actions")->fetchColumn();
$c['risk']->post("/committees/$did/actions/$daid", ['owner_user_id' => $uid('demo.risk'), 'deadline' => date('Y-m-d', strtotime('-2 days')), 'status' => 'completed'], "/committees/$did");
check('completing a follow-up sets completed_on and clears overdue', $pdo->query("SELECT completed_on FROM decision_actions WHERE id=$daid")->fetchColumn() === date('Y-m-d') && !str_contains($c['risk']->get('/committees?overdue=1')['body'], "/committees/$did\""));
check('exec/auditor read-only; dept manager blocked', $c['exec']->post("/committees/$did/actions", ['title' => 'x'], "/committees/$did")['code'] === 403 && $c['auditor']->get("/committees/$did/edit")['code'] === 403 && $c['retail']->get("/committees/$did")['code'] === 403);
check('Arabic search of decisions works', (function () use ($c, $df) { $c['risk']->post('/committees', $df(['title' => 'قرار اعتماد سياسة السيولة', 'committee' => 'sharia_board']), '/committees/new'); return str_contains($c['risk']->get('/committees?q=' . urlencode('سياسة السيولة'))['body'], 'قرار اعتماد'); })());

section('Phase 2: policies and versions');
$pf = fn(array $o = []) => $o + ['title' => 'Credit Risk Policy', 'category' => 'policy', 'department_id' => '', 'owner_user_id' => $uid('demo.compliance'), 'review_months' => '12'];
$n = (int)$pdo->query('SELECT COUNT(*) FROM policies')->fetchColumn();
$c['compliance']->post('/policies', $pf(['review_months' => '0']), '/policies/new'); $c['compliance']->post('/policies', $pf(['category' => 'x']), '/policies/new'); check('policy: invalid input rejected', (int)$pdo->query('SELECT COUNT(*) FROM policies')->fetchColumn() === $n);
$c['compliance']->post('/policies', $pf(), '/policies/new'); $pid = (int)$pdo->query('SELECT MAX(id) FROM policies')->fetchColumn(); check('policy created draft (POL-000)', $pdo->query("SELECT status FROM policies WHERE id=$pid")->fetchColumn() === 'draft');
$vf = fn(array $o = []) => $o + ['version_label' => 'v1.0', 'effective_date' => '2026-01-15', 'approved_by' => 'Board resolution 5/2026 (test)', 'approval_date' => '2026-01-10', 'change_summary' => 'Initial issue'];
$pv = fn(Client $cl, int $id, array $o, array $files = []) => $cl->post("/policies/$id/versions", $o, "/policies/$id", $files);
$pv($c['compliance'], $pid, $vf(['effective_date' => '']), []); check('version: missing effective date rejected', (int)$pdo->query("SELECT COUNT(*) FROM policy_versions WHERE policy_id=$pid")->fetchColumn() === 0);
$pv($c['compliance'], $pid, $vf(), ['file' => ['shell.php', '<?php echo 1;', 'application/x-php']]); check('version with a .php file rejected atomically (no version row)', (int)$pdo->query("SELECT COUNT(*) FROM policy_versions WHERE policy_id=$pid")->fetchColumn() === 0);
$pv($c['compliance'], $pid, $vf(), ['file' => ['policy-v1.pdf', $pdf, 'application/pdf']]);
$pol = $pdo->query("SELECT * FROM policies WHERE id=$pid")->fetch();
check('v1 added: current, policy approved, next review = effective + 12 months', (int)$pdo->query("SELECT COUNT(*) FROM policy_versions WHERE policy_id=$pid AND is_current=1")->fetchColumn() === 1 && $pol['status'] === 'approved' && $pol['next_review_date'] === '2027-01-15');
$vid1 = (int)$pdo->query("SELECT id FROM policy_versions WHERE policy_id=$pid AND version_label='v1.0'")->fetchColumn();
check('version document stored as attachment of the version', (int)$pdo->query("SELECT COUNT(*) FROM attachments WHERE entity_type='policy_version' AND entity_id=$vid1")->fetchColumn() === 1);
$pv($c['compliance'], $pid, $vf(), []); check('duplicate version label rejected', (int)$pdo->query("SELECT COUNT(*) FROM policy_versions WHERE policy_id=$pid")->fetchColumn() === 1);
$pv($c['compliance'], $pid, $vf(['version_label' => 'v1.1', 'effective_date' => '2026-06-01', 'change_summary' => 'Amended limits']), []);
check('v1.1 becomes current; v1.0 kept as history (immutable)', (int)$pdo->query("SELECT COUNT(*) FROM policy_versions WHERE policy_id=$pid")->fetchColumn() === 2 && $pdo->query("SELECT version_label FROM policy_versions WHERE policy_id=$pid AND is_current=1")->fetchColumn() === 'v1.1' && $pdo->query("SELECT change_summary FROM policy_versions WHERE id=$vid1")->fetchColumn() === 'Initial issue');
$show = $c['tripoli']->get("/policies/$pid"); check('everyone can read policies; version history displayed', $show['code'] === 200 && str_contains($show['body'], 'v1.0') && str_contains($show['body'], 'v1.1') && str_contains($show['body'], 'Amended limits'));
$paid = (int)$pdo->query("SELECT MAX(id) FROM attachments WHERE entity_type='policy_version'")->fetchColumn();
check('policy document downloadable by branch liaison; anonymous blocked', $c['tripoli']->get("/attachments/$paid")['body'] === $pdf && str_contains((string)(new Client($B))->get("/attachments/$paid")['loc'], '/login'));
check('branch liaison cannot add versions / delete attachments (403/404)', $pv($c['tripoli'], $pid, $vf(['version_label' => 'x']), [])['code'] === 403 && in_array($c['tripoli']->post("/attachments/$paid/delete", [], "/policies/$pid")['code'], [403, 404], true) && $pdo->query("SELECT deleted_at FROM attachments WHERE id=$paid")->fetchColumn() === null);
$c['compliance']->post("/policies/$pid", $pf(['status' => 'retired']), "/policies/$pid/edit"); check('policy can be retired; review-overdue filter excludes retired', $pdo->query("SELECT status FROM policies WHERE id=$pid")->fetchColumn() === 'retired' && !str_contains($c['compliance']->get('/policies?review_overdue=1')['body'], "/policies/$pid\""));
$c['compliance']->post("/policies/$pid", $pf(['status' => 'approved']), "/policies/$pid/edit");
$pdo->exec("UPDATE policies SET next_review_date = '2020-01-01' WHERE id=$pid");
check('review-overdue filter finds policy past next review date', str_contains($c['compliance']->get('/policies?review_overdue=1')['body'], "/policies/$pid\""));

section('Phase 2: RCSA workflow');
$tq = fn(array $o = []) => $o + ['title' => 'Branch operations self-assessment', 'description' => 'd', 'q_en' => ['Is dual control applied to cash?', 'Are KYC files complete?'], 'q_ar' => ['هل تطبق الرقابة الثنائية على النقد؟', 'هل ملفات اعرف عميلك مكتملة؟'], 'q_cat' => ['', '']];
$n = (int)$pdo->query('SELECT COUNT(*) FROM rcsa_templates')->fetchColumn();
$c['risk']->post('/rcsa/templates', $tq(['q_ar' => ['', 'x']]), '/rcsa/templates/new'); $c['risk']->post('/rcsa/templates', $tq(['q_en' => [''], 'q_ar' => [''], 'q_cat' => ['']]), '/rcsa/templates/new'); check('template: missing Arabic text / no questions rejected', (int)$pdo->query('SELECT COUNT(*) FROM rcsa_templates')->fetchColumn() === $n);
check('compliance officer cannot create templates (403)', $c['compliance']->post('/rcsa/templates', $tq(), '/rcsa/templates/new')['code'] === 403);
$c['risk']->post('/rcsa/templates', $tq(), '/rcsa/templates/new'); $tid = (int)$pdo->query('SELECT MAX(id) FROM rcsa_templates')->fetchColumn(); check('template with 2 bilingual questions created', (int)$pdo->query("SELECT COUNT(*) FROM rcsa_questions WHERE template_id=$tid")->fetchColumn() === 2);
$lf = fn(array $o = []) => $o + ['template_id' => $tid, 'department_id' => $deptRet, 'assessor_user_id' => $uid('demo.dept.retail'), 'due_date' => date('Y-m-d', strtotime('-1 day'))];
$nA = (int)$pdo->query('SELECT COUNT(*) FROM rcsa_assessments')->fetchColumn(); $c['risk']->post('/rcsa/launch', $lf(['assessor_user_id' => 99999]), '/rcsa/launch'); $c['risk']->post('/rcsa/launch', $lf(['due_date' => 'x']), '/rcsa/launch'); check('launch: invalid assessor/date rejected', (int)$pdo->query('SELECT COUNT(*) FROM rcsa_assessments')->fetchColumn() === $nA);
$c['risk']->post('/rcsa/launch', $lf(), '/rcsa/launch'); $rid2 = (int)$pdo->query('SELECT MAX(id) FROM rcsa_assessments')->fetchColumn();
check('assessment launched with one empty answer per question (RCSA-0000)', (int)$pdo->query("SELECT COUNT(*) FROM rcsa_answers WHERE assessment_id=$rid2")->fetchColumn() === 2 && preg_match('/^RCSA-\d{4}$/', $pdo->query("SELECT code FROM rcsa_assessments WHERE id=$rid2")->fetchColumn()) === 1);
check('scope: retail manager sees it; IT manager 404; Tripoli liaison 404 (no branch)', $c['retail']->get("/rcsa/$rid2")['code'] === 200 && $c['it']->get("/rcsa/$rid2")['code'] === 404 && $c['tripoli']->get("/rcsa/$rid2")['code'] === 404 && !str_contains($c['it']->get('/rcsa')['body'], "/rcsa/$rid2\""));
$qids = array_column($pdo->query("SELECT id FROM rcsa_questions WHERE template_id=$tid ORDER BY id")->fetchAll(), 'id');
$ans = fn(array $r, array $cm = []) => ['rating' => [$qids[0] => $r[0], $qids[1] => $r[1]], 'comment' => [$qids[0] => $cm[0] ?? '', $qids[1] => $cm[1] ?? '']];
$c['retail']->post("/rcsa/$rid2/submit", $ans(['effective', '']), "/rcsa/$rid2"); check('submit with an unanswered question rejected', $pdo->query("SELECT status FROM rcsa_assessments WHERE id=$rid2")->fetchColumn() === 'draft');
$c['retail']->post("/rcsa/$rid2/submit", $ans(['effective', 'partially_effective']), "/rcsa/$rid2"); check('submit: partially effective without comment rejected', $pdo->query("SELECT status FROM rcsa_assessments WHERE id=$rid2")->fetchColumn() === 'draft');
$c['retail']->post("/rcsa/$rid2/save", $ans(['effective', 'partially_effective'], ['', 'draft note']), "/rcsa/$rid2"); check('draft saved without full validation', $pdo->query("SELECT rating FROM rcsa_answers WHERE assessment_id=$rid2 AND question_id={$qids[1]}")->fetchColumn() === 'partially_effective');
$c['retail']->post("/rcsa/$rid2/save", ['rating' => [$qids[0] => 'bogus']], "/rcsa/$rid2"); check('invalid rating value rejected', $pdo->query("SELECT rating FROM rcsa_answers WHERE assessment_id=$rid2 AND question_id={$qids[0]}")->fetchColumn() === 'effective');
$foreignRiskId = (int)$pdo->query("SELECT MIN(id) FROM risks WHERE department_id<>$deptRet")->fetchColumn();
$c['retail']->post("/rcsa/$rid2/save", ['rating' => [$qids[0] => 'effective'], 'risk' => [$qids[0] => $foreignRiskId]], "/rcsa/$rid2"); check('linking a risk outside the user scope rejected', $pdo->query("SELECT risk_id FROM rcsa_answers WHERE assessment_id=$rid2 AND question_id={$qids[0]}")->fetchColumn() === null);
check('IT manager / exec cannot respond (404/403)', $c['it']->post("/rcsa/$rid2/save", $ans(['effective', 'effective']), "/rcsa/$rid2")['code'] === 404 && $c['exec']->post("/rcsa/$rid2/save", $ans(['effective', 'effective']), '/rcsa')['code'] === 403);
$c['retail']->post("/rcsa/$rid2/submit", $ans(['effective', 'partially_effective'], ['', 'KYC gaps remain']), "/rcsa/$rid2"); check('submit succeeds when complete', $pdo->query("SELECT status FROM rcsa_assessments WHERE id=$rid2")->fetchColumn() === 'submitted');
check('dashboard shows RCSA awaiting review (= DB)', (function () use ($admin2, $pdo) { $n = (int)$pdo->query("SELECT COUNT(*) FROM rcsa_assessments WHERE status='submitted'")->fetchColumn(); preg_match_all('#<div class="v"[^>]*>([^<]+)</div>#', $admin2->get('/')['body'], $m); return in_array((string)$n, $m[1], true) && $n >= 1; })());
check('submitted assessment is read-only for the assessor (403)', $c['retail']->post("/rcsa/$rid2/save", $ans(['ineffective', 'ineffective']), "/rcsa/$rid2")['code'] === 403);
check('assessor cannot review (403)', $c['retail']->post("/rcsa/$rid2/review", ['decision' => 'approve'], "/rcsa/$rid2")['code'] === 403);
$c['risk']->post("/rcsa/$rid2/review", ['decision' => 'return', 'review_comment' => ''], "/rcsa/$rid2"); check('returning without a comment rejected', $pdo->query("SELECT status FROM rcsa_assessments WHERE id=$rid2")->fetchColumn() === 'submitted');
$c['risk']->post("/rcsa/$rid2/review", ['decision' => 'return', 'review_comment' => 'Explain the KYC gap'], "/rcsa/$rid2"); check('returned with comment; assessor can edit again', $pdo->query("SELECT status FROM rcsa_assessments WHERE id=$rid2")->fetchColumn() === 'returned' && str_contains($c['retail']->get("/rcsa/$rid2")['body'], 'Explain the KYC gap'));
$c['retail']->post("/rcsa/$rid2/submit", $ans(['effective', 'effective']), "/rcsa/$rid2"); check('resubmitted', $pdo->query("SELECT status FROM rcsa_assessments WHERE id=$rid2")->fetchColumn() === 'submitted');
$c['risk']->post("/rcsa/$rid2/review", ['decision' => 'approve'], "/rcsa/$rid2"); $ra = $pdo->query("SELECT status, reviewer_user_id FROM rcsa_assessments WHERE id=$rid2")->fetch(); check('approved by reviewer (recorded)', $ra['status'] === 'approved' && (int)$ra['reviewer_user_id'] === $uid('demo.risk'));
check('approved assessment locked', $c['retail']->post("/rcsa/$rid2/save", $ans(['ineffective', 'ineffective']), "/rcsa/$rid2")['code'] === 403 && $c['risk']->post("/rcsa/$rid2/review", ['decision' => 'return', 'review_comment' => 'x'], "/rcsa/$rid2")['code'] === 302);
check('audit trail for RCSA lifecycle', (int)$pdo->query("SELECT COUNT(DISTINCT action) FROM audit_log WHERE action IN ('rcsa.launched','rcsa.saved','rcsa.submitted','rcsa.returned','rcsa.approved')")->fetchColumn() === 5);
// self review
$c['risk']->post('/rcsa/launch', $lf(['assessor_user_id' => $uid('demo.risk')]), '/rcsa/launch'); $rid3 = (int)$pdo->query('SELECT MAX(id) FROM rcsa_assessments')->fetchColumn();
$pdo->exec("UPDATE rcsa_assessments SET status='submitted' WHERE id=$rid3");
$c['risk']->post("/rcsa/$rid3/review", ['decision' => 'approve'], "/rcsa/$rid3"); check('assessor cannot review their own assessment (segregation)', $pdo->query("SELECT status FROM rcsa_assessments WHERE id=$rid3")->fetchColumn() === 'submitted');
$c['risk']->post("/rcsa/templates/$tid", ['title' => 'Branch operations self-assessment', 'description' => 'd', 'is_active' => '1', 'q_en' => ['New question?'], 'q_ar' => ['سؤال جديد؟'], 'q_cat' => ['']], "/rcsa/templates/$tid/edit");
check('adding a question to a template adds empty answer rows to open drafts only', (int)$pdo->query("SELECT COUNT(*) FROM rcsa_questions WHERE template_id=$tid")->fetchColumn() === 3 && (int)$pdo->query("SELECT COUNT(*) FROM rcsa_answers WHERE assessment_id=$rid2")->fetchColumn() === 2);

section('Phase 2: governance dashboard tiles = database');
$gd = $admin2->get('/')['body']; preg_match_all('#<div class="v"[^>]*>([^<]+)</div>#', $gd, $mm); $vals = $mm[1];
$latest = "JOIN kri_readings lr ON lr.id = (SELECT x.id FROM kri_readings x WHERE x.kri_id = k.id ORDER BY x.reading_date DESC, x.id DESC LIMIT 1)";
$exp = [
    'KRI red' => (int)$pdo->query("SELECT COUNT(*) FROM kris k $latest WHERE k.is_active=1 AND lr.status='red'")->fetchColumn(),
    'KRI amber' => (int)$pdo->query("SELECT COUNT(*) FROM kris k $latest WHERE k.is_active=1 AND lr.status='amber'")->fetchColumn(),
    'open breaches' => (int)$pdo->query("SELECT COUNT(*) FROM appetite_breaches WHERE status IN ('open','escalated')")->fetchColumn(),
    'decision actions overdue' => (int)$pdo->query("SELECT COUNT(*) FROM decision_actions WHERE status IN ('not_started','in_progress') AND deadline < CURDATE()")->fetchColumn(),
    'policies overdue' => (int)$pdo->query("SELECT COUNT(*) FROM policies WHERE status <> 'retired' AND next_review_date < CURDATE()")->fetchColumn(),
    'rcsa overdue' => (int)$pdo->query("SELECT COUNT(*) FROM rcsa_assessments WHERE status IN ('draft','returned') AND due_date < CURDATE()")->fetchColumn(),
];
$tileOrder = array_slice($vals, 6, 8); // after the 6 phase-1 KPIs: kri_red, kri_amber, breaches, obl_overdue, dec_overdue, rcsa_review, rcsa_overdue, pol_overdue
check('governance tiles: KRI red/amber equal DB', (int)$tileOrder[0] === $exp['KRI red'] && (int)$tileOrder[1] === $exp['KRI amber'], json_encode($tileOrder) . json_encode($exp));
check('governance tiles: breaches, committee, RCSA overdue, policies equal DB', (int)$tileOrder[2] === $exp['open breaches'] && (int)$tileOrder[4] === $exp['decision actions overdue'] && (int)$tileOrder[6] === $exp['rcsa overdue'] && (int)$tileOrder[7] === $exp['policies overdue']);
$tr = $c['retail']->get('/')['body']; check('retail manager dashboard shows only permitted governance tiles (no appetite/committee)', !str_contains($tr, 'appetite') && !str_contains($tr, '/committees?overdue=1') && str_contains($tr, '/compliance?overdue=1'));
check('liaison dashboard has no governance strip', !str_contains($c['tripoli']->get('/')['body'], 'kris?status'));
check('sidebar shows only permitted modules', str_contains($c['retail']->get('/')['body'], 'href="/policies"') && !str_contains($c['retail']->get('/')['body'], 'href="/appetite"') && str_contains($c['exec']->get('/')['body'], 'href="/appetite"'));
check('Phase 2 actions are audited', (int)$pdo->query("SELECT COUNT(DISTINCT action) FROM audit_log WHERE action IN ('kri.created','kri.reading_added','appetite.created','appetite.breach_detected','compliance.created','compliance.status','committee.created','policy.version_added','rcsa.launched','export.compliance')")->fetchColumn() === 10);
check('audit log has no secrets after Phase 2 activity', (int)$pdo->query("SELECT COUNT(*) FROM audit_log WHERE detail LIKE '%$DEMO_PW%' OR detail LIKE '%$ADMIN_PW%'")->fetchColumn() === 0);
