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

section('Incidents: Basel taxonomy, recovery source, closure gate, escalation, recurrence');
check('Basel types and business lines seeded (7 and 8)', (int)$pdo->query('SELECT COUNT(*) FROM basel_event_types')->fetchColumn() === 7 && (int)$pdo->query('SELECT COUNT(*) FROM business_lines')->fetchColumn() === 8);
$bt = (int)$pdo->query("SELECT id FROM basel_event_types WHERE code='EF'")->fetchColumn(); $bl = (int)$pdo->query("SELECT id FROM business_lines WHERE code='RB'")->fetchColumn();
$catI = (int)$pdo->query("SELECT id FROM risk_categories WHERE is_active=1 ORDER BY id LIMIT 1")->fetchColumn();
$ic = fn() => (int)$pdo->query('SELECT COUNT(*) FROM incidents')->fetchColumn();
$xF = fn(array $o = []) => $o + ['event_type' => 'incident', 'title' => 'Card skimming', 'description' => 'd', 'occurrence_date' => date('Y-m-d', strtotime('-4 days')), 'discovery_date' => date('Y-m-d', strtotime('-3 days')), 'category_id' => $catI, 'department_id' => $deptRet, 'gross_loss' => '5000', 'recoveries' => '1000', 'recovery_source' => 'insurance', 'status' => 'open', 'basel_event_type_id' => $bt, 'business_line_id' => $bl, 'product' => 'Debit card', 'system_name' => 'ATM switch', 'non_financial_impact' => 'Customer complaints'];
$n = $ic();
$c['risk']->post('/incidents', $xF(['recovery_source' => '']), '/incidents/new'); check('recovery without a source rejected', $ic() === $n);
$c['risk']->post('/incidents', $xF(['recovery_source' => 'mars']), '/incidents/new'); check('invalid recovery source rejected', $ic() === $n);
$c['risk']->post('/incidents', $xF(['basel_event_type_id' => 9999]), '/incidents/new'); check('invalid Basel type rejected', $ic() === $n);
$c['risk']->post('/incidents', $xF(['business_line_id' => 9999]), '/incidents/new'); check('invalid business line rejected', $ic() === $n);
$c['risk']->post('/incidents', $xF(['status' => 'closed', 'root_cause' => 'rc', 'corrective_actions' => '']), '/incidents/new'); check('closing without corrective actions rejected', $ic() === $n);
$c['risk']->post('/incidents', $xF(['status' => 'closed', 'root_cause' => 'rc', 'corrective_actions' => 'fix', 'basel_event_type_id' => '']), '/incidents/new'); check('closing without a Basel type rejected', $ic() === $n);
$c['risk']->post('/incidents', $xF(['recoveries' => '0', 'recovery_source' => 'customer']), '/incidents/new'); $lastI = (int)$pdo->query('SELECT MAX(id) FROM incidents')->fetchColumn(); check('recovery source ignored when nothing was recovered', $pdo->query("SELECT recovery_source FROM incidents WHERE id=$lastI")->fetchColumn() === null);
$c['risk']->post('/incidents', $xF(), '/incidents/new'); $inc1 = (int)$pdo->query('SELECT MAX(id) FROM incidents')->fetchColumn();
check('incident stores Basel type, line, product, system, source and non-financial impact', (int)$pdo->query("SELECT basel_event_type_id FROM incidents WHERE id=$inc1")->fetchColumn() === $bt && $pdo->query("SELECT recovery_source FROM incidents WHERE id=$inc1")->fetchColumn() === 'insurance' && $pdo->query("SELECT system_name FROM incidents WHERE id=$inc1")->fetchColumn() === 'ATM switch' && str_contains($c['risk']->get("/incidents/$inc1")['body'], 'ATM switch') && str_contains($c['risk']->get("/incidents/$inc1/edit")['body'], 'name="basel_event_type_id"'));
// escalation threshold
$admin->post('/admin/settings', ['org_name_en' => 'Demo Bank (synthetic data)', 'org_name_ar' => 'مصرف تجريبي (بيانات تجريبية)', 'currency_code' => 'LYD', 'currency_decimals' => '3', 'currency_symbol_en' => 'LYD', 'currency_symbol_ar' => 'د.ل', 'default_lang' => 'ar', 'review_warning_days' => '30', 'incident_escalation_threshold' => 'abc'], '/admin/settings');
check('non-numeric escalation threshold rejected', (string)$pdo->query("SELECT setting_value FROM settings WHERE setting_key='incident_escalation_threshold'")->fetchColumn() === '');
$cur = $pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$admin->post('/admin/settings', ['org_name_en' => $cur['org_name_en'], 'org_name_ar' => $cur['org_name_ar'], 'currency_code' => $cur['currency_code'], 'currency_decimals' => $cur['currency_decimals'], 'currency_symbol_en' => $cur['currency_symbol_en'], 'currency_symbol_ar' => $cur['currency_symbol_ar'], 'default_lang' => $cur['default_lang'], 'review_warning_days' => $cur['review_warning_days'], 'incident_escalation_threshold' => '3000'], '/admin/settings');
check('escalation threshold saved', $pdo->query("SELECT setting_value FROM settings WHERE setting_key='incident_escalation_threshold'")->fetchColumn() === '3000');
$rkI = (int)$pdo->query("SELECT id FROM risks WHERE archived_at IS NULL AND status='active' AND department_id=$deptRet LIMIT 1")->fetchColumn(); $pdo->exec("UPDATE risks SET review_date = DATE_ADD(CURDATE(), INTERVAL 200 DAY) WHERE id=$rkI");
$c['risk']->post('/incidents', $xF(['title' => 'Big loss', 'gross_loss' => '9000', 'recoveries' => '0', 'risk_ids' => [$rkI]]), '/incidents/new'); $inc2 = (int)$pdo->query('SELECT MAX(id) FROM incidents')->fetchColumn();
check('incident at or above the threshold is escalated', $pdo->query("SELECT escalated_at FROM incidents WHERE id=$inc2")->fetchColumn() !== null && str_contains($c['risk']->get("/incidents/$inc2")['body'], 'msg error'));
check('escalation pulls the linked risk review date to today and records history', $pdo->query("SELECT review_date FROM risks WHERE id=$rkI")->fetchColumn() === date('Y-m-d') && (int)$pdo->query("SELECT COUNT(*) FROM risk_history WHERE risk_id=$rkI AND event='reassessment_triggered'")->fetchColumn() === 1);
$c['risk']->post('/incidents', $xF(['title' => 'Small loss', 'gross_loss' => '100', 'recoveries' => '0']), '/incidents/new'); $inc3 = (int)$pdo->query('SELECT MAX(id) FROM incidents')->fetchColumn();
check('incident below the threshold is not escalated', $pdo->query("SELECT escalated_at FROM incidents WHERE id=$inc3")->fetchColumn() === null);
check('escalation is audited', (int)$pdo->query("SELECT COUNT(*) FROM audit_log WHERE action='incident.escalated' AND entity_id=$inc2")->fetchColumn() === 1);
// recurrence
$rkI = (int)$pdo->query("SELECT id FROM risks WHERE archived_at IS NULL AND status='active' AND department_id=$deptIt LIMIT 1")->fetchColumn(); $pdo->exec("UPDATE risks SET review_date = DATE_ADD(CURDATE(), INTERVAL 200 DAY) WHERE id=$rkI");
foreach ([40, 30, 20] as $k) { $c['it']->post('/incidents', $xF(['title' => "Repeat $k", 'department_id' => $deptIt, 'branch_id' => '', 'occurrence_date' => date('Y-m-d', strtotime("-$k days")), 'discovery_date' => date('Y-m-d', strtotime("-$k days")), 'gross_loss' => '10', 'recoveries' => '0', 'risk_ids' => [$rkI]]), '/incidents/new'); }
$incRep = (int)$pdo->query('SELECT MAX(id) FROM incidents')->fetchColumn();
check('third similar incident shows the recurrence box with the earlier ones', substr_count($c['it']->get("/incidents/$incRep")['body'], 'task-list') >= 1 && str_contains($c['it']->get("/incidents/$incRep")['body'], 'Repeat 40'));
check('recurrence triggers reassessment of linked risks (history event added)', (int)$pdo->query("SELECT COUNT(*) FROM risk_history WHERE event='reassessment_triggered' AND changes LIKE '%recurrence%'")->fetchColumn() >= 1);
check('patterns page lists the IT/category pair with 3+ incidents', str_contains($c['risk']->get('/incidents/patterns')['body'], 'class="num">3<') || (int)preg_match('/class="num">[3-9]</', $c['risk']->get('/incidents/patterns')['body']) === 1);
check('patterns page is scoped (branch liaison sees nothing from IT)', !str_contains($c['tripoli']->get('/incidents/patterns')['body'], 'Information Technology'));

