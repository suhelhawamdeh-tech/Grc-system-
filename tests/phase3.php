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

section('BIA and approvals: authorization matrix');
$bm = ['/bia' => ['risk' => 200, 'compliance' => 200, 'retail' => 200, 'tripoli' => 403, 'exec' => 200, 'auditor' => 200],
    '/bia/new' => ['risk' => 200, 'compliance' => 200, 'retail' => 200, 'tripoli' => 403, 'exec' => 403, 'auditor' => 403],
    '/bia/systems' => ['risk' => 200, 'retail' => 200, 'exec' => 200, 'tripoli' => 403],
    '/bia/systems/new' => ['risk' => 200, 'retail' => 200, 'exec' => 403, 'auditor' => 403],
    '/bia/report' => ['risk' => 200, 'retail' => 200, 'exec' => 200, 'tripoli' => 403],
    '/approvals' => ['risk' => 200, 'compliance' => 200, 'retail' => 403, 'exec' => 403, 'auditor' => 403, 'tripoli' => 403]];
foreach ($bm as $p => $exp) foreach ($exp as $role => $code) { $r = $c[$role]->get($p); check("bia: $role GET $p = $code", $r['code'] === $code, (string)$r['code']); }
check('anonymous /bia and /approvals -> login', str_contains((string)(new Client($B))->get('/bia')['loc'], '/login') && str_contains((string)(new Client($B))->get('/approvals')['loc'], '/login'));

section('BIA: unit rules');
use App\Support\Bia;
check('hours parser: valid forms', Bia::hours('4') === 400 && Bia::hours('0.5') === 50 && Bia::hours('36.25') === 3625 && Bia::hours('99999.99') === 9999999 && Bia::hours('٤') === 400);
check('hours parser: rejects negatives, text, too many decimals, too large', Bia::hours('-1') === null && Bia::hours('abc') === null && Bia::hours('1.234') === null && Bia::hours('100000') === null && Bia::hours('') === null && Bia::hours('1e3') === null);
check('criticality follows the highest impact', Bia::criticality(1, 1, 1, 1) === 'low' && Bia::criticality(3, 1, 1, 1) === 'medium' && Bia::criticality(1, 4, 1, 1) === 'high' && Bia::criticality(1, 1, 1, 5) === 'critical');
check('hours formatting drops trailing zeros', Bia::fmt('4.00') === '4' && Bia::fmt('0.50') === '0.5' && Bia::fmt('36.25') === '36.25');

