<?php
// Release 3 (usability foundation) tests. Included by tests/run.php after phase2.php (shares $B, $pdo, $admin, $c[], $DEMO_PW, $root).
section('Usability: help centre content');
$loadHelp = fn(string $f) => (function () use ($f) { return require $f; })();
$helpEn = $loadHelp($root . '/app/help/en.php'); $helpAr = $loadHelp($root . '/app/help/ar.php');
check('help: AR and EN have identical topic slugs', array_keys($helpEn['topics']) === array_keys($helpAr['topics']));
check('help: every topic has summary, steps and a matching step count in both languages', (function () use ($helpEn, $helpAr) { foreach ($helpEn['topics'] as $s => $t) { $a = $helpAr['topics'][$s]; if ($t['summary'] === '' || $a['summary'] === '' || !$t['steps'] || count($t['steps']) !== count($a['steps'])) return false; } return true; })());
check('help: page map and role quick-starts only reference existing topics', (function () use ($helpEn, $helpAr) { foreach ([$helpEn, $helpAr] as $h) { foreach ($h['pages'] as $s) if (!isset($h['topics'][$s])) return false; foreach ($h['roles'] as $r) foreach ($r['steps'] as [$s]) if (!isset($h['topics'][$s])) return false; } return array_keys($helpEn['roles']) === array_keys($helpAr['roles']) && array_keys($helpEn['roles']) === ['admin', 'risk_manager', 'compliance_officer', 'dept_manager', 'branch_liaison', 'exec_viewer', 'auditor']; })());
check('help: glossary same size in both languages', count($helpEn['glossary']) === count($helpAr['glossary']));
foreach (['risk' => 'risk_manager', 'compliance' => 'compliance_officer', 'retail' => 'dept_manager', 'tripoli' => 'branch_liaison', 'exec' => 'exec_viewer', 'auditor' => 'auditor'] as $k => $role) {
    $r = $c[$k]->get('/help'); check("help centre opens for $role", $r['code'] === 200 && str_contains($r['body'], 'help-card'));
    foreach (['/my', '/notes', '/help/panel?path=/risks', '/notes/panel'] as $p) check("$role GET $p = 200", $c[$k]->get($p)['code'] === 200);
}
check('anonymous cannot open help, notes or my tasks', (function () use ($B) { foreach (['/help', '/my', '/notes', '/help/panel', '/notes/panel'] as $p) { if (!str_contains((string)(new Client($B))->get($p)['loc'], '/login')) return false; } return true; })());
$h = $c['tripoli']->get('/help?t=admin'); check('role-restricted topic hidden from branch liaison (admin guide not shown)', !str_contains($h['body'], 'class="card help-topic"'));
$h = $c['risk']->get('/help?t=risks'); check('topic page shows steps', str_contains($h['body'], 'class="card help-topic"') && str_contains($h['body'], '<ol class="steps">'));
$pn = $c['risk']->get('/help/panel?path=' . urlencode('/kris/3')); check('context panel resolves page by longest prefix (KRI)', str_contains($pn['body'], 'panel-title') && str_contains($pn['body'], 'help?t=kris'));
$pn = $c['risk']->get('/help/panel?path=' . urlencode('/does-not-exist')); check('context panel falls back to getting-started', str_contains($pn['body'], 'help?t=start') && !str_contains($pn['body'], '<html'));
$pn = $c['risk']->get('/help/panel?path=' . urlencode('/risks/new')); check('panel fragment has no layout and no inline script', !str_contains($pn['body'], '<script') && !str_contains($pn['body'], '<body'));
$mainHtml = $c['risk']->get('/')['body'];
check('layout: no inline script (CSP script-src self)', !preg_match('#<script(?![^>]*\bsrc=)[^>]*>#', $mainHtml));
check('layout: help, notes and my-tasks buttons present', str_contains($mainHtml, 'data-panel="help"') && str_contains($mainHtml, 'data-panel="notes"') && str_contains($mainHtml, 'href="/my"'));
check('welcome card shows on first dashboard visit', str_contains($c['exec']->get('/')['body'], 'class="welcome card"'));
$c['exec']->post('/help/dismiss', ['key' => 'welcome_seen'], '/');
check('welcome card hidden after dismiss and stays hidden', !str_contains($c['exec']->get('/')['body'], 'class="welcome card"'));
$before = (int)$pdo->query('SELECT COUNT(*) FROM user_prefs')->fetchColumn(); $c['auditor']->post('/help/dismiss', ['key' => 'arbitrary_key'], '/');
check('dismiss ignores unknown preference keys', (int)$pdo->query('SELECT COUNT(*) FROM user_prefs')->fetchColumn() === $before);