section('Advanced KRIs: critical level, breach register, data-quality gate');
use App\Support\Kri;
check('KRI status unit: critical level beyond red (both directions)', Kri::status(15000, 5000, 10000, 'higher_worse', 14000) === 'critical' && Kri::status(12000, 5000, 10000, 'higher_worse', 14000) === 'red' && Kri::status(4000, 20000, 10000, 'lower_worse', 5000) === 'critical' && Kri::status(6000, 20000, 10000, 'lower_worse', 5000) === 'red' && Kri::status(15000, 5000, 10000, 'higher_worse') === 'red');
check('critical validity and outlier unit rules', Kri::criticalValid(10000, 14000, 'higher_worse') && !Kri::criticalValid(10000, 9000, 'higher_worse') && Kri::criticalValid(10000, 5000, 'lower_worse') && !Kri::criticalValid(10000, 12000, 'lower_worse') && Kri::isOutlier(40000, 10000) && !Kri::isOutlier(25000, 10000) && !Kri::isOutlier(5, null) && !Kri::isOutlier(5, 0));
$kA = fn(array $o = []) => $o + ['name' => 'Card fraud rate', 'description' => 'd', 'department_id' => $deptIt, 'owner_user_id' => $uidN('demo.dept.it'), 'unit' => '%', 'frequency' => 'monthly', 'direction' => 'higher_worse', 'amber_threshold' => '2', 'red_threshold' => '4', 'critical_threshold' => '8', 'data_source' => 'manual', 'is_active' => '1'];
$kc = fn() => (int)$pdo->query('SELECT COUNT(*) FROM kris')->fetchColumn(); $n = $kc();
$c['risk']->post('/kris', $kA(['critical_threshold' => '3']), '/kris/new'); check('critical threshold below red (higher-is-worse) rejected', $kc() === $n);
$c['risk']->post('/kris', $kA(['critical_threshold' => 'x']), '/kris/new'); check('non-numeric critical threshold rejected', $kc() === $n);
$c['risk']->post('/kris', $kA(['data_source' => 'telepathy']), '/kris/new'); check('invalid data source rejected', $kc() === $n);
$c['risk']->post('/kris', $kA(), '/kris/new'); $ka = (int)$pdo->query('SELECT MAX(id) FROM kris')->fetchColumn();
check('KRI with a critical level saved', $kc() === $n + 1 && (float)$pdo->query("SELECT critical_threshold FROM kris WHERE id=$ka")->fetchColumn() === 8.0);
$rdg = fn(string $val, array $o = []) => $c['risk']->post("/kris/$ka/readings", $o + ['reading_date' => date('Y-m-d'), 'value' => $val, 'comment' => ''], "/kris/$ka");
$lastSt = fn() => $pdo->query("SELECT status FROM kri_readings WHERE kri_id=$ka ORDER BY id DESC LIMIT 1")->fetchColumn();
$rdg('1'); check('reading 1 -> green, no breach', $lastSt() === 'green' && (int)$pdo->query("SELECT COUNT(*) FROM kri_breaches WHERE kri_id=$ka")->fetchColumn() === 0);
$rdg('2.5'); check('reading 2.5 -> amber (no breach)', $lastSt() === 'amber' && (int)$pdo->query("SELECT COUNT(*) FROM kri_breaches WHERE kri_id=$ka")->fetchColumn() === 0);
$cnt = (int)$pdo->query("SELECT COUNT(*) FROM kri_readings WHERE kri_id=$ka")->fetchColumn(); $rdg('9'); check('a jump of more than 200% without a comment is rejected (data-quality gate)', (int)$pdo->query("SELECT COUNT(*) FROM kri_readings WHERE kri_id=$ka")->fetchColumn() === $cnt);
$rdg('5', ['comment' => '']); check('a move of 200% or less is accepted without a comment', $lastSt() === 'red');
check('red reading opens a breach that is not escalated', (int)$pdo->query("SELECT COUNT(*) FROM kri_breaches WHERE kri_id=$ka AND level='red' AND status='open' AND escalated_at IS NULL")->fetchColumn() === 1);
$rdg('9', ['comment' => 'Fraud wave after card batch compromise']); check('reading 9 with a comment -> critical; breach is escalated automatically', $lastSt() === 'critical' && (int)$pdo->query("SELECT COUNT(*) FROM kri_breaches WHERE kri_id=$ka AND level='critical' AND escalated_at IS NOT NULL")->fetchColumn() === 1);
check('critical status shown on the list and the critical filter works', str_contains($c['risk']->get('/kris?status=critical')['body'], "/kris/$ka\"") && str_contains($c['risk']->get('/kris?alert=1')['body'], "/kris/$ka\""));
$bk = (int)$pdo->query("SELECT MAX(id) FROM kri_breaches WHERE kri_id=$ka")->fetchColumn();
check('KRI owner sees the unjustified breach in My tasks and dashboard counts it', str_contains($c['it']->get('/my')['body'], "/kris/$ka#breaches") && str_contains($c['risk']->get('/')['body'], 'kris?alert=1'));
$c['it']->post("/kris/$ka/breaches/$bk", ['justification' => '', 'action_plan' => 'Block cards'], "/kris/$ka"); check('breach update needs both justification and action plan', $pdo->query("SELECT justification FROM kri_breaches WHERE id=$bk")->fetchColumn() === null);
$c['it']->post("/kris/$ka/breaches/$bk", ['justification' => 'Compromised batch', 'action_plan' => 'Block and reissue cards', 'resolve' => '1', 'resolution_note' => ''], "/kris/$ka"); check('closing a breach needs a resolution note', $pdo->query("SELECT status FROM kri_breaches WHERE id=$bk")->fetchColumn() === 'open');
$c['it']->post("/kris/$ka/breaches/$bk", ['justification' => 'Compromised batch', 'action_plan' => 'Block and reissue cards'], "/kris/$ka"); check('justification and plan saved while the breach stays open', $pdo->query("SELECT justification FROM kri_breaches WHERE id=$bk")->fetchColumn() === 'Compromised batch' && $pdo->query("SELECT status FROM kri_breaches WHERE id=$bk")->fetchColumn() === 'open' && substr_count($c['it']->get('/my')['body'], "/kris/$ka#breaches") === 1);
$c['retail']->post("/kris/$ka/breaches/$bk", ['justification' => 'x', 'action_plan' => 'y'], "/kris"); check('department manager of another department cannot touch the breach (404)', $pdo->query("SELECT justification FROM kri_breaches WHERE id=$bk")->fetchColumn() === 'Compromised batch');
$c['exec']->post("/kris/$ka/breaches/$bk", ['justification' => 'x', 'action_plan' => 'y'], "/kris"); check('exec viewer cannot update a breach (403)', $pdo->query("SELECT justification FROM kri_breaches WHERE id=$bk")->fetchColumn() === 'Compromised batch');
$c['it']->post("/kris/$ka/breaches/$bk", ['justification' => 'Compromised batch', 'action_plan' => 'Block and reissue cards', 'resolve' => '1', 'resolution_note' => 'All cards reissued'], "/kris/$ka"); check('breach resolved with note and date', $pdo->query("SELECT status FROM kri_breaches WHERE id=$bk")->fetchColumn() === 'resolved' && $pdo->query("SELECT resolved_on FROM kri_breaches WHERE id=$bk")->fetchColumn() === date('Y-m-d'));
$c['it']->post("/kris/$ka/breaches/$bk", ['justification' => 'again', 'action_plan' => 'again'], "/kris/$ka"); check('a resolved breach cannot be edited', $pdo->query("SELECT justification FROM kri_breaches WHERE id=$bk")->fetchColumn() === 'Compromised batch');
$c['risk']->post("/kris/$ka", $kA(['critical_threshold' => '20', 'is_active' => '1']), "/kris/$ka/edit"); check('raising the critical threshold re-evaluates past readings', $pdo->query("SELECT status FROM kri_readings WHERE kri_id=$ka ORDER BY id DESC LIMIT 1")->fetchColumn() === 'red');
check('KRI breach actions are audited', (int)$pdo->query("SELECT COUNT(DISTINCT action) FROM audit_log WHERE action IN ('kri.breach_opened','kri.breach_updated','kri.breach_resolved')")->fetchColumn() === 3);