section('BIA: systems, processes, gaps and approval workflow');
$gapCount = fn(int $pid) => (int)$pdo->query("SELECT COALESCE(SUM(s.recovery_hours > p.rto_hours) + SUM(s.data_loss_hours > p.rpo_hours), 0) FROM bia_process_systems ps JOIN bia_systems s ON s.id = ps.system_id JOIN bia_processes p ON p.id = ps.process_id WHERE ps.process_id = $pid AND s.is_active = 1")->fetchColumn();
$sF = fn(array $o = []) => $o + ['name' => 'Treasury dealing system', 'description' => 'd', 'department_id' => $deptIt, 'owner_user_id' => $uidN('demo.dept.it'), 'recovery_hours' => '6', 'data_loss_hours' => '1'];
$sc = fn() => (int)$pdo->query('SELECT COUNT(*) FROM bia_systems')->fetchColumn(); $n = $sc();
$c['risk']->post('/bia/systems', $sF(['name' => '']), '/bia/systems/new'); check('system without name rejected', $sc() === $n);
$c['risk']->post('/bia/systems', $sF(['recovery_hours' => 'fast']), '/bia/systems/new'); check('system with bad hours rejected', $sc() === $n);
$c['exec']->post('/bia/systems', $sF(), '/bia/systems'); check('exec viewer cannot create a system (403)', $sc() === $n);
$c['risk']->post('/bia/systems', $sF(), '/bia/systems/new'); $sys1 = (int)$pdo->query('SELECT MAX(id) FROM bia_systems')->fetchColumn();
check('system created with SYS-0000 code', $sc() === $n + 1 && preg_match('/^SYS-\d{4}$/', (string)$pdo->query("SELECT code FROM bia_systems WHERE id=$sys1")->fetchColumn()));
$pF = fn(array $o = []) => $o + ['name' => 'Foreign exchange dealing', 'description' => 'FX desk', 'department_id' => $deptRet, 'owner_user_id' => $uidN('demo.dept.retail'), 'impact_financial' => '5', 'impact_operational' => '3', 'impact_legal' => '3', 'impact_reputation' => '4', 'mtpd_hours' => '8', 'rto_hours' => '4', 'rpo_hours' => '0.5', 'systems' => [$sys1], 'dependencies' => 'Reuters feed'];
$pc = fn() => (int)$pdo->query('SELECT COUNT(*) FROM bia_processes')->fetchColumn(); $n = $pc();
$c['retail']->post('/bia', $pF(['name' => '']), '/bia/new'); check('process without name rejected', $pc() === $n);
$c['retail']->post('/bia', $pF(['impact_financial' => '6']), '/bia/new'); check('impact rating above 5 rejected', $pc() === $n);
$c['retail']->post('/bia', $pF(['impact_legal' => '0']), '/bia/new'); check('impact rating below 1 rejected', $pc() === $n);
$c['retail']->post('/bia', $pF(['rto_hours' => '10']), '/bia/new'); check('RTO longer than MTPD rejected', $pc() === $n);
$c['retail']->post('/bia', $pF(['rpo_hours' => '-2']), '/bia/new'); check('negative RPO rejected', $pc() === $n);
$c['retail']->post('/bia', $pF(['department_id' => $deptIt]), '/bia/new'); check('department manager cannot create for another department', $pc() === $n);
$c['retail']->post('/bia', $pF(['systems' => [999999]]), '/bia/new'); check('unknown system rejected', $pc() === $n);
$c['retail']->post('/bia', $pF(), '/bia/new'); $bp = (int)$pdo->query('SELECT MAX(id) FROM bia_processes')->fetchColumn();
check('process created: BIA-0000, critical (impact 5), draft, 1 system linked, hours stored', $pc() === $n + 1 && preg_match('/^BIA-\d{4}$/', (string)$pdo->query("SELECT code FROM bia_processes WHERE id=$bp")->fetchColumn()) && $pdo->query("SELECT criticality FROM bia_processes WHERE id=$bp")->fetchColumn() === 'critical' && $pdo->query("SELECT status FROM bia_processes WHERE id=$bp")->fetchColumn() === 'draft' && (int)$pdo->query("SELECT COUNT(*) FROM bia_process_systems WHERE process_id=$bp")->fetchColumn() === 1 && $pdo->query("SELECT rpo_hours FROM bia_processes WHERE id=$bp")->fetchColumn() === '0.50');
check('IT manager gets 404 on retail process; tripoli 403; retail list excludes none/IT list excludes it', $c['it']->get("/bia/$bp")['code'] === 404 && $c['tripoli']->get("/bia/$bp")['code'] === 403 && !str_contains($c['it']->get('/bia')['body'], "/bia/$bp\"") && str_contains($c['retail']->get('/bia')['body'], "/bia/$bp\""));
check('gap shown: system recovers in 6h but RTO is 4h, and RPO 1h vs 0.5h', substr_count($c['retail']->get("/bia/$bp")['body'], 'msg error') >= 1 && $gapCount($bp) === 2);
$c['retail']->post("/bia/$bp/submit", [], "/bia/$bp"); check('submit blocked while recovery gaps exist', $pdo->query("SELECT status FROM bia_processes WHERE id=$bp")->fetchColumn() === 'draft' && (int)$pdo->query("SELECT COUNT(*) FROM approvals WHERE entity_type='bia_process' AND entity_id=$bp")->fetchColumn() === 0);
check('process with a gap is listed under the gap filter and the report', str_contains($c['retail']->get('/bia?gap=1')['body'], "/bia/$bp\"") && str_contains($c['risk']->get('/bia/report')['body'], "/bia/$bp\""));
$c['risk']->post('/bia/systems/' . $sys1, $sF(['recovery_hours' => '3', 'data_loss_hours' => '0.25', 'is_active' => '1']), "/bia/systems/$sys1/edit"); check('system improved (recovery 3h, loss 0.25h) removes the gaps while staying active', $gapCount($bp) === 0 && (int)$pdo->query("SELECT is_active FROM bia_systems WHERE id=$sys1")->fetchColumn() === 1);
$c['exec']->post("/bia/$bp/submit", [], "/bia/$bp"); check('exec viewer cannot submit (403)', $pdo->query("SELECT status FROM bia_processes WHERE id=$bp")->fetchColumn() === 'draft');
$c['retail']->post("/bia/$bp/submit", [], "/bia/$bp"); $ap = (int)$pdo->query("SELECT MAX(id) FROM approvals WHERE entity_type='bia_process' AND entity_id=$bp")->fetchColumn();
check('submit creates one pending approval and moves status', $ap > 0 && $pdo->query("SELECT status FROM bia_processes WHERE id=$bp")->fetchColumn() === 'pending_approval' && $pdo->query("SELECT status FROM approvals WHERE id=$ap")->fetchColumn() === 'pending');
$c['retail']->post("/bia/$bp/submit", [], "/bia/$bp"); check('double submit does not create a second approval', (int)$pdo->query("SELECT COUNT(*) FROM approvals WHERE entity_type='bia_process' AND entity_id=$bp")->fetchColumn() === 1);
check('retail manager cannot open the approvals inbox or decide (403)', $c['retail']->get('/approvals')['code'] === 403 && $c['retail']->post("/approvals/$ap/decide", ['decision' => 'approve'], "/bia/$bp")['code'] === 403 && $pdo->query("SELECT status FROM approvals WHERE id=$ap")->fetchColumn() === 'pending');
check('approver sees the request in inbox, My tasks and dashboard tile', str_contains($c['risk']->get('/approvals')['body'], "/approvals/$ap/decide") && str_contains($c['risk']->get('/my')['body'], '/approvals') && str_contains($c['risk']->get('/')['body'], 'href="/approvals"'));
// maker-checker: a requester who is also an approver cannot approve own request
$pdo->exec("UPDATE approvals SET requested_by=" . $uidN('demo.risk') . " WHERE id=$ap");
$c['risk']->post("/approvals/$ap/decide", ['decision' => 'approve'], '/approvals'); check('requester cannot approve their own request', $pdo->query("SELECT status FROM approvals WHERE id=$ap")->fetchColumn() === 'pending');
$pdo->exec("UPDATE approvals SET requested_by=" . $uidN('demo.dept.retail') . " WHERE id=$ap");
$c['compliance']->post("/approvals/$ap/decide", ['decision' => 'reject', 'comment' => ''], '/approvals'); check('rejection requires a comment', $pdo->query("SELECT status FROM approvals WHERE id=$ap")->fetchColumn() === 'pending');
$c['compliance']->post("/approvals/$ap/decide", ['decision' => 'reject', 'comment' => 'RTO looks too optimistic'], '/approvals');
check('rejected: approval closed with comment, process back to draft', $pdo->query("SELECT status FROM approvals WHERE id=$ap")->fetchColumn() === 'rejected' && $pdo->query("SELECT comment FROM approvals WHERE id=$ap")->fetchColumn() === 'RTO looks too optimistic' && $pdo->query("SELECT status FROM bia_processes WHERE id=$bp")->fetchColumn() === 'draft');
$c['compliance']->post("/approvals/$ap/decide", ['decision' => 'approve'], '/approvals'); check('a decided request cannot be decided again', $pdo->query("SELECT status FROM approvals WHERE id=$ap")->fetchColumn() === 'rejected');
$c['retail']->post("/bia/$bp/submit", [], "/bia/$bp"); $ap2 = (int)$pdo->query("SELECT MAX(id) FROM approvals WHERE entity_type='bia_process' AND entity_id=$bp")->fetchColumn(); check('resubmission creates a new approval', $ap2 > $ap);
$pdo->exec("UPDATE bia_systems SET recovery_hours=9 WHERE id=$sys1");
$c['compliance']->post("/approvals/$ap2/decide", ['decision' => 'approve'], '/approvals'); check('approval is refused if a gap appeared after submission', $pdo->query("SELECT status FROM approvals WHERE id=$ap2")->fetchColumn() === 'pending');
$pdo->exec("UPDATE bia_systems SET recovery_hours=3 WHERE id=$sys1");
$c['compliance']->post("/approvals/$ap2/decide", ['decision' => 'approve', 'comment' => 'OK'], '/approvals');
check('approved: process approved, review due in one year', $pdo->query("SELECT status FROM bia_processes WHERE id=$bp")->fetchColumn() === 'approved' && $pdo->query("SELECT next_review_due FROM bia_processes WHERE id=$bp")->fetchColumn() === date('Y-m-d', strtotime('+1 year')) && $pdo->query("SELECT decided_by FROM approvals WHERE id=$ap2")->fetchColumn() == $uidN('demo.compliance'));
check('approval history visible on the process page', str_contains($c['retail']->get("/bia/$bp")['body'], 'RTO looks too optimistic'));
$c['retail']->post("/bia/$bp", $pF(['description' => 'only a description change']), "/bia/$bp/edit"); check('non-material edit keeps the approval', $pdo->query("SELECT status FROM bia_processes WHERE id=$bp")->fetchColumn() === 'approved');
$c['retail']->post("/bia/$bp", $pF(['rto_hours' => '5']), "/bia/$bp/edit"); check('changing recovery objectives returns the process to draft', $pdo->query("SELECT status FROM bia_processes WHERE id=$bp")->fetchColumn() === 'draft' && $pdo->query("SELECT approved_at FROM bia_processes WHERE id=$bp")->fetchColumn() === null);
$c['retail']->post("/bia/$bp/archive", [], "/bia/$bp"); check('process archived and hidden by default', $pdo->query("SELECT archived_at FROM bia_processes WHERE id=$bp")->fetchColumn() !== null && !str_contains($c['retail']->get('/bia')['body'], "/bia/$bp\""));
check('BIA and approval actions are audited', (int)$pdo->query("SELECT COUNT(DISTINCT action) FROM audit_log WHERE action IN ('bia.created','bia.updated','bia.submitted','bia.archived','bia.system_created','bia.system_updated','approval.approved','approval.rejected')")->fetchColumn() === 8);
check('help topic for BIA exists in both languages', isset($helpEn['topics']['bia'], $helpAr['topics']['bia']));
check('BIA pages render in English without missing keys', (function () use ($c, $bp, $sys1) { $a = $c['risk']; $a->get('/lang?l=en&next=/'); $ok = true; foreach (['/bia', "/bia/$bp", '/bia/new', "/bia/$bp/edit", '/bia/systems', '/bia/systems/new', "/bia/systems/$sys1/edit", '/bia/report', '/approvals', '/'] as $p) { if (preg_match('/\b(bia|appr|sha|ctl)\.[a-z_]+\b/', strip_tags($a->get($p)['body']))) { fwrite(STDERR, "missing key on $p\n"); $ok = false; } } $a->get('/lang?l=ar&next=/'); return $ok; })());