section('Usability: notes and reminders');
$uidN = fn($n) => (int)$pdo->query("SELECT id FROM users WHERE username='$n'")->fetchColumn();
$nc = fn() => (int)$pdo->query('SELECT COUNT(*) FROM user_notes')->fetchColumn();
$n0 = $nc();
$c['risk']->post('/notes', ['body' => '', 'page_path' => '/risks'], '/notes'); check('empty note rejected', $nc() === $n0);
$c['risk']->post('/notes', ['body' => str_repeat('x', 501)], '/notes'); check('501-character note rejected', $nc() === $n0);
$c['risk']->post('/notes', ['body' => 'past', 'remind_on' => '2001-01-01'], '/notes'); check('past reminder date rejected', $nc() === $n0);
$c['risk']->post('/notes', ['body' => 'bad', 'remind_on' => '2026-02-31'], '/notes'); check('impossible reminder date rejected', $nc() === $n0);
$c['risk']->post('/notes', ['body' => 'Check IT <b>risks</b> with Ali', 'page_path' => '/risks?status=active'], '/notes');
check('note saved for current user with page link', $nc() === $n0 + 1 && (int)$pdo->query('SELECT user_id FROM user_notes ORDER BY id DESC LIMIT 1')->fetchColumn() === $uidN('demo.risk') && $pdo->query('SELECT page_path FROM user_notes ORDER BY id DESC LIMIT 1')->fetchColumn() === '/risks?status=active');
$nid = (int)$pdo->query('SELECT MAX(id) FROM user_notes')->fetchColumn();
check('note text is HTML-escaped on output', str_contains($c['risk']->get('/notes')['body'], 'Check IT &lt;b&gt;risks&lt;/b&gt; with Ali') && !str_contains($c['risk']->get('/notes')['body'], '<b>risks</b>'));
foreach (['//evil.example/x', 'https://evil.example/', 'javascript:alert(1)', "/ok\\evil"] as $bad) { $c['risk']->post('/notes', ['body' => 'link test', 'page_path' => $bad], '/notes'); check("unsafe page_path '$bad' not stored", $pdo->query('SELECT page_path FROM user_notes ORDER BY id DESC LIMIT 1')->fetchColumn() === null); }
$tomorrow = date('Y-m-d', strtotime('+1 day'));
$c['risk']->post('/notes', ['body' => 'future reminder', 'remind_on' => $tomorrow], '/notes'); check('future reminder accepted', $pdo->query('SELECT remind_on FROM user_notes ORDER BY id DESC LIMIT 1')->fetchColumn() === $tomorrow);
check('notes are private: other user gets 404 on toggle and delete', $c['compliance']->post("/notes/$nid/done", [], '/notes')['code'] === 404 && $c['compliance']->post("/notes/$nid/delete", [], '/notes')['code'] === 404 && !str_contains($c['compliance']->get('/notes')['body'], 'Check IT'));
check('admin cannot see another user\'s notes either', !str_contains($admin->get('/notes')['body'], 'Check IT'));
$pdo->exec("INSERT INTO user_notes (user_id, body, remind_on, created_at) VALUES (" . $uidN('demo.risk') . ", 'DUE-NOTE-MARKER', CURDATE(), NOW())");
$dueId = (int)$pdo->query('SELECT MAX(id) FROM user_notes')->fetchColumn();
check('due reminder appears in My tasks and the badge counts it', str_contains($c['risk']->get('/my')['body'], 'DUE-NOTE-MARKER') && str_contains($c['risk']->get('/')['body'], 'badge-count'));
check('due reminder not shown to another user', !str_contains($c['compliance']->get('/my')['body'], 'DUE-NOTE-MARKER'));
$c['risk']->post("/notes/$dueId/done", ['next' => 'my'], '/my'); check('mark done removes it from My tasks', !str_contains($c['risk']->get('/my')['body'], 'DUE-NOTE-MARKER') && (int)$pdo->query("SELECT is_done FROM user_notes WHERE id=$dueId")->fetchColumn() === 1);
$c['risk']->post("/notes/$dueId/done", [], '/notes'); check('reopen works', (int)$pdo->query("SELECT is_done FROM user_notes WHERE id=$dueId")->fetchColumn() === 0);
$c['risk']->post("/notes/$dueId/delete", [], '/notes'); check('delete own note', !$pdo->query("SELECT 1 FROM user_notes WHERE id=$dueId")->fetchColumn());
check('note POST without CSRF token is rejected', (function () use ($B) { $cl = new Client($B); $cl->login('demo.risk', $GLOBALS['DEMO_PW']); $r = $cl->req('POST', '/notes', ['body' => 'no csrf']); return $r['code'] === 419; })());

section('Usability: My tasks');
$ownerId = $uidN('demo.dept.retail');
$rid = (int)$pdo->query('SELECT id FROM risks WHERE archived_at IS NULL AND department_id = (SELECT department_id FROM users WHERE id=' . $ownerId . ') LIMIT 1')->fetchColumn();
$pdo->exec("INSERT INTO risk_actions (risk_id, title, owner_user_id, deadline, status, created_by, created_at, updated_at) VALUES ($rid, 'MYTASK-OVERDUE-ACTION', $ownerId, DATE_SUB(CURDATE(), INTERVAL 3 DAY), 'in_progress', $ownerId, NOW(), NOW())");
$mt = $c['retail']->get('/my')['body'];
check('assigned overdue action listed in My tasks with overdue style', str_contains($mt, 'MYTASK-OVERDUE-ACTION') && str_contains($mt, 'class="overdue"'));
check('others do not see it in their My tasks', !str_contains($c['compliance']->get('/my')['body'], 'MYTASK-OVERDUE-ACTION'));
$pdo->exec("UPDATE risk_actions SET status='completed', completed_on=CURDATE() WHERE title='MYTASK-OVERDUE-ACTION'");
check('completed action leaves My tasks', !str_contains($c['retail']->get('/my')['body'], 'MYTASK-OVERDUE-ACTION'));

