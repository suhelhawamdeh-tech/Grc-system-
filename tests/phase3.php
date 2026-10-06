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