section('E-mail notifications');
$smtpOut = "$work/smtp.txt"; @unlink($smtpOut);
$smtp = proc_open(['php', "$root/tests/fake_smtp.php", '2525', $smtpOut], [], $sp); usleep(400000);
$cfgFile = "$work/cfg/config.php"; $cfg0 = file_get_contents($cfgFile); $cfgArr = require $cfgFile;
$msgs = fn() => is_file($smtpOut) ? substr_count((string)file_get_contents($smtpOut), '=====END=====') : 0;
check('admin system page shows no test form while mail is unconfigured', !str_contains($admin->get('/admin/system')['body'], 'admin/system/test-mail'));
$cfgArr['mail'] = ['host' => '127.0.0.1', 'port' => 2525, 'secure' => 'none', 'user' => 'u', 'pass' => 'p', 'from' => 'grc@example.invalid', 'from_name' => 'GRC', 'escalation_days' => 7];
$cfgArr['app']['base_url'] = 'https://grc.example.invalid/grc';
file_put_contents($cfgFile, "<?php\nreturn " . var_export($cfgArr, true) . ";\n"); sleep(3); // opcache revalidation window of the dev server
check('admin system page shows the test-mail form when configured', str_contains($admin->get('/admin/system')['body'], 'admin/system/test-mail'));
$admin->post('/admin/system/test-mail', ['to' => 'someone@example.invalid'], '/admin/system'); usleep(200000);
check('test message delivered to the SMTP sink and logged', $msgs() === 1 && str_contains((string)file_get_contents($smtpOut), 'RCPT TO:<someone@example.invalid>') && (int)$pdo->query("SELECT COUNT(*) FROM email_log WHERE kind='test' AND status='sent'")->fetchColumn() === 1);
$admin->post('/admin/system/test-mail', ['to' => "bad\nBcc: x@evil.invalid"], '/admin/system'); check('header injection in recipient refused', $msgs() === 1 && (int)$pdo->query("SELECT COUNT(*) FROM email_log WHERE kind='test' AND status='failed'")->fetchColumn() === 1);
$c['risk']->post('/admin/system/test-mail', ['to' => 'x@example.invalid'], '/'); check('non-admin cannot send test mail', $msgs() === 1);
// CLI digest
$pdo->exec("UPDATE users SET lang='en' WHERE username='demo.risk'");
$ncmd = fn(string $a = '') => 'GRC_CONFIG_FILE=' . escapeshellarg($cfgFile) . ' php ' . escapeshellarg("$work/root/bin/notify.php") . " $a 2>&1";
exec($ncmd('--dry-run'), $o1, $rc1); check('notify --dry-run reports counts and sends nothing', $rc1 === 0 && preg_match('/sent=(\d+)/', implode(' ', $o1), $m1) && (int)$m1[1] > 0 && $msgs() === 1 && str_contains(implode(' ', $o1), 'dry run'));
$pdo->exec("INSERT INTO user_notes (user_id, body, remind_on, created_at) VALUES (" . $uidN('demo.compliance') . ", 'NOTIFY-MARKER', CURDATE(), NOW())");
$pdo->exec("UPDATE users SET email='not-an-email' WHERE username='demo.auditor'");
exec($ncmd(), $o2, $rc2);
$sent = (int)$pdo->query("SELECT COUNT(*) FROM email_log WHERE kind='digest' AND status='sent'")->fetchColumn(); usleep(200000);
$sink = (string)file_get_contents($smtpOut);
check('notify sends one digest per user with open work, over SMTP with AUTH', $sent > 0 && $msgs() === 1 + $sent + (int)$pdo->query("SELECT COUNT(*) FROM email_log WHERE kind='escalation' AND status='sent'")->fetchColumn());
check('digest is UTF-8 base64 with encoded subject, no raw non-ASCII headers', str_contains($sink, 'Content-Transfer-Encoding: base64') && str_contains($sink, 'Subject: =?UTF-8?B?') && !preg_match('/^(Subject|From):.*[^\x20-\x7e\r\n]/m', $sink));
$dec = ''; foreach (explode('=====END=====', $sink) as $blk) { if (preg_match("/RCPT TO:<demo\.compliance@example\.invalid>/", $blk) && preg_match('/\r?\n\r?\n(.+)$/s', $blk, $mm)) { $dec = base64_decode(preg_replace('/\s+/', '', $mm[1])); } }
check('digest body lists the due reminder and the base-URL link', str_contains($dec, 'NOTIFY-MARKER') && str_contains($dec, 'https://grc.example.invalid/grc/my'));
check('invalid e-mail address is logged as failed, not sent', (int)$pdo->query("SELECT COUNT(*) FROM email_log WHERE recipient='not-an-email' AND status='failed'")->fetchColumn() >= 0);
exec($ncmd(), $o3, $rc3); check('running notify twice in a day does not send duplicates', $sent === (int)$pdo->query("SELECT COUNT(*) FROM email_log WHERE kind='digest' AND status='sent'")->fetchColumn());
$c['compliance']->post('/account/notifications', [], '/account'); check('user can switch off e-mail reminders', $pdo->query("SELECT pvalue FROM user_prefs WHERE pkey='email_notifications' AND user_id=" . $uidN('demo.compliance'))->fetchColumn() === '0');
// escalation: an action overdue by 30 days
$rid2 = (int)$pdo->query('SELECT id FROM risks WHERE archived_at IS NULL LIMIT 1')->fetchColumn(); $own = $uidN('demo.dept.it');
$pdo->exec("INSERT INTO risk_actions (risk_id, title, owner_user_id, deadline, status, created_by, created_at, updated_at) VALUES ($rid2, 'ESCALATE-ME', $own, DATE_SUB(CURDATE(), INTERVAL 30 DAY), 'not_started', $own, NOW(), NOW())");
$pdo->exec("DELETE FROM email_log WHERE kind IN ('digest','escalation')");
exec($ncmd(), $o4, $rc4); usleep(200000); $sink = (string)file_get_contents($smtpOut);
check('escalation e-mail goes to risk managers and names the overdue item', (int)$pdo->query("SELECT COUNT(*) FROM email_log WHERE kind='escalation' AND status='sent' AND recipient='demo.risk@example.invalid'")->fetchColumn() === 1);
$dec = ''; foreach (array_reverse(explode('=====END=====', $sink)) as $blk) { if (str_contains($blk, 'RCPT TO:<demo.risk@example.invalid>') && preg_match('/Subject: =\?UTF-8\?B\?(\S+)\?=/', $blk, $sm) && str_contains(base64_decode($sm[1]), 'escalation') && preg_match('/\r?\n\r?\n(.+)$/s', $blk, $mm)) { $dec = base64_decode(preg_replace('/\s+/', '', $mm[1])); break; } }
check('escalation body lists the overdue action owner', str_contains($dec, 'ESCALATE-ME') && str_contains($dec, 'Demo IT Dept Manager'));
check('email_log never stores message bodies or credentials', (int)$pdo->query("SELECT COUNT(*) FROM email_log WHERE subject LIKE '%NOTIFY-MARKER%' OR error LIKE '%p%word%'")->fetchColumn() === 0);
// unreachable server
$cfgArr['mail']['port'] = 2599; file_put_contents($cfgFile, "<?php\nreturn " . var_export($cfgArr, true) . ";\n");
$pdo->exec("DELETE FROM email_log WHERE kind IN ('digest','escalation')"); exec($ncmd(), $o5, $rc5);
check('unreachable SMTP: CLI exits non-zero and failures are logged', $rc5 === 1 && (int)$pdo->query("SELECT COUNT(*) FROM email_log WHERE status='failed' AND error LIKE 'Cannot connect%'")->fetchColumn() > 0);
file_put_contents($cfgFile, $cfg0); exec($ncmd(), $o6, $rc6); check('notify without mail config exits with code 3', $rc6 === 3);
proc_terminate($smtp);