section('Controls: authorization matrix');
$cm = [
    '/controls' => ['risk' => 200, 'compliance' => 200, 'retail' => 200, 'tripoli' => 403, 'exec' => 200, 'auditor' => 200],
    '/controls/new' => ['risk' => 200, 'compliance' => 200, 'retail' => 403, 'tripoli' => 403, 'exec' => 403, 'auditor' => 403],
];
foreach ($cm as $p => $exp) foreach ($exp as $role => $code) { $r = $c[$role]->get($p); check("controls: $role GET $p = $code", $r['code'] === $code, (string)$r['code']); }
check('anonymous /controls -> login', str_contains((string)(new Client($B))->get('/controls')['loc'], '/login'));

section('Controls: library, linking and testing');
$deptIt = (int)$pdo->query("SELECT id FROM departments WHERE code='IT'")->fetchColumn(); $deptRet = (int)$pdo->query("SELECT id FROM departments WHERE code='RET'")->fetchColumn();
$cF = fn(array $o = []) => $o + ['name' => 'Segregation of duties in payments', 'description' => 'Initiator and approver differ', 'department_id' => $deptIt, 'owner_user_id' => $uidN('demo.dept.it'), 'control_type' => 'preventive', 'nature' => 'manual', 'frequency' => 'daily', 'test_frequency' => 'annual', 'is_key' => '1'];
$cc = fn() => (int)$pdo->query('SELECT COUNT(*) FROM controls')->fetchColumn();
$n = $cc();
$c['risk']->post('/controls', $cF(['name' => '']), '/controls/new'); check('control without name rejected', $cc() === $n);
$c['risk']->post('/controls', $cF(['control_type' => 'weird']), '/controls/new'); check('invalid control type rejected', $cc() === $n);
$c['risk']->post('/controls', $cF(['department_id' => 99999]), '/controls/new'); check('invalid department rejected', $cc() === $n);
$c['retail']->post('/controls', $cF(), '/controls'); check('department manager cannot create a control (403)', $cc() === $n);
$c['risk']->post('/controls', $cF(), '/controls/new');
$cid = (int)$pdo->query('SELECT MAX(id) FROM controls')->fetchColumn(); check('control created with CTL-0000 code, key flag, next test due set', $cid > 0 && $cc() === $n + 1 && preg_match('/^CTL-\d{4}$/', (string)$pdo->query("SELECT code FROM controls WHERE id=$cid")->fetchColumn()) && (int)$pdo->query("SELECT is_key FROM controls WHERE id=$cid")->fetchColumn() === 1 && $pdo->query("SELECT next_test_due FROM controls WHERE id=$cid")->fetchColumn() > date('Y-m-d'));
$c['risk']->post('/controls', $cF(['name' => 'Retail branch cash count', 'department_id' => $deptRet, 'owner_user_id' => $uidN('demo.dept.retail'), 'is_key' => '']), '/controls/new'); $cid2 = (int)$pdo->query('SELECT MAX(id) FROM controls')->fetchColumn();
check('IT manager sees IT control, not retail control (404)', $c['it']->get("/controls/$cid")['code'] === 200 && $c['it']->get("/controls/$cid2")['code'] === 404);
check('retail list excludes the IT control', !str_contains($c['retail']->get('/controls')['body'], "/controls/$cid\"") && str_contains($c['retail']->get('/controls')['body'], "/controls/$cid2\""));
check('branch liaison gets 403 on a control', $c['tripoli']->get("/controls/$cid")['code'] === 403);
// linking
$rk = (int)$pdo->query("SELECT id FROM risks WHERE archived_at IS NULL AND department_id=$deptIt LIMIT 1")->fetchColumn(); if (!$rk) $rk = (int)$pdo->query('SELECT id FROM risks WHERE archived_at IS NULL LIMIT 1')->fetchColumn();
$rcnt = fn() => (int)$pdo->query("SELECT COUNT(*) FROM risk_controls WHERE control_id=$cid")->fetchColumn();
$c['risk']->post("/controls/$cid/risks", ['risk_id' => 999999], "/controls/$cid"); check('linking a nonexistent risk rejected', $rcnt() === 0);
$c['risk']->post("/controls/$cid/risks", ['risk_id' => $rk], "/controls/$cid"); check('risk linked to control', $rcnt() === 1);
$c['risk']->post("/controls/$cid/risks", ['risk_id' => $rk], "/controls/$cid"); check('linking twice is idempotent', $rcnt() === 1);
check('risk page shows the linked control and an "unproven" notice', str_contains($c['risk']->get("/risks/$rk")['body'], "/controls/$cid\"") && str_contains($c['risk']->get("/risks/$rk")['body'], 'id="controls"'));
check('exec viewer cannot link (403)', $c['exec']->post("/controls/$cid/risks", ['risk_id' => $rk], "/controls")['code'] === 403);
// testing rules
$tF = fn(array $o = []) => $o + ['test_type' => 'operating', 'test_date' => date('Y-m-d'), 'sample_size' => '25', 'exceptions' => '0', 'result' => 'effective', 'conclusion' => ''];
$tc = fn() => (int)$pdo->query("SELECT COUNT(*) FROM control_tests WHERE control_id=$cid")->fetchColumn();
$ev = ['file' => ['evidence.pdf', $pdf, 'application/pdf']];
$c['it']->post("/controls/$cid/tests", $tF(), "/controls/$cid"); check('IT dept manager (not a tester) gets 403', $tc() === 0);
$ownerNote = $c['risk']->post('/controls', $cF(['name' => 'Owned by risk manager', 'owner_user_id' => $uidN('demo.risk')]), '/controls/new'); $cidOwn = (int)$pdo->query('SELECT MAX(id) FROM controls')->fetchColumn();
$c['risk']->post("/controls/$cidOwn/tests", $tF(), "/controls/$cidOwn", $ev); check('owner cannot test their own control (independence)', (int)$pdo->query("SELECT COUNT(*) FROM control_tests WHERE control_id=$cidOwn")->fetchColumn() === 0);
$c['compliance']->post("/controls/$cid/tests", $tF(), "/controls/$cid"); check('Effective without evidence rejected', $tc() === 0);
$c['compliance']->post("/controls/$cid/tests", $tF(['exceptions' => '2']), "/controls/$cid", $ev); check('Effective with exceptions rejected', $tc() === 0);
$c['compliance']->post("/controls/$cid/tests", $tF(['exceptions' => '30']), "/controls/$cid", $ev); check('exceptions greater than sample rejected', $tc() === 0);
$c['compliance']->post("/controls/$cid/tests", $tF(['sample_size' => '0']), "/controls/$cid", $ev); check('zero sample rejected for operating test', $tc() === 0);
$c['compliance']->post("/controls/$cid/tests", $tF(['test_date' => '2999-01-01']), "/controls/$cid", $ev); check('future test date rejected', $tc() === 0);
$c['compliance']->post("/controls/$cid/tests", $tF(['result' => 'bogus']), "/controls/$cid", $ev); check('invalid result rejected', $tc() === 0);
$c['compliance']->post("/controls/$cid/tests", $tF(['result' => 'ineffective', 'exceptions' => '9']), "/controls/$cid"); check('Ineffective without conclusion rejected', $tc() === 0);
$c['compliance']->post("/controls/$cid/tests", $tF(['result' => 'effective']), "/controls/$cid", ['file' => ['x.exe', 'MZ', 'application/octet-stream']]); check('Effective with a disallowed file rejected', $tc() === 0);
$c['compliance']->post("/controls/$cid/tests", $tF(), "/controls/$cid", $ev);
check('valid Effective test saved with evidence; control summary updated', $tc() === 1 && $pdo->query("SELECT last_result FROM controls WHERE id=$cid")->fetchColumn() === 'effective' && $pdo->query("SELECT last_tested_on FROM controls WHERE id=$cid")->fetchColumn() === date('Y-m-d') && $pdo->query("SELECT next_test_due FROM controls WHERE id=$cid")->fetchColumn() === date('Y-m-d', strtotime('+1 year')) && (int)$pdo->query("SELECT COUNT(*) FROM attachments a JOIN control_tests t ON t.id=a.entity_id WHERE a.entity_type='control_test' AND t.control_id=$cid")->fetchColumn() === 1);
$tid = (int)$pdo->query("SELECT MAX(id) FROM control_tests WHERE control_id=$cid")->fetchColumn(); $aid = (int)$pdo->query("SELECT id FROM attachments WHERE entity_type='control_test' AND entity_id=$tid")->fetchColumn();
check('evidence downloadable by viewer in scope, 404 for out-of-scope department', $c['exec']->get("/attachments/$aid")['code'] === 200 && $c['retail']->get("/attachments/$aid")['code'] === 404 && str_contains($c['it']->get("/controls/$cid")['body'], "/attachments/$aid\""));
check('no remediation action created for an effective test', (int)$pdo->query("SELECT COUNT(*) FROM risk_actions WHERE title LIKE '%CTL-%' AND risk_id=$rk")->fetchColumn() === 0);
$aBefore = (int)$pdo->query('SELECT COUNT(*) FROM risk_actions')->fetchColumn();
$c['compliance']->post("/controls/$cid/tests", $tF(['result' => 'partially_effective', 'exceptions' => '4', 'conclusion' => 'Four payments lacked a second approver']), "/controls/$cid");
check('partially effective test saved without evidence, control marked, remediation action created on the linked risk', $tc() === 2 && $pdo->query("SELECT last_result FROM controls WHERE id=$cid")->fetchColumn() === 'partially_effective' && (int)$pdo->query('SELECT COUNT(*) FROM risk_actions')->fetchColumn() === $aBefore + 1 && (int)$pdo->query("SELECT COUNT(*) FROM risk_actions WHERE risk_id=$rk AND title LIKE '%Segregation of duties%' AND owner_user_id=" . $uidN('demo.dept.it'))->fetchColumn() === 1);
$c['compliance']->post("/controls/$cid/tests", $tF(['result' => 'ineffective', 'exceptions' => '9', 'conclusion' => 'Approvals bypassed']), "/controls/$cid");
check('second failing test does not duplicate the open remediation action', (int)$pdo->query("SELECT COUNT(*) FROM risk_actions WHERE risk_id=$rk AND title LIKE '%Segregation of duties%'")->fetchColumn() === 1);
check('risk page now warns about a weak linked control', str_contains($c['risk']->get("/risks/$rk")['body'], 'msg error') && str_contains($c['risk']->get("/risks/$rk")['body'], "/controls/$cid\""));
check('controls list filters: ineffective shows it, untested hides it', str_contains($c['risk']->get('/controls?state=ineffective')['body'], "/controls/$cid\"") && !str_contains($c['risk']->get('/controls?state=untested')['body'], "/controls/$cid\""));
check('dashboard shows control tiles for risk manager', str_contains($c['risk']->get('/')['body'], 'controls?state=ineffective'));
$c['compliance']->post("/controls/$cid/tests", $tF(['test_type' => 'design', 'sample_size' => '', 'exceptions' => '', 'result' => 'effective']), "/controls/$cid", $ev);
check('design test needs no sample', $tc() === 4 && $pdo->query("SELECT sample_size FROM control_tests WHERE control_id=$cid ORDER BY id DESC LIMIT 1")->fetchColumn() === null);
// my tasks
check('testers see untested/overdue controls in My tasks (due soon control listed)', str_contains($c['compliance']->get('/my')['body'], 'CTL-') || (int)$pdo->query("SELECT COUNT(*) FROM controls WHERE archived_at IS NULL AND next_test_due <= DATE_ADD(CURDATE(), INTERVAL 14 DAY) AND owner_user_id <> " . $uidN('demo.compliance'))->fetchColumn() === 0);
// edit / archive / unlink
$c['risk']->post("/controls/$cid", $cF(['name' => 'Segregation of duties (revised)', 'test_frequency' => 'quarterly']), "/controls/$cid/edit");
check('control edited; next test due recalculated for new cycle', $pdo->query("SELECT name FROM controls WHERE id=$cid")->fetchColumn() === 'Segregation of duties (revised)' && $pdo->query("SELECT next_test_due FROM controls WHERE id=$cid")->fetchColumn() === date('Y-m-d', strtotime('+3 months')));
$c['retail']->post("/controls/$cid", $cF(), "/controls"); check('retail manager cannot edit (403)', $pdo->query("SELECT name FROM controls WHERE id=$cid")->fetchColumn() === 'Segregation of duties (revised)');
$c['risk']->post("/controls/$cid/risks/$rk/remove", [], "/controls/$cid"); check('link removed', $rcnt() === 0);
$c['risk']->post("/controls/$cid/archive", [], "/controls/$cid"); check('control archived (hidden by default)', $pdo->query("SELECT archived_at FROM controls WHERE id=$cid")->fetchColumn() !== null && !str_contains($c['risk']->get('/controls')['body'], "/controls/$cid\""));
$tcn = $tc(); $c['compliance']->post("/controls/$cid/tests", $tF(), "/controls/$cid", $ev); check('archived control cannot be tested', $tc() === $tcn);
check('controls are audited', (int)$pdo->query("SELECT COUNT(DISTINCT action) FROM audit_log WHERE action IN ('control.created','control.updated','control.linked','control.unlinked','control.tested','control.archived')")->fetchColumn() === 6);
check('help topic for controls exists in both languages and page map points to it', isset($helpEn['topics']['controls'], $helpAr['topics']['controls']) && ($helpEn['pages']['/controls'] ?? '') === 'controls');

