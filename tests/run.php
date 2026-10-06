<?php
declare(strict_types=1);

/**
 * Automated test suite (no external dependencies).  Usage:  php tests/run.php
 * Needs a MySQL/MariaDB user with full rights on two EMPTY-able databases (they are wiped!):
 *   GRC_TEST_DB_HOST (localhost) GRC_TEST_DB_USER (grc) GRC_TEST_DB_PASS (devpass) GRC_TEST_DB1 (grc_test) GRC_TEST_DB2 (grc_test2)
 * The app is copied to a temp dir and served with PHP's built-in server, so tests run against a clean install.
 */
$T = ['pass' => 0, 'fail' => 0, 'fails' => []];
function check(string $name, bool $cond, string $detail = ''): void
{
    global $T;
    if ($cond) {
        $T['pass']++;
        echo "  PASS  $name\n";
    } else {
        $T['fail']++;
        $T['fails'][] = $name;
        echo "  FAIL  $name" . ($detail !== '' ? "  [$detail]" : '') . "\n";
    }
}
function section(string $s): void { echo "\n== $s ==\n"; }

$root = dirname(__DIR__);
$env = fn($k, $d) => getenv($k) !== false ? getenv($k) : $d;
$dbh = $env('GRC_TEST_DB_HOST', 'localhost'); $dbu = $env('GRC_TEST_DB_USER', 'grc'); $dbp = $env('GRC_TEST_DB_PASS', 'devpass');
$db1 = $env('GRC_TEST_DB1', 'grc_test'); $db2 = $env('GRC_TEST_DB2', 'grc_test2');
$ADMIN_PW = 'Adm1n-Test-Pass-77'; $DEMO_PW = 'Demo-Test-Pass-88';

function wipe(string $h, string $u, string $p, string $db): PDO
{
    $pdo = new PDO("mysql:host=$h;dbname=$db;charset=utf8mb4", $u, $p, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
        $pdo->exec("DROP TABLE `$t`");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    return $pdo;
}
function copyTree(string $src, string $dst): void
{
    @mkdir($dst, 0777, true);
    foreach (['app', 'public', 'database', 'bin'] as $d) {
        exec('cp -r ' . escapeshellarg("$src/$d") . ' ' . escapeshellarg("$dst/$d"));
    }
    foreach (['logs', 'uploads', 'sessions', 'cache'] as $d) {
        @mkdir("$dst/storage/$d", 0777, true);
    }
}
function startServer(string $rootDir, int $port, array $envv, string $router): array
{
    $envs = '';
    foreach ($envv as $k => $v) { $envs .= $k . '=' . escapeshellarg($v) . ' '; }
    $cmd = "cd " . escapeshellarg($rootDir) . " && {$envs}exec php -S 127.0.0.1:$port " . escapeshellarg($router) . ' >' . escapeshellarg("$rootDir/server.log") . ' 2>&1';
    $proc = proc_open(['/bin/sh', '-c', $cmd], [], $pipes);
    for ($i = 0; $i < 50; $i++) {
        $s = @fsockopen('127.0.0.1', $port, $en, $es, 0.2);
        if ($s) { fclose($s); break; }
        usleep(100000);
    }
    return [$proc];
}

/** Tiny cookie-aware HTTP client. */
final class Client
{
    public string $jar;
    public function __construct(public string $base) { $this->jar = tempnam(sys_get_temp_dir(), 'cj'); }
    public function req(string $method, string $path, array $data = [], array $files = [], array $headers = []): array
    {
        $ch = curl_init($this->base . $path);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => $headers]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($files) {
                foreach ($files as $k => [$name, $content, $mime]) {
                    $tmp = tempnam(sys_get_temp_dir(), 'up'); file_put_contents($tmp, $content);
                    $data[$k] = new CURLFile($tmp, $mime, $name);
                }
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            }
        }
        $raw = curl_exec($ch);
        $hs = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $r = ['code' => curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'head' => substr((string)$raw, 0, $hs), 'body' => substr((string)$raw, $hs)];
        preg_match('/^Location:\s*(\S+)/mi', $r['head'], $m);
        $r['loc'] = $m[1] ?? null;
        curl_close($ch);
        return $r;
    }
    public function get(string $p, array $h = []): array { return $this->req('GET', $p, [], [], $h); }
    public function csrf(string $page = '/login'): string
    {
        $r = $this->get($page);
        preg_match('/name="_csrf" value="([a-f0-9]+)"/', $r['body'], $m);
        return $m[1] ?? '';
    }
    /** POST with a fresh CSRF token fetched from $formPage. */
    public function post(string $path, array $data, string $formPage = '/login', array $files = []): array
    {
        $data['_csrf'] = $this->csrf($formPage);
        return $this->req('POST', $path, $data, $files);
    }
    public function login(string $u, string $p): array { return $this->post('/login', ['username' => $u, 'password' => $p]); }
    public function cookie(): string { preg_match('/GRCSESSID\s+(\S+)/', (string)file_get_contents($this->jar), $m); return $m[1] ?? ''; }
}

// =========================================================================
section('Unit: money, scoring, csv, permissions, i18n');
define('APP_ROOT', $root);
require $root . '/app/bootstrap.php';
use App\Core\{Money, Perm, Audit, Scope};
use App\Support\{Csv, Scoring};

check('Money::parse plain', Money::parse('1234.5') === 1234500);
check('Money::parse thousands separators', Money::parse('1,234,567.891') === 1234567891);
check('Money::parse Arabic-Indic digits', Money::parse('١٢٣٤٫٥') === 1234500);
check('Money::parse rejects negative', Money::parse('-5') === null);
check('Money::parse rejects 4 decimals', Money::parse('1.2345') === null);
check('Money::parse rejects junk', Money::parse('12abc') === null && Money::parse('1e5') === null);
check('Money exact arithmetic 0.3-0.1', Money::toDb(Money::parse('0.3') - Money::parse('0.1')) === '0.200');
check('Money::toDb/fromDb round trip', Money::fromDb(Money::toDb(9999999999999)) === 9999999999999 && Money::toDb(1) === '0.001');
check('Scoring score = L x I', Scoring::score(4, 5) === 20 && Scoring::score(1, 1) === 1);
check('Scoring bands: valid tiling accepted', Scoring::validateBands([['min' => 1, 'max' => 4], ['min' => 5, 'max' => 9], ['min' => 10, 'max' => 25]]) === []);
check('Scoring bands: gap rejected', Scoring::validateBands([['min' => 1, 'max' => 4], ['min' => 6, 'max' => 25]]) !== []);
check('Scoring bands: overlap rejected', Scoring::validateBands([['min' => 1, 'max' => 10], ['min' => 8, 'max' => 25]]) !== []);
check('Scoring bands: short coverage rejected', Scoring::validateBands([['min' => 1, 'max' => 20]]) !== []);
foreach (['=1+1' => "'=1+1", '+SUM(A1)' => "'+SUM(A1)", '-2+3' => "'-2+3", '@cmd' => "'@cmd", "\t=x" => "'\t=x", ' =1' => "' =1", 'plain' => 'plain', 'مرحبا' => 'مرحبا', '2024-01-01' => '2024-01-01'] as $in => $out) {
    check('Csv::safe(' . json_encode($in, JSON_UNESCAPED_UNICODE) . ')', Csv::safe($in) === $out);
}
check('Csv::safe leaves real numbers alone', Csv::safe(-5) === '-5' && Csv::safe(3.5) === '3.5');
check('Csv::line quotes commas/quotes', Csv::line(['a,b', 'c"d'], ',') === "\"a,b\",\"c\"\"d\"\r\n");
check('Audit::clean redacts secrets', Audit::clean(['password' => 'x', 'new_password' => 'y', 'token' => 'z', 'ok' => 'v', 'nested' => ['db_pass' => 'q']]) === ['password' => '[redacted]', 'new_password' => '[redacted]', 'token' => '[redacted]', 'ok' => 'v', 'nested' => ['db_pass' => '[redacted]']]);
check('Perm: auditor read-only', Perm::roleHas('auditor', 'risk.view') && !Perm::roleHas('auditor', 'risk.create') && Perm::roleHas('auditor', 'audit.view'));
check('Perm: exec viewer cannot write/admin', !Perm::roleHas('exec_viewer', 'risk.edit') && !Perm::roleHas('exec_viewer', 'users.manage'));
check('Perm: only admin manages users/org/settings', array_reduce(Perm::ROLES, fn($c, $r) => $c && (Perm::roleHas($r, 'users.manage') === ($r === 'admin')) && (Perm::roleHas($r, 'org.manage') === ($r === 'admin')) && (Perm::roleHas($r, 'settings.manage') === ($r === 'admin')), true));
check('Perm: seven roles', count(Perm::ROLES) === 7);
check('Scope::sql fails closed for unassigned dept manager', Scope::sql(['role' => 'dept_manager', 'department_id' => null], 'r') === ['r.department_id = ?', [0]]);
check('Scope::allows branch liaison', Scope::allows(['role' => 'branch_liaison', 'branch_id' => 3], ['branch_id' => 3]) && !Scope::allows(['role' => 'branch_liaison', 'branch_id' => 3], ['branch_id' => 4]) && !Scope::allows(['role' => 'branch_liaison', 'branch_id' => 3], ['branch_id' => null]));
$en = App\Core\I18n::load('en'); $ar = App\Core\I18n::load('ar');
check('i18n: en/ar have identical keys', array_keys($en) === array_keys($ar) || (array_diff_key($en, $ar) === [] && array_diff_key($ar, $en) === []), 'missing in ar: ' . implode(',', array_keys(array_diff_key($en, $ar))) . ' missing in en: ' . implode(',', array_keys(array_diff_key($ar, $en))));
check('i18n: no empty Arabic values', count(array_filter($ar, fn($v) => trim($v) === '')) === 0);
check('i18n: Arabic values contain Arabic script (except tokens)', count(array_filter($ar, fn($v, $k) => !preg_match('/\p{Arabic}/u', $v) && !in_array($k, ['common.csv_note'], true), ARRAY_FILTER_USE_BOTH)) === 0, implode(',', array_keys(array_filter($ar, fn($v) => !preg_match('/\p{Arabic}/u', $v)))));
// Every literal t('key') / enum_label used in code exists
$used = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app'));
foreach ($it as $f) {
    if (!str_ends_with((string)$f, '.php') || str_contains((string)$f, '/lang/')) continue;
    $src = file_get_contents((string)$f);
    preg_match_all("/\bt\('([a-z_]+\.[a-z_0-9]+)'/", $src, $m); $used = array_merge($used, $m[1]);
    preg_match_all("/'((?:account|action|admin|appetite|audit|auth|cmt|comp|common|dash|error|incident|kri|nav|password|pol|rcsa|report|risk|scales|settings|setup|sys|upload|validation|history)\.[a-z_0-9]+)'/", $src, $m2); $used = array_merge($used, $m2[1]);
    preg_match_all("/enum_label\('([a-z_]+)', '([a-z_]+)'\)/", $src, $m3); foreach ($m3[1] as $i => $g) $used[] = "enum.$g." . $m3[2][$i];
}
$missing = array_values(array_diff(array_unique($used), array_keys($en)));
$missing = array_values(array_filter($missing, fn($k) => !preg_match('/^(app\.|bia\.gap_$|report\.board_pack$|audit\.view$|scales\.(levels_updated|bands_updated|approved)$|dash\.heat_caption_$|settings\.manage$|(appetite|kri|rcsa)\.(manage|view|report|respond|created|updated|breach_detected|breach_recorded|breach_updated|reading_added|template_created|template_updated)$)/', $k)));
check('i18n: all referenced keys exist', $missing === [], implode(',', $missing));
$css = file_get_contents($root . '/public/assets/css/app.css');
check('RTL: stylesheet uses logical properties only', !preg_match('/(margin|padding)-(left|right)\s*:|text-align\s*:\s*(left|right)|\b(left|right)\s*:\s*-?\d/', $css));
$mig = glob($root . '/database/migrations/*.sql');
check('Migrations exist and are ordered', count($mig) >= 2);

