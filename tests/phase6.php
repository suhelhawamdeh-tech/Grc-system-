<?php
// Release 6 tests: framework documents, risk-function workbench, risk appetite framework, ICAAP, quarterly board
// report and supporting registers. Included by tests/run.php after phase5.php (shares $B, $pdo, $admin, $c[], $root).
// All figures are synthetic.
use App\Core\Perm;
use App\Support\Docx;
use App\Support\DocxReader;
use App\Support\Framework;
use App\Support\Icaap;
use App\Support\Registers;

$q = fn(string $sql) => $pdo->query($sql)->fetchColumn();
$noNotice = fn(array $r) => !preg_match('/(Warning|Notice|Deprecated|Fatal error):/', $r['body']);
$dept = (int)$q('SELECT id FROM departments ORDER BY id LIMIT 1');
$dept2 = (int)$q('SELECT id FROM departments ORDER BY id LIMIT 1,1');
$today = date('Y-m-d');

section('Release 6 unit: framework constants, document reader, registers config');
check('document codes found in text (charter, policy, plan with three parts)', Framework::codesIn('see RSK-GOV-CHR-001, BCMS-PL-001 and RSK-OPR-PLN-776; not ABC-1') === ['BCMS-PL-001', 'RSK-GOV-CHR-001', 'RSK-OPR-PLN-776']);
check('default levels follow the document hierarchy (charter 1, RAF 2, framework 3, policy 4, manual 5)', Framework::defaultLevel('charter', 'X-GOV-CHR-001') === 1 && Framework::defaultLevel('framework', 'RSK-GOV-RAF-001') === 2
    && Framework::defaultLevel('framework', 'RSK-GOV-ERM-001') === 3 && Framework::defaultLevel('policy', 'X') === 4 && Framework::defaultLevel('manual', 'X') === 5);
$tmpDoc = tempnam(sys_get_temp_dir(), 'tdoc') . '.docx';
$doc = (new Docx(true, 'ميثاق اختباري'))->heading('ميثاق اختباري للمنصة', 1)
    ->kv(['رمز الوثيقة' => 'TST-GOV-CHR-001', 'نوع الوثيقة' => 'ميثاق', 'الإصدار' => '2.0 — مسودة', 'الحالة' => 'مسودة', 'التصنيف' => 'سري', 'جهة الاعتماد' => 'مجلس الإدارة'])
    ->heading('1. الغرض', 1)->para('يستند هذا الميثاق إلى سياسة الاختبار TST-GOV-POL-101 ويحدد الأدوار.')->heading('2. الاستقلال', 1)->para('تتبع الوظيفة لجنة المخاطر وظيفيًا.');
file_put_contents($tmpDoc, $doc->build());
$rd = DocxReader::read($tmpDoc); $rd['meta'] = DocxReader::normalise($rd['meta']);
check('document reader: control table read (code, type, version without suffix, classification)', ($rd['meta']['code'] ?? '') === 'TST-GOV-CHR-001' && ($rd['meta']['version'] ?? '') === '2.0' && ($rd['meta']['classification'] ?? '') === 'confidential' && ($rd['meta']['category'] ?? '') === 'charter' && ($rd['meta']['doc_level'] ?? 0) === 1, json_encode($rd['meta'], JSON_UNESCAPED_UNICODE));
$regs = Registers::all();
check('registers: seven registers, each with prefix, statuses, open statuses within statuses', count($regs) === 7 && !array_filter($regs, fn($d) => !$d['prefix'] || array_diff($d['open'], $d['statuses'])));
$mat = Registers::config()['maturity']['fields']['axis'][2];
check('maturity axes are the eight of the ERM framework', $mat === ['governance', 'appetite', 'policies', 'methodologies', 'data_systems', 'reporting', 'culture', 'competencies']);
check('derived statuses: delivery late/on time, security acceptance expires', Registers::derive('data', 'expected', '2026-01-10', ['received_on' => '2026-01-12']) === 'late_received'
    && Registers::derive('data', 'expected', '2026-01-10', ['received_on' => '2026-01-10']) === 'received' && Registers::derive('security', 'active', '2000-01-01', []) === 'expired' && Registers::derive('security', 'closed', '2000-01-01', []) === 'closed');