section('Sharia compliance: authorization matrix');
$sm = ['/sharia' => ['risk' => 200, 'compliance' => 200, 'retail' => 200, 'tripoli' => 403, 'exec' => 200, 'auditor' => 200],
    '/sharia/rulings' => ['risk' => 200, 'retail' => 200, 'tripoli' => 403, 'exec' => 200, 'auditor' => 200],
    '/sharia/rulings/new' => ['risk' => 403, 'compliance' => 200, 'retail' => 403, 'exec' => 403, 'auditor' => 403],
    '/sharia/reviews' => ['risk' => 200, 'retail' => 200, 'tripoli' => 403, 'exec' => 200],
    '/sharia/reviews/new' => ['risk' => 403, 'compliance' => 200, 'retail' => 403],
    '/sharia/events' => ['risk' => 200, 'retail' => 200, 'tripoli' => 403, 'exec' => 200, 'auditor' => 200],
    '/sharia/events/new' => ['risk' => 200, 'compliance' => 200, 'retail' => 200, 'tripoli' => 403, 'exec' => 403, 'auditor' => 403],
    '/sharia/report' => ['risk' => 200, 'retail' => 200, 'exec' => 200, 'auditor' => 200, 'tripoli' => 403]];
foreach ($sm as $p => $exp) foreach ($exp as $role => $code) { $r = $c[$role]->get($p); check("sharia: $role GET $p = $code", $r['code'] === $code, (string)$r['code']); }
check('anonymous /sharia -> login', str_contains((string)(new Client($B))->get('/sharia')['loc'], '/login'));