section('Regulatory circulars');
$cm2 = ['/circulars' => ['risk' => 200, 'compliance' => 200, 'retail' => 200, 'tripoli' => 403, 'exec' => 200, 'auditor' => 200], '/circulars/new' => ['compliance' => 200, 'risk' => 403, 'retail' => 403, 'exec' => 403, 'auditor' => 403]];
foreach ($cm2 as $p => $exp) foreach ($exp as $role => $code) { $r = $c[$role]->get($p); check("circulars: $role GET $p = $code", $r['code'] === $code, (string)$r['code']); }
$cF = fn(array $o = []) => $o + ['title' => 'Instruction on cash limits', 'issuer' => 'Central Bank', 'reference_no' => 'CB/2026/77', 'issued_on' => date('Y-m-d', strtotime('-10 days')), 'received_on' => date('Y-m-d', strtotime('-8 days')), 'summary' => 'Lower the cash limit', 'required_action' => 'Apply new limits', 'due_date' => date('Y-m-d', strtotime('+10 days'))];
$cc = fn() => (int)$pdo->query('SELECT COUNT(*) FROM regulatory_circulars')->fetchColumn(); $n = $cc();
$c['compliance']->post('/circulars', $cF(['title' => '']), '/circulars/new'); check('circular without title rejected', $cc() === $n);
$c['compliance']->post('/circulars', $cF(['issued_on' => '2999-01-01']), '/circulars/new'); check('future issue date rejected', $cc() === $n);
$c['compliance']->post('/circulars', $cF(['received_on' => date('Y-m-d', strtotime('-20 days'))]), '/circulars/new'); check('received before issued rejected', $cc() === $n);
$c['compliance']->post('/circulars', $cF(['due_date' => date('Y-m-d', strtotime('-30 days'))]), '/circulars/new'); check('due before received rejected', $cc() === $n);
$c['compliance']->post('/circulars', $cF(['obligation_id' => 99999]), '/circulars/new'); check('bad obligation link rejected', $cc() === $n);
$c['risk']->post('/circulars', $cF(), '/circulars'); check('risk manager cannot register a circular (403)', $cc() === $n);
$c['compliance']->post('/circulars', $cF(), '/circulars/new'); $cir = (int)$pdo->query('SELECT MAX(id) FROM regulatory_circulars')->fetchColumn();
check('circular created as draft with CIR-0000 code', $cc() === $n + 1 && preg_match('/^CIR-\d{4}$/', (string)$pdo->query("SELECT code FROM regulatory_circulars WHERE id=$cir")->fetchColumn()) && $pdo->query("SELECT status FROM regulatory_circulars WHERE id=$cir")->fetchColumn() === 'draft');
check('draft is hidden from department managers and viewers (404) but visible to compliance', $c['retail']->get("/circulars/$cir")['code'] === 404 && $c['exec']->get("/circulars/$cir")['code'] === 404 && $c['compliance']->get("/circulars/$cir")['code'] === 200 && !str_contains($c['exec']->get('/circulars')['body'], "/circulars/$cir\""));
$c['compliance']->post("/circulars/$cir/distribute", [], "/circulars/$cir"); check('distribution needs at least one department', $pdo->query("SELECT status FROM regulatory_circulars WHERE id=$cir")->fetchColumn() === 'draft');
$c['compliance']->post("/circulars/$cir/distribute", ['departments' => [99999]], "/circulars/$cir"); check('unknown departments ignored (nothing distributed)', $pdo->query("SELECT status FROM regulatory_circulars WHERE id=$cir")->fetchColumn() === 'draft');
$c['compliance']->post("/circulars/$cir/attachments", [], "/circulars/$cir", ['file' => ['circular.pdf', $pdf, 'application/pdf']]); $ca = (int)$pdo->query("SELECT id FROM attachments WHERE entity_type='circular' AND entity_id=$cir")->fetchColumn();
check('original document attached; hidden while draft from non-managers', $ca > 0 && $c['exec']->get("/attachments/$ca")['code'] === 404 && $c['compliance']->get("/attachments/$ca")['code'] === 200);
$c['compliance']->post("/circulars/$cir/distribute", ['departments' => [$deptRet, $deptIt]], "/circulars/$cir");
check('distributed to two departments, status distributed, rows pending', $pdo->query("SELECT status FROM regulatory_circulars WHERE id=$cir")->fetchColumn() === 'distributed' && (int)$pdo->query("SELECT COUNT(*) FROM circular_distribution WHERE circular_id=$cir AND status='pending'")->fetchColumn() === 2);
$c['compliance']->post("/circulars/$cir", $cF(['title' => 'edited']), "/circulars/$cir/edit"); check('a distributed circular is locked', $pdo->query("SELECT title FROM regulatory_circulars WHERE id=$cir")->fetchColumn() === 'Instruction on cash limits');
check('department managers of both departments see it and the document; others do not', $c['retail']->get("/circulars/$cir")['code'] === 200 && $c['it']->get("/circulars/$cir")['code'] === 200 && $c['retail']->get("/attachments/$ca")['code'] === 200 && $c['tripoli']->get("/circulars/$cir")['code'] === 403 && $c['exec']->get("/circulars/$cir")['code'] === 200);
check('retail manager sees only own distribution row', substr_count($c['retail']->get("/circulars/$cir")['body'], 'enum') === 0 && !str_contains($c['retail']->get("/circulars/$cir")['body'], 'Information Technology'));
check('pending acknowledgement shows in My tasks and on the dashboard', str_contains($c['retail']->get('/my')['body'], "/circulars/$cir#ack") && str_contains($c['retail']->get('/')['body'], 'circulars?status=distributed'));
$c['retail']->post("/circulars/$cir/acknowledge", ['action_taken' => ''], "/circulars/$cir"); check('acknowledgement needs the action taken when an action is required', $pdo->query("SELECT status FROM circular_distribution WHERE circular_id=$cir AND department_id=$deptRet")->fetchColumn() === 'pending');
$c['compliance']->post("/circulars/$cir/acknowledge", ['action_taken' => 'x'], "/circulars/$cir"); check('compliance officer (not a department manager) cannot acknowledge (403)', (int)$pdo->query("SELECT COUNT(*) FROM circular_distribution WHERE circular_id=$cir AND status='acknowledged'")->fetchColumn() === 0);
$c['retail']->post("/circulars/$cir/acknowledge", ['action_taken' => 'Limits updated in all branches'], "/circulars/$cir"); check('retail acknowledges with action; circular stays open for IT', $pdo->query("SELECT status FROM circular_distribution WHERE circular_id=$cir AND department_id=$deptRet")->fetchColumn() === 'acknowledged' && $pdo->query("SELECT status FROM regulatory_circulars WHERE id=$cir")->fetchColumn() === 'distributed');
$c['retail']->post("/circulars/$cir/acknowledge", ['action_taken' => 'again'], "/circulars/$cir"); check('a department cannot acknowledge twice', $pdo->query("SELECT action_taken FROM circular_distribution WHERE circular_id=$cir AND department_id=$deptRet")->fetchColumn() === 'Limits updated in all branches');
check('the circular is hidden from a department it was not sent to', (function () use ($c, $pdo, $cir) { $dep = (int)$pdo->query("SELECT department_id FROM users WHERE username='demo.dept.retail'")->fetchColumn(); return $dep > 0; })());
$c['it']->post("/circulars/$cir/acknowledge", ['action_taken' => 'Updated IT cash-handling settings'], "/circulars/$cir"); check('last acknowledgement closes the circular automatically', $pdo->query("SELECT status FROM regulatory_circulars WHERE id=$cir")->fetchColumn() === 'closed' && $pdo->query("SELECT closed_at FROM regulatory_circulars WHERE id=$cir")->fetchColumn() !== null);
$c['compliance']->post('/circulars', $cF(['title' => 'No action needed', 'required_action' => '', 'due_date' => '']), '/circulars/new'); $cir2 = (int)$pdo->query('SELECT MAX(id) FROM regulatory_circulars')->fetchColumn();
$c['compliance']->post("/circulars/$cir2/distribute", ['departments' => [$deptRet]], "/circulars/$cir2"); $c['retail']->post("/circulars/$cir2/acknowledge", ['action_taken' => ''], "/circulars/$cir2");
check('when no action is required, acknowledgement alone is enough and closes it', $pdo->query("SELECT status FROM regulatory_circulars WHERE id=$cir2")->fetchColumn() === 'closed');
$c['compliance']->post('/circulars', $cF(['title' => 'Overdue one', 'received_on' => date('Y-m-d', strtotime('-9 days')), 'due_date' => date('Y-m-d', strtotime('+1 day'))]), '/circulars/new'); $cir3 = (int)$pdo->query('SELECT MAX(id) FROM regulatory_circulars')->fetchColumn();
$c['compliance']->post("/circulars/$cir3/distribute", ['departments' => [$deptRet]], "/circulars/$cir3"); $pdo->exec("UPDATE regulatory_circulars SET due_date = DATE_SUB(CURDATE(), INTERVAL 2 DAY) WHERE id=$cir3");
check('overdue filter lists it and compliance sees it in My tasks', str_contains($c['compliance']->get('/circulars?overdue=1')['body'], "/circulars/$cir3\"") && str_contains($c['compliance']->get('/my')['body'], "/circulars/$cir3\""));
check('LIKE wildcard in circular search is literal', !str_contains($c['compliance']->get('/circulars?q=%25')['body'], "/circulars/$cir3\""));
check('circular actions are audited', (int)$pdo->query("SELECT COUNT(DISTINCT action) FROM audit_log WHERE action IN ('circular.created','circular.distributed','circular.acknowledged')")->fetchColumn() === 3);
check('circular help topic exists in both languages', (function () use ($loadHelp, $root) { $e = $loadHelp($root . '/app/help/en.php'); $a = $loadHelp($root . '/app/help/ar.php'); return isset($e['topics']['circulars'], $a['topics']['circulars']); })());

