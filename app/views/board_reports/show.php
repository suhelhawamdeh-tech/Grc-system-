<?php /** @var array $x @var array $departments @var array $priority @var array $events @var array $followup */
require __DIR__ . '/_fmt.php';
$r = $x['r']; $n = $r['narr']; $rid = (int)$r['id']; $locked = $r['status'] === 'approved'; $edit = can('rm.work') && !$locked;
$narr = function (array $keys, string $anchor) use ($r, $n, $rid, $edit): string {
    $h = '<form method="post" action="' . e(url('board-reports/' . $rid)) . '" novalidate>' . csrf_field() . '<input type="hidden" name="anchor" value="' . e($anchor) . '"><div class="form-grid">';
    foreach ($keys as $k) {
        $h .= ff('textarea', $k, 'brr.' . $k, $n[$k] ?? '', ['wide' => true, 'hint' => t(sprintf('brr.hint_%s', $k)), 'extra' => ' rows="4"' . ($edit ? '' : ' readonly')]);
    }
    return $h . '</div>' . ($edit ? '<div class="actions"><button class="btn small" type="submit">' . e(t('common.save')) . '</button></div>' : '') . '</form>';
};
$rows = function (string $kind) use ($x, $edit, $departments, $disp): void { require __DIR__ . '/_rows.php'; };
?>
<div class="page-head"><div><h1><?= icon('file') ?> <?= e(t('brr.report_title')) ?> — <?= e(t('brr.quarter_label', ['q' => $r['quarter'], 'y' => $r['year']])) ?></h1>
<p class="sub"><?= e($r['code']) ?> · <?= e(t('brr.period_line', ['f' => fdate($r['period_start']), 't' => fdate($r['period_end'])])) ?> · <span class="badge<?= $locked ? ' ok' : '' ?>"><?= e(t('enum.brst.' . $r['status'])) ?></span> · <?= cls_badge($r['classification']) ?></p></div>
<div class="actions"><a class="btn secondary" href="<?= e(url('board-reports')) ?>"><?= e(t('common.back')) ?></a><a class="btn secondary" href="<?= e(url('board-reports/' . $rid . '/print')) ?>"><?= e(t('common.print')) ?></a><a class="btn secondary" href="<?= e(url('board-reports/' . $rid . '/docx')) ?>"><?= icon('file') ?> Word</a></div></div>
<?php if ($locked): ?><div class="card notice"><p><?= icon('lock') ?> <?= e(t('brr.locked_note')) ?></p></div><?php endif; ?>
<nav class="card toc" aria-label="<?= e(t('brr.contents')) ?>"><strong><?= e(t('brr.contents')) ?>:</strong> <a href="#letter"><?= e(t('brr.letter')) ?></a><?php foreach ($tpl['sections'] as $no => $s): ?> · <a href="#s<?= $no ?>"><?= $no === 11 ? e($ar ? 'م' : 'A') : $no ?>. <?= e($ar ? $s[0] : $s[1]) ?></a><?php endforeach; ?> · <a href="#approval"><?= e(t('brr.approval')) ?></a></nav>

<section class="card" id="letter"><h2><?= e(t('brr.letter')) ?></h2>
<form method="post" action="<?= e(url('board-reports/' . $rid)) ?>" novalidate><?= csrf_field() ?><input type="hidden" name="anchor" value="letter"><div class="form-grid">
<?= ff('text', 'ref_no', 'brr.ref_no', $r['ref_no'] ?? '', ['extra' => $edit ? '' : ' readonly']) ?>
<?= ff('date', 'report_date', 'brr.report_date', $r['report_date'], ['required' => true, 'extra' => $edit ? '' : ' readonly']) ?>
<?= ff('select', 'classification', 'docs.classification', $r['classification'], ['blank' => false, 'options' => ['internal' => t('enum.cls.internal'), 'confidential' => t('enum.cls.confidential'), 'secret' => t('enum.cls.secret')]]) ?>
<?= ff('text', 'signatory_name', 'brr.signatory_name', $r['signatory_name'] ?? '', ['extra' => $edit ? '' : ' readonly']) ?>
<?= ff('text', 'signatory_title', 'brr.signatory_title', $r['signatory_title'] ?? '', ['extra' => $edit ? '' : ' readonly']) ?>
<?php foreach (['letter_intro', 'letter_messages', 'letter_request'] as $k): ?><?= ff('textarea', $k, 'brr.' . $k, $n[$k] ?? '', ['wide' => true, 'hint' => t(sprintf('brr.hint_%s', $k)), 'extra' => ' rows="4"' . ($edit ? '' : ' readonly')]) ?><?php endforeach; ?>
</div><?php if ($edit): ?><div class="actions"><button class="btn" type="submit"><?= e(t('common.save')) ?></button></div><?php endif; ?></form></section>