section('Sharia compliance: rulings');
$rF = fn(array $o = []) => $o + ['title' => 'Takaful contribution allocation', 'ruling_type' => 'fatwa', 'subject' => 'Takaful', 'ruling_text' => 'Contributions are donations (tabarru).', 'reference_no' => 'SSB/1', 'issued_on' => '2026-01-15', 'status' => 'active'];
$rc = fn() => (int)$pdo->query('SELECT COUNT(*) FROM sharia_rulings')->fetchColumn(); $n = $rc();
$c['compliance']->post('/sharia/rulings', $rF(['title' => '']), '/sharia/rulings/new'); check('ruling without title rejected', $rc() === $n);
$c['compliance']->post('/sharia/rulings', $rF(['ruling_type' => 'x']), '/sharia/rulings/new'); check('bad ruling type rejected', $rc() === $n);
$c['compliance']->post('/sharia/rulings', $rF(['issued_on' => '2026-13-40']), '/sharia/rulings/new'); check('bad ruling date rejected', $rc() === $n);
$c['risk']->post('/sharia/rulings', $rF(), '/sharia/rulings'); check('risk manager cannot create a ruling (403)', $rc() === $n);
$c['compliance']->post('/sharia/rulings', $rF(), '/sharia/rulings/new'); $shr = (int)$pdo->query('SELECT MAX(id) FROM sharia_rulings')->fetchColumn();
check('ruling created with SHR-0000 code', $rc() === $n + 1 && preg_match('/^SHR-\d{4}$/', (string)$pdo->query("SELECT code FROM sharia_rulings WHERE id=$shr")->fetchColumn()));
check('rulings visible to department manager and searchable', str_contains($c['retail']->get("/sharia/rulings/$shr")['body'], 'Takaful') && str_contains($c['retail']->get('/sharia/rulings?q=Takaful')['body'], "/sharia/rulings/$shr\""));
check('LIKE wildcard in ruling search is literal', !str_contains($c['retail']->get('/sharia/rulings?q=%25')['body'], "/sharia/rulings/$shr\""));
$c['compliance']->post("/sharia/rulings/$shr", $rF(['status' => 'superseded']), "/sharia/rulings/$shr/edit"); check('ruling status updated and filterable', $pdo->query("SELECT status FROM sharia_rulings WHERE id=$shr")->fetchColumn() === 'superseded' && str_contains($c['risk']->get('/sharia/rulings?status=superseded')['body'], "/sharia/rulings/$shr\""));
$c['compliance']->post("/sharia/rulings/$shr/attachments", [], "/sharia/rulings/$shr", ['file' => ['ruling.pdf', $pdf, 'application/pdf']]); $ra = (int)$pdo->query("SELECT id FROM attachments WHERE entity_type='sharia_ruling' AND entity_id=$shr")->fetchColumn();
check('ruling document uploaded and readable by viewers', $ra > 0 && $c['exec']->get("/attachments/$ra")['code'] === 200 && $c['tripoli']->get("/attachments/$ra")['code'] === 404);
$c['risk']->post("/sharia/rulings/$shr/attachments", [], "/sharia/rulings/$shr", ['file' => ['x.pdf', $pdf, 'application/pdf']]); check('risk manager cannot upload ruling documents', (int)$pdo->query("SELECT COUNT(*) FROM attachments WHERE entity_type='sharia_ruling' AND entity_id=$shr")->fetchColumn() === 1);