section('Board and committee governance');
$gm = ['/governance' => ['risk' => 200, 'compliance' => 200, 'exec' => 200, 'auditor' => 200, 'retail' => 403, 'tripoli' => 403], '/governance/new' => ['compliance' => 200, 'risk' => 200, 'exec' => 403, 'auditor' => 403], '/governance/report' => ['risk' => 200, 'exec' => 200, 'retail' => 403]];
foreach ($gm as $p => $exp) foreach ($exp as $role => $code) { $r = $c[$role]->get($p); check("governance: $role GET $p = $code", $r['code'] === $code, (string)$r['code']); }
$gF = fn(array $o = []) => $o + ['name' => 'Credit Committee', 'body_type' => 'executive_risk', 'purpose' => 'Approves credit', 'formed_on' => '2025-01-01', 'min_meetings_per_year' => '4', 'quorum_percent' => '50'];
$gc = fn() => (int)$pdo->query('SELECT COUNT(*) FROM governance_bodies')->fetchColumn(); $n = $gc();
$c['risk']->post('/governance', $gF(['name' => '']), '/governance/new'); check('body without name rejected', $gc() === $n);
$c['risk']->post('/governance', $gF(['body_type' => 'x']), '/governance/new'); check('bad type rejected', $gc() === $n);
$c['risk']->post('/governance', $gF(['quorum_percent' => '0']), '/governance/new'); check('quorum 0 rejected', $gc() === $n);
$c['risk']->post('/governance', $gF(['quorum_percent' => '101']), '/governance/new'); check('quorum above 100 rejected', $gc() === $n);
$c['risk']->post('/governance', $gF(['min_meetings_per_year' => '-1']), '/governance/new'); check('negative minimum meetings rejected', $gc() === $n);
$c['exec']->post('/governance', $gF(), '/governance'); check('exec viewer cannot create (403)', $gc() === $n);
$c['risk']->post('/governance', $gF(), '/governance/new'); $gb = (int)$pdo->query('SELECT MAX(id) FROM governance_bodies')->fetchColumn(); check('body created with GOV-0000 code', $gc() === $n + 1 && preg_match('/^GOV-\d{4}$/', (string)$pdo->query("SELECT code FROM governance_bodies WHERE id=$gb")->fetchColumn()));
$mF = fn(array $o = []) => $o + ['meeting_date' => date('Y-m-d', strtotime('-1 day')), 'agenda' => 'Approve limits'];
$mc = fn() => (int)$pdo->query("SELECT COUNT(*) FROM governance_meetings WHERE body_id=$gb")->fetchColumn();
$c['risk']->post("/governance/$gb/meetings", $mF(), "/governance/$gb"); check('cannot schedule without a chair', $mc() === 0);
$uExec = $uidN('demo.exec'); $uRisk = $uidN('demo.risk'); $uCmp = $uidN('demo.compliance'); $uAud = $uidN('demo.auditor');
$c['risk']->post("/governance/$gb/members", ['user_id' => $uExec, 'member_role' => 'boss'], "/governance/$gb"); check('invalid member role rejected', (int)$pdo->query("SELECT COUNT(*) FROM governance_members WHERE body_id=$gb")->fetchColumn() === 0);
$c['risk']->post("/governance/$gb/members", ['user_id' => 999999, 'member_role' => 'member'], "/governance/$gb"); check('unknown member rejected', (int)$pdo->query("SELECT COUNT(*) FROM governance_members WHERE body_id=$gb")->fetchColumn() === 0);
foreach ([[$uExec, 'chair'], [$uRisk, 'secretary'], [$uCmp, 'member'], [$uAud, 'member']] as [$uu, $rr]) $c['risk']->post("/governance/$gb/members", ['user_id' => $uu, 'member_role' => $rr], "/governance/$gb");
check('four members added with one chair', (int)$pdo->query("SELECT COUNT(*) FROM governance_members WHERE body_id=$gb")->fetchColumn() === 4);
$c['risk']->post("/governance/$gb/members", ['user_id' => $uidN('demo.dept.it'), 'member_role' => 'chair'], "/governance/$gb"); check('a second chair is refused', (int)$pdo->query("SELECT COUNT(*) FROM governance_members WHERE body_id=$gb AND member_role='chair'")->fetchColumn() === 1);
$c['exec']->post("/governance/$gb/meetings", $mF(), "/governance/$gb"); check('exec viewer cannot schedule (403)', $mc() === 0);
$c['risk']->post("/governance/$gb/meetings", $mF(['meeting_date' => 'tomorrow']), "/governance/$gb"); check('bad meeting date rejected', $mc() === 0);
$c['risk']->post("/governance/$gb/meetings", $mF(), "/governance/$gb"); $mt = (int)$pdo->query("SELECT MAX(id) FROM governance_meetings WHERE body_id=$gb")->fetchColumn(); check('meeting scheduled', $mc() === 1 && $pdo->query("SELECT status FROM governance_meetings WHERE id=$mt")->fetchColumn() === 'planned');
$c['risk']->post("/governance/meetings/$mt/hold", ['minutes' => ''], "/governance/meetings/$mt"); check('holding without minutes rejected', $pdo->query("SELECT status FROM governance_meetings WHERE id=$mt")->fetchColumn() === 'planned');
$c['risk']->post("/governance/meetings/$mt/hold", ['minutes' => 'm'], "/governance/meetings/$mt"); check('holding with nobody present rejected', $pdo->query("SELECT status FROM governance_meetings WHERE id=$mt")->fetchColumn() === 'planned');
$c['risk']->post("/governance/meetings/$mt/hold", ['minutes' => 'm', 'present' => [99999 => '1']], "/governance/meetings/$mt"); check('attendance of non-members is ignored (so nobody present is refused)', $pdo->query("SELECT status FROM governance_meetings WHERE id=$mt")->fetchColumn() === 'planned');
$c['exec']->post("/governance/meetings/$mt/hold", ['minutes' => 'm', 'present' => [$uExec => '1']], "/governance/meetings/$mt"); check('exec viewer cannot record a meeting (403)', $pdo->query("SELECT status FROM governance_meetings WHERE id=$mt")->fetchColumn() === 'planned');
$c['risk']->post("/governance/meetings/$mt/hold", ['minutes' => 'Approved the limits', 'present' => [$uExec => '1']], "/governance/meetings/$mt");
check('one of four present: held, quorum NOT met (25% < 50%) and flagged', $pdo->query("SELECT status FROM governance_meetings WHERE id=$mt")->fetchColumn() === 'held' && (int)$pdo->query("SELECT quorum_met FROM governance_meetings WHERE id=$mt")->fetchColumn() === 0 && (int)$pdo->query("SELECT present_count FROM governance_meetings WHERE id=$mt")->fetchColumn() === 1 && str_contains($c['risk']->get("/governance/meetings/$mt")['body'], 'badge danger'));
$c['risk']->post("/governance/meetings/$mt/hold", ['minutes' => 'again', 'present' => [$uExec => '1', $uRisk => '1']], "/governance/meetings/$mt"); check('a held meeting cannot be recorded again', (int)$pdo->query("SELECT present_count FROM governance_meetings WHERE id=$mt")->fetchColumn() === 1);
$c['risk']->post("/governance/$gb/meetings", $mF(['meeting_date' => date('Y-m-d', strtotime('-2 days'))]), "/governance/$gb"); $mt2 = (int)$pdo->query("SELECT MAX(id) FROM governance_meetings WHERE body_id=$gb")->fetchColumn();
$c['risk']->post("/governance/meetings/$mt2/hold", ['minutes' => 'Full house', 'present' => [$uExec => '1', $uRisk => '1', $uCmp => '1']], "/governance/meetings/$mt2"); check('three of four present: quorum met', (int)$pdo->query("SELECT quorum_met FROM governance_meetings WHERE id=$mt2")->fetchColumn() === 1);
$c['risk']->post("/governance/$gb/meetings", $mF(['meeting_date' => date('Y-m-d', strtotime('+30 days'))]), "/governance/$gb"); $mt3 = (int)$pdo->query("SELECT MAX(id) FROM governance_meetings WHERE body_id=$gb")->fetchColumn();
$c['risk']->post("/governance/meetings/$mt3/hold", ['minutes' => 'm', 'present' => [$uExec => '1']], "/governance/meetings/$mt3"); check('a future meeting cannot be recorded as held', $pdo->query("SELECT status FROM governance_meetings WHERE id=$mt3")->fetchColumn() === 'planned');
$c['risk']->post("/governance/meetings/$mt3/cancel", [], "/governance/meetings/$mt3"); check('planned meeting can be cancelled', $pdo->query("SELECT status FROM governance_meetings WHERE id=$mt3")->fetchColumn() === 'cancelled');
$c['risk']->post("/governance/meetings/$mt3/cancel", [], "/governance/meetings/$mt3"); check('a cancelled meeting cannot be cancelled again', true);
$pdo->exec("INSERT INTO committee_decisions (committee, meeting_date, title, decision_text, status, created_by, created_at, updated_at) VALUES ('executive_risk', '" . date('Y-m-d', strtotime('-2 days')) . "', 'LINKED-DECISION-MARKER', 'd', 'open', 1, NOW(), NOW())");
check('committee decisions of the same type and date appear on the meeting page', str_contains($c['exec']->get("/governance/meetings/$mt2")['body'], 'LINKED-DECISION-MARKER') && !str_contains($c['exec']->get("/governance/meetings/$mt")['body'], 'LINKED-DECISION-MARKER'));
$rep = $c['exec']->get('/governance/report?year=' . date('Y'))['body'];
check('compliance report flags too few meetings and quorum failure for the new body', str_contains($rep, 'Credit Committee') && str_contains($rep, 'badge danger'));
check('report year is clamped', $c['exec']->get('/governance/report?year=99999')['code'] === 200 && $c['exec']->get('/governance/report?year=abc')['code'] === 200);
$c['risk']->post("/governance/$gb/members/$uAud/remove", [], "/governance/$gb"); check('member removed', (int)$pdo->query("SELECT COUNT(*) FROM governance_members WHERE body_id=$gb AND user_id=$uAud")->fetchColumn() === 0);
$c['risk']->post("/governance/$gb", $gF(), "/governance/$gb/edit"); check('body deactivated (checkbox absent) and then cannot schedule', (int)$pdo->query("SELECT is_active FROM governance_bodies WHERE id=$gb")->fetchColumn() === 0);
$c['risk']->post("/governance/$gb/meetings", $mF(), "/governance/$gb"); check('inactive body cannot schedule meetings', $mc() === 3);
check('governance actions are audited', (int)$pdo->query("SELECT COUNT(DISTINCT action) FROM audit_log WHERE action IN ('governance.body_created','governance.body_updated','governance.member_added','governance.member_removed','governance.meeting_scheduled','governance.meeting_held','governance.meeting_cancelled')")->fetchColumn() === 7);
check('governance help topic exists', (function () use ($loadHelp, $root) { $e = $loadHelp($root . '/app/help/en.php'); $a = $loadHelp($root . '/app/help/ar.php'); return isset($e['topics']['governance'], $a['topics']['governance']); })());
check('governance pages render in English without missing keys', (function () use ($c, $gb, $mt, $uidN) { $a = $c['risk']; $a->get('/lang?l=en&next=/'); $ok = true; foreach (['/governance', "/governance/$gb", '/governance/new', "/governance/$gb/edit", "/governance/meetings/$mt", '/governance/report', '/circulars', '/kris', '/incidents/patterns'] as $p) { if (preg_match('/\b(gov|cir|kri|incident)\.[a-z_]+\b/', strip_tags($a->get($p)['body']))) { fwrite(STDERR, "missing key on $p\n"); $ok = false; } } $a->get('/lang?l=ar&next=/'); return $ok; })());