// =========================================================================
section('Clean install via CLI on an empty database, then start server');
$work = sys_get_temp_dir() . '/grc-test-' . getmypid();
exec('rm -rf ' . escapeshellarg($work));
copyTree($root, "$work/root");
mkdir("$work/cfg");
$pdo = wipe($dbh, $dbu, $dbp, $db1);
check('database wiped (no tables)', $pdo->query('SHOW TABLES')->rowCount() === 0);
$ienv = ['GRC_DB_HOST' => $dbh, 'GRC_DB_NAME' => $db1, 'GRC_DB_USER' => $dbu, 'GRC_DB_PASS' => $dbp, 'GRC_ADMIN_USER' => 'admin', 'GRC_ADMIN_PASS' => $ADMIN_PW, 'GRC_ADMIN_EMAIL' => 'admin@example.invalid',
    'GRC_DEMO' => '1', 'GRC_DEMO_PASS' => $DEMO_PW, 'GRC_CONFIG_FILE' => "$work/cfg/config.php"];
$prefix = ''; foreach ($ienv as $k => $v) $prefix .= "$k=" . escapeshellarg($v) . ' ';
exec($prefix . 'php ' . escapeshellarg("$work/root/bin/install.php") . ' 2>&1', $out, $rc);
check('CLI install succeeds', $rc === 0, implode(' | ', $out));
$tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
foreach (['users', 'risks', 'incidents', 'risk_actions', 'risk_history', 'attachments', 'audit_log', 'score_bands', 'rating_levels', 'schema_migrations', 'login_attempts'] as $t) check("table $t exists", in_array($t, $tables, true));
check('second install refused (already installed)', (function () use ($prefix, $work) { exec($prefix . 'php ' . escapeshellarg("$work/root/bin/install.php") . ' 2>&1', $o, $rc); return $rc === 4; })());
check('admin password stored hashed', password_verify($ADMIN_PW, $pdo->query("SELECT password_hash FROM users WHERE username='admin'")->fetchColumn()));
check('no plaintext passwords anywhere in users table', $pdo->query("SELECT COUNT(*) FROM users WHERE password_hash LIKE '%$DEMO_PW%' OR password_hash LIKE '%$ADMIN_PW%'")->fetchColumn() == 0);
check('score bands seeded tile 1..25', (function () use ($pdo) { $b = $pdo->query('SELECT min_score m, max_score x FROM score_bands ORDER BY min_score')->fetchAll(); $e = 1; foreach ($b as $r) { if ((int)$r['m'] !== $e) return false; $e = (int)$r['x'] + 1; } return $e === 26; })());
check('Islamic categories seeded', $pdo->query("SELECT COUNT(*) FROM risk_categories WHERE kind='islamic'")->fetchColumn() >= 3);
check('demo thresholds flagged unapproved', $pdo->query("SELECT setting_value FROM settings WHERE setting_key='thresholds_approved'")->fetchColumn() === '0');

$srvEnv = ['GRC_CONFIG_FILE' => "$work/cfg/config.php"];
[$srv1] = startServer("$work/root", 8091, $srvEnv, "$root/tests/router.php");
$B = 'http://127.0.0.1:8091';
$anon = new Client($B);

// =========================================================================
section('Authentication');
$r = $anon->get('/'); check('anonymous / redirects to login', $r['code'] === 302 && str_contains((string)$r['loc'], '/login'));
foreach (['/risks', '/risks/1', '/incidents', '/admin/users', '/audit', '/risks/export', '/incidents/export', '/reports', '/attachments/1', '/account', '/actions'] as $p) {
    $r = $anon->get($p); check("anonymous $p -> login", $r['code'] === 302 && str_contains((string)$r['loc'], '/login'));
}
$r = $anon->req('POST', '/login', ['username' => 'admin', 'password' => $ADMIN_PW]); check('login POST without CSRF token rejected (419)', $r['code'] === 419);
$r = $anon->req('POST', '/login', ['username' => 'admin', 'password' => $ADMIN_PW, '_csrf' => 'deadbeef']); check('login POST with bad CSRF token rejected', $r['code'] === 419);
$r = $anon->login('admin', 'wrong-password'); check('wrong password: redirected back to login', $r['code'] === 302 && str_contains((string)$r['loc'], '/login'));
$page = $anon->get('/login'); check('wrong password: generic error, no user enumeration', str_contains($page['body'], 'كلمة المرور غير صحيحة'));
$r = $anon->login('no.such.user', 'whatever-123456'); $page = $anon->get('/login'); check('unknown user: identical error message', str_contains($page['body'], 'كلمة المرور غير صحيحة'));
$before = $anon->cookie();
$r = $anon->login('admin', $ADMIN_PW); $after = $anon->cookie();
check('login success redirects to dashboard', $r['code'] === 302 && !str_contains((string)$r['loc'], 'login'));
check('session ID regenerated on login', $before !== '' && $after !== '' && $before !== $after);
check('session cookie is HttpOnly + SameSite', (bool)preg_match('/Set-Cookie: GRCSESSID=[^;]+;.*HttpOnly/i', $anon->get('/login')['head'] . $r['head']) && str_contains($r['head'] . $anon->req('POST', '/login')['head'], 'SameSite=Lax'));
$admin = $anon;
$r = $admin->get('/'); check('admin dashboard 200', $r['code'] === 200);
$r = $admin->req('POST', '/logout'); check('logout without CSRF token rejected', $r['code'] === 419);
$r = $admin->get('/logout'); check('logout via GET not allowed', $r['code'] === 405);
$r = $admin->post('/logout', [], '/account'); check('logout POST works', $r['code'] === 302);
check('after logout / needs login', str_contains((string)$admin->get('/')['loc'], '/login'));
$r = $admin->login('admin', $ADMIN_PW); check('admin can log in again', $r['code'] === 302);

// throttling (uses demo.exec; cleared afterwards)
$t = new Client($B);
for ($i = 0; $i < 5; $i++) $t->login('demo.exec', 'bad-guess-' . $i . 'aaaa');
$r = $t->login('demo.exec', $DEMO_PW); $pg = $t->get('/login');
check('login throttled after 5 failures even with correct password', $r['code'] === 302 && str_contains($pg['body'], 'محاولات فاشلة'));
check('throttle logged in audit', (int)$pdo->query("SELECT COUNT(*) FROM audit_log WHERE action='login.throttled'")->fetchColumn() >= 1);
$pdo->exec('DELETE FROM login_attempts');

// must change password
$u = new Client($B);
$r = $u->login('demo.risk', $DEMO_PW); check('demo user with temp password is sent to /account', str_contains((string)$r['loc'], '/account'));
check('must-change users are blocked from other pages', str_contains((string)$u->get('/risks')['loc'], '/account'));
$r = $u->post('/account/password', ['current_password' => $DEMO_PW, 'new_password' => 'short1', 'confirm_password' => 'short1'], '/account'); check('weak new password rejected', $r['code'] === 302 && str_contains((string)$r['loc'], '/account'));
$r = $u->post('/account/password', ['current_password' => 'wrong', 'new_password' => 'A-Brand-New-Pass-99', 'confirm_password' => 'A-Brand-New-Pass-99'], '/account'); check('wrong current password rejected', str_contains((string)$r['loc'], '/account'));
$r = $u->post('/account/password', ['current_password' => $DEMO_PW, 'new_password' => 'A-Brand-New-Pass-99', 'confirm_password' => 'A-Brand-New-Pass-99'], '/account'); check('password change accepted', $r['code'] === 302 && !str_contains((string)$r['loc'], 'account'));
check('after change /risks accessible', $u->get('/risks')['code'] === 200);
$pdo->exec("UPDATE users SET must_change_password = 0 WHERE role <> 'admin'");
$pdo->exec("UPDATE users SET password_hash = " . $pdo->quote(password_hash($DEMO_PW, PASSWORD_DEFAULT)) . " WHERE username = 'demo.risk'");

// =========================================================================
section('Authorization: roles, direct URLs, scope');
$c = [];
foreach (['risk' => 'demo.risk', 'compliance' => 'demo.compliance', 'retail' => 'demo.dept.retail', 'it' => 'demo.dept.it', 'tripoli' => 'demo.branch.tripoli', 'benghazi' => 'demo.branch.benghazi', 'exec' => 'demo.exec', 'auditor' => 'demo.auditor'] as $k => $un) {
    $c[$k] = new Client($B); $r = $c[$k]->login($un, $DEMO_PW);
    check("login $un", $r['code'] === 302 && !str_contains((string)$r['loc'], 'login'));
}
$matrix = [ // path => [role => expected status]
    '/admin/users' => ['risk' => 403, 'compliance' => 403, 'retail' => 403, 'tripoli' => 403, 'exec' => 403, 'auditor' => 403],
    '/admin/departments' => ['risk' => 403, 'exec' => 403, 'auditor' => 403],
    '/admin/settings' => ['risk' => 403, 'retail' => 403],
    '/admin/system' => ['risk' => 403],
    '/audit' => ['risk' => 403, 'compliance' => 403, 'retail' => 403, 'tripoli' => 403, 'exec' => 403, 'auditor' => 200],
    '/admin/scales' => ['risk' => 200, 'compliance' => 403, 'retail' => 403, 'auditor' => 403],
    '/risks/new' => ['risk' => 200, 'retail' => 200, 'tripoli' => 200, 'exec' => 403, 'auditor' => 403],
    '/incidents/new' => ['exec' => 403, 'auditor' => 403, 'tripoli' => 200],
    '/risks' => ['exec' => 200, 'auditor' => 200], '/' => ['exec' => 200, 'auditor' => 200, 'tripoli' => 200],
    '/reports' => ['exec' => 200, 'auditor' => 200],
];
foreach ($matrix as $p => $exp) foreach ($exp as $role => $code) { $r = $c[$role]->get($p); check("$role GET $p = $code", $r['code'] === $code, (string)$r['code']); }
$anyRisk = (int)$pdo->query("SELECT MIN(id) FROM risks")->fetchColumn();
$r = $c['exec']->post('/risks/' . $anyRisk . '/archive', [], '/risks'); check('exec viewer cannot archive (403)', $r['code'] === 403);
$r = $c['auditor']->post('/risks', ['title' => 'x'], '/risks'); check('auditor cannot create (403)', $r['code'] === 403);
$r = $c['exec']->post('/risks/' . $anyRisk, ['title' => 'hacked'], '/risks'); check('exec viewer cannot edit (403)', $r['code'] === 403);
check('title unchanged after forbidden edit', $pdo->query("SELECT title FROM risks WHERE id=$anyRisk")->fetchColumn() !== 'hacked');
$r = $c['risk']->post('/admin/users', ['username' => 'evil'], '/risks'); check('risk manager cannot POST admin/users (403)', $r['code'] === 403);
check('no user "evil" created', (int)$pdo->query("SELECT COUNT(*) FROM users WHERE username='evil'")->fetchColumn() === 0);