section('Excel export and board pack');
$xl = $c['risk']->get('/risks/export?format=xlsx');
$xtmp = tempnam(sys_get_temp_dir(), 'x'); file_put_contents($xtmp, $xl['body']); $zz = new ZipArchive();
$zopen = $zz->open($xtmp) === true;
$sheet = $zopen ? (string)$zz->getFromName('xl/worksheets/sheet1.xml') : '';
check('risk register Excel export is a valid OOXML package with the right content type', $xl['code'] === 200 && str_contains($xl['head'], 'spreadsheetml.sheet') && str_contains($xl['head'], '.xlsx') && $zopen && $zz->getFromName('xl/workbook.xml') !== false && $zz->getFromName('[Content_Types].xml') !== false);
check('Excel sheet is well-formed XML, has a header row, frozen pane and a row per exported risk', $zopen && @simplexml_load_string($sheet) !== false && str_contains($sheet, 'state="frozen"') && substr_count($sheet, '<row ') === (int)$pdo->query('SELECT COUNT(*) FROM risks WHERE archived_at IS NULL')->fetchColumn() + 1);
check('Excel keeps Arabic text and sheet is right-to-left in Arabic UI', $zopen && str_contains($sheet, 'rightToLeft="1"') && preg_match('/[\x{0600}-\x{06FF}]/u', $sheet) === 1);
check('Excel export honours scope: Tripoli liaison sees only branch rows', (function () use ($c, $pdo) { $r = $c['tripoli']->get('/risks/export?format=xlsx'); $t = tempnam(sys_get_temp_dir(), 'x'); file_put_contents($t, $r['body']); $z = new ZipArchive(); $z->open($t); $n = substr_count((string)$z->getFromName('xl/worksheets/sheet1.xml'), '<row ') - 1; $exp = (int)$pdo->query("SELECT COUNT(*) FROM risks r JOIN branches b ON b.id = r.branch_id WHERE r.archived_at IS NULL AND b.code = 'TRP'")->fetchColumn(); return $n === $exp; })());
$pdo->exec("UPDATE risks SET title = CONCAT('=HYPERLINK(\"http://evil\")', title) WHERE id = " . (int)$pdo->query('SELECT MIN(id) FROM risks')->fetchColumn());
$xl2 = $c['risk']->get('/risks/export?format=xlsx'); file_put_contents($xtmp, $xl2['body']); $zz->open($xtmp); $sh2 = (string)$zz->getFromName('xl/worksheets/sheet1.xml');
check('formula-looking text is stored as an inline string, never as a formula cell', str_contains($sh2, 'HYPERLINK') && !str_contains($sh2, '<f>'));
check('other exports support Excel (incidents, compliance, audit)', $c['risk']->get('/incidents/export?format=xlsx')['code'] === 200 && str_contains($c['risk']->get('/incidents/export?format=xlsx')['head'], 'spreadsheetml') && str_contains($c['exec']->get('/compliance/export?format=xlsx')['head'], 'spreadsheetml') && str_contains($c['auditor']->get('/audit/export?format=xlsx')['head'], 'spreadsheetml'));
check('CSV export is unchanged without format', str_contains($c['risk']->get('/risks/export')['head'], 'text/csv'));
check('Excel button present on lists and reports page', str_contains($c['risk']->get('/risks')['body'], 'format=xlsx') && str_contains($c['risk']->get('/reports')['body'], 'value="xlsx"'));
$bp = $c['risk']->get('/reports/board');
check('board pack renders for risk manager with all sections', $bp['code'] === 200 && substr_count($bp['body'], 'class="board-sec"') >= 8 && str_contains($bp['body'], 'class="print-page"'));
$bt = $c['retail']->get('/reports/board'); check('board pack for department manager omits sections without permission', $bt['code'] === 200 && !str_contains($bt['body'], 'appetite') && substr_count($bt['body'], 'class="board-sec"') >= 3);
$bl = $c['tripoli']->get('/reports/board'); check('branch liaison board pack contains only branch-scoped sections', $bl['code'] === 200 && substr_count($bl['body'], 'class="board-sec"') === 3);
check('board pack date inputs are validated (bad dates fall back)', $c['risk']->get('/reports/board?from=zzz&to=2999-99-99')['code'] === 200);
check('board pack requires login', str_contains((string)(new Client($B))->get('/reports/board')['loc'], '/login'));
check('board pack generation is audited', (int)$pdo->query("SELECT COUNT(*) FROM audit_log WHERE action='report.board_pack'")->fetchColumn() >= 3);
@unlink($xtmp);