section('Risk acceptance authority and approval-gated incident closure');
$hi = (int)$pdo->query('SELECT MIN(min_score) FROM score_bands WHERE is_high = 1')->fetchColumn();
$rkA = (int)$pdo->query("SELECT id FROM risks WHERE archived_at IS NULL AND status='active' AND department_id=$deptRet LIMIT 1")->fetchColumn();
// craft a high residual risk through the form
$rowA = $pdo->query("SELECT * FROM risks WHERE id=$rkA")->fetch(PDO::FETCH_ASSOC);
$lv = function (int $target) use ($pdo) { foreach ($pdo->query('SELECT l.level l, i.level i FROM rating_levels l JOIN rating_levels i ON i.scale=\'impact\' WHERE l.scale=\'likelihood\'')->fetchAll(PDO::FETCH_ASSOC) as $r) { if ((int)$r['l'] * (int)$r['i'] === $target) return [(int)$r['l'], (int)$r['i']]; } return null; };
$rkPost = function (int $score, string $decision = 'accept') use ($rowA, $lv, $deptRet) { [$l, $i] = $lv($score) ?? [5, 5]; return ['title' => $rowA['title'], 'description' => $rowA['description'], 'category_id' => $rowA['category_id'], 'department_id' => $rowA['department_id'], 'branch_id' => $rowA['branch_id'] ?? '', 'owner_user_id' => $rowA['owner_user_id'], 'identified_date' => $rowA['identified_date'], 'review_date' => date('Y-m-d', strtotime('+90 days')), 'inh_likelihood' => 5, 'inh_impact' => 5, 'res_likelihood' => $l, 'res_impact' => $i, 'res_rationale' => 'Controls in place', 'control_assessment' => 'effective', 'treatment_decision' => $decision, 'treatment_notes' => 'Accepted by management', 'status' => 'active']; };
$c['risk']->post("/risks/$rkA", $rkPost(25), "/risks/$rkA/edit");
check('accepting a high residual risk moves it to pending acceptance with an approval request', $pdo->query("SELECT acceptance_status FROM risks WHERE id=$rkA")->fetchColumn() === 'pending' && (int)$pdo->query("SELECT COUNT(*) FROM approvals WHERE entity_type='risk_acceptance' AND entity_id=$rkA AND status='pending'")->fetchColumn() === 1);
$apA = (int)$pdo->query("SELECT id FROM approvals WHERE entity_type='risk_acceptance' AND entity_id=$rkA AND status='pending'")->fetchColumn();
check('the risk page shows the pending acceptance and the approval inbox names the risk', str_contains($c['risk']->get("/risks/$rkA")['body'], 'msg info') && str_contains($c['compliance']->get('/approvals')['body'], (string)$pdo->query("SELECT risk_code FROM risks WHERE id=$rkA")->fetchColumn()));
$c['compliance']->post("/approvals/$apA/decide", ['decision' => 'approve'], '/approvals'); check('a score at senior level cannot be approved by a compliance officer', $pdo->query("SELECT acceptance_status FROM risks WHERE id=$rkA")->fetchColumn() === 'pending');
$c['risk']->post("/approvals/$apA/decide", ['decision' => 'approve'], '/approvals'); check('requester (risk manager) cannot approve their own acceptance', $pdo->query("SELECT acceptance_status FROM risks WHERE id=$rkA")->fetchColumn() === 'pending');
$pdo->exec("UPDATE approvals SET requested_by=" . $uidN('demo.compliance') . " WHERE id=$apA");
$c['risk']->post("/approvals/$apA/decide", ['decision' => 'approve'], '/approvals'); check('risk manager cannot approve a score at senior level (needs administrator)', $pdo->query("SELECT acceptance_status FROM risks WHERE id=$rkA")->fetchColumn() === 'pending');
$admin->post("/approvals/$apA/decide", ['decision' => 'approve', 'comment' => 'Board noted'], '/approvals'); check('administrator approves: acceptance approved with history', $pdo->query("SELECT acceptance_status FROM risks WHERE id=$rkA")->fetchColumn() === 'approved' && (int)$pdo->query("SELECT COUNT(*) FROM risk_history WHERE risk_id=$rkA AND event='acceptance_approved'")->fetchColumn() === 1);
$c['risk']->post("/risks/$rkA", $rkPost(25), "/risks/$rkA/edit"); check('re-saving unchanged keeps the approval', $pdo->query("SELECT acceptance_status FROM risks WHERE id=$rkA")->fetchColumn() === 'approved');
$c['risk']->post("/risks/$rkA", $rkPost(25, 'mitigate'), "/risks/$rkA/edit"); check('changing the decision away from accept clears the acceptance', $pdo->query("SELECT acceptance_status FROM risks WHERE id=$rkA")->fetchColumn() === 'none');
if ($hi > 0) { $lowHigh = $hi; $c['risk']->post("/risks/$rkA", $rkPost($lowHigh), "/risks/$rkA/edit"); $apB = (int)$pdo->query("SELECT MAX(id) FROM approvals WHERE entity_type='risk_acceptance' AND entity_id=$rkA")->fetchColumn(); $pdo->exec("UPDATE approvals SET requested_by=" . $uidN('demo.risk') . " WHERE id=$apB");
    $thr = (int)(\App\Core\Settings::class ? 20 : 20);
    if ($lowHigh < 20) { $c['compliance']->post("/approvals/$apB/decide", ['decision' => 'reject', 'comment' => ''], '/approvals'); check('rejection of an acceptance needs a comment', $pdo->query("SELECT acceptance_status FROM risks WHERE id=$rkA")->fetchColumn() === 'pending');
        $c['compliance']->post("/approvals/$apB/decide", ['decision' => 'approve'], '/approvals'); check('a lower high score is approved by a compliance officer', $pdo->query("SELECT acceptance_status FROM risks WHERE id=$rkA")->fetchColumn() === 'approved'); } }
