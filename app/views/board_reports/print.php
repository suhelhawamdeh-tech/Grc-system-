<?php /** @var array $x @var array $priority @var array $events @var array $bands */
require __DIR__ . '/_fmt.php';
$r = $x['r']; $n = $x['r']['narr']; $rows = $x['rows']; $fields = \App\Controllers\BoardReportController::FIELDS;
$txt = fn(string $k) => trim((string)($n[$k] ?? ''));
$para = fn(string $k) => $txt($k) !== '' ? '<p class="pre">' . e($txt($k)) . '</p>' : '';
$box = fn(string $title, array $lines) => '<div class="card notice"><strong>' . e($title) . '</strong>' . implode('', array_map(fn($l) => '<p class="pre">' . e($l) . '</p>', array_filter($lines, fn($l) => trim((string)$l) !== ''))) . '</div>';
$table = function (string $kind, array $only = []) use ($rows, $fields, $disp): string {
    $f = $only ? array_intersect_key($fields[$kind], array_flip($only)) : $fields[$kind];
    $h = '<table class="data"><thead><tr><th>#</th>';
    foreach ($f as [$type, $label]) {
        $h .= '<th>' . e(t($label)) . '</th>';
    }
    $h .= '</tr></thead><tbody>';
    foreach ($rows[$kind] ?? [] as $i => $row) {
        $h .= '<tr><td>' . ($i + 1) . '</td>';
        foreach ($f as $k => [$type]) {
            $h .= '<td>' . $disp($row, $k, $type) . '</td>';
        }
        $h .= '</tr>';
    }
    return $h . (empty($rows[$kind]) ? '<tr><td colspan="' . (count($f) + 1) . '" class="muted">—</td></tr>' : '') . '</tbody></table>';
};
$q = t('brr.quarter_label', ['q' => $r['quarter'], 'y' => $r['year']]);
?>
<h2><?= e(t('brr.report_title')) ?> — <?= e($q) ?></h2>
<table class="data kv"><tbody><tr><th><?= e(t('brr.prepared_by')) ?></th><td><?= e(t('brr.prepared_by_value')) ?></td><th><?= e(t('brr.submitted_to')) ?></th><td><?= e(t('brr.submitted_to_value')) ?></td></tr>
<tr><th><?= e(t('brr.report_date')) ?></th><td><?= e(fdate($r['report_date'])) ?></td><th><?= e(t('brr.ref_no')) ?></th><td><?= e($r['ref_no'] ?? '—') ?></td></tr>
<tr><th><?= e(t('brr.period')) ?></th><td><?= e(t('brr.period_line', ['f' => fdate($r['period_start']), 't' => fdate($r['period_end'])])) ?></td><th><?= e(t('docs.classification')) ?></th><td><?= e(t('enum.cls.' . $r['classification'])) ?> — <?= e(t('brr.board_only')) ?></td></tr></tbody></table>
<p><?= e(t('brr.letter_to')) ?><br><?= e(t('brr.letter_through')) ?></p><p><strong><?= e(t('brr.letter_subject', ['q' => $q])) ?></strong></p><p><?= e(t('brr.letter_greeting')) ?></p>
<?= $para('letter_intro') . $para('letter_messages') . $para('letter_request') ?>
<p><?= e(t('brr.letter_closing')) ?></p><p><strong><?= e($r['signatory_title'] ?? '') ?><br><?= e($r['signatory_name'] ?? '') ?></strong></p>
<h3><?= e(t('brr.contents')) ?></h3><table class="data"><thead><tr><th><?= e(t('brr.section')) ?></th><th><?= e(t('brr.title_col')) ?></th><th><?= e(t('brr.purpose')) ?></th></tr></thead><tbody>
<?php foreach ($tpl['sections'] as $no => $s): ?><tr><td><?= $no === 11 ? e($ar ? 'م' : 'A') : $no ?></td><td><?= e($ar ? $s[0] : $s[1]) ?></td><td><?= e(t('enum.brpurpose.' . $s[2])) ?></td></tr><?php endforeach; ?></tbody></table>
<?= $box(t('brr.reading_note'), $ar ? $tpl['reading_ar'] : $tpl['reading_en']) ?>
<h3>1. <?= e($secTitle(1)) ?></h3><?= $para('s1_overview') ?>
<?php if (!empty($rows['tile'])): ?><div class="kpis"><?php foreach ($rows['tile'] as $tile): ?><div class="kpi<?= $tile['level'] === 'danger' ? ' alert' : '' ?>"><div class="v"><?= e($tile['detail']) ?></div><div class="l"><?= e($tile['title']) ?><?= $tile['extra'] ? ' — ' . e($tile['extra']) : '' ?></div></div><?php endforeach; ?></div><?php endif; ?>
<h4><?= e(t('brr.profile')) ?></h4><?= $table('profile') ?>
<?php if (!empty($rows['message'])): ?><h4><?= e(t('brr.messages')) ?></h4><ul><?php foreach ($rows['message'] as $m): ?><li><strong><?= e($m['title']) ?>:</strong> <?= e($m['detail']) ?></li><?php endforeach; ?></ul><?php endif; ?>
<h3>2. <?= e($secTitle(2)) ?></h3><h4><?= e(t('brr.sources')) ?></h4><?= $table('source') ?><h4><?= e(t('brr.references')) ?></h4><?= $table('reference') ?>
<h3>3. <?= e($secTitle(3)) ?></h3><?= $para('s3_credit') ?: '<p>—</p>' ?><?= $txt('s3_assessment') !== '' ? $box(t('brr.s3_assessment'), [$txt('s3_assessment')]) : '' ?>
<h3>4. <?= e($secTitle(4)) ?></h3><?= $para('s4_market') ?: '<p>—</p>' ?><?php if (!empty($rows['observation'])): ?><h4><?= e(t('brr.observations')) ?></h4><?= $table('observation') ?><?php endif; ?>
<?php if ($txt('s4_responsibility') !== ''): ?><h4><?= e(t('brr.s4_responsibility')) ?></h4><?= $para('s4_responsibility') ?><?php endif; ?>
<h3>5. <?= e($secTitle(5)) ?></h3><?= $para('s5_operational') ?: '<p>—</p>' ?>
<?php if ($priority): ?><h4><?= e(t('brr.priority_risks')) ?></h4><table class="data"><thead><tr><th><?= e(t('risk.id')) ?></th><th><?= e(t('risk.title')) ?></th><th><?= e(t('brr.score')) ?></th><th><?= e(t('risk.department')) ?></th></tr></thead><tbody>
<?php foreach ($priority as $p): ?><tr><td><?= e($p['risk_code']) ?></td><td><?= e($p['title']) ?></td><td><?= (int)$p['res_score'] ?> (<?= (int)$p['res_likelihood'] ?>×<?= (int)$p['res_impact'] ?>)</td><td><?= e(lname(['name_ar' => $p['dept_ar'], 'name_en' => $p['dept_en']])) ?></td></tr><?php endforeach; ?></tbody></table><?php endif; ?>
<h3>6. <?= e($secTitle(6)) ?></h3><?= $para('s6_capital') ?: '<p>—</p>' ?><?= $txt('s6_reading') !== '' ? $box(t('brr.s6_reading'), [$txt('s6_reading')]) : '' ?>
<h3>7. <?= e($secTitle(7)) ?></h3><?= $para('s7_intro') ?><?= $table('dq') ?>
<h3>8. <?= e($secTitle(8)) ?></h3><p><?= e(t('brr.decisions_intro')) ?></p><?= $table('decision', ['code', 'title', 'level', 'detail', 'extra']) ?>
<h3>9. <?= e($secTitle(9)) ?></h3><?= $para('s9_roadmap') ?: '<p>—</p>' ?>
<h3>10. <?= e($secTitle(10)) ?></h3><?= $para('s10_intro') ?>
<table class="data"><thead><tr><th><?= e(t('brr.f_priority')) ?></th><th><?= e(t('brr.priority_meaning')) ?></th><th><?= e(t('brr.priority_closure')) ?></th></tr></thead><tbody><?php foreach ($tpl['priorities'] as $p): ?><tr><td><?= e(t('enum.brprio.' . $p)) ?></td><td><?= e(t(sprintf('brr.prio_meaning_%s', $p))) ?></td><td><?= e(t(sprintf('brr.prio_closure_%s', $p))) ?></td></tr><?php endforeach; ?></tbody></table>
<?php $byDept = []; foreach ($rows['recommendation'] ?? [] as $rec) { $byDept[$rec['department_id'] ?? 0][] = $rec; } $i = 0;
foreach ($byDept as $recs): $i++; $refs = implode('، ', array_unique(array_filter(array_map(fn($y) => trim((string)$y['refs']), $recs)))); ?>
<h4>10.<?= $i ?> <?= e($recs[0]['dept_ar'] ? lname(['name_ar' => $recs[0]['dept_ar'], 'name_en' => $recs[0]['dept_en']]) : '—') ?></h4><?php if ($refs !== ''): ?><p class="muted small"><?= e(t('brr.linked_decisions')) ?>: <?= e($refs) ?></p><?php endif; ?>
<table class="data"><thead><tr><th>#</th><th><?= e(t('brr.f_action')) ?></th><th><?= e(t('brr.f_output')) ?></th><th><?= e(t('brr.f_duration')) ?></th><th><?= e(t('brr.f_priority')) ?></th></tr></thead><tbody>
<?php foreach ($recs as $k => $rr): ?><tr><td><?= $k + 1 ?></td><td class="pre"><?= e($rr['title']) ?></td><td><?= e((string)$rr['detail']) ?></td><td><?= e($rr['extra'] ?: ($rr['days'] ? t('brr.days_n', ['n' => $rr['days']]) : '')) ?></td><td><?= $disp($rr, 'priority', 'priority') ?></td></tr><?php endforeach; ?></tbody></table><?php endforeach; ?>
<?= $box(t('brr.followup_title'), [$txt('s10_followup')]) ?>
<table class="data signatures"><thead><tr><th><?= e(t('brr.prepared_by')) ?></th><th><?= e(t('brr.approval')) ?></th></tr></thead><tbody><tr><td><?= e(t('brr.prepared_by_value')) ?></td><td><?= e(trim(($r['signatory_title'] ?? '') . ' — ' . ($r['signatory_name'] ?? ''))) ?></td></tr><tr><td><?= e(t('rm.signature')) ?>: ……</td><td><?= e(t('rm.signature')) ?>: ……</td></tr></tbody></table>
<h3><?= e($secTitle(11)) ?></h3><h4><?= e(t('brr.annex_scale')) ?></h4><table class="data"><thead><tr><th><?= e(t('brr.score')) ?></th><th><?= e(t('brr.f_level')) ?></th><th><?= e(t('brr.meaning')) ?></th></tr></thead><tbody>
<?php foreach ($bands as $b): ?><tr><td><?= (int)$b['min_score'] ?> – <?= (int)$b['max_score'] ?></td><td><?= e(lname($b, 'label')) ?></td><td><?= e(lname($b, 'description')) ?></td></tr><?php endforeach; ?></tbody></table><p class="muted small"><?= e(t('brr.scale_note')) ?></p>
<h4><?= e(t('brr.annex_events')) ?></h4><table class="data"><thead><tr><th>#</th><th><?= e(t('incident.name')) ?></th><th><?= e(t('incident.occurrence_date')) ?></th><th><?= e(t('incident.category')) ?></th><th><?= e(t('incident.net_loss')) ?></th><th><?= e(t('incident.status')) ?></th></tr></thead><tbody>
<?php foreach ($events as $k => $ev): ?><tr><td><?= $k + 1 ?></td><td><?= e($ev['title']) ?></td><td><?= e(fdate($ev['occurrence_date'])) ?></td><td><?= e(lname(['name_ar' => $ev['cat_ar'], 'name_en' => $ev['cat_en']])) ?></td><td><?= e(\App\Core\Money::format($ev['net_loss'])) ?></td><td><?= e(enum_label('incident_status', $ev['status'])) ?></td></tr><?php endforeach; ?>
<?php if (!$events): ?><tr><td colspan="6" class="muted">—</td></tr><?php endif; ?></tbody></table>
<p style="text-align:center"><strong><?= e(t('brr.end')) ?></strong></p>