$deptOf = fn($code) => (int)$pdo->query("SELECT id FROM departments WHERE code='$code'")->fetchColumn();
$brOf = fn($code) => (int)$pdo->query("SELECT id FROM branches WHERE code='$code'")->fetchColumn();
$retail = $deptOf('RET'); $corp = $deptOf('COR'); $itd = $deptOf('IT'); $trp = $brOf('TRP'); $bng = $brOf('BNG');
$foreignRisk = (int)$pdo->query("SELECT MIN(id) FROM risks WHERE department_id=$corp")->fetchColumn();
$ownRisk = (int)$pdo->query("SELECT MIN(id) FROM risks WHERE department_id=$retail")->fetchColumn();
check('retail manager sees own-department risk', $c['retail']->get("/risks/$ownRisk")['code'] === 200);
check('retail manager: other department risk -> 404', $c['retail']->get("/risks/$foreignRisk")['code'] === 404);
check('retail manager: other dept risk edit URL -> 404', $c['retail']->get("/risks/$foreignRisk/edit")['code'] === 404);
check('retail manager: POST edit foreign risk -> 404 & unchanged', ($c['retail']->post("/risks/$foreignRisk", ['title' => 'pwned'], '/risks/new')['code'] === 404) && $pdo->query("SELECT title FROM risks WHERE id=$foreignRisk")->fetchColumn() !== 'pwned');
check('retail manager: archive foreign risk -> 404', $c['retail']->post("/risks/$foreignRisk/archive", [], '/risks')['code'] === 404 && $pdo->query("SELECT archived_at FROM risks WHERE id=$foreignRisk")->fetchColumn() === null);
check('retail manager: print foreign risk -> 404', $c['retail']->get("/risks/$foreignRisk/print")['code'] === 404);
$body = $c['retail']->get('/risks?archived=all')['body']; preg_match_all('#href="/risks/(\d+)"#', $body, $m); $ids = array_unique($m[1]);
$allowed = array_column($pdo->query("SELECT id FROM risks WHERE department_id=$retail")->fetchAll(), 'id');
check('retail list shows only own-department risks', $ids && !array_diff($ids, $allowed), implode(',', array_diff($ids, $allowed)));
check('retail list shows all own-department risks (count)', count($ids) === count($allowed), count($ids) . ' vs ' . count($allowed));
$csv = $c['retail']->get('/risks/export'); $rows = array_map('str_getcsv', explode("\r\n", trim(substr($csv['body'], 3))));
$codes = array_column(array_slice($rows, 1), 0); $expectCodes = array_column($pdo->query("SELECT risk_code FROM risks WHERE department_id=$retail AND archived_at IS NULL")->fetchAll(), 'risk_code');
sort($codes); sort($expectCodes);
check('CSV export limited to authorised rows (retail)', $codes === $expectCodes, count($codes) . ' vs ' . count($expectCodes));
check('CSV export honours filters', (function () use ($c, $pdo, $retail) { $csv = $c['retail']->get('/risks/export?q=' . urlencode('Cash handling')); $rows = array_map('str_getcsv', explode("\r\n", trim(substr($csv['body'], 3)))); return count($rows) - 1 === 1; })());
check('CSV cannot be widened via department param (retail asks for corporate)', (function () use ($c, $corp) { $csv = $c['retail']->get('/risks/export?department=' . $corp); return count(explode("\r\n", trim(substr($csv['body'], 3)))) === 1; })());
$body = $c['tripoli']->get('/risks?archived=all')['body']; preg_match_all('#href="/risks/(\d+)"#', $body, $m); $ids = array_unique($m[1]);
$allowed = array_column($pdo->query("SELECT id FROM risks WHERE branch_id=$trp")->fetchAll(), 'id');
check('branch liaison list = own branch only', $ids && !array_diff($ids, $allowed) && count($ids) === count($allowed));
$bngRisk = (int)$pdo->query("SELECT MIN(id) FROM risks WHERE branch_id=$bng")->fetchColumn();
check('Tripoli liaison cannot open Benghazi risk (404)', $c['tripoli']->get("/risks/$bngRisk")['code'] === 404);
$deptOnly = (int)$pdo->query("SELECT MIN(id) FROM risks WHERE branch_id IS NULL")->fetchColumn();
check('branch liaison cannot open branch-less risk (404)', $c['tripoli']->get("/risks/$deptOnly")['code'] === 404);
check('Tripoli liaison incident export only Tripoli', (function () use ($c, $pdo, $trp) { $csv = $c['tripoli']->get('/incidents/export'); $rows = array_slice(array_map('str_getcsv', explode("\r\n", trim(substr($csv['body'], 3)))), 1); $exp = (int)$pdo->query("SELECT COUNT(*) FROM incidents WHERE branch_id=$trp AND archived_at IS NULL")->fetchColumn(); return count($rows) === $exp; })());
$foreignInc = (int)$pdo->query("SELECT MIN(id) FROM incidents WHERE branch_id IS NULL OR branch_id <> $trp")->fetchColumn();
check('Tripoli liaison cannot open foreign incident (404)', $c['tripoli']->get("/incidents/$foreignInc")['code'] === 404);
check('exec viewer sees every risk in list', (function () use ($c, $pdo) { $n = (int)$pdo->query('SELECT COUNT(*) FROM risks WHERE archived_at IS NULL')->fetchColumn(); return str_contains($c['exec']->get('/risks')['body'], "$n " . 'سجل') || str_contains($c['exec']->get('/risks')['body'], "$n"); })());
// creating outside scope
$cats = (int)$pdo->query("SELECT MIN(id) FROM risk_categories")->fetchColumn();
$userRet = (int)$pdo->query("SELECT id FROM users WHERE username='demo.dept.retail'")->fetchColumn();
$riskFields = fn(array $o = []) => $o + ['title' => 'Scope test risk', 'description' => 'desc', 'category_id' => $cats, 'department_id' => $retail, 'owner_user_id' => $userRet, 'identified_date' => date('Y-m-d'), 'inh_likelihood' => 4, 'inh_impact' => 3, 'res_likelihood' => 2, 'res_impact' => 3, 'res_rationale' => 'controls reduce likelihood', 'control_assessment' => 'effective', 'treatment_decision' => 'mitigate', 'status' => 'active'];
$n0 = (int)$pdo->query('SELECT COUNT(*) FROM risks')->fetchColumn();
$r = $c['retail']->post('/risks', $riskFields(['department_id' => $corp]), '/risks/new'); check('dept manager cannot create risk in another department', (int)$pdo->query('SELECT COUNT(*) FROM risks')->fetchColumn() === $n0 && str_contains((string)$r['loc'], '/risks/new'));
$r = $c['tripoli']->post('/risks', $riskFields(['branch_id' => $bng, 'department_id' => $retail]), '/risks/new'); check('branch liaison cannot create risk for another branch', (int)$pdo->query('SELECT COUNT(*) FROM risks')->fetchColumn() === $n0);
$r = $c['tripoli']->post('/risks', $riskFields(['branch_id' => $trp, 'department_id' => $retail, 'title' => 'Tripoli liaison risk']), '/risks/new'); check('branch liaison can create risk for own branch', preg_match('#/risks/\d+$#', (string)$r['loc']) === 1);
$lid = (int)$pdo->query("SELECT MAX(id) FROM risks")->fetchColumn();
$r = $c['tripoli']->post("/risks/$lid", $riskFields(['branch_id' => $bng, 'department_id' => $retail, 'title' => 'moved']), "/risks/$lid/edit"); check('branch liaison cannot move risk to another branch', $pdo->query("SELECT branch_id FROM risks WHERE id=$lid")->fetchColumn() == $trp);
$r = $c['tripoli']->post("/risks/$lid/archive", [], '/risks'); check('branch liaison cannot archive (no permission)', $r['code'] === 403);

// dashboard totals vs database (scoped)
$kpi = function (Client $cl, string $qs = '') { $b = $cl->get('/' . $qs)['body']; preg_match_all('#<div class="v"[^>]*>([^<]+)</div>#', $b, $m); return $m[1]; };
$today = date('Y-m-d');
$high = (int)$pdo->query('SELECT MIN(min_score) FROM score_bands WHERE is_high=1')->fetchColumn();
foreach (['admin' => [$admin, '1=1'], 'retail' => [$c['retail'], "r.department_id=$retail"], 'tripoli' => [$c['tripoli'], "r.branch_id=$trp"], 'exec' => [$c['exec'], '1=1']] as $who => [$cl, $sc]) {
    $k = $kpi($cl);
    $active = (int)$pdo->query("SELECT COUNT(*) FROM risks r WHERE r.archived_at IS NULL AND r.status='active' AND $sc")->fetchColumn();
    $hi = (int)$pdo->query("SELECT COUNT(*) FROM risks r WHERE r.archived_at IS NULL AND r.status='active' AND r.res_score>=$high AND $sc")->fetchColumn();
    $od = (int)$pdo->query("SELECT COUNT(*) FROM risk_actions a JOIN risks r ON r.id=a.risk_id WHERE r.archived_at IS NULL AND a.status IN ('not_started','in_progress') AND a.deadline<'$today' AND $sc")->fetchColumn();
    check("dashboard[$who] active risks = DB ($active)", (int)$k[0] === $active, $k[0]);
    check("dashboard[$who] high residual = DB ($hi)", (int)$k[1] === $hi, $k[1]);
    check("dashboard[$who] overdue actions = DB ($od)", (int)$k[2] === $od, $k[2]);
    $isc = str_replace('r.', 'i.', $sc);
    $from = date('Y-01-01'); $to = date('Y-12-31');
    $inc = $pdo->query("SELECT COUNT(*) c, COALESCE(SUM(net_loss),0) n FROM incidents i WHERE i.archived_at IS NULL AND i.occurrence_date BETWEEN '$from' AND '$to' AND $isc")->fetch();
    $kk = $kpi($cl, "?from=$from&to=$to");
    check("dashboard[$who] incident count for period = DB ({$inc['c']})", (int)$kk[4] === (int)$inc['c'], $kk[4]);
    check("dashboard[$who] net loss for period = DB", (preg_match('/^[\d,]+\.\d+/', $kk[5], $nm) ? str_replace(',', '', $nm[0]) : '') === number_format((float)$inc['n'], 3, '.', ''), $kk[5] . ' vs ' . $inc['n']);
}
$hm = $admin->get('/')['body']; $cellSum = 0; preg_match_all('#href="/risks\?status=active&(?:amp;)?res_l=\d&(?:amp;)?res_i=\d"[^>]*><span class="n">(\d+)</span>#', $hm, $m); $cellSum = array_sum($m[1]);
check('residual heat map cells sum to active risk count', $cellSum === (int)$pdo->query("SELECT COUNT(*) FROM risks WHERE archived_at IS NULL AND status='active'")->fetchColumn(), (string)$cellSum);
check('dashboard chart links point to filtered record lists', str_contains($hm, 'href="/risks?status=active&amp;category=') && str_contains($hm, 'href="/actions?overdue=1"') && str_contains($hm, 'href="/incidents?from='));
$catLink = null; preg_match('#href="(/risks\?status=active&amp;category=(\d+))"[^>]*><span class="lbl">[^<]*</span>.*?<span class="val">(\d+)</span>#s', $hm, $m);
if ($m) { $lst = $admin->get(html_entity_decode($m[1]))['body']; preg_match_all('#<td class="nowrap"><a href="/risks/\d+">RSK#', $lst, $mm); check('chart drill-down list count equals chart value', count($mm[0]) === (int)$m[3], count($mm[0]) . ' vs ' . $m[3]); }