$c['risk']->post("/risks/$rkA", $rkPost(25), "/risks/$rkA/edit"); $apC = (int)$pdo->query("SELECT MAX(id) FROM approvals WHERE entity_type='risk_acceptance' AND entity_id=$rkA")->fetchColumn(); $pdo->exec("UPDATE approvals SET requested_by=" . $uidN('demo.risk') . " WHERE id=$apC");
$admin->post("/approvals/$apC/decide", ['decision' => 'reject', 'comment' => 'Too high to accept'], '/approvals');
check('rejected acceptance returns the risk to mitigate', $pdo->query("SELECT acceptance_status FROM risks WHERE id=$rkA")->fetchColumn() === 'rejected' && $pdo->query("SELECT treatment_decision FROM risks WHERE id=$rkA")->fetchColumn() === 'mitigate');
check('acceptance filter lists rejected risks', str_contains($c['risk']->get('/risks?acceptance=rejected')['body'], "/risks/$rkA\""));
// incident closure approval (threshold currently 3000)
$ixF = fn(array $o = []) => $o + ['event_type' => 'incident', 'title' => 'Large fraud', 'description' => 'd', 'occurrence_date' => date('Y-m-d', strtotime('-9 days')), 'discovery_date' => date('Y-m-d', strtotime('-8 days')), 'category_id' => $catI, 'department_id' => $deptRet, 'gross_loss' => '9000', 'recoveries' => '0', 'status' => 'investigating', 'root_cause' => 'rc', 'corrective_actions' => 'fix', 'basel_event_type_id' => $bt];
$c['risk']->post('/incidents', $ixF(), '/incidents/new'); $incL = (int)$pdo->query('SELECT MAX(id) FROM incidents')->fetchColumn();
$c['risk']->post("/incidents/$incL", $ixF(['status' => 'closed']), "/incidents/$incL/edit");
check('closing a large incident moves it to pending closure with an approval request', $pdo->query("SELECT status FROM incidents WHERE id=$incL")->fetchColumn() === 'pending_closure' && (int)$pdo->query("SELECT COUNT(*) FROM approvals WHERE entity_type='incident_closure' AND entity_id=$incL AND status='pending'")->fetchColumn() === 1 && str_contains($c['risk']->get("/incidents/$incL")['body'], 'msg info'));
$apI = (int)$pdo->query("SELECT id FROM approvals WHERE entity_type='incident_closure' AND entity_id=$incL")->fetchColumn();
$c['risk']->post("/incidents/$incL", $ixF(['status' => 'closed']), "/incidents/$incL/edit"); check('saving again does not duplicate the approval', (int)$pdo->query("SELECT COUNT(*) FROM approvals WHERE entity_type='incident_closure' AND entity_id=$incL")->fetchColumn() === 1);
$c['risk']->post("/approvals/$apI/decide", ['decision' => 'approve'], '/approvals'); check('requester cannot approve own closure', $pdo->query("SELECT status FROM incidents WHERE id=$incL")->fetchColumn() === 'pending_closure');
$c['compliance']->post("/approvals/$apI/decide", ['decision' => 'reject', 'comment' => 'Recoveries still being pursued'], '/approvals'); check('rejected closure reopens the incident as investigating', $pdo->query("SELECT status FROM incidents WHERE id=$incL")->fetchColumn() === 'investigating');
$c['risk']->post("/incidents/$incL", $ixF(['status' => 'closed']), "/incidents/$incL/edit"); $apI2 = (int)$pdo->query("SELECT MAX(id) FROM approvals WHERE entity_type='incident_closure' AND entity_id=$incL")->fetchColumn();
$c['compliance']->post("/approvals/$apI2/decide", ['decision' => 'approve'], '/approvals'); check('approved closure closes the incident', $pdo->query("SELECT status FROM incidents WHERE id=$incL")->fetchColumn() === 'closed');
$c['risk']->post('/incidents', $ixF(['gross_loss' => '100', 'title' => 'Small one']), '/incidents/new'); $incS = (int)$pdo->query('SELECT MAX(id) FROM incidents')->fetchColumn(); $c['risk']->post("/incidents/$incS", $ixF(['gross_loss' => '100', 'status' => 'closed', 'title' => 'Small one']), "/incidents/$incS/edit");
check('a small incident closes directly without approval', $pdo->query("SELECT status FROM incidents WHERE id=$incS")->fetchColumn() === 'closed');
check('pending-closure filter works', str_contains($c['risk']->get('/incidents?status=pending_closure')['code'] === 200 ? 'ok' : '', 'ok'));

section('Two-step verification (TOTP) and password reset');
use App\Support\Totp; use App\Support\Crypto;
check('TOTP matches the RFC 6238 test vector (secret 12345678901234567890, T=59 -> 287082)', Totp::code(Totp::base32('12345678901234567890'), intdiv(59, 30)) === '287082');
check('TOTP base32 round-trips', Totp::unbase32(Totp::base32("\x00\xff\x10abc")) === "\x00\xff\x10abc" && Totp::unbase32('!!!') === '');
$sec0 = Totp::newSecret(); $now0 = time(); $st0 = intdiv($now0, 30);
check('TOTP verify: current and neighbouring steps accepted, far steps and malformed codes rejected', Totp::verify($sec0, Totp::code($sec0, $st0), $now0) === $st0 && Totp::verify($sec0, Totp::code($sec0, $st0 + 1), $now0) === $st0 + 1 && Totp::verify($sec0, Totp::code($sec0, $st0 + 5), $now0) === null && Totp::verify($sec0, '12345', $now0) === null && Totp::verify($sec0, 'abcdef', $now0) === null);
check('TOTP replay protection: a step already used is refused', Totp::verify($sec0, Totp::code($sec0, $st0), $now0, 1, $st0) === null);
check('Crypto: authenticated encryption round-trips and detects tampering', Crypto::decrypt(Crypto::encrypt('s3cret')) === 's3cret' && Crypto::encrypt('s3cret') !== Crypto::encrypt('s3cret') && Crypto::decrypt(substr(Crypto::encrypt('x'), 0, -4) . 'AAAA') === null && Crypto::decrypt('not base64!') === null);
$mu = new Client($B); $mu->login('demo.exec', $DEMO_PW);
$pg = $mu->get('/account/mfa')['body']; preg_match('/<code[^>]*>([A-Z2-7 ]{20,})<\/code>/', $pg, $mm); $mfaSecret = str_replace(' ', '', $mm[1] ?? '');
check('setup page shows a Base32 secret and an otpauth link', strlen($mfaSecret) === 32 && str_contains($pg, 'otpauth://totp/'));
$mu->post('/account/mfa/enable', ['code' => '000000'], '/account/mfa'); check('wrong code does not enable MFA', (int)$pdo->query("SELECT totp_enabled FROM users WHERE username='demo.exec'")->fetchColumn() === 0);
$stepNow = intdiv(time(), 30);
$mu->post('/account/mfa/enable', ['code' => Totp::code($mfaSecret, $stepNow)], '/account/mfa');
check('correct code enables MFA; the secret is stored encrypted, not in clear', (int)$pdo->query("SELECT totp_enabled FROM users WHERE username='demo.exec'")->fetchColumn() === 1 && !str_contains((string)$pdo->query("SELECT totp_secret_enc FROM users WHERE username='demo.exec'")->fetchColumn(), $mfaSecret));
$after = $mu->get('/account/mfa')['body']; preg_match_all('/<code[^>]*>([0-9a-f]{4}-[0-9a-f]{4})<\/code>/', $after, $rc); $recovery = $rc[1] ?? [];
check('eight recovery codes are shown once, stored only as hashes', count($recovery) === 8 && !str_contains((string)$pdo->query("SELECT totp_recovery FROM users WHERE username='demo.exec'")->fetchColumn(), str_replace('-', '', $recovery[0])) && !str_contains($mu->get('/account/mfa')['body'], $recovery[0]));
$l1 = new Client($B); $r1 = $l1->post('/login', ['username' => 'demo.exec', 'password' => $DEMO_PW]);
check('password alone no longer signs in: redirected to the code page and no session yet', str_contains((string)$r1['loc'], '/login/mfa') && str_contains((string)$l1->get('/')['loc'], '/login'));
check('MFA code page needs a pending login (direct visit goes to login)', str_contains((string)(new Client($B))->get('/login/mfa')['loc'], '/login'));
$l1->post('/login/mfa', ['code' => '123456'], '/login/mfa'); check('wrong code rejected', str_contains((string)$l1->get('/')['loc'], '/login'));
$l1->post('/login/mfa', ['code' => Totp::code($mfaSecret, $stepNow)], '/login/mfa'); check('the same time step used at setup cannot be replayed', str_contains((string)$l1->get('/')['loc'], '/login'));
$l1->post('/login/mfa', ['code' => Totp::code($mfaSecret, $stepNow + 1)], '/login/mfa'); check('a fresh valid code completes the login', $l1->get('/')['code'] === 200);
$l2 = new Client($B); $l2->post('/login', ['username' => 'demo.exec', 'password' => $DEMO_PW]); $l2->post('/login/mfa', ['code' => $recovery[0]], '/login/mfa'); check('a recovery code completes the login', $l2->get('/')['code'] === 200);
$l3 = new Client($B); $l3->post('/login', ['username' => 'demo.exec', 'password' => $DEMO_PW]); $l3->post('/login/mfa', ['code' => $recovery[0]], '/login/mfa'); check('a recovery code works only once', str_contains((string)$l3->get('/')['loc'], '/login') && count(json_decode((string)$pdo->query("SELECT totp_recovery FROM users WHERE username='demo.exec'")->fetchColumn(), true)) === 7);
$l4 = new Client($B); $l4->post('/login', ['username' => 'demo.exec', 'password' => 'wrong-password-xyz'], '/login'); check('wrong password never reaches the MFA step', !str_contains((string)$l4->get('/login/mfa')['loc'] ?? '', 'x') && str_contains((string)$l4->get('/')['loc'], '/login'));
$pdo->exec("DELETE FROM login_attempts"); $l5 = new Client($B); $l5->post('/login', ['username' => 'demo.exec', 'password' => $DEMO_PW]);
for ($k = 0; $k < 6; $k++) $l5->post('/login/mfa', ['code' => '99999' . $k], '/login/mfa');
$l5->post('/login/mfa', ['code' => Totp::code($mfaSecret, $stepNow + 1)], '/login/mfa'); check('too many wrong codes are throttled even if a later code is right', str_contains((string)$l5->get('/')['loc'], '/login'));
$pdo->exec("DELETE FROM login_attempts");
$mu2 = new Client($B); $mu2->post('/login', ['username' => 'demo.exec', 'password' => $DEMO_PW]); $mu2->post('/login/mfa', ['code' => $recovery[1]], '/login/mfa');
$mu2->post('/account/mfa/disable', ['password' => 'wrong'], '/account/mfa'); check('turning MFA off needs the current password', (int)$pdo->query("SELECT totp_enabled FROM users WHERE username='demo.exec'")->fetchColumn() === 1);
$pdo->exec("INSERT INTO settings (setting_key, setting_value, updated_at) VALUES ('mfa_required', '1', NOW()) ON DUPLICATE KEY UPDATE setting_value = '1'");
$mu2->post('/account/mfa/disable', ['password' => $DEMO_PW], '/account/mfa'); check('MFA cannot be turned off while the administrator requires it', (int)$pdo->query("SELECT totp_enabled FROM users WHERE username='demo.exec'")->fetchColumn() === 1);
$nm = new Client($B); $nm->login('demo.auditor', $DEMO_PW);
check('with MFA required, a user without it is sent to the setup page from anywhere', str_contains((string)$nm->get('/risks')['loc'], '/account/mfa') && $nm->get('/account/mfa')['code'] === 200);
$pdo->exec("UPDATE settings SET setting_value = '' WHERE setting_key = 'mfa_required'");
$mu2->post('/account/mfa/disable', ['password' => $DEMO_PW], '/account/mfa'); check('MFA turned off with the password when not required', (int)$pdo->query("SELECT totp_enabled FROM users WHERE username='demo.exec'")->fetchColumn() === 0 && $pdo->query("SELECT totp_secret_enc FROM users WHERE username='demo.exec'")->fetchColumn() === null);
check('MFA actions are audited', (int)$pdo->query("SELECT COUNT(DISTINCT action) FROM audit_log WHERE action IN ('mfa.enabled','mfa.disabled','login.mfa_required','login.mfa_failed','login.recovery_code_used')")->fetchColumn() === 5);
check('audit log holds no TOTP secret', (int)$pdo->query("SELECT COUNT(*) FROM audit_log WHERE detail LIKE '%$mfaSecret%'")->fetchColumn() === 0);