section('Sharia compliance: reviews');
$vF = fn(array $o = []) => $o + ['title' => 'Annual Sharia audit of IT-supported products', 'review_type' => 'internal_audit', 'department_id' => $deptIt, 'reviewer_user_id' => $uidN('demo.compliance'), 'planned_date' => date('Y-m-d', strtotime('+10 days')), 'status' => 'planned', 'scope' => 'Online products'];
$vc = fn() => (int)$pdo->query('SELECT COUNT(*) FROM sharia_reviews')->fetchColumn(); $n = $vc();
$c['compliance']->post('/sharia/reviews', $vF(['title' => '']), '/sharia/reviews/new'); check('review without title rejected', $vc() === $n);
$c['compliance']->post('/sharia/reviews', $vF(['reviewer_user_id' => 99999]), '/sharia/reviews/new'); check('review with bad reviewer rejected', $vc() === $n);
$c['compliance']->post('/sharia/reviews', $vF(['status' => 'completed']), '/sharia/reviews/new'); check('completed review needs summary and completion date', $vc() === $n);
$c['compliance']->post('/sharia/reviews', $vF(['status' => 'completed', 'summary' => 'ok', 'completed_on' => '2999-01-01']), '/sharia/reviews/new'); check('future completion date rejected', $vc() === $n);
$c['compliance']->post('/sharia/reviews', $vF(), '/sharia/reviews/new'); $svr = (int)$pdo->query('SELECT MAX(id) FROM sharia_reviews')->fetchColumn(); check('review created with SRV-0000 code', $vc() === $n + 1 && preg_match('/^SRV-\d{4}$/', (string)$pdo->query("SELECT code FROM sharia_reviews WHERE id=$svr")->fetchColumn()));
check('assigned reviewer sees the due review in My tasks', str_contains($c['compliance']->get('/my')['body'], "/sharia/reviews/$svr\""));
$c['compliance']->post("/sharia/reviews/$svr", $vF(['status' => 'completed', 'summary' => 'No exceptions found.', 'completed_on' => date('Y-m-d')]), "/sharia/reviews/$svr/edit"); check('review completed with summary', $pdo->query("SELECT status FROM sharia_reviews WHERE id=$svr")->fetchColumn() === 'completed' && !str_contains($c['compliance']->get('/my')['body'], "/sharia/reviews/$svr\""));
check('risk manager cannot edit reviews (403)', $c['risk']->post("/sharia/reviews/$svr", $vF(['title' => 'hacked']), '/sharia/reviews')['code'] === 403);