<section class="card" id="s1"><h2>1. <?= e($secTitle(1)) ?></h2><?= $narr(['s1_overview'], 's1') ?>
<h3><?= e(t('brr.tiles')) ?></h3>
<?php if (!empty($x['rows']['tile'])): ?><div class="kpis"><?php foreach ($x['rows']['tile'] as $tile): ?><div class="kpi<?= $tile['level'] === 'danger' ? ' alert' : '' ?>"><div class="v"><?= e($tile['detail']) ?></div><div class="l"><?= e($tile['title']) ?><?= $tile['extra'] ? ' — ' . e($tile['extra']) : '' ?></div></div><?php endforeach; ?></div><?php endif; ?>
<?php if ($edit): ?><form method="post" action="<?= e(url('board-reports/' . $rid . '/tiles')) ?>" class="inline"><?= csrf_field() ?><button class="btn small gold" type="submit"><?= e(t('brr.suggest_tiles')) ?></button> <span class="muted small"><?= e(t('brr.suggest_hint')) ?></span></form><?php endif; ?>
<?php $rows('tile'); ?>
<h3><?= e(t('brr.profile')) ?></h3><?php $rows('profile'); ?>
<h3><?= e(t('brr.messages')) ?></h3><?php $rows('message'); ?></section>

<section class="card" id="s2"><h2>2. <?= e($secTitle(2)) ?></h2>
<div class="card notice"><strong><?= e(t('brr.reading_note')) ?></strong><ul><?php foreach ($ar ? $tpl['reading_ar'] : $tpl['reading_en'] as $l): ?><li><?= e($l) ?></li><?php endforeach; ?></ul></div>
<h3><?= e(t('brr.sources')) ?></h3><?php $rows('source'); ?>
<h3><?= e(t('brr.references')) ?></h3>
<?php if ($edit): ?><form method="post" action="<?= e(url('board-reports/' . $rid . '/references')) ?>" class="inline"><?= csrf_field() ?><button class="btn small gold" type="submit"><?= e(t('brr.add_framework_refs')) ?></button></form><?php endif; ?>
<?php $rows('reference'); ?></section>