// =========================================================================
section('Risk workflow: create / validate / score / edit / filter / archive');
$admin = new Client($B); $admin->login('admin', $ADMIN_PW);
$adminId = (int)$pdo->query("SELECT id FROM users WHERE username='admin'")->fetchColumn();
$mk = fn(array $o = []) => $riskFields($o + ['owner_user_id' => $adminId]);
$n0 = (int)$pdo->query('SELECT COUNT(*) FROM risks')->fetchColumn();
$r = $admin->post('/risks', $mk(['title' => '']), '/risks/new'); check('create: missing title rejected', (int)$pdo->query('SELECT COUNT(*) FROM risks')->fetchColumn() === $n0);
$pg = $admin->get('/risks/new'); check('create: validation message shown in Arabic', str_contains($pg['body'], 'مطلوب'));
$r = $admin->post('/risks', $mk(['inh_likelihood' => 6]), '/risks/new'); check('create: likelihood 6 rejected', (int)$pdo->query('SELECT COUNT(*) FROM risks')->fetchColumn() === $n0);
$r = $admin->post('/risks', $mk(['inh_impact' => 0]), '/risks/new'); check('create: impact 0 rejected', (int)$pdo->query('SELECT COUNT(*) FROM risks')->fetchColumn() === $n0);
$r = $admin->post('/risks', $mk(['category_id' => 99999]), '/risks/new'); check('create: unknown category rejected', (int)$pdo->query('SELECT COUNT(*) FROM risks')->fetchColumn() === $n0);
$r = $admin->post('/risks', $mk(['res_likelihood' => 1, 'res_impact' => 1, 'res_rationale' => '']), '/risks/new'); check('create: residual differing from inherent needs rationale', (int)$pdo->query('SELECT COUNT(*) FROM risks')->fetchColumn() === $n0);
$r = $admin->post('/risks', $mk(['identified_date' => '2999-01-01']), '/risks/new'); check('create: future identification date rejected', (int)$pdo->query('SELECT COUNT(*) FROM risks')->fetchColumn() === $n0);
$r = $admin->post('/risks', $mk(['review_date' => '2000-01-01']), '/risks/new'); check('create: review date before identification rejected', (int)$pdo->query('SELECT COUNT(*) FROM risks')->fetchColumn() === $n0);
$r = $admin->post('/risks', $mk(['treatment_decision' => 'accept', 'inh_likelihood' => 5, 'inh_impact' => 5, 'res_likelihood' => 5, 'res_impact' => 5, 'res_rationale' => '', 'treatment_notes' => '']), '/risks/new'); check('create: accepting a critical residual risk needs justification', (int)$pdo->query('SELECT COUNT(*) FROM risks')->fetchColumn() === $n0);
$r = $admin->post('/risks', $mk(['title' => 'اختبار: خطر عربي للبحث', 'description' => 'وصف عربي', 'inh_likelihood' => 4, 'inh_impact' => 5, 'res_likelihood' => 2, 'res_impact' => 4, 'est_financial_impact' => '1,250,000.500']), '/risks/new');
check('create: valid risk accepted', preg_match('#/risks/(\d+)$#', (string)$r['loc'], $m) === 1);
$rid = (int)($m[1] ?? 0); $row = $pdo->query("SELECT * FROM risks WHERE id=$rid")->fetch();
check('score: inherent = 4x5 = 20 (server-calculated)', (int)$row['inh_score'] === 20);
check('score: residual = 2x4 = 8, recorded explicitly (not deducted)', (int)$row['res_score'] === 8 && (int)$row['res_likelihood'] === 2 && (int)$row['res_impact'] === 4);
check('risk code format RSK-00000', (bool)preg_match('/^RSK-\d{5}$/', $row['risk_code']));
check('Arabic text stored intact (utf8mb4)', $row['title'] === 'اختبار: خطر عربي للبحث');
check('financial amount stored exactly', $row['est_financial_impact'] === '1250000.500');
check('history row "created" written', (int)$pdo->query("SELECT COUNT(*) FROM risk_history WHERE risk_id=$rid AND event='created'")->fetchColumn() === 1);
check('audit row risk.created written', (int)$pdo->query("SELECT COUNT(*) FROM audit_log WHERE action='risk.created' AND entity_id=$rid")->fetchColumn() === 1);
$show = $admin->get("/risks/$rid")['body']; check('view page shows both scores and band names', str_contains($show, '20') && str_contains($show, '8 ·'));
check('financial threshold advisory shown when amounts disagree with chosen impact', str_contains($show, 'badge warn'));
// forged score fields are ignored
$r = $admin->post("/risks/$rid", $mk(['title' => 'اختبار: خطر عربي للبحث', 'description' => 'وصف عربي', 'inh_likelihood' => 4, 'inh_impact' => 5, 'res_likelihood' => 2, 'res_impact' => 4, 'inh_score' => 1, 'res_score' => 1, 'est_financial_impact' => '1,250,000.500']), "/risks/$rid/edit");
check('forged inh_score/res_score POST fields ignored', $r['code'] === 302 && (int)$pdo->query("SELECT inh_score FROM risks WHERE id=$rid")->fetchColumn() === 20);
check('no-op edit reports no changes and writes no history', (int)$pdo->query("SELECT COUNT(*) FROM risk_history WHERE risk_id=$rid AND event='updated'")->fetchColumn() === 0);
$r = $admin->post("/risks/$rid", $mk(['title' => 'اختبار: خطر عربي للبحث', 'description' => 'وصف عربي', 'inh_likelihood' => 4, 'inh_impact' => 5, 'res_likelihood' => 3, 'res_impact' => 4, 'res_rationale' => 'reassessed after test', 'est_financial_impact' => '1,250,000.500']), "/risks/$rid/edit");
$row = $pdo->query("SELECT * FROM risks WHERE id=$rid")->fetch(); check('edit: residual recalculated 3x4=12', (int)$row['res_score'] === 12);
$h = $pdo->query("SELECT changes FROM risk_history WHERE risk_id=$rid AND event='updated' ORDER BY id DESC LIMIT 1")->fetchColumn(); $hj = json_decode((string)$h, true);
check('history records field-level old/new values', isset($hj['res_likelihood']) && $hj['res_likelihood'] == [2, 3] && !isset($hj['title']));
check('history visible on the risk page', str_contains($admin->get("/risks/$rid")['body'], 'reassessed after test') || str_contains($admin->get("/risks/$rid")['body'], 'diff'));
// filters & search
$list = fn(string $qs) => $admin->get('/risks' . $qs)['body'];
check('search (Arabic) finds the risk', str_contains($list('?q=' . urlencode('عربي للبحث')), "/risks/$rid\""));
check('search (risk code) finds the risk', str_contains($list('?q=' . urlencode($row['risk_code'])), "/risks/$rid\""));
check('search: LIKE wildcard % is literal', !str_contains($list('?q=' . urlencode('%')), "/risks/$rid\""));
check('search: SQL injection attempt is harmless', $admin->get('/risks?q=' . urlencode("' OR 1=1 -- "))['code'] === 200 && !str_contains($list('?q=' . urlencode("' OR 1=1 -- ")), "/risks/$rid\""));
check('filter by category', str_contains($list("?category=$cats"), "/risks/$rid\"") && !str_contains($list('?category=' . ($cats + 1)), "/risks/$rid\""));
check('filter by residual likelihood/impact (matrix cell)', str_contains($list('?res_l=3&res_i=4'), "/risks/$rid\"") && !str_contains($list('?res_l=2&res_i=4'), "/risks/$rid\""));
check('filter by inherent cell', str_contains($list('?inh_l=4&inh_i=5'), "/risks/$rid\""));
$bandHigh = (int)$pdo->query('SELECT id FROM score_bands WHERE 12 BETWEEN min_score AND max_score')->fetchColumn(); $bandLow = (int)$pdo->query('SELECT id FROM score_bands WHERE 1 BETWEEN min_score AND max_score')->fetchColumn();
check('filter by residual score band', str_contains($list("?band=$bandHigh"), "/risks/$rid\"") && !str_contains($list("?band=$bandLow"), "/risks/$rid\""));
check('filter by department + owner', str_contains($list("?department=$retail&owner=$adminId"), "/risks/$rid\"") && !str_contains($list("?department=$corp"), "/risks/$rid\""));
check('sort by residual score does not error', $admin->get('/risks?sort=res&dir=asc')['code'] === 200 && $admin->get('/risks?sort=DROP')['code'] === 200);
check('junk filter values ignored safely', $admin->get('/risks?category=abc&band[]=1&res_l=99&sort[]=x')['code'] === 200);
// XSS
$r = $admin->post('/risks', $mk(['title' => '<script>alert(1)</script>"><img src=x onerror=alert(2)>']), '/risks/new'); preg_match('#/risks/(\d+)$#', (string)$r['loc'], $m); $xid = (int)$m[1];
$pg = $admin->get("/risks/$xid")['body'] . $admin->get('/risks')['body'];
check('output escaping: script tag not rendered raw', !str_contains($pg, '<script>alert(1)') && str_contains($pg, '&lt;script&gt;') && !str_contains($pg, '<img src=x'));
// actions
$act = fn(array $o) => $admin->post("/risks/$rid/actions", $o + ['title' => 'Late action', 'owner_user_id' => $adminId, 'deadline' => date('Y-m-d', strtotime('-10 days')), 'status' => 'in_progress'], "/risks/$rid");
$before = (int)$pdo->query('SELECT COUNT(*) FROM risk_actions')->fetchColumn();
$act(['title' => '']); check('action without title rejected', (int)$pdo->query('SELECT COUNT(*) FROM risk_actions')->fetchColumn() === $before);
$act(['deadline' => 'nonsense']); check('action with invalid date rejected', (int)$pdo->query('SELECT COUNT(*) FROM risk_actions')->fetchColumn() === $before);
$act([]); check('valid action accepted', (int)$pdo->query('SELECT COUNT(*) FROM risk_actions')->fetchColumn() === $before + 1);
check('overdue action appears in /actions?overdue=1', str_contains($admin->get('/actions?overdue=1')['body'], 'Late action'));
$aid = (int)$pdo->query('SELECT MAX(id) FROM risk_actions')->fetchColumn();
$admin->post("/risks/$rid/actions/$aid", ['owner_user_id' => $adminId, 'deadline' => date('Y-m-d', strtotime('-10 days')), 'status' => 'completed'], "/risks/$rid");
check('completing an action sets completed_on and removes it from overdue', $pdo->query("SELECT completed_on FROM risk_actions WHERE id=$aid")->fetchColumn() === date('Y-m-d') && !str_contains($admin->get('/actions?overdue=1')['body'], 'Late action'));
// archive
$admin->post("/risks/$rid/archive", [], '/risks'); check('archive sets archived_at', $pdo->query("SELECT archived_at FROM risks WHERE id=$rid")->fetchColumn() !== null);
check('archived risk hidden from default list', !str_contains($list(''), "/risks/$rid\""));
check('archived risk found with archived=1', str_contains($list('?archived=1'), "/risks/$rid\""));
check('archived risk excluded from dashboard active count', (int)$kpi($admin)[0] === (int)$pdo->query("SELECT COUNT(*) FROM risks WHERE archived_at IS NULL AND status='active'")->fetchColumn());
check('archived risk is read-only (edit redirects)', $admin->get("/risks/$rid/edit")['code'] === 302);
$admin->post("/risks/$rid/restore", [], '/risks'); check('restore clears archived_at', $pdo->query("SELECT archived_at FROM risks WHERE id=$rid")->fetchColumn() === null);
check('no hard delete route exists', $admin->post("/risks/$rid/delete", [], '/risks')['code'] === 404);