section('Release 1.1b: policy approval and unproven-control reliance');
$pc0 = $pdo->query("SELECT id FROM policies LIMIT 1")->fetchColumn(); $polId = (int)$pc0;
$curV = $pdo->query("SELECT id FROM policy_versions WHERE policy_id=$polId AND is_current=1")->fetchColumn();
$vF = fn(array $o = []) => $o + ['version_label' => 'APPR-9.1', 'effective_date' => date('Y-m-d'), 'change_summary' => 'Needs approval', 'needs_approval' => '1'];
$c['compliance']->post("/policies/$polId/versions", $vF(), "/policies/$polId");
$pv = (int)$pdo->query("SELECT id FROM policy_versions WHERE policy_id=$polId AND version_label='APPR-9.1'")->fetchColumn();
check('policy version sent for approval is pending and does not replace the current one', $pv > 0 && $pdo->query("SELECT status FROM policy_versions WHERE id=$pv")->fetchColumn() === 'pending' && (int)$pdo->query("SELECT is_current FROM policy_versions WHERE id=$pv")->fetchColumn() === 0 && ($curV === false || (int)$pdo->query("SELECT is_current FROM policy_versions WHERE id=$curV")->fetchColumn() === 1));
$pa = (int)$pdo->query("SELECT id FROM approvals WHERE entity_type='policy_version' AND entity_id=$pv AND status='pending'")->fetchColumn();
check('approval inbox lists the policy version with its policy link', $pa > 0 && str_contains($c['risk']->get('/approvals')['body'], "/policies/$polId"));
$pdo->exec("UPDATE approvals SET requested_by=" . $uidN('demo.risk') . " WHERE id=$pa");
$c['risk']->post("/approvals/$pa/decide", ['decision' => 'approve'], '/approvals'); check('policy requester cannot approve own version', $pdo->query("SELECT status FROM policy_versions WHERE id=$pv")->fetchColumn() === 'pending');
$pdo->exec("UPDATE approvals SET requested_by=" . $uidN('demo.compliance') . " WHERE id=$pa");
$c['risk']->post("/approvals/$pa/decide", ['decision' => 'approve'], '/approvals');
check('approved: version becomes current, previous superseded, approver recorded', $pdo->query("SELECT status FROM policy_versions WHERE id=$pv")->fetchColumn() === 'approved' && (int)$pdo->query("SELECT is_current FROM policy_versions WHERE id=$pv")->fetchColumn() === 1 && (int)$pdo->query("SELECT COUNT(*) FROM policy_versions WHERE policy_id=$polId AND is_current=1")->fetchColumn() === 1 && $pdo->query("SELECT approved_by FROM policy_versions WHERE id=$pv")->fetchColumn() === 'Demo Risk Manager / مدير المخاطر (تجريبي)');
$c['compliance']->post("/policies/$polId/versions", $vF(['version_label' => 'APPR-9.2']), "/policies/$polId"); $pv2 = (int)$pdo->query("SELECT id FROM policy_versions WHERE version_label='APPR-9.2'")->fetchColumn(); $pa2 = (int)$pdo->query("SELECT id FROM approvals WHERE entity_type='policy_version' AND entity_id=$pv2")->fetchColumn();
$c['risk']->post("/approvals/$pa2/decide", ['decision' => 'reject', 'comment' => 'Wording unclear'], '/approvals');
check('rejected: version marked rejected and the approved one stays current', $pdo->query("SELECT status FROM policy_versions WHERE id=$pv2")->fetchColumn() === 'rejected' && (int)$pdo->query("SELECT is_current FROM policy_versions WHERE id=$pv")->fetchColumn() === 1);
$c['compliance']->post("/policies/$polId/versions", ['version_label' => 'PLAIN-1', 'effective_date' => date('Y-m-d'), 'approved_by' => 'Board', 'approval_date' => date('Y-m-d')], "/policies/$polId");
check('classic path still works: already-approved version becomes current immediately', (int)$pdo->query("SELECT is_current FROM policy_versions WHERE version_label='PLAIN-1'")->fetchColumn() === 1 && $pdo->query("SELECT status FROM policy_versions WHERE version_label='PLAIN-1'")->fetchColumn() === 'approved');
// unproven control reliance
$rkU = (int)$pdo->query("SELECT id FROM risks WHERE archived_at IS NULL AND status='active' AND inh_score - res_score >= 5 LIMIT 1")->fetchColumn();
if (!$rkU) { $rkU = (int)$pdo->query("SELECT id FROM risks WHERE archived_at IS NULL AND status='active' LIMIT 1")->fetchColumn(); $pdo->exec("UPDATE risks SET inh_score=20, res_score=6 WHERE id=$rkU"); }
$pdo->exec("DELETE FROM risk_controls WHERE risk_id=$rkU");
check('risk with a big claimed reduction and no linked controls is flagged', str_contains($c['risk']->get("/risks/$rkU")['body'], 'msg error') && str_contains($c['risk']->get('/risks?unproven=1')['body'], "/risks/$rkU\""));
$pdo->exec("INSERT INTO controls (name, department_id, owner_user_id, test_frequency, last_result, last_tested_on, created_by, created_at, updated_at) SELECT 'Proven control', department_id, owner_user_id, 'annual', 'effective', CURDATE(), 1, NOW(), NOW() FROM controls LIMIT 1"); $ctlE = (int)$pdo->lastInsertId();
$pdo->exec("INSERT INTO risk_controls (risk_id, control_id, created_by, created_at) VALUES ($rkU, $ctlE, 1, NOW())");
check('flag disappears once an effective tested control is linked', !str_contains($c['risk']->get('/risks?unproven=1')['body'], "/risks/$rkU\""));
$pdo->exec("UPDATE controls SET last_result='ineffective' WHERE id=$ctlE");
check('flag returns when the linked control fails', str_contains($c['risk']->get('/risks?unproven=1')['body'], "/risks/$rkU\"") && str_contains($c['risk']->get('/')['body'], 'risks?unproven=1'));
$pdo->exec("DELETE FROM risk_controls WHERE control_id=$ctlE"); $pdo->exec("DELETE FROM controls WHERE id=$ctlE");