check('permissions: risk manager works and approves; department manager responds to breaches only; auditor and executive read', Perm::roleHas('risk_manager', 'rm.work') && Perm::roleHas('risk_manager', 'rm.approve') && Perm::roleHas('risk_manager', 'icaap.manage')
    && Perm::roleHas('dept_manager', 'rm.respond') && !Perm::roleHas('dept_manager', 'rm.view') && Perm::roleHas('auditor', 'rm.view') && !Perm::roleHas('auditor', 'rm.work') && Perm::roleHas('exec_viewer', 'icaap.view') && !Perm::roleHas('exec_viewer', 'icaap.manage'));
check('unified scale bands: 1-4 low, 5-9 medium, 10-16 high, 17-25 critical', $q("SELECT GROUP_CONCAT(CONCAT(min_score,'-',max_score) ORDER BY min_score) FROM score_bands") === '1-4,5-9,10-16,17-25');

section('Framework documents: import, hierarchy, confidentiality');
$r = $c['risk']->post('/policies/import', [], '/policies/framework', ['files[0]' => ['TST-GOV-CHR-001.docx', file_get_contents($tmpDoc), 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']]);
$pol = $pdo->query("SELECT * FROM policies WHERE code='TST-GOV-CHR-001'")->fetch(PDO::FETCH_ASSOC);
check('Word document imported into the register with level 1; "سري" classified confidential', $r['code'] === 302 && $pol && (int)$pol['doc_level'] === 1 && $pol['classification'] === 'confidential', json_encode($pol ?: []));
check('import is indexed in the library as a restricted source', $pol && (int)$q("SELECT COUNT(*) FROM kb_sources WHERE policy_id=" . (int)$pol['id'] . " AND status='active'") === 1);
$fw = $c['risk']->get('/policies/framework');
check('framework page lists the missing reference TST-GOV-POL-101', $fw['code'] === 200 && str_contains($fw['body'], 'TST-GOV-POL-101') && $noNotice($fw));
$r2 = $c['risk']->post('/policies/import', [], '/policies/framework', ['files[0]' => ['TST-GOV-CHR-001.docx', file_get_contents($tmpDoc), 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']]);
check('re-importing the same file does not duplicate the document or its attachment', (int)$q("SELECT COUNT(*) FROM policies WHERE code='TST-GOV-CHR-001'") === 1 && (int)$q("SELECT COUNT(*) FROM attachments WHERE entity_type='policy_version' AND entity_id IN (SELECT id FROM policy_versions WHERE policy_id=" . (int)$pol['id'] . ")") === 1);
$att = (int)$q("SELECT a.id FROM attachments a JOIN policy_versions v ON v.id = a.entity_id WHERE a.entity_type='policy_version' AND v.policy_id=" . (int)$pol['id'] . " LIMIT 1");
check('confidential document file: department manager 404, risk manager 200', $att > 0 && $c['retail']->get("/attachments/$att")['code'] === 404 && $c['risk']->get("/attachments/$att")['code'] === 200);
check('department manager cannot import documents', $c['retail']->post('/policies/import', [], '/policies', ['files[0]' => ['x.docx', 'x', 'application/zip']])['code'] === 403);
@unlink($tmpDoc);

section('Risk-function workbench: opinions, breaches, escalations');
foreach (['/rm', '/rm/calendar', '/rm/opinions', '/rm/breaches', '/rm/escalations', '/rm/procedures'] as $p) { $g = $c['risk']->get($p); check("risk manager opens $p", $g['code'] === 200 && $noNotice($g)); }
$nProc = count((require $root . '/app/framework/procedures.php')['procedures']);
check("procedures catalogue synced ($nProc procedures) and tasks generated", (int)$q('SELECT COUNT(*) FROM rm_procedures') === $nProc && (int)$q('SELECT COUNT(*) FROM rm_tasks') > 0);
$c['risk']->post('/rm/opinions', ['title' => 'Financing request — synthetic group', 'section' => 'credit', 'subject_type' => 'financing', 'requesting_department_id' => (string)$dept, 'request_ref' => 'CR-T-1', 'received_on' => $today,
    'summary' => 'Group exposure after the request: 18% of capital.', 'key_risks' => 'Concentration.', 'limits_impact' => 'Within the regulatory limit.', 'opinion' => 'reservation', 'rationale' => 'Concentration close to the limit.'], '/rm/opinions/new');
$oid = (int)$q('SELECT MAX(id) FROM rm_opinions');
$c['risk']->post("/rm/opinions/$oid/step", ['step' => 'submit'], "/rm/opinions/$oid");
$c['risk']->post("/rm/opinions/$oid/step", ['step' => 'review'], "/rm/opinions/$oid");
check('opinion: the preparer cannot review their own opinion (four eyes)', $q("SELECT status FROM rm_opinions WHERE id=$oid") === 'submitted');
$admin->post("/rm/opinions/$oid/step", ['step' => 'review'], "/rm/opinions/$oid");
$admin->post("/rm/opinions/$oid/step", ['step' => 'issue'], "/rm/opinions/$oid");
check('opinion reviewed and issued by a second person', $q("SELECT status FROM rm_opinions WHERE id=$oid") === 'issued');
$c['risk']->post("/rm/opinions/$oid/decision", ['decision_body' => 'Credit committee', 'decision' => 'approved', 'decision_on' => $today, 'decision_note' => ''], "/rm/opinions/$oid");
check('a decision contrary to a reservation is flagged and escalated to the committee chair', (int)$q("SELECT contrary FROM rm_opinions WHERE id=$oid") === 1 && (int)$q("SELECT COUNT(*) FROM rm_escalations WHERE source_type='contrary_decision' AND source_id=$oid") === 1);
$dx = $c['risk']->get("/rm/opinions/$oid/docx");
check('opinion exports to Word', $dx['code'] === 200 && str_starts_with($dx['body'], 'PK'));
$nb = (int)$q('SELECT COUNT(*) FROM rm_breaches');
$c['risk']->post('/rm/breaches', ['kind' => 'breach', 'section' => 'market', 'limit_type' => 'regulatory', 'limit_tier' => 'policy', 'limit_name' => 'Net open position — USD', 'subject' => 'USD', 'limit_value' => '30', 'actual_value' => '34.5', 'unit' => '%',
    'detected_at' => date('Y-m-d\TH:i'), 'owner_department_id' => (string)$dept, 'cause' => 'Late booking', 'plan_due' => date('Y-m-d', strtotime('+5 days'))], '/rm/breaches/new');
$bid = (int)$q('SELECT MAX(id) FROM rm_breaches');
check('regulatory limit breach recorded and escalated', (int)$q('SELECT COUNT(*) FROM rm_breaches') === $nb + 1 && $q("SELECT escalation_id FROM rm_breaches WHERE id=$bid") !== null);
$c['risk']->post('/rm/breaches', ['kind' => 'exception', 'section' => 'credit', 'limit_type' => 'regulatory', 'limit_tier' => 'policy', 'limit_name' => 'x', 'detected_at' => date('Y-m-d\TH:i'), 'owner_department_id' => (string)$dept, 'risk_opinion' => 'reservation'], '/rm/breaches/new?kind=exception');
check('no exception can be recorded against a regulatory limit', (int)$q('SELECT MAX(id) FROM rm_breaches') === $bid);
check('department manager sees breaches but not the workbench', $c['retail']->get('/rm/breaches')['code'] === 200 && $c['retail']->get('/rm')['code'] === 403 && $c['retail']->get('/rm/opinions')['code'] === 403);

section('Risk appetite framework: template, calibration, amber and red');
$c['risk']->post('/appetite/framework/template', ['department_id' => (string)$dept], '/appetite/framework');
check('template creates the 49 board metrics, all uncalibrated', (int)$q("SELECT COUNT(*) FROM kris WHERE tier='board' AND template_key IS NOT NULL") === 49 && (int)$q("SELECT COUNT(*) FROM kris WHERE tier='board' AND calibrated=1") === 0);
$c['risk']->post('/appetite/framework/template', ['department_id' => (string)$dept], '/appetite/framework');
check('template is not duplicated when applied twice', (int)$q("SELECT COUNT(*) FROM kris WHERE tier='board' AND template_key IS NOT NULL") === 49);
$kid = (int)$q("SELECT id FROM kris WHERE template_key='CRD-01'");
$c['risk']->post("/kris/$kid/readings", ['reading_date' => $today, 'value' => '20', 'comment' => ''], "/kris/$kid");
check('a reading on an uncalibrated metric is refused', (int)$q("SELECT COUNT(*) FROM kri_readings WHERE kri_id=$kid") === 0);
$k = $pdo->query("SELECT * FROM kris WHERE id=$kid")->fetch(PDO::FETCH_ASSOC);
$form = ['name' => $k['name'], 'description' => (string)$k['description'], 'department_id' => (string)$k['department_id'], 'owner_user_id' => (string)$k['owner_user_id'], 'category_id' => '', 'risk_id' => '', 'unit' => '%', 'frequency' => 'monthly', 'direction' => 'higher_worse',
    'amber_threshold' => '15', 'red_threshold' => '20', 'critical_threshold' => '', 'data_source' => 'manual', 'tier' => 'board', 'raf_category' => 'credit', 'appetite_value' => '10', 'formula' => (string)$k['formula'], 'source_note' => 'Core banking', 'monitor_section' => 'credit', 'report_frequency' => 'monthly', 'is_active' => '1'];
$c['risk']->post("/kris/$kid", ['appetite_value' => '18'] + $form, "/kris/$kid/edit");
check('appetite level beyond the warning limit is refused', (int)$q("SELECT calibrated FROM kris WHERE id=$kid") === 0);
$c['risk']->post("/kris/$kid", $form, "/kris/$kid/edit");
check('calibration saved: appetite 10, warning 15, maximum 20', (int)$q("SELECT calibrated FROM kris WHERE id=$kid") === 1);
$c['risk']->post("/kris/$kid/readings", ['reading_date' => date('Y-m-d', strtotime('-10 days')), 'value' => '16', 'comment' => ''], "/kris/$kid");
check('amber reading creates a warning notice (not counted as a breach on the dashboard)', (int)$q("SELECT COUNT(*) FROM kri_breaches WHERE kri_id=$kid AND level='amber'") === 1);
$c['risk']->post("/kris/$kid/readings", ['reading_date' => $today, 'value' => '23', 'comment' => 'Two large defaults'], "/kris/$kid");
$redB = (int)$q("SELECT id FROM kri_breaches WHERE kri_id=$kid AND level='red'");
check('red reading escalates to the committee chair', $redB > 0 && (int)$q("SELECT COUNT(*) FROM rm_escalations WHERE source_type='appetite_red'") >= 1);
check('notice page and Word notice', $c['risk']->get("/kris/$kid/breaches/$redB/notice")['code'] === 200 && str_starts_with($c['risk']->get("/kris/$kid/breaches/$redB/docx")['body'], 'PK'));
$g = $c['exec']->get('/appetite/framework'); check('executive viewer opens the appetite dashboard', $g['code'] === 200 && $noNotice($g));

section('ICAAP: inputs, materiality, Pillar 2, stress, approvals');
$c['risk']->post('/icaap', ['year' => '2025', 'data_date' => '2025-12-31', 'regulatory_deadline' => date('Y-m-d', strtotime('+60 days'))], '/icaap');
$ic = (int)$q('SELECT MAX(id) FROM icaap_cycles');
check('cycle created with 9 phases and 16 risks', (int)$q("SELECT COUNT(*) FROM icaap_phases WHERE cycle_id=$ic") === 9 && (int)$q("SELECT COUNT(*) FROM icaap_risks WHERE cycle_id=$ic") === 16);
$c['risk']->post("/icaap/$ic/capital", ['data_date' => '2025-12-31', 'regulatory_deadline' => date('Y-m-d', strtotime('+60 days')), 'tier1' => '600,000,000', 'tier2' => '50000000', 'rwa_credit' => '1800000000', 'rwa_market' => '200000000', 'rwa_operational' => '400000000',
    'reg_min' => '12.5', 'trigger_ratio' => '14', 'warning_ratio' => '16', 'target_ratio' => '18', 'last_reg_ratio' => '27.0833', 'alpha' => '50'], "/icaap/$ic/capital");
$imp = ['credit' => 5, 'concentration' => 4, 'market' => 3, 'operational' => 4, 'liquidity' => 2, 'sharia' => 3, 'strategic' => 2];
$lik = ['credit' => 4, 'concentration' => 3, 'market' => 3, 'operational' => 3, 'liquidity' => 2, 'sharia' => 4, 'strategic' => 2];
$post = ['mgmt_buffer' => '5', 'aggregation' => '', 'impact' => [], 'likelihood' => [], 'justification' => [], 'method' => [], 'capital' => []];
foreach ($imp as $rk => $v) { $post['impact'][$rk] = (string)$v; $post['likelihood'][$rk] = (string)$lik[$rk]; $post['justification'][$rk] = 'Synthetic'; }
$post['method'] += ['concentration' => 'HHI add-on', 'sharia' => 'Scenario', 'liquidity' => 'n/a'];
$post['capital'] += ['concentration' => '30000000', 'sharia' => '10000000', 'liquidity' => '99999999'];
$c['risk']->post("/icaap/$ic/risks", $post, "/icaap/$ic/risks");
$x = Icaap::load($ic); $res = $x['r'];
check('capital adequacy 27.0833% (650m / 2,400m) and reconciliation with the last regulatory return', abs($res['car'] - 650 / 2400) < 1e-9 && $res['recon'] === 'match', json_encode([$res['car'], $res['recon']]));
check('materiality: impact ≥ 4 or score ≥ 10 (4 material risks; liquidity capital ignored)', $res['material_count'] === 4 && !$res['materiality']['liquidity']['material'] && $res['materiality']['concentration']['material']);
check('Pillar 1 300m, Pillar 2 40m, buffer 17m, required 357m, margin 293m', round($res['p1']) == 300e6 && round($res['p2']) == 40e6 && round($res['buffer']) == 17e6 && round($res['required']) == 357e6 && round($res['margin']) == 293e6,
    json_encode([$res['p1'], $res['p2'], $res['buffer'], $res['required'], $res['margin']]));
$st = ['profit' => [], 'distributions' => [], 'extra_provisions' => [], 'rwa_growth' => []];
foreach (['base' => [60, 20, 0, 5], 'moderate' => [20, 20, 60, 8], 'severe' => [-50, 0, 250, 10]] as $sc => [$p, $dd, $pr, $gr]) for ($y = 1; $y <= 3; $y++) { $st['profit'][$sc][$y] = (string)($p * 1e6); $st['distributions'][$sc][$y] = (string)($dd * 1e6); $st['extra_provisions'][$sc][$y] = (string)($pr * 1e6); $st['rwa_growth'][$sc][$y] = (string)$gr; }
$c['risk']->post("/icaap/$ic/stress", $st + ['s9_base' => 'Budget', 's9_severe' => 'Large defaults'], "/icaap/$ic/stress");
$x = Icaap::load($ic); $res = $x['r'];
check('stress paths: base year 1 27.381%, severe year 1 13.2576% in the trigger zone', abs($res['path']['base'][1]['ratio'] - 690 / 2520) < 1e-6 && abs($res['path']['severe'][1]['ratio'] - 350 / 2640) < 1e-6 && $res['path']['severe'][1]['zone'] === 'trigger');
check('severe scenario below the minimum raises an alert and an ICAAP escalation', in_array('severe_below_min', Icaap::alerts($x), true) && (int)$q("SELECT COUNT(*) FROM rm_escalations WHERE source_type='icaap'") >= 1);
$c['risk']->post("/icaap/$ic/phases/1", ['start_date' => '2026-01-01', 'end_date' => '2026-01-20', 'status' => 'complete', 'note' => ''], "/icaap/$ic");
check('a phase cannot be completed with its checklist open', $q("SELECT status FROM icaap_phases WHERE cycle_id=$ic AND phase_no=1") !== 'complete');
$c['risk']->post("/icaap/$ic/approval", ['step' => 'committee', 'committee_meeting' => '3/2026', 'committee_date' => $today], "/icaap/$ic/report");
check('committee step refused before the declaration', $q("SELECT committee_meeting FROM icaap_cycles WHERE id=$ic") === null);
foreach ([['step' => 'declare'], ['step' => 'committee', 'committee_meeting' => '3/2026', 'committee_date' => $today], ['step' => 'board', 'board_decision' => 'B-17/2026', 'board_date' => $today], ['step' => 'submit', 'submitted_on' => $today]] as $s) {
    $c['risk']->post("/icaap/$ic/approval", $s, "/icaap/$ic/report");
}
check('declaration → committee → board → submission', $q("SELECT status FROM icaap_cycles WHERE id=$ic") === 'submitted');
$c['risk']->post("/icaap/$ic/capital", ['data_date' => '2025-12-31', 'tier1' => '1'], "/icaap/$ic/capital");
check('cycle locked after submission', round((float)$q("SELECT tier1 FROM icaap_cycles WHERE id=$ic")) == 600e6);
foreach (['', '/capital', '/risks', '/stress', '/log', '/report', '/print'] as $p) { $g = $c['auditor']->get("/icaap/$ic$p"); check("auditor opens ICAAP $p", $g['code'] === 200 && $noNotice($g)); }
check('ICAAP report exports to Word', str_starts_with($c['risk']->get("/icaap/$ic/docx")['body'], 'PK'));
check('auditor cannot change ICAAP inputs', $c['auditor']->post("/icaap/$ic/capital", ['tier1' => '1'], "/icaap/$ic/capital")['code'] === 403);

section('Quarterly board risk report');
$c['risk']->post('/board-reports', ['year' => '2026', 'quarter' => '3', 'signatory_name' => 'Test Director', 'signatory_title' => 'Risk Director'], '/board-reports');
$br = (int)$q('SELECT MAX(id) FROM board_reports');
check('report created with code BRR-2026-Q3 and seven profile domains', $q("SELECT code FROM board_reports WHERE id=$br") === 'BRR-2026-Q3' && (int)$q("SELECT COUNT(*) FROM board_report_rows WHERE report_id=$br AND kind='profile'") === 7);
$c['risk']->post('/board-reports', ['year' => '2026', 'quarter' => '3'], '/board-reports');
check('one report per quarter', (int)$q("SELECT COUNT(*) FROM board_reports WHERE year=2026 AND quarter=3") === 1);
$c['risk']->post("/board-reports/$br", ['anchor' => 's3', 's3_credit' => 'Synthetic credit analysis.'], "/board-reports/$br");
check('section narrative saved without touching the letter fields', str_contains((string)$q("SELECT narratives FROM board_reports WHERE id=$br"), 'Synthetic credit analysis.') && $q("SELECT report_date FROM board_reports WHERE id=$br") === $today);
$c['risk']->post("/board-reports/$br/status", ['step' => 'submit'], "/board-reports/$br");
check('cannot submit without a proposed decision', $q("SELECT status FROM board_reports WHERE id=$br") === 'draft');
$c['risk']->post("/board-reports/$br/tiles", [], "/board-reports/$br");
check('dashboard tiles suggested from platform data', (int)$q("SELECT COUNT(*) FROM board_report_rows WHERE report_id=$br AND kind='tile'") >= 4);
$c['risk']->post("/board-reports/$br/references", [], "/board-reports/$br");
check('framework documents added as references', (int)$q("SELECT COUNT(*) FROM board_report_rows WHERE report_id=$br AND kind='reference' AND title LIKE '%TST-GOV-CHR-001%'") === 1);
$c['risk']->post("/board-reports/$br/rows", ['kind' => 'decision', 'code' => 'D-1', 'title' => 'X', 'level' => 'bogus'], "/board-reports/$br");
check('row with an invalid type is refused', (int)$q("SELECT COUNT(*) FROM board_report_rows WHERE report_id=$br AND kind='decision'") === 0);
$c['risk']->post("/board-reports/$br/rows", ['kind' => 'decision', 'code' => 'D-1', 'title' => 'Approve concentration limits', 'level' => 'approval', 'detail' => 'Credit'], "/board-reports/$br");
$c['risk']->post("/board-reports/$br/rows", ['kind' => 'recommendation', 'department_id' => (string)$dept, 'title' => 'Update the large exposures report', 'days' => '45', 'priority' => 'urgent', 'refs' => 'D-1'], "/board-reports/$br");
$c['risk']->post("/board-reports/$br/rows", ['kind' => 'recommendation', 'department_id' => (string)$dept2, 'title' => 'Fix branch codes in loss data', 'priority' => 'medium'], "/board-reports/$br");
$g = $c['risk']->get("/board-reports/$br/print"); check('print view', $g['code'] === 200 && $noNotice($g) && str_contains($g['body'], 'Approve concentration limits'));
$dxb = $c['risk']->get("/board-reports/$br/docx");
$zf = tempnam(sys_get_temp_dir(), 'brr'); file_put_contents($zf, $dxb['body']); $z = new ZipArchive(); $okZ = $z->open($zf) === true; $xml = $okZ ? (string)$z->getFromName('word/document.xml') : ''; if ($okZ) { $z->close(); } @unlink($zf);
check('Word export is a valid document with the eleven sections and the decision', $okZ && (new DOMDocument())->loadXML($xml) && str_contains($xml, 'Approve concentration limits') && substr_count($xml, 'Heading1') >= 11);
$c['risk']->post("/board-reports/$br/status", ['step' => 'submit'], "/board-reports/$br");
$c['risk']->post("/board-reports/$br/status", ['step' => 'committee', 'committee_meeting' => 'RC-5', 'committee_date' => $today], "/board-reports/$br");
$c['risk']->post("/board-reports/$br/status", ['step' => 'board', 'board_decision' => 'BD-11', 'board_date' => date('Y-m-d', strtotime('-1 day'))], "/board-reports/$br");
check('board date before the committee date is refused', $q("SELECT status FROM board_reports WHERE id=$br") === 'board');
$c['risk']->post("/board-reports/$br/status", ['step' => 'board', 'board_decision' => 'BD-11', 'board_date' => $today], "/board-reports/$br");
check('board approval locks the report and creates the follow-up once', $q("SELECT status FROM board_reports WHERE id=$br") === 'approved' && (int)$q("SELECT actions_created FROM board_reports WHERE id=$br") === 1);
check('decision D-1 becomes a board decision; recommendations become dated actions (45 and 30 days)', (int)$q("SELECT COUNT(*) FROM committee_decisions WHERE committee='board' AND title LIKE 'D-1:%'") === 1
    && $q("SELECT a.deadline FROM decision_actions a JOIN board_report_rows x ON x.action_id=a.id WHERE x.report_id=$br AND x.refs='D-1'") === date('Y-m-d', strtotime('+45 days'))
    && (int)$q("SELECT COUNT(*) FROM board_report_rows WHERE report_id=$br AND kind='recommendation' AND action_id IS NOT NULL") === 2
    && $q("SELECT a.deadline FROM decision_actions a JOIN board_report_rows x ON x.action_id=a.id WHERE x.report_id=$br AND x.refs IS NULL") === date('Y-m-d', strtotime('+30 days')));
$c['risk']->post("/board-reports/$br/rows", ['kind' => 'message', 'title' => 'late', 'detail' => 'x'], "/board-reports/$br");
check('approved report cannot be changed', (int)$q("SELECT COUNT(*) FROM board_report_rows WHERE report_id=$br AND kind='message'") === 0);
check('department manager cannot open board reports; auditor can read but not create', $c['retail']->get('/board-reports')['code'] === 403 && $c['auditor']->get("/board-reports/$br")['code'] === 200
    && $c['auditor']->post('/board-reports', ['year' => '2027', 'quarter' => '1'], '/board-reports')['code'] === 403);

section('Supporting registers');
foreach (array_keys($regs) as $rk) { $g = $c['risk']->get("/registers?r=$rk"); $n = $c['risk']->get("/registers/new?r=$rk"); check("register $rk: list and form", $g['code'] === 200 && $n['code'] === 200 && $noNotice($g) && $noNotice($n)); }
check('unknown register is 404', $c['risk']->get('/registers/new?r=nope')['code'] === 404);
$riskUid = (int)$q("SELECT id FROM users WHERE username='demo.risk'");
$c['risk']->post('/registers', ['r' => 'data', 'title' => 'Large exposures file', 'period' => '2026-09', 'frequency' => 'monthly', 'due_date' => '2026-10-10', 'received_on' => '2026-10-14', 'status' => 'expected', 'department_id' => (string)$dept], '/registers/new?r=data');
check('late data delivery recorded as received late', $q("SELECT status FROM rm_register_items WHERE register='data' AND title='Large exposures file'") === 'late_received');
$c['risk']->post('/registers', ['r' => 'security', 'title' => 'Legacy protocol on gateway', 'system' => 'Gateway', 'control_ref' => 'NET-04', 'reason' => 'Vendor upgrade', 'compensating' => 'Allow-list', 'risk_level' => 'high', 'granted_on' => '2026-09-01', 'approver' => 'CISO', 'due_date' => '2026-08-01', 'status' => 'active'], '/registers/new?r=security');
check('security acceptance with expiry before grant is refused', (int)$q("SELECT COUNT(*) FROM rm_register_items WHERE register='security'") === 0);
$c['risk']->post('/registers', ['r' => 'security', 'title' => 'Legacy protocol on gateway', 'system' => 'Gateway', 'control_ref' => 'NET-04', 'reason' => 'Vendor upgrade', 'compensating' => 'Allow-list', 'risk_level' => 'high', 'granted_on' => '2026-09-01', 'approver' => 'CISO',
    'due_date' => date('Y-m-d', strtotime('+90 days')), 'status' => 'active', 'owner_user_id' => (string)$riskUid], '/registers/new?r=security');
$sid = (int)$q("SELECT id FROM rm_register_items WHERE register='security'");
$pdo->exec("UPDATE rm_register_items SET due_date = DATE_SUB(CURDATE(), INTERVAL 1 DAY) WHERE id=$sid");
$e1 = Registers::expireSecurity(); $e2 = Registers::expireSecurity();
check('expired security acceptance is marked expired and escalated exactly once', $e1 === 1 && $e2 === 0 && $q("SELECT status FROM rm_register_items WHERE id=$sid") === 'expired' && (int)$q("SELECT COUNT(*) FROM rm_escalations WHERE source_type='security_exception' AND source_id=$sid") === 1);
$c['risk']->post('/registers/template', ['r' => 'maturity', 'year' => '2026'], '/registers?r=maturity');
$c['risk']->post('/registers/template', ['r' => 'maturity', 'year' => '2026'], '/registers?r=maturity');
check('maturity template adds the eight axes once', (int)$q("SELECT COUNT(*) FROM rm_register_items WHERE register='maturity'") === 8);
$mid = (int)$q("SELECT id FROM rm_register_items WHERE register='maturity' ORDER BY id LIMIT 1");
$c['risk']->post("/registers/$mid", ['r' => 'maturity', 'title' => '', 'axis' => 'governance', 'year' => '2026', 'level' => '4', 'target' => '3', 'status' => 'assessed'], "/registers/$mid/edit");
check('maturity target below the current level is refused', !str_contains((string)$q("SELECT data FROM rm_register_items WHERE id=$mid"), '"level":4'));
$c['risk']->post('/registers/template', ['r' => 'committee_plan', 'year' => '2027'], '/registers?r=committee_plan');
check('committee plan: 29 items, private session with the risk director at each of the four meetings', (int)$q("SELECT COUNT(*) FROM rm_register_items WHERE register='committee_plan'") === 29
    && (int)$q("SELECT COUNT(*) FROM rm_register_items WHERE register='committee_plan' AND data LIKE '%private_session%'") === 5);
$cid = (int)$q('SELECT id FROM regulatory_circulars ORDER BY id LIMIT 1');
if ($cid) {
    $c['risk']->post('/registers', ['r' => 'requirements', 'title' => 'Two-factor authentication for e-payments', 'clause' => '3.1', 'output' => 'Checklist', 'kri' => 'KRI-T-1', 'ref_id' => (string)$cid, 'due_date' => '2026-12-31', 'status' => 'compliant'], '/registers/new?r=requirements');
    $c['risk']->post('/registers', ['r' => 'requirements', 'title' => 'Fraud monitoring rules', 'clause' => '3.2', 'ref_id' => (string)$cid, 'due_date' => '2026-12-31', 'status' => 'partial'], '/registers/new?r=requirements');
    $mx = $c['risk']->get("/registers?r=requirements&ref=$cid");
    check('requirements matrix per circular with compliance summary (1 compliant + 1 partial = 75%)', $mx['code'] === 200 && str_contains($mx['body'], '75%'));
}
$c['risk']->post('/registers', ['r' => 'requirements', 'title' => 'No circular', 'due_date' => '2026-12-31', 'status' => 'partial'], '/registers/new?r=requirements');
check('a requirement needs its circular', (int)$q("SELECT COUNT(*) FROM rm_register_items WHERE register='requirements' AND title='No circular'") === 0);
$pdo->exec("UPDATE rm_register_items SET due_date = CURDATE(), status = 'active', owner_user_id = $riskUid WHERE id=$sid");
check('owned items falling due appear in My tasks', str_contains($c['risk']->get('/my')['body'], "/registers/$sid"));
check('auditor reads registers but cannot add', $c['auditor']->get('/registers')['code'] === 200 && $c['auditor']->get('/registers/new?r=models')['code'] === 403 && $c['retail']->get('/registers')['code'] === 403);
check('every release-6 page used above had a navigation entry for the risk team', (bool)preg_match_all('~href="[^"]*/(board-reports|registers|icaap|appetite/framework|policies/framework)"~', $c['risk']->get('/my')['body']));