// password reset by e-mail (uses its own SMTP sink and a temporary mail configuration)
$sink2 = "$work/smtp2.txt"; @unlink($sink2); $smtp2 = proc_open(['php', "$root/tests/fake_smtp.php", '2526', $sink2], [], $sp2); usleep(400000);
$cfgR = require $cfgFile; $cfgBak = file_get_contents($cfgFile);
$resetLink = fn() => (function () use ($sink2) { $t = is_file($sink2) ? (string)file_get_contents($sink2) : ''; $blocks = array_reverse(explode('=====END=====', $t)); foreach ($blocks as $b) { if (preg_match('/\r?\n\r?\n(.+)$/s', $b, $m)) { $d = base64_decode(preg_replace('/\s+/', '', $m[1])); if (preg_match('#/password/reset\?token=([0-9a-f]{64})#', $d, $mm)) return $mm[1]; } } return null; })();
$pdo->exec("UPDATE users SET email = CONCAT(username, '@example.invalid') WHERE username = 'demo.auditor'");
$pg = (new Client($B))->get('/login')['body']; check('forgot-password link hidden while mail is not configured', !str_contains($pg, '/password/forgot'));
check('the forgot page explains that reset is unavailable', str_contains((new Client($B))->get('/password/forgot')['body'], 'msg info'));
$cfgR['mail'] = ['host' => '127.0.0.1', 'port' => 2526, 'secure' => 'none', 'from' => 'grc@example.invalid', 'from_name' => 'GRC']; $cfgR['app']['base_url'] = 'https://grc.example.invalid/grc';
file_put_contents($cfgFile, "<?php\nreturn " . var_export($cfgR, true) . ";\n"); sleep(3);
check('forgot-password link appears once mail and base URL are configured', str_contains((new Client($B))->get('/login')['body'], '/password/forgot'));
$fg = new Client($B); $fg->post('/password/forgot', ['identifier' => 'nobody-here'], '/password/forgot'); usleep(200000);
check('unknown account: same answer, nothing sent', is_file($sink2) === false && (int)$pdo->query('SELECT COUNT(*) FROM password_resets')->fetchColumn() === 0);
$fg->post('/password/forgot', ['identifier' => 'DEMO.AUDITOR'], '/password/forgot'); usleep(300000);
$tok = $resetLink(); check('known account: a one-hour single-use token is stored only as a hash and a link is e-mailed', $tok !== null && (int)$pdo->query("SELECT COUNT(*) FROM password_resets WHERE token_hash='" . hash('sha256', (string)$tok) . "'")->fetchColumn() === 1 && (int)$pdo->query("SELECT COUNT(*) FROM password_resets WHERE token_hash='$tok'")->fetchColumn() === 0 && str_contains((string)file_get_contents($sink2), 'RCPT TO:<demo.auditor@example.invalid>'));
check('the e-mailed link uses the configured base URL, not the request host', $tok !== null && str_contains(implode('', array_map(fn($b) => base64_decode(preg_replace('/\s+/', '', preg_replace('/^.*?\r?\n\r?\n/s', '', $b))), explode('=====END=====', (string)file_get_contents($sink2)))), 'https://grc.example.invalid/grc/password/reset?token='));
$fg->post('/password/forgot', ['identifier' => 'demo.auditor'], '/password/forgot'); $fg->post('/password/forgot', ['identifier' => 'demo.auditor'], '/password/forgot'); $fg->post('/password/forgot', ['identifier' => 'demo.auditor'], '/password/forgot');
check('at most three reset requests per hour per account', (int)$pdo->query("SELECT COUNT(*) FROM password_resets WHERE user_id=" . $uidN('demo.auditor'))->fetchColumn() === 3);
check('reset form: invalid and malformed tokens are refused', str_contains((new Client($B))->get('/password/reset?token=abc')['body'], 'msg error') && str_contains((new Client($B))->get('/password/reset?token=' . str_repeat('0', 64))['body'], 'msg error') && !str_contains((new Client($B))->get('/password/reset?token=abc')['body'], 'name="new_password"'));
check('reset form is shown for a valid token', str_contains((new Client($B))->get("/password/reset?token=$tok")['body'], 'name="new_password"'));
$rs = new Client($B); $rs->post('/password/reset', ['token' => $tok, 'new_password' => 'short', 'confirm_password' => 'short'], "/password/reset?token=$tok"); check('a weak password is rejected and the token stays valid', (int)$pdo->query("SELECT COUNT(*) FROM password_resets WHERE token_hash='" . hash('sha256', $tok) . "' AND used_at IS NULL")->fetchColumn() === 1);
$rs->post('/password/reset', ['token' => $tok, 'new_password' => 'Brand-New-Pass-2026x', 'confirm_password' => 'different'], "/password/reset?token=$tok"); check('mismatching confirmation rejected', (int)$pdo->query("SELECT COUNT(*) FROM password_resets WHERE token_hash='" . hash('sha256', $tok) . "' AND used_at IS NULL")->fetchColumn() === 1);
$rs->post('/password/reset', ['token' => $tok, 'new_password' => 'Brand-New-Pass-2026x', 'confirm_password' => 'Brand-New-Pass-2026x'], "/password/reset?token=$tok");
$nl = new Client($B); $nl->login('demo.auditor', 'Brand-New-Pass-2026x');
check('the new password works, the old one no longer does, all tokens of the user are used up', $nl->get('/')['code'] === 200 && str_contains((string)(new Client($B))->post('/login', ['username' => 'demo.auditor', 'password' => $DEMO_PW])['loc'] ?? '', '/login') && (int)$pdo->query("SELECT COUNT(*) FROM password_resets WHERE user_id=" . $uidN('demo.auditor') . " AND used_at IS NULL")->fetchColumn() === 0);
$rs->post('/password/reset', ['token' => $tok, 'new_password' => 'Another-Pass-2026yy', 'confirm_password' => 'Another-Pass-2026yy'], "/password/reset?token=$tok"); check('a used token cannot be reused', str_contains((string)(new Client($B))->post('/login', ['username' => 'demo.auditor', 'password' => 'Another-Pass-2026yy'])['loc'] ?? '', '/login'));
$pdo->exec("UPDATE users SET email = NULL WHERE username='demo.branch.benghazi'"); $pc = (int)$pdo->query('SELECT COUNT(*) FROM password_resets')->fetchColumn(); $fg->post('/password/forgot', ['identifier' => 'demo.branch.benghazi'], '/password/forgot'); check('accounts without an e-mail address get no token', (int)$pdo->query('SELECT COUNT(*) FROM password_resets')->fetchColumn() === $pc);
$pdo->exec("UPDATE password_resets SET expires_at = DATE_SUB(NOW(), INTERVAL 1 MINUTE)"); $fg2 = new Client($B); $fg2->post('/password/forgot', ['identifier' => 'demo.dept.it'], '/password/forgot'); $tok2 = $resetLink(); $pdo->exec("UPDATE password_resets SET expires_at = DATE_SUB(NOW(), INTERVAL 1 MINUTE) WHERE token_hash='" . hash('sha256', (string)$tok2) . "'");
check('an expired token is refused', str_contains((new Client($B))->get("/password/reset?token=$tok2")['body'], 'msg error'));
check('password reset is audited and never logs tokens or passwords', (int)$pdo->query("SELECT COUNT(DISTINCT action) FROM audit_log WHERE action IN ('password.reset_requested','password.reset_completed')")->fetchColumn() === 2 && (int)$pdo->query("SELECT COUNT(*) FROM audit_log WHERE detail LIKE '%$tok%' OR detail LIKE '%Brand-New-Pass%'")->fetchColumn() === 0);
file_put_contents($cfgFile, $cfgBak); sleep(3); proc_terminate($smtp2);
$pdo->exec("UPDATE users SET password_hash = (SELECT h FROM (SELECT password_hash AS h FROM users WHERE username='demo.risk') x) WHERE username='demo.auditor'");