// =========================================================================
section('Incidents: loss calculation and validation');
$iF = fn(array $o = []) => $o + ['event_type' => 'incident', 'title' => 'Test incident', 'description' => 'd', 'occurrence_date' => date('Y-m-d', strtotime('-5 days')), 'discovery_date' => date('Y-m-d', strtotime('-3 days')), 'category_id' => $cats, 'department_id' => $retail, 'branch_id' => $trp, 'gross_loss' => '1,000.500', 'recoveries' => '250.250', 'status' => 'open', 'root_cause' => 'rc'];
$n0 = (int)$pdo->query('SELECT COUNT(*) FROM incidents')->fetchColumn();
$r = $admin->post('/incidents', $iF(['risk_ids' => [$rid]]), '/incidents/new'); preg_match('#/incidents/(\d+)$#', (string)$r['loc'], $m); $iid = (int)($m[1] ?? 0);
$row = $pdo->query("SELECT * FROM incidents WHERE id=$iid")->fetch();
check('incident created; net = gross - recoveries = 750.250', $iid > 0 && $row['gross_loss'] === '1000.500' && $row['recoveries'] === '250.250' && $row['net_loss'] === '750.250', json_encode($row));
check('incident code INC-00000', (bool)preg_match('/^INC-\d{5}$/', $row['incident_code']));
check('incident linked to risk', (int)$pdo->query("SELECT COUNT(*) FROM incident_risks WHERE incident_id=$iid AND risk_id=$rid")->fetchColumn() === 1);
check('risk page lists linked incident', str_contains($admin->get("/risks/$rid")['body'], $row['incident_code']));
$admin->post('/incidents', $iF(['gross_loss' => '0.3', 'recoveries' => '0.1', 'title' => 'float trap']), '/incidents/new'); check('0.3 - 0.1 = 0.200 exactly (no float error)', $pdo->query("SELECT net_loss FROM incidents WHERE title='float trap'")->fetchColumn() === '0.200');
$bad = function (string $label, array $o) use ($admin, $pdo, $iF) { $n = (int)$pdo->query('SELECT COUNT(*) FROM incidents')->fetchColumn(); $admin->post('/incidents', $iF($o), '/incidents/new'); check($label, (int)$pdo->query('SELECT COUNT(*) FROM incidents')->fetchColumn() === $n); };
$bad('recoveries > gross rejected', ['gross_loss' => '100', 'recoveries' => '100.001']);
$bad('negative gross rejected', ['gross_loss' => '-5']);
$bad('non-numeric gross rejected', ['gross_loss' => '12abc']);
$bad('4-decimal amount rejected', ['gross_loss' => '1.2345']);
$bad('occurrence after discovery rejected', ['occurrence_date' => date('Y-m-d', strtotime('-1 day')), 'discovery_date' => date('Y-m-d', strtotime('-2 days'))]);
$bad('future occurrence rejected', ['occurrence_date' => date('Y-m-d', strtotime('+2 days')), 'discovery_date' => date('Y-m-d', strtotime('+3 days'))]);
$bad('near miss with loss rejected', ['event_type' => 'near_miss', 'gross_loss' => '10']);
$bad('closing without root cause rejected', ['status' => 'closed', 'root_cause' => '']);
$bad('linking a non-existent risk rejected', ['risk_ids' => [999999]]);
$bad('invalid category rejected', ['category_id' => 999999]);
$admin->post('/incidents', $iF(['event_type' => 'near_miss', 'gross_loss' => '0', 'recoveries' => '0', 'title' => 'nm ok']), '/incidents/new'); check('near miss with zero loss accepted', (int)$pdo->query("SELECT COUNT(*) FROM incidents WHERE title='nm ok' AND event_type='near_miss'")->fetchColumn() === 1);
$admin->post("/incidents/$iid", $iF(['gross_loss' => '2000', 'recoveries' => '500.5', 'risk_ids' => [$rid]]), "/incidents/$iid/edit"); check('incident edit recalculates net (1499.500)', $pdo->query("SELECT net_loss FROM incidents WHERE id=$iid")->fetchColumn() === '1499.500');
$lst = $admin->get('/incidents?q=Test+incident')['body']; check('incident list totals row equals DB sum', str_contains($lst, '1,499.500') && (bool)preg_match('/<tfoot>.*1,499.500/s', $lst));
$sum = $pdo->query("SELECT SUM(net_loss) FROM incidents WHERE archived_at IS NULL")->fetchColumn(); $lst = $admin->get('/incidents')['body'];
check('unfiltered list total net loss = DB SUM', str_contains($lst, number_format((float)$sum, 3, '.', ',')), (string)$sum);
$admin->post("/incidents/$iid/archive", [], '/incidents'); check('incident archive/hide', !str_contains($admin->get('/incidents')['body'], "/incidents/$iid\"") && str_contains($admin->get('/incidents?archived=1')['body'], "/incidents/$iid\""));
$admin->post("/incidents/$iid/restore", [], '/incidents');
check('incident filters: type / date / linked risk', str_contains($admin->get('/incidents?type=near_miss')['body'], 'nm ok') && !str_contains($admin->get('/incidents?type=near_miss')['body'], 'float trap') && str_contains($admin->get("/incidents?risk=$rid")['body'], "/incidents/$iid\""));
check('audit row for incident with amounts', (int)$pdo->query("SELECT COUNT(*) FROM audit_log WHERE action='incident.created' AND detail LIKE '%750.250%'")->fetchColumn() === 1);

// =========================================================================
section('CSV export safety and print reports');
$evil = ['=HYPERLINK("http://evil.example","click")', '+1+1', '-2+3', '@SUM(1+1)', "\t=1+1"];
foreach ($evil as $k => $t) $admin->post('/risks', $mk(['title' => $t, 'description' => "desc $k"]), '/risks/new');
$csv = $admin->get('/risks/export');
check('CSV content type + attachment headers', str_contains($csv['head'], 'text/csv') && str_contains($csv['head'], 'attachment'));
check('CSV starts with UTF-8 BOM', str_starts_with($csv['body'], "\xEF\xBB\xBF"));
check('CSV contains Arabic text', str_contains($csv['body'], 'اختبار: خطر عربي للبحث'));
$rowsAll = array_map('str_getcsv', explode("\r\n", trim(substr($csv['body'], 3))));
$cols = count($rowsAll[0]); check('CSV rows have consistent column count', count(array_filter($rowsAll, fn($r) => count($r) === $cols)) === count($rowsAll));
$titles = array_column($rowsAll, 1);
check('formula injection neutralised (=HYPERLINK)', in_array("'" . $evil[0], $titles, true) && !in_array($evil[0], $titles, true));
check('formula injection neutralised (+ - @ tab)', in_array("'+1+1", $titles, true) && in_array("'-2+3", $titles, true) && in_array("'@SUM(1+1)", $titles, true) && in_array("'=1+1", $titles, true));
$bad = array_filter($rowsAll, fn($r) => array_filter($r, fn($c) => preg_match('/^[=+\-@\t\r]/', $c)));
check('no CSV cell starts with a formula character', count($bad) === 0, json_encode(array_values($bad)[0] ?? ''));
$semi = $admin->get('/risks/export?sep=;'); check('semicolon variant works', str_contains(explode("\r\n", $semi['body'])[0], ';') && !str_contains(explode("\r\n", $semi['body'])[0], ','));
$ic = $admin->get('/incidents/export'); check('incident CSV: BOM, amounts plain decimals', str_starts_with($ic['body'], "\xEF\xBB\xBF") && str_contains($ic['body'], '1499.500') && !str_contains($ic['body'], '1,499'));
check('audit CSV export (auditor) works and is logged', $c['auditor']->get('/audit/export')['code'] === 200 && (int)$pdo->query("SELECT COUNT(*) FROM audit_log WHERE action='export.audit'")->fetchColumn() === 1);
check('exports are audited with row counts', (int)$pdo->query("SELECT COUNT(*) FROM audit_log WHERE action='export.risks' AND detail LIKE '%rows%'")->fetchColumn() >= 1);
$pr = $admin->get('/risks/print'); check('printable risk report: RTL, print CSS, no nav', $pr['code'] === 200 && str_contains($pr['body'], 'dir="rtl"') && str_contains($pr['body'], 'print-page') && !str_contains($pr['body'], 'sidenav'));
$en = new Client($B); $en->get('/lang?l=en'); $en->login('admin', $ADMIN_PW);
$pr = $en->get('/risks/print'); check('printable report in English is LTR', str_contains($pr['body'], 'dir="ltr"') && str_contains($pr['body'], 'Confidential'));
check('single-risk print works', $admin->get("/risks/$rid/print")['code'] === 200 && $admin->get('/incidents/print')['code'] === 200);
check('print report respects scope (retail sees no corporate rows)', !str_contains($c['retail']->get('/risks/print')['body'], 'Delayed loan repayment monitoring'));