section('Sharia compliance: non-compliance events and purification');
$eF = fn(array $o = []) => $o + ['title' => 'Late fee recorded as income', 'description' => 'Branch recorded late charges as income', 'detected_on' => date('Y-m-d', strtotime('-2 days')), 'department_id' => $deptRet, 'severity' => 'high', 'ruling_id' => $shr];
$ec = fn() => (int)$pdo->query('SELECT COUNT(*) FROM sharia_events')->fetchColumn(); $n = $ec();
$c['retail']->post('/sharia/events', $eF(['title' => '']), '/sharia/events/new'); check('event without title rejected', $ec() === $n);
$c['retail']->post('/sharia/events', $eF(['severity' => 'catastrophic']), '/sharia/events/new'); check('bad severity rejected', $ec() === $n);
$c['retail']->post('/sharia/events', $eF(['detected_on' => '2999-01-01']), '/sharia/events/new'); check('future detection date rejected', $ec() === $n);
$c['retail']->post('/sharia/events', $eF(['department_id' => $deptIt]), '/sharia/events/new'); check('department manager cannot report for another department', $ec() === $n);
$c['retail']->post('/sharia/events', $eF(['ruling_id' => 999999]), '/sharia/events/new'); check('bad ruling reference rejected', $ec() === $n);
$c['exec']->post('/sharia/events', $eF(), '/sharia/events'); check('exec viewer cannot report (403)', $ec() === $n);
$c['retail']->post('/sharia/events', $eF(), '/sharia/events/new'); $sev1 = (int)$pdo->query('SELECT MAX(id) FROM sharia_events')->fetchColumn();
check('event reported with SNC-0000 code, status reported and high severity auto-escalated', $ec() === $n + 1 && preg_match('/^SNC-\d{4}$/', (string)$pdo->query("SELECT code FROM sharia_events WHERE id=$sev1")->fetchColumn()) && $pdo->query("SELECT status FROM sharia_events WHERE id=$sev1")->fetchColumn() === 'reported' && $pdo->query("SELECT escalated_at FROM sharia_events WHERE id=$sev1")->fetchColumn() !== null);
$c['retail']->post('/sharia/events', $eF(['title' => 'Minor documentation gap', 'severity' => 'low']), '/sharia/events/new'); $sev2 = (int)$pdo->query('SELECT MAX(id) FROM sharia_events')->fetchColumn();
check('low severity event is not escalated', $pdo->query("SELECT escalated_at FROM sharia_events WHERE id=$sev2")->fetchColumn() === null);
check('IT manager gets 404 on retail event; retail sees it; tripoli 403', $c['it']->get("/sharia/events/$sev1")['code'] === 404 && $c['retail']->get("/sharia/events/$sev1")['code'] === 200 && $c['tripoli']->get("/sharia/events/$sev1")['code'] === 403);
check('IT manager list excludes retail events', !str_contains($c['it']->get('/sharia/events')['body'], "/sharia/events/$sev1\""));
check('dept manager does not see manage forms', !str_contains($c['retail']->get("/sharia/events/$sev1")['body'], '/decision"'));
check('compliance officer sees the open event in My tasks', str_contains($c['compliance']->get('/my')['body'], "/sharia/events/$sev1\""));
check('retail manager cannot decide (403) or edit (403)', $c['retail']->post("/sharia/events/$sev1/decision", ['decision_text' => 'x', 'decided_on' => date('Y-m-d')], "/sharia/events/$sev1")['code'] === 403 && $c['retail']->post("/sharia/events/$sev1", $eF(), "/sharia/events/$sev1")['code'] === 403);
$dF = fn(array $o = []) => $o + ['decided_on' => date('Y-m-d'), 'decision_text' => 'Income to be purified; staff retrained.', 'impermissible_income' => '12500.250'];
$c['compliance']->post("/sharia/events/$sev1/decision", $dF(['decision_text' => '']), "/sharia/events/$sev1"); check('decision without text rejected', $pdo->query("SELECT status FROM sharia_events WHERE id=$sev1")->fetchColumn() === 'reported');
$c['compliance']->post("/sharia/events/$sev1/decision", $dF(['impermissible_income' => '-5']), "/sharia/events/$sev1"); check('negative income rejected', $pdo->query("SELECT status FROM sharia_events WHERE id=$sev1")->fetchColumn() === 'reported');
$c['compliance']->post("/sharia/events/$sev1/decision", $dF(['impermissible_income' => 'abc']), "/sharia/events/$sev1"); check('non-numeric income rejected', $pdo->query("SELECT status FROM sharia_events WHERE id=$sev1")->fetchColumn() === 'reported');
$c['compliance']->post("/sharia/events/$sev1/decision", $dF(['decided_on' => date('Y-m-d', strtotime('-30 days'))]), "/sharia/events/$sev1"); check('decision date before detection rejected', $pdo->query("SELECT status FROM sharia_events WHERE id=$sev1")->fetchColumn() === 'reported');
$c['compliance']->post("/sharia/events/$sev1/close", [], "/sharia/events/$sev1"); check('cannot close before a decision', $pdo->query("SELECT status FROM sharia_events WHERE id=$sev1")->fetchColumn() === 'reported');
$c['compliance']->post("/sharia/events/$sev1/review", [], "/sharia/events/$sev1"); check('event moved to under review', $pdo->query("SELECT status FROM sharia_events WHERE id=$sev1")->fetchColumn() === 'under_review');
$c['compliance']->post("/sharia/events/$sev1/decision", $dF(), "/sharia/events/$sev1");
check('decision recorded with exact income and purification pending', $pdo->query("SELECT status FROM sharia_events WHERE id=$sev1")->fetchColumn() === 'decided' && $pdo->query("SELECT impermissible_income FROM sharia_events WHERE id=$sev1")->fetchColumn() === '12500.250' && $pdo->query("SELECT purification_status FROM sharia_events WHERE id=$sev1")->fetchColumn() === 'pending');
$c['compliance']->post("/sharia/events/$sev1/close", [], "/sharia/events/$sev1"); check('cannot close while purification pending', $pdo->query("SELECT status FROM sharia_events WHERE id=$sev1")->fetchColumn() === 'decided');
$c['compliance']->post("/sharia/events/$sev1/decision", $dF(), "/sharia/events/$sev1"); check('decision cannot be recorded twice', (int)$pdo->query("SELECT COUNT(*) FROM audit_log WHERE action='sharia.event_decided' AND entity_id=$sev1")->fetchColumn() === 1);
$c['compliance']->post("/sharia/events/$sev1/purify", ['purified_on' => date('Y-m-d'), 'beneficiary' => ''], "/sharia/events/$sev1"); check('purification needs a beneficiary', $pdo->query("SELECT purification_status FROM sharia_events WHERE id=$sev1")->fetchColumn() === 'pending');
$c['compliance']->post("/sharia/events/$sev1/purify", ['purified_on' => '2999-01-01', 'beneficiary' => 'Charity'], "/sharia/events/$sev1"); check('future purification date rejected', $pdo->query("SELECT purification_status FROM sharia_events WHERE id=$sev1")->fetchColumn() === 'pending');
check('pending purification shows on list filter and dashboard tile', str_contains($c['compliance']->get('/sharia/events?purification=1')['body'], "/sharia/events/$sev1\"") && str_contains($c['compliance']->get('/')['body'], 'sharia/events?purification=1'));
$c['compliance']->post("/sharia/events/$sev1/purify", ['purified_on' => date('Y-m-d'), 'beneficiary' => 'Approved Charity Foundation'], "/sharia/events/$sev1"); check('purification recorded', $pdo->query("SELECT purification_status FROM sharia_events WHERE id=$sev1")->fetchColumn() === 'purified' && $pdo->query("SELECT beneficiary FROM sharia_events WHERE id=$sev1")->fetchColumn() === 'Approved Charity Foundation');
$c['compliance']->post("/sharia/events/$sev1/close", [], "/sharia/events/$sev1"); check('event closed after decision and purification', $pdo->query("SELECT status FROM sharia_events WHERE id=$sev1")->fetchColumn() === 'closed' && $pdo->query("SELECT closed_at FROM sharia_events WHERE id=$sev1")->fetchColumn() !== null);
$c['compliance']->post("/sharia/events/$sev1", $eF(['title' => 'edited after close']), "/sharia/events/$sev1"); check('closed event is read-only', $pdo->query("SELECT title FROM sharia_events WHERE id=$sev1")->fetchColumn() === 'Late fee recorded as income');
// no-income decision path
$c['compliance']->post("/sharia/events/$sev2/decision", $dF(['impermissible_income' => '']), "/sharia/events/$sev2"); check('decision with no income needs no purification', $pdo->query("SELECT purification_status FROM sharia_events WHERE id=$sev2")->fetchColumn() === 'not_required');
$c['compliance']->post("/sharia/events/$sev2/close", [], "/sharia/events/$sev2"); check('event without income closes directly after decision', $pdo->query("SELECT status FROM sharia_events WHERE id=$sev2")->fetchColumn() === 'closed');
// evidence
$c['retail']->post("/sharia/events/$sev2/attachments", [], "/sharia/events/$sev2", ['file' => ['evidence.pdf', $pdf, 'application/pdf']]); $ea = (int)$pdo->query("SELECT id FROM attachments WHERE entity_type='sharia_event' AND entity_id=$sev2")->fetchColumn();
check('department manager uploads evidence to own event; IT manager cannot read it (404)', $ea > 0 && $c['exec']->get("/attachments/$ea")['code'] === 200 && $c['it']->get("/attachments/$ea")['code'] === 404);
// report
$rep = $c['exec']->get('/sharia/report?year=' . date('Y'));
check('annual report renders totals from data (income, purified, pending)', $rep['code'] === 200 && str_contains($rep['body'], '12') && preg_match('/12[,.\s]?500/', $rep['body']) === 1);
check('report year is clamped / invalid year harmless', $c['exec']->get('/sharia/report?year=99999')['code'] === 200 && $c['exec']->get('/sharia/report?year=abc')['code'] === 200);
check('Sharia actions are audited', (int)$pdo->query("SELECT COUNT(DISTINCT action) FROM audit_log WHERE action IN ('sharia.ruling_created','sharia.ruling_updated','sharia.review_created','sharia.review_updated','sharia.event_reported','sharia.event_review','sharia.event_decided','sharia.event_purified','sharia.event_closed')")->fetchColumn() === 9);
check('help topic for Sharia exists in both languages', isset($helpEn['topics']['sharia'], $helpAr['topics']['sharia']));
check('Sharia pages render in English without missing keys', (function () use ($c, $shr, $sev1, $svr) { $a = $c['compliance']; $a->get('/lang?l=en&next=/'); $ok = true; foreach (['/sharia', '/sharia/rulings', "/sharia/rulings/$shr", '/sharia/rulings/new', '/sharia/reviews', "/sharia/reviews/$svr", '/sharia/reviews/new', '/sharia/events', "/sharia/events/$sev1", '/sharia/events/new', '/sharia/report', '/controls', '/my', '/help', '/notes'] as $p) { $b = $a->get($p)['body']; $chrome = preg_replace('#<(header|nav|option|td|dd|li|a|p|h1|h2)\b[^>]*>.*?</\1>#su', '', $b); $text = strip_tags($chrome); if (preg_match('/\b(sha|ctl|my|help|notes)\.[a-z_]+\b/', strip_tags($b))) { fwrite(STDERR, "missing key on $p\n"); $ok = false; } } $a->get('/lang?l=ar&next=/'); return $ok; })());