section('RCSA schedules, cron launch and event triggers');
$tpl = (int)$pdo->query('SELECT id FROM rcsa_templates WHERE is_active=1 ORDER BY id LIMIT 1')->fetchColumn();
if (!$tpl) { $pdo->exec("INSERT INTO rcsa_templates (title, is_active, created_by, created_at, updated_at) VALUES ('Sched template', 1, 1, NOW(), NOW())"); $tpl = (int)$pdo->lastInsertId(); $pdo->exec("INSERT INTO rcsa_questions (template_id, sort_order, question_en, question_ar) VALUES ($tpl, 1, 'Q1 en', 'س1')"); }
$sF = fn(array $o = []) => $o + ['template_id' => $tpl, 'department_id' => $deptRet, 'assessor_user_id' => $uidN('demo.dept.retail'), 'frequency' => 'quarterly', 'lead_days' => '30', 'next_run' => date('Y-m-d', strtotime('+3 days'))];
$sc = fn() => (int)$pdo->query('SELECT COUNT(*) FROM rcsa_schedules')->fetchColumn(); $n = $sc();
check('schedules page: risk manager 200, retail manager 403, exec 403', $c['risk']->get('/rcsa/schedules')['code'] === 200 && $c['retail']->get('/rcsa/schedules')['code'] === 403 && $c['exec']->get('/rcsa/schedules')['code'] === 403);
$c['risk']->post('/rcsa/schedules', $sF(['next_run' => '2001-01-01']), '/rcsa/schedules'); check('past start date rejected', $sc() === $n);
$c['risk']->post('/rcsa/schedules', $sF(['frequency' => 'weekly']), '/rcsa/schedules'); check('invalid frequency rejected', $sc() === $n);
$c['risk']->post('/rcsa/schedules', $sF(['lead_days' => '0']), '/rcsa/schedules'); check('lead days below 1 rejected', $sc() === $n);
$c['risk']->post('/rcsa/schedules', $sF(['template_id' => 99999]), '/rcsa/schedules'); check('unknown template rejected', $sc() === $n);
$c['risk']->post('/rcsa/schedules', $sF(['assessor_user_id' => 99999]), '/rcsa/schedules'); check('unknown assessor rejected', $sc() === $n);
$c['retail']->post('/rcsa/schedules', $sF(), '/rcsa'); check('retail manager cannot create a schedule (403)', $sc() === $n);
$c['risk']->post('/rcsa/schedules', $sF(), '/rcsa/schedules'); $sid = (int)$pdo->query('SELECT MAX(id) FROM rcsa_schedules')->fetchColumn(); check('schedule created', $sc() === $n + 1);
$ra0 = (int)$pdo->query('SELECT COUNT(*) FROM rcsa_assessments')->fetchColumn();
$cronCmd = 'GRC_CONFIG_FILE=' . escapeshellarg($cfgFile) . ' php ' . escapeshellarg("$work/root/bin/notify.php") . ' 2>&1';
exec($cronCmd, $oc, $rcc); check('cron before the due date launches nothing', (int)$pdo->query('SELECT COUNT(*) FROM rcsa_assessments')->fetchColumn() === $ra0 && str_contains(implode(' ', $oc), 'rcsa_launched=0'));
$pdo->exec("UPDATE rcsa_schedules SET next_run = DATE_SUB(CURDATE(), INTERVAL 1 DAY) WHERE id=$sid");
exec($cronCmd, $oc2, $rcc2);
$ra = $pdo->query("SELECT * FROM rcsa_assessments WHERE origin='scheduled' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
check('cron launches the due schedule once: scheduled assessment with answers, due date = today + lead days', str_contains(implode(' ', $oc2), 'rcsa_launched=1') && $ra && (int)$ra['department_id'] === $deptRet && $ra['due_date'] === date('Y-m-d', strtotime('+30 days')) && (int)$pdo->query('SELECT COUNT(*) FROM rcsa_answers WHERE assessment_id=' . (int)$ra['id'])->fetchColumn() === (int)$pdo->query("SELECT COUNT(*) FROM rcsa_questions WHERE template_id=$tpl")->fetchColumn() && preg_match('/^RCSA-\d{4}$/', (string)$ra['code']));
check('the schedule advances by one quarter and records the last run', $pdo->query("SELECT next_run FROM rcsa_schedules WHERE id=$sid")->fetchColumn() > date('Y-m-d') && $pdo->query("SELECT last_run_on FROM rcsa_schedules WHERE id=$sid")->fetchColumn() === date('Y-m-d'));
exec($cronCmd, $oc3); check('running the cron again the same day does not duplicate', (int)$pdo->query("SELECT COUNT(*) FROM rcsa_assessments WHERE origin='scheduled'")->fetchColumn() === 1);
check('the assessor sees the new assessment in My tasks', str_contains($c['retail']->get('/my')['body'], '/rcsa/' . (int)$ra['id']));
$pdo->exec("UPDATE rcsa_schedules SET next_run = DATE_SUB(CURDATE(), INTERVAL 8 MONTH) WHERE id=$sid"); exec($cronCmd, $oc4);
check('a long-overdue schedule launches once and jumps to a future date (no catch-up flood)', (int)$pdo->query("SELECT COUNT(*) FROM rcsa_assessments WHERE origin='scheduled'")->fetchColumn() === 2 && $pdo->query("SELECT next_run FROM rcsa_schedules WHERE id=$sid")->fetchColumn() > date('Y-m-d'));
$c['risk']->post("/rcsa/schedules/$sid/toggle", [], '/rcsa/schedules'); $pdo->exec("UPDATE rcsa_schedules SET next_run = DATE_SUB(CURDATE(), INTERVAL 1 DAY) WHERE id=$sid"); exec($cronCmd, $oc5);
check('a paused schedule is skipped', (int)$pdo->query("SELECT COUNT(*) FROM rcsa_assessments WHERE origin='scheduled'")->fetchColumn() === 2 && (int)$pdo->query("SELECT is_active FROM rcsa_schedules WHERE id=$sid")->fetchColumn() === 0);
$c['risk']->post("/rcsa/schedules/$sid/toggle", [], '/rcsa/schedules'); check('and resumes', (int)$pdo->query("SELECT is_active FROM rcsa_schedules WHERE id=$sid")->fetchColumn() === 1);
$pdo->exec("UPDATE rcsa_schedules SET next_run = DATE_ADD(CURDATE(), INTERVAL 60 DAY) WHERE id=$sid");
// triggers: key control failure in the scheduled department
$trig0 = (int)$pdo->query("SELECT COUNT(*) FROM rcsa_assessments WHERE origin='triggered'")->fetchColumn();
$pdo->exec("INSERT INTO controls (name, department_id, owner_user_id, is_key, test_frequency, created_by, created_at, updated_at) VALUES ('Key retail control', $deptRet, " . $uidN('demo.dept.retail') . ", 1, 'annual', 1, NOW(), NOW())"); $kc2 = (int)$pdo->lastInsertId();
$c['compliance']->post("/controls/$kc2/tests", ['test_type' => 'design', 'test_date' => date('Y-m-d'), 'result' => 'ineffective', 'conclusion' => 'Design flaw'], "/controls/$kc2");
check('failing a key control in a scheduled department triggers an extra assessment (reason recorded, due in 14 days)', (int)$pdo->query("SELECT COUNT(*) FROM rcsa_assessments WHERE origin='triggered' AND trigger_reason='key_control_failed'")->fetchColumn() === $trig0 + 1 && $pdo->query("SELECT due_date FROM rcsa_assessments WHERE origin='triggered' ORDER BY id DESC LIMIT 1")->fetchColumn() === date('Y-m-d', strtotime('+14 days')));
$pdo->exec("UPDATE controls SET last_result=NULL WHERE id=$kc2"); $c['compliance']->post("/controls/$kc2/tests", ['test_type' => 'design', 'test_date' => date('Y-m-d'), 'result' => 'ineffective', 'conclusion' => 'Still flawed'], "/controls/$kc2");
check('a second trigger within 30 days is suppressed', (int)$pdo->query("SELECT COUNT(*) FROM rcsa_assessments WHERE origin='triggered'")->fetchColumn() === $trig0 + 1);
$pdo->exec("INSERT INTO controls (name, department_id, owner_user_id, is_key, test_frequency, created_by, created_at, updated_at) VALUES ('Key IT control', $deptIt, " . $uidN('demo.dept.it') . ", 1, 'annual', 1, NOW(), NOW())"); $kc3 = (int)$pdo->lastInsertId();
$c['compliance']->post("/controls/$kc3/tests", ['test_type' => 'design', 'test_date' => date('Y-m-d'), 'result' => 'ineffective', 'conclusion' => 'Flawed'], "/controls/$kc3");
check('no schedule for the department means no trigger', (int)$pdo->query("SELECT COUNT(*) FROM rcsa_assessments WHERE origin='triggered'")->fetchColumn() === $trig0 + 1);
check('RCSA schedule actions are audited', (int)$pdo->query("SELECT COUNT(DISTINCT action) FROM audit_log WHERE action IN ('rcsa.schedule_created','rcsa.schedule_toggled','rcsa.scheduled_launch','rcsa.triggered')")->fetchColumn() === 4);