<section class="card" id="s3"><h2>3. <?= e($secTitle(3)) ?></h2><?= $narr(['s3_credit', 's3_assessment'], 's3') ?></section>
<section class="card" id="s4"><h2>4. <?= e($secTitle(4)) ?></h2><?= $narr(['s4_market', 's4_responsibility'], 's4') ?><h3><?= e(t('brr.observations')) ?></h3><?php $rows('observation'); ?></section>
<section class="card" id="s5"><h2>5. <?= e($secTitle(5)) ?></h2><?= $narr(['s5_operational'], 's5') ?>
<h3><?= e(t('brr.priority_risks')) ?></h3><p class="muted small"><?= e(t('brr.priority_hint')) ?></p>
<div class="table-wrap"><table class="data"><thead><tr><th><?= e(t('risk.id')) ?></th><th><?= e(t('risk.title')) ?></th><th class="num"><?= e(t('brr.score')) ?></th><th><?= e(t('risk.department')) ?></th></tr></thead><tbody>
<?php if (!$priority): ?><tr><td colspan="4" class="muted"><?= e(t('common.no_data')) ?></td></tr><?php endif; ?>
<?php foreach ($priority as $p): ?><tr><td><a href="<?= e(url('risks/' . $p['id'])) ?>"><?= e($p['risk_code']) ?></a></td><td><?= e($p['title']) ?></td><td class="num"><?= (int)$p['res_score'] ?> (<?= (int)$p['res_likelihood'] ?>×<?= (int)$p['res_impact'] ?>)</td><td><?= e(lname(['name_ar' => $p['dept_ar'], 'name_en' => $p['dept_en']])) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<section class="card" id="s6"><h2>6. <?= e($secTitle(6)) ?></h2><?= $narr(['s6_capital', 's6_reading'], 's6') ?></section>
<section class="card" id="s7"><h2>7. <?= e($secTitle(7)) ?></h2><?= $narr(['s7_intro'], 's7') ?><?php $rows('dq'); ?></section>
<section class="card" id="s8"><h2>8. <?= e($secTitle(8)) ?></h2><p class="muted"><?= e(t('brr.decisions_intro')) ?></p><?php $rows('decision'); ?></section>
<section class="card" id="s9"><h2>9. <?= e($secTitle(9)) ?></h2><?= $narr(['s9_roadmap'], 's9') ?></section>
<section class="card" id="s10"><h2>10. <?= e($secTitle(10)) ?></h2><?= $narr(['s10_intro'], 's10') ?>
<div class="table-wrap"><table class="data"><thead><tr><th><?= e(t('brr.f_priority')) ?></th><th><?= e(t('brr.priority_meaning')) ?></th><th><?= e(t('brr.priority_closure')) ?></th></tr></thead><tbody><?php foreach ($tpl['priorities'] as $p): ?><tr><td><?= $disp(['priority' => $p], 'priority', 'priority') ?></td><td><?= e(t(sprintf('brr.prio_meaning_%s', $p))) ?></td><td><?= e(t(sprintf('brr.prio_closure_%s', $p))) ?></td></tr><?php endforeach; ?></tbody></table></div>
<p class="muted small"><?= e(t('brr.rec_hint')) ?></p><?php $rows('recommendation'); ?>
<?= $narr(['s10_followup'], 's10') ?></section>
<section class="card" id="s11"><h2><?= e($secTitle(11)) ?></h2>
<h3><?= e(t('brr.annex_scale')) ?></h3><div class="table-wrap"><table class="data"><thead><tr><th><?= e(t('brr.score')) ?></th><th><?= e(t('brr.f_level')) ?></th><th><?= e(t('brr.meaning')) ?></th></tr></thead><tbody><?php foreach (\App\Support\BoardReport::bands() as $b): ?><tr><td><?= (int)$b['min_score'] ?> – <?= (int)$b['max_score'] ?></td><td><?= e(lname($b, 'label')) ?></td><td><?= e(lname($b, 'description')) ?></td></tr><?php endforeach; ?></tbody></table></div>
<p class="muted small"><?= e(t('brr.scale_note')) ?></p>
<h3><?= e(t('brr.annex_events')) ?> (<?= count($events) ?>)</h3><div class="table-wrap"><table class="data"><thead><tr><th><?= e(t('incident.id')) ?></th><th><?= e(t('incident.name')) ?></th><th><?= e(t('incident.occurrence_date')) ?></th><th><?= e(t('incident.category')) ?></th><th class="num"><?= e(t('incident.net_loss')) ?></th><th><?= e(t('incident.status')) ?></th></tr></thead><tbody>
<?php if (!$events): ?><tr><td colspan="6" class="muted"><?= e(t('common.no_data')) ?></td></tr><?php endif; ?>
<?php foreach ($events as $ev): ?><tr><td><a href="<?= e(url('incidents/' . $ev['id'])) ?>"><?= e($ev['incident_code']) ?></a></td><td><?= e($ev['title']) ?></td><td><?= e(fdate($ev['occurrence_date'])) ?></td><td><?= e(lname(['name_ar' => $ev['cat_ar'], 'name_en' => $ev['cat_en']])) ?></td><td class="num"><?= e(\App\Core\Money::format($ev['net_loss'])) ?></td><td><?= e(enum_label('incident_status', $ev['status'])) ?></td></tr><?php endforeach; ?></tbody></table></div></section>