// =========================================================================
section('Uploads');
$pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
$up = fn(string $name, string $content, string $mime = 'application/octet-stream', ?Client $cl = null, ?int $id = null) => ($cl ?? $admin)->post('/risks/' . ($id ?? $rid) . '/attachments', [], '/risks/' . ($id ?? $rid), ['file' => [$name, $content, $mime]]);
$cnt = fn() => (int)$pdo->query('SELECT COUNT(*) FROM attachments WHERE deleted_at IS NULL')->fetchColumn();
$n = $cnt(); $up('report.pdf', $pdf, 'application/pdf'); check('valid PDF accepted', $cnt() === $n + 1);
$pid = (int)$pdo->query('SELECT MAX(id) FROM attachments')->fetchColumn();
$n = $cnt(); $up('pic.png', $png, 'image/png'); check('valid PNG accepted', $cnt() === $n + 1);
$n = $cnt(); $up('notes.txt', "plain text\n", 'text/plain'); check('valid TXT accepted', $cnt() === $n + 1);
$n = $cnt(); $up('shell.php', '<?php system($_GET["c"]); ?>', 'application/x-php'); check('.php upload rejected', $cnt() === $n);
$n = $cnt(); $up('evil.pdf', '<?php system($_GET["c"]); ?>', 'application/pdf'); check('PHP code disguised as .pdf rejected (content sniffing)', $cnt() === $n);
$n = $cnt(); $up('evil.png', $pdf, 'image/png'); check('PDF disguised as .png rejected', $cnt() === $n);
$n = $cnt(); $up('run.exe', "MZ\x90\x00", 'application/x-msdownload'); check('.exe rejected', $cnt() === $n);
$n = $cnt(); $up('page.html', '<script>alert(1)</script>', 'text/html'); check('.html rejected', $cnt() === $n);
$n = $cnt(); $up('a.php.pdf', '<?php echo 1;', 'application/pdf'); check('double extension with PHP content rejected', $cnt() === $n);
$n = $cnt(); $up('fake.docx', 'not a zip', 'application/octet-stream'); check('fake .docx (not OOXML) rejected', $cnt() === $n);
$n = $cnt(); $up('huge.pdf', $pdf . str_repeat('A', 6 * 1024 * 1024), 'application/pdf'); check('6 MB file rejected (limit 5 MB)', $cnt() === $n);
$n = $cnt(); $up('empty.txt', '', 'text/plain'); check('empty file rejected', $cnt() === $n);
$stored = $pdo->query("SELECT stored_name FROM attachments WHERE id=$pid")->fetchColumn();
check('stored under random .bin name outside public/', (bool)preg_match('#^\d{4}/\d{2}/[0-9a-f]{32}\.bin$#', $stored) && is_file("$work/root/storage/uploads/$stored") && !is_file("$work/root/public/$stored"));
$d = $admin->get("/attachments/$pid"); check('authorised download returns exact bytes', $d['code'] === 200 && $d['body'] === $pdf);
check('download forces attachment + nosniff', str_contains($d['head'], 'attachment') && str_contains(strtolower($d['head']), 'x-content-type-options: nosniff') && str_contains($d['head'], 'application/octet-stream'));
check('unauthenticated download -> login', str_contains((string)(new Client($B))->get("/attachments/$pid")['loc'], '/login'));
$corpRisk = $foreignRisk; $n = $cnt(); $up('x.pdf', $pdf, 'application/pdf', $c['retail'], $corpRisk); check('out-of-scope user cannot upload to foreign risk (404)', $cnt() === $n);
$up('cross.pdf', $pdf, 'application/pdf', $admin, $corpRisk); $cid = (int)$pdo->query('SELECT MAX(id) FROM attachments')->fetchColumn();
check('out-of-scope user cannot download foreign attachment (404)', $c['retail']->get("/attachments/$cid")['code'] === 404);
check('exec viewer cannot upload (403)', $up('x.pdf', $pdf, 'application/pdf', $c['exec'])['code'] === 403);
check('auditor can download in-scope attachment', $c['auditor']->get("/attachments/$pid")['code'] === 200);
check('storage/ is not reachable over HTTP', (new Client($B))->get('/storage/uploads/' . $stored)['code'] === 404 && (new Client($B))->get('/storage/installed.lock')['code'] === 404);
check('config file is outside the public folder', !str_starts_with(realpath("$work/cfg/config.php"), realpath("$work/root/public")));
$admin->post('/attachments/' . $pid . '/delete', [], "/risks/$rid"); check('attachment delete is soft (file kept for audit)', $pdo->query("SELECT deleted_at FROM attachments WHERE id=$pid")->fetchColumn() !== null && is_file("$work/root/storage/uploads/$stored") && $admin->get("/attachments/$pid")['code'] === 404);

// =========================================================================
section('Admin: users, org structure, scales, settings, audit');
$nu = fn(array $o = []) => $o + ['username' => 'new.user', 'full_name' => 'New User', 'email' => 'new@example.invalid', 'role' => 'dept_manager', 'department_id' => $itd, 'password' => 'Init-Pass-Word-12', 'lang' => 'en'];
$admin->post('/admin/users', $nu(['role' => 'dept_manager', 'department_id' => '']), '/admin/users/new'); check('dept manager without department rejected', (int)$pdo->query("SELECT COUNT(*) FROM users WHERE username='new.user'")->fetchColumn() === 0);
$admin->post('/admin/users', $nu(['role' => 'branch_liaison', 'branch_id' => '']), '/admin/users/new'); check('branch liaison without branch rejected', (int)$pdo->query("SELECT COUNT(*) FROM users WHERE username='new.user'")->fetchColumn() === 0);
$admin->post('/admin/users', $nu(['password' => 'weak']), '/admin/users/new'); check('weak initial password rejected', (int)$pdo->query("SELECT COUNT(*) FROM users WHERE username='new.user'")->fetchColumn() === 0);
$admin->post('/admin/users', $nu(['role' => 'superuser']), '/admin/users/new'); check('invalid role rejected', (int)$pdo->query("SELECT COUNT(*) FROM users WHERE username='new.user'")->fetchColumn() === 0);
$admin->post('/admin/users', $nu(), '/admin/users/new'); $nuRow = $pdo->query("SELECT * FROM users WHERE username='new.user'")->fetch();
check('admin creates user (hashed pw, must change)', $nuRow && password_verify('Init-Pass-Word-12', $nuRow['password_hash']) && (int)$nuRow['must_change_password'] === 1);
$admin->post('/admin/users', $nu(), '/admin/users/new'); check('duplicate username rejected', (int)$pdo->query("SELECT COUNT(*) FROM users WHERE username='new.user'")->fetchColumn() === 1);
$adm = (int)$pdo->query("SELECT id FROM users WHERE username='admin'")->fetchColumn();
$admin->post("/admin/users/$adm", ['full_name' => 'Admin', 'email' => 'admin@example.invalid', 'role' => 'auditor', 'is_active' => '1'], "/admin/users/$adm/edit"); check('cannot demote the last administrator', $pdo->query("SELECT role FROM users WHERE id=$adm")->fetchColumn() === 'admin');
$admin->post("/admin/users/$adm", ['full_name' => 'Admin', 'email' => 'admin@example.invalid', 'role' => 'admin'], "/admin/users/$adm/edit"); check('cannot deactivate own account', (int)$pdo->query("SELECT is_active FROM users WHERE id=$adm")->fetchColumn() === 1);
$uid = (int)$nuRow['id'];
$admin->post("/admin/users/$uid", ['full_name' => 'New User', 'email' => 'new@example.invalid', 'role' => 'dept_manager', 'department_id' => $itd], "/admin/users/$uid/edit"); check('admin can deactivate a user', (int)$pdo->query("SELECT is_active FROM users WHERE id=$uid")->fetchColumn() === 0);
$dc = new Client($B); $dc->login('new.user', 'Init-Pass-Word-12'); check('deactivated user cannot sign in', str_contains((string)$dc->get('/')['loc'], '/login'));
$admin->post("/admin/users/$uid/password", ['password' => 'Reset-Pass-Word-34'], "/admin/users/$uid/edit"); check('admin password reset forces change', (int)$pdo->query("SELECT must_change_password FROM users WHERE id=$uid")->fetchColumn() === 1 && password_verify('Reset-Pass-Word-34', $pdo->query("SELECT password_hash FROM users WHERE id=$uid")->fetchColumn()));
// ownership transfer
$owned = (int)$pdo->query("SELECT COUNT(*) FROM risks WHERE owner_user_id=$adm AND archived_at IS NULL")->fetchColumn();
$rm = (int)$pdo->query("SELECT id FROM users WHERE username='demo.risk'")->fetchColumn();
$rm0 = (int)$pdo->query("SELECT COUNT(*) FROM risks WHERE owner_user_id=$rm AND archived_at IS NULL")->fetchColumn();
$admin->post("/admin/users/$adm/transfer", ['to_user' => $rm], "/admin/users/$adm/edit");
check('ownership transfer moves all active risks', (int)$pdo->query("SELECT COUNT(*) FROM risks WHERE owner_user_id=$adm AND archived_at IS NULL")->fetchColumn() === 0 && (int)$pdo->query("SELECT COUNT(*) FROM risks WHERE owner_user_id=$rm AND archived_at IS NULL")->fetchColumn() === $rm0 + $owned);
// departments / branches / categories / periods
$admin->post('/admin/departments', ['code' => 'tst', 'name_en' => 'Test Dept', 'name_ar' => 'إدارة اختبار', 'is_active' => '1'], '/admin/departments/new'); check('department created (code upper-cased)', (int)$pdo->query("SELECT COUNT(*) FROM departments WHERE code='TST' AND name_ar='إدارة اختبار'")->fetchColumn() === 1);
$admin->post('/admin/departments', ['code' => 'TST', 'name_en' => 'Dup', 'name_ar' => 'مكرر', 'is_active' => '1'], '/admin/departments/new'); check('duplicate department code rejected', (int)$pdo->query("SELECT COUNT(*) FROM departments WHERE code='TST'")->fetchColumn() === 1);
$admin->post('/admin/branches', ['code' => 'TB1', 'name_en' => 'Test Branch', 'name_ar' => 'فرع اختبار', 'city' => 'X', 'is_active' => '1'], '/admin/branches/new'); check('branch created', (int)$pdo->query("SELECT COUNT(*) FROM branches WHERE code='TB1'")->fetchColumn() === 1);
$admin->post('/admin/categories', ['code' => 'ISL-TST', 'name_en' => 'Custom Islamic risk', 'name_ar' => 'خطر إسلامي مخصص', 'kind' => 'islamic', 'sort_order' => '300', 'is_active' => '1'], '/admin/categories/new'); check('custom Islamic category created', (int)$pdo->query("SELECT COUNT(*) FROM risk_categories WHERE code='ISL-TST' AND kind='islamic'")->fetchColumn() === 1);
$admin->post('/admin/periods', ['name_en' => 'Bad', 'name_ar' => 'سيئ', 'start_date' => '2026-05-01', 'end_date' => '2026-01-01', 'is_active' => '1'], '/admin/periods/new'); check('period with end before start rejected', (int)$pdo->query("SELECT COUNT(*) FROM reporting_periods WHERE name_en='Bad'")->fetchColumn() === 0);
$admin->post('/admin/periods', ['name_en' => 'H1 test', 'name_ar' => 'نصف سنة', 'start_date' => '2026-01-01', 'end_date' => '2026-06-30', 'is_active' => '1'], '/admin/periods/new'); check('reporting period created', (int)$pdo->query("SELECT COUNT(*) FROM reporting_periods WHERE name_en='H1 test'")->fetchColumn() === 1);
$pid2 = (int)$pdo->query("SELECT id FROM reporting_periods WHERE name_en='H1 test'")->fetchColumn();
$dsh = $admin->get("/?period=$pid2")['body']; check('dashboard honours selected reporting period', str_contains($dsh, '2026-01-01') && str_contains($dsh, '2026-06-30'));
// scales
$sc = $admin->get('/admin/scales')['body']; check('scales page shows demo-threshold warning', str_contains($sc, 'DEMONSTRATION') || str_contains($sc, 'تجريبية'));
$b1 = ['band' => [0 => ['id' => '', 'min' => '1', 'max' => '10', 'label_en' => 'Low', 'label_ar' => 'منخفض', 'color' => '#00aa00'], 1 => ['id' => '', 'min' => '12', 'max' => '25', 'label_en' => 'High', 'label_ar' => 'مرتفع', 'color' => '#ff0000', 'is_high' => '1']]];
$nb = (int)$pdo->query('SELECT COUNT(*) FROM score_bands')->fetchColumn(); $admin->post('/admin/scales/bands', $b1, '/admin/scales'); check('bands with a gap (11 missing) rejected', (int)$pdo->query('SELECT COUNT(*) FROM score_bands')->fetchColumn() === $nb);
$b1['band'][1]['min'] = '11'; $b1['band'][1]['color'] = 'red'; $admin->post('/admin/scales/bands', $b1, '/admin/scales'); check('band with invalid colour rejected', (int)$pdo->query("SELECT COUNT(*) FROM score_bands WHERE label_en='Low' AND max_score=10")->fetchColumn() === 0);
$admin->post('/admin/scales/approve', [], '/admin/scales'); check('approval recorded with user + audit', $pdo->query("SELECT setting_value FROM settings WHERE setting_key='thresholds_approved'")->fetchColumn() === '1' && (int)$pdo->query("SELECT COUNT(*) FROM audit_log WHERE action='scales.approved'")->fetchColumn() === 1);
$old = $pdo->query('SELECT id,min_score,max_score,label_en,label_ar,color,is_high FROM score_bands ORDER BY min_score')->fetchAll();
$post = []; foreach ($old as $i => $b) $post[$i] = ['id' => $b['id'], 'min' => $b['min_score'], 'max' => $b['max_score'], 'label_en' => $b['label_en'] . ($i === 0 ? ' X' : ''), 'label_ar' => $b['label_ar'], 'color' => $b['color'], 'is_high' => $b['is_high'] ? '1' : ''];
$admin->post('/admin/scales/bands', ['band' => $post], '/admin/scales'); check('valid band edit saved in place and resets approval', $pdo->query("SELECT COUNT(*) FROM score_bands WHERE label_en LIKE '% X'")->fetchColumn() == 1 && $pdo->query("SELECT setting_value FROM settings WHERE setting_key='thresholds_approved'")->fetchColumn() === '0');
$lv = []; foreach (['likelihood', 'impact'] as $s) for ($i = 1; $i <= 5; $i++) { $lv["{$s}_{$i}_label_en"] = "$s L$i"; $lv["{$s}_{$i}_label_ar"] = "مستوى $i"; $lv["{$s}_{$i}_desc_en"] = 'd'; $lv["{$s}_{$i}_desc_ar"] = 'و'; if ($s === 'impact') $lv["{$s}_{$i}_fin"] = (string)(($i - 1) * 1000); }
$lvBad = $lv; $lvBad['impact_3_fin'] = '500'; $admin->post('/admin/scales/levels', $lvBad, '/admin/scales'); check('non-increasing financial thresholds rejected', $pdo->query("SELECT COUNT(*) FROM rating_levels WHERE label_en='likelihood L1'")->fetchColumn() == 0);
$admin->post('/admin/scales/levels', $lv, '/admin/scales'); check('levels + financial thresholds saved', $pdo->query("SELECT financial_min FROM rating_levels WHERE scale='impact' AND level=3")->fetchColumn() === '2000.000');
check('risk manager may configure scales; compliance may not', $c['risk']->get('/admin/scales')['code'] === 200 && $c['compliance']->post('/admin/scales/approve', [], '/')['code'] === 403);
// settings
$admin->post('/admin/settings', ['org_name_en' => 'Org', 'org_name_ar' => 'مؤسسة', 'currency_code' => 'usd', 'currency_decimals' => '2', 'currency_symbol_en' => 'USD', 'currency_symbol_ar' => 'دولار', 'default_lang' => 'en', 'review_warning_days' => '30'], '/admin/settings');
check('currency is configurable (LYD default -> USD)', $pdo->query("SELECT setting_value FROM settings WHERE setting_key='currency_code'")->fetchColumn() === 'USD');
$incBody = $admin->get('/incidents')['body']; check('money display follows currency + decimals', (str_contains($incBody, 'USD') || str_contains($incBody, 'دولار')) && !str_contains($incBody, '1,499.500') && str_contains($incBody, '1,499.50'));
$admin->post('/admin/settings', ['org_name_en' => 'Org', 'org_name_ar' => 'مؤسسة', 'currency_code' => 'XX', 'currency_decimals' => '3', 'currency_symbol_en' => 'LYD', 'currency_symbol_ar' => 'د.ل', 'default_lang' => 'ar', 'review_warning_days' => '30'], '/admin/settings'); check('invalid currency code rejected', $pdo->query("SELECT setting_value FROM settings WHERE setting_key='currency_code'")->fetchColumn() === 'USD');
$admin->post('/admin/settings', ['org_name_en' => 'Demo Bank (synthetic data)', 'org_name_ar' => 'مصرف تجريبي (بيانات تجريبية)', 'currency_code' => 'LYD', 'currency_decimals' => '3', 'currency_symbol_en' => 'LYD', 'currency_symbol_ar' => 'د.ل', 'default_lang' => 'ar', 'review_warning_days' => '30'], '/admin/settings');
check('currency restored to LYD default', $pdo->query("SELECT setting_value FROM settings WHERE setting_key='currency_code'")->fetchColumn() === 'LYD' && str_contains($admin->get('/incidents')['body'], 'د.ل'));
// audit log content
check('audit log records logins, failures, admin actions', (int)$pdo->query("SELECT COUNT(DISTINCT action) FROM audit_log WHERE action IN ('login.success','login.failed','user.created','risk.created','password.changed','export.risks')")->fetchColumn() === 6);
check('audit log never contains passwords or hashes', (int)$pdo->query("SELECT COUNT(*) FROM audit_log WHERE detail LIKE '%$DEMO_PW%' OR detail LIKE '%$ADMIN_PW%' OR detail LIKE '%Init-Pass%' OR detail LIKE '%Reset-Pass%' OR detail LIKE '%\$2y\$%' OR detail LIKE '%A-Brand-New%'")->fetchColumn() === 0);
$au = $c['auditor']->get('/audit?action=risk.created'); check('auditor can view/filter the audit log', $au['code'] === 200 && str_contains($au['body'], 'risk.created'));
check('audit log is append-only in UI (no edit/delete routes)', $admin->post('/audit/1/delete', [], '/audit')['code'] === 404);
check('system status page for admin', $admin->get('/admin/system')['code'] === 200);
$sys = $admin->post('/admin/system/migrate', [], '/admin/system'); check('migrate with nothing pending is a no-op', $sys['code'] === 302);

// =========================================================================
require __DIR__ . '/phase2.php';
require __DIR__ . '/phase3.php';

// =========================================================================
section('Language switch, RTL / LTR rendering');
$a = new Client($B);
$p = $a->get('/login'); check('default (ar) login: lang=ar dir=rtl', str_contains($p['body'], '<html lang="ar" dir="rtl">'));
$a->get('/lang?l=en'); $p = $a->get('/login'); check('switch to English: lang=en dir=ltr', str_contains($p['body'], '<html lang="en" dir="ltr">') && str_contains($p['body'], 'Sign in'));
$a->get('/lang?l=ar'); $p = $a->get('/login'); check('switch back to Arabic', str_contains($p['body'], 'dir="rtl"') && str_contains($p['body'], 'تسجيل الدخول'));
$r = $a->get('/lang?l=en&next=' . urlencode('//evil.example/x')); check('language switch: open redirect blocked', !str_contains((string)$r['loc'], 'evil.example'));
$r = $a->get('/lang?l=en&next=' . urlencode('https://evil.example/')); check('language switch: absolute URL blocked', !str_contains((string)$r['loc'], 'evil.example'));
$a->get('/lang?l=xx'); check('unsupported language ignored', str_contains($a->get('/login')['body'], 'lang="en"'));
$a->login('admin', $ADMIN_PW);
$dEn = $a->get('/')['body']; check('English dashboard: LTR, English labels', str_contains($dEn, 'dir="ltr"') && str_contains($dEn, 'Active risks') && str_contains($dEn, 'Residual risk heat map'));
$a->get('/lang?l=ar'); $dAr = $a->get('/')['body']; check('Arabic dashboard: RTL, Arabic labels', str_contains($dAr, 'dir="rtl"') && str_contains($dAr, 'المخاطر النشطة') && str_contains($dAr, 'خريطة حرارة'));
check('language persisted on user profile', $pdo->query("SELECT lang FROM users WHERE username='admin'")->fetchColumn() === 'ar');
foreach (['/risks', '/risks/new', "/risks/$rid", '/incidents', '/incidents/new', '/actions', '/reports', '/admin/users', '/admin/scales', '/account', '/kris', '/appetite', '/compliance', '/committees', '/policies', '/rcsa', '/rcsa/launch', '/rcsa/templates/new', '/kris/new', '/appetite/new', '/compliance/new', '/committees/new', '/policies/new'] as $pth) {
    $ar1 = $a->get($pth)['body']; check("Arabic $pth has no untranslated keys", !preg_match('/\b(common|risk|incident|dash|admin|scales|action|report|audit|nav|auth|account|enum|upload|validation)\.[a-z_]{3,}\b/', strip_tags(preg_replace('#<(script|style).*?</\1>#s', '', $ar1))) , $pth);
}
$a->get('/lang?l=en'); foreach (['/risks', '/risks/new', "/risks/$rid", '/incidents', '/incidents/new', '/actions', '/reports', '/admin/users', '/admin/scales', '/account', '/kris', '/appetite', '/compliance', '/committees', '/policies', '/rcsa', '/rcsa/launch', '/rcsa/templates/new', '/kris/new', '/appetite/new', '/compliance/new', '/committees/new', '/policies/new', '/admin/settings', '/admin/system'] as $pth) {
    $en1 = $a->get($pth)['body']; check("English $pth has no untranslated keys / no Arabic UI chrome", !preg_match('/\b(common|risk|incident|dash|admin|scales|action|report|audit|nav|auth|account|enum|upload|validation)\.[a-z_]{3,}\b/', strip_tags(preg_replace('#<(script|style).*?</\1>#s', '', $en1))), $pth);
}
$a->get('/lang?l=ar'); $ge = $a->post('/risks', $mk(['title' => '']), '/risks/new'); $pg = $a->get('/risks/new'); check('Arabic validation messages displayed', str_contains($pg['body'], 'الحقل «'));
$a->get('/lang?l=en'); $a->post('/risks', $mk(['title' => '']), '/risks/new'); $pg = $a->get('/risks/new'); check('English validation messages displayed', str_contains($pg['body'], 'is required'));
check('heat map has accessible labels', str_contains($dEn, 'aria-label="Likelihood') && str_contains($dEn, '<th scope="row">'));
check('security headers present', str_contains($dEn, '<title>') && str_contains($a->get('/login')['head'], 'Content-Security-Policy') && str_contains($a->get('/login')['head'], 'X-Frame-Options: DENY'));
check('no inline scripts (CSP-friendly)', !preg_match('/<script(?![^>]*\bsrc=)[^>]*>/', $dEn) && !preg_match('/\son(click|load|error)=/', $dEn));
check('errors are generic in production (404)', str_contains($a->get('/nope')['body'], 'Something went wrong') && !str_contains($a->get('/nope')['body'], 'Stack'));