<section class="card" id="approval"><h2><?= e(t('brr.approval')) ?></h2><p class="muted"><?= e(t('brr.approval_hint')) ?></p><dl class="details">
<dt><?= e(t('common.status')) ?></dt><dd><?= e(t('enum.brst.' . $r['status'])) ?></dd>
<dt><?= e(t('icaap.committee_meeting')) ?></dt><dd><?= e(trim(($r['committee_meeting'] ?? '—') . ' ' . fdate($r['committee_date']))) ?></dd>
<dt><?= e(t('icaap.board_decision')) ?></dt><dd><?= e(trim(($r['board_decision'] ?? '—') . ' ' . fdate($r['board_date']))) ?></dd></dl>
<?php $st = fn(string $step) => '<form method="post" action="' . e(url('board-reports/' . $rid . '/status')) . '" class="form-grid" novalidate>' . csrf_field() . '<input type="hidden" name="step" value="' . $step . '">'; ?>
<?php if (can('rm.work') && $r['status'] === 'draft'): ?><?= $st('submit') ?><div class="field"><button class="btn gold" type="submit"><?= e(t('brr.submit_committee')) ?></button></div></form>
<?php elseif (can('rm.work') && $r['status'] === 'committee'): ?><?= $st('committee') ?><?= ff('text', 'committee_meeting', 'icaap.committee_meeting', '', ['required' => true]) ?><?= ff('date', 'committee_date', 'icaap.committee_date', date('Y-m-d'), ['required' => true]) ?><div class="field"><button class="btn gold" type="submit"><?= e(t('icaap.record_committee')) ?></button></div></form>
<?php elseif (can('rm.approve') && $r['status'] === 'board'): ?><?= $st('board') ?><?= ff('text', 'board_decision', 'icaap.board_decision', '', ['required' => true]) ?><?= ff('date', 'board_date', 'icaap.board_date', date('Y-m-d'), ['required' => true]) ?><div class="field"><button class="btn gold" type="submit"><?= e(t('brr.record_board')) ?></button></div></form><p class="muted small"><?= e(t('brr.board_effect')) ?></p><?php endif; ?>
<?php if (can('rm.work') && in_array($r['status'], ['committee', 'board'], true)): ?><?= $st('reopen') ?><div class="field"><button class="btn secondary small" type="submit"><?= e(t('brr.reopen')) ?></button></div></form><?php endif; ?>
<?php if ($followup): $done = count(array_filter($followup, fn($f) => $f['status'] === 'completed')); ?><h3><?= e(t('brr.followup')) ?> — <?= $done ?>/<?= count($followup) ?></h3>
<div class="table-wrap"><table class="data"><thead><tr><th><?= e(t('brr.f_action')) ?></th><th><?= e(t('brr.f_priority')) ?></th><th><?= e(t('brr.owner')) ?></th><th><?= e(t('brr.deadline')) ?></th><th><?= e(t('common.status')) ?></th></tr></thead><tbody>
<?php foreach ($followup as $f): $late = !in_array($f['status'], ['completed', 'cancelled'], true) && $f['deadline'] < date('Y-m-d'); ?><tr><td><a href="<?= e(url('committees/' . $f['decision_id'])) ?>"><?= e(mb_strimwidth($f['rtitle'], 0, 120, '…')) ?></a></td><td><?= $disp($f, 'priority', 'priority') ?></td><td><?= e($f['full_name']) ?></td><td><?= e(fdate($f['deadline'])) ?><?= $late ? ' <span class="badge danger">' . e(t('brr.late')) . '</span>' : '' ?></td><td><?= status_badge($f['status']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