// =========================================================================
section('Web setup wizard on a clean database + subdirectory deployment');
$pdo2 = wipe($dbh, $dbu, $dbp, $db2);
copyTree($root, "$work/root2");
[$srv2] = startServer("$work/root2", 8092, ['GRC_TEST_BASE' => '/grc'], "$root/tests/router.php");
$S = 'http://127.0.0.1:8092'; $s = new Client($S);
$r = $s->get('/grc/'); check('subdir: uninstalled app redirects to /grc/setup', $r['code'] === 302 && str_contains((string)$r['loc'], '/grc/setup'), (string)$r['loc']);
$r = $s->get('/grc/login'); check('subdir: /login before install redirects to setup', str_contains((string)$r['loc'], '/grc/setup'));
$pg = $s->get('/grc/setup'); check('subdir: setup page renders with /grc-prefixed asset URLs', $pg['code'] === 200 && str_contains($pg['body'], '/grc/assets/css/app.css') && str_contains($pg['body'], 'action="/grc/setup/token"'));
$tokFile = "$work/root2/storage/setup-token.txt"; check('setup token file created in storage/', is_file($tokFile) && strlen(trim(file_get_contents($tokFile))) === 32);
$r = $s->post('/grc/setup/db', ['db_host' => $dbh, 'db_name' => $db2, 'db_user' => $dbu, 'db_pass' => $dbp], '/grc/setup'); check('setup DB step refused without token (403)', $r['code'] === 403);
$r = $s->post('/grc/setup/finish', ['username' => 'root1'], '/grc/setup'); check('setup finish refused without token', $r['code'] === 403);
$r = $s->post('/grc/setup/token', ['token' => 'wrong'], '/grc/setup'); $pg = $s->get('/grc/setup'); check('wrong setup token rejected', str_contains($pg['body'], 'setup/token'));
$r = $s->post('/grc/setup/token', ['token' => trim(file_get_contents($tokFile))], '/grc/setup'); $pg = $s->get('/grc/setup'); check('correct token unlocks DB step', str_contains($pg['body'], 'setup/db'));
$r = $s->post('/grc/setup/db', ['db_host' => $dbh, 'db_port' => '3306', 'db_name' => $db2, 'db_user' => $dbu, 'db_pass' => 'WRONG', 'timezone' => 'Africa/Tripoli'], '/grc/setup'); $pg = $s->get('/grc/setup'); check('bad DB credentials give a clear error and no config written', !is_file("$work/grc-config/config.php") && !is_file("$work/root2/config/config.php") && str_contains($pg['body'], 'setup/db'));
$r = $s->post('/grc/setup/db', ['db_host' => $dbh, 'db_port' => '3306', 'db_name' => $db2, 'db_user' => $dbu, 'db_pass' => $dbp, 'timezone' => 'Africa/Tripoli'], '/grc/setup');
$cfgPath = "$work/root2/../grc-config/config.php"; $cfgPath = is_file($cfgPath) ? $cfgPath : "$work/root2/config/config.php";
check('valid DB credentials: config file written (outside app folder when possible)', is_file($cfgPath), (string)$r['code']);
check('config file has restrictive permissions and a deny .htaccess', (fileperms($cfgPath) & 0077) === 0 && is_file(dirname($cfgPath) . '/.htaccess'));
$pg = $s->get('/grc/setup'); check('after DB step the admin form appears', str_contains($pg['body'], 'setup/finish'));
$adminForm = ['org_name_en' => 'Setup Bank', 'org_name_ar' => 'مصرف الإعداد', 'full_name' => 'First Admin', 'username' => 'first.admin', 'email' => 'first@example.invalid', 'password' => 'Fir5t-Admin-Pass-99', 'password_confirm' => 'Fir5t-Admin-Pass-99'];
$r = $s->post('/grc/setup/finish', array_merge($adminForm, ['password' => 'short', 'password_confirm' => 'short']), '/grc/setup'); check('weak admin password rejected', $pdo2->query('SHOW TABLES')->rowCount() === 0);
$r = $s->post('/grc/setup/finish', array_merge($adminForm, ['password_confirm' => 'different-Pass-1']), '/grc/setup'); check('mismatched confirmation rejected', $pdo2->query('SHOW TABLES')->rowCount() === 0);
$r = $s->post('/grc/setup/finish', $adminForm, '/grc/setup'); check('setup completes and redirects to login', $r['code'] === 302 && str_contains((string)$r['loc'], '/grc/login'), (string)$r['loc']);
check('schema created on clean DB, no demo data unless requested', (int)$pdo2->query('SELECT COUNT(*) FROM risks')->fetchColumn() === 0 && (int)$pdo2->query('SELECT COUNT(*) FROM users')->fetchColumn() === 1 && (int)$pdo2->query('SELECT COUNT(*) FROM score_bands')->fetchColumn() >= 3);
check('installed.lock written and setup token deleted', is_file("$work/root2/storage/installed.lock") && !is_file($tokFile));
check('setup becomes inaccessible (404) after install', $s->get('/grc/setup')['code'] === 404 && $s->post('/grc/setup/finish', $adminForm, '/grc/login')['code'] === 404 && $s->post('/grc/setup/db', ['db_host' => 'x'], '/grc/login')['code'] === 404);
check('setup stays closed even if lock file is deleted (users exist)', (function () use ($work, $s) { unlink("$work/root2/storage/installed.lock"); $c = $s->get('/grc/setup')['code']; return $c === 404; })());
check('audit log records setup completion', (int)$pdo2->query("SELECT COUNT(*) FROM audit_log WHERE action='setup.completed'")->fetchColumn() === 1);
$pg = $s->get('/grc/login'); check('subdir: login page links carry /grc prefix', str_contains($pg['body'], 'action="/grc/login"') && str_contains($pg['body'], '/grc/lang?l=en'));
$r = $s->login('first.admin', 'Fir5t-Admin-Pass-99'); $r = $s->post('/grc/login', ['username' => 'first.admin', 'password' => 'Fir5t-Admin-Pass-99'], '/grc/login');
check('subdir: login works and redirects within /grc/', $r['code'] === 302 && str_starts_with((string)$r['loc'], '/grc/') , (string)$r['loc']);
$pg = $s->get('/grc/'); check('subdir: dashboard renders; nav links prefixed', $pg['code'] === 200 && str_contains($pg['body'], 'href="/grc/risks"') && str_contains($pg['body'], 'action="/grc/logout"'));
check('subdir: session cookie path scoped to /grc/', str_contains(file_get_contents($s->jar), "/grc/\tFALSE") || str_contains(file_get_contents($s->jar), "/grc/"));
$pg = $s->get('/grc/risks/new'); check('empty install: risk form works with configured categories, no departments needed for admin view', $pg['code'] === 200);
check('empty install dashboard shows zero (metrics derive from data, none fabricated)', (function () use ($s) { $b = $s->get('/grc/')['body']; preg_match_all('#<div class="v"[^>]*>([^<]+)</div>#', $b, $m); return $m[1][0] === '0' && $m[1][1] === '0' && $m[1][4] === '0'; })());
$r = (new Client($S))->get('/grc/risks'); check('subdir: unauthenticated deep link redirects to /grc/login with a /grc next parameter', str_starts_with((string)$r['loc'], '/grc/login') && str_contains((string)$r['loc'], 'next=%2Fgrc%2Frisks'), (string)$r['loc']);
check('assets served under subdir', $s->get('/grc/assets/css/app.css')['code'] === 200);

// =========================================================================
section('Packaging');
$zipScript = "$root/bin/package.php";
if (is_file($zipScript)) {
    exec('php ' . escapeshellarg($zipScript) . ' ' . escapeshellarg("$work/pkg.zip") . ' 2>&1', $o, $rc);
    check('package script runs', $rc === 0 && is_file("$work/pkg.zip"), implode(' | ', $o));
    if (is_file("$work/pkg.zip")) {
        $z = new ZipArchive(); $z->open("$work/pkg.zip"); $names = []; for ($i = 0; $i < $z->numFiles; $i++) $names[] = $z->getNameIndex($i);
        check('ZIP contains app, public, database, docs', (bool)array_filter($names, fn($n) => str_ends_with($n, 'app/bootstrap.php')) && (bool)array_filter($names, fn($n) => str_ends_with($n, 'public/index.php')) && (bool)array_filter($names, fn($n) => str_ends_with($n, 'database/install.sql')));
        check('ZIP excludes tests, .git, config, secrets, uploads, logs, dev tools', !array_filter($names, fn($n) => preg_match('#(^|/)(tests|\.git|node_modules)/|config/config\.php|\.env|installed\.lock|setup-token|storage/(uploads|logs|sessions)/(?!\\.htaccess|\\.gitkeep|index\\.html)#', $n)));
        check('ZIP has empty storage skeleton with deny .htaccess', (bool)array_filter($names, fn($n) => str_ends_with($n, 'storage/.htaccess')));
        $z->close();
        // install.sql (phpMyAdmin path) imports into an empty database and matches the migrations
        $pdo3 = wipe($dbh, $dbu, $dbp, $db2);
        $zz = new ZipArchive(); $zz->open("$work/pkg.zip"); $sql = $zz->getFromName('grc-platform/database/install.sql'); $zz->close();
        $imp = new PDO("mysql:host=$dbh;dbname=$db2;charset=utf8mb4", $dbu, $dbp, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => true]);
        try { $imp->exec((string)$sql); $okImp = true; } catch (Throwable $e) { $okImp = false; echo $e->getMessage(); }
        check('install.sql imports cleanly into an empty database', $okImp);
        check('imported schema equals CLI-installed schema (same tables)', $pdo3->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) === $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN));
        check('imported DB has reference data but no users', (int)$pdo3->query('SELECT COUNT(*) FROM risk_categories')->fetchColumn() >= 12 && (int)$pdo3->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0 && (int)$pdo3->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn() === count(glob("$root/database/migrations/*.sql")));
    }
} else check('package script exists', false);

// =========================================================================
foreach ([$srv1, $srv2] as $p) { proc_terminate($p); }
exec('pkill -f "php -S 127.0.0.1:809[12]"');
exec('rm -rf ' . escapeshellarg($work));
echo "\n================ RESULT ================\n";
echo "passed: {$T['pass']}   failed: {$T['fail']}\n";
if ($T['fail']) { echo "FAILED:\n - " . implode("\n - ", $T['fails']) . "\n"; }
exit($T['fail'] ? 1 : 0);
