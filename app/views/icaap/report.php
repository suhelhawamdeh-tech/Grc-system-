<?php /** @var array $x @var array $alerts */ $c = $x['c']; require __DIR__ . '/_tabs.php'; $edit = can('icaap.manage') && !$locked; $nar = \App\Support\Icaap::data()['narratives']; ?>
<div class="card notice"><p><?= e(t('icaap.report_hint')) ?></p></div>
<form method="post" action="<?= e(url('icaap/' . $c['id'] . '/report')) ?>" novalidate><?= csrf_field() ?>
<?php foreach ($nar as $sec => $keys): if ($sec === 9) continue; ?><section class="card"><h2><?= (int)$sec ?>. <?= e(t(sprintf('icaap.s%d', $sec))) ?></h2><div class="form-grid">
<?php foreach ($keys as $k): ?><?= ff('textarea', $k, 'icaap.' . $k, $c['narr'][$k] ?? '', ['wide' => true, 'extra' => ' rows="3"' . ($edit ? '' : ' readonly')]) ?><?php endforeach; ?></div></section><?php endforeach; ?>
<p class="muted small"><?= e(t('icaap.s9_where')) ?></p>
<?php if ($edit): ?><div class="actions"><button class="btn" type="submit"><?= e(t('common.save')) ?></button></div><?php endif; ?></form>
<section class="card" id="approval"><h2><?= e(t('icaap.s16')) ?></h2><p class="muted"><?= e(t('icaap.declaration_text')) ?></p><dl class="details">
<dt><?= e(t('icaap.declared')) ?></dt><dd><?= $c['declared_at'] ? e(fdatetime($c['declared_at'])) : '—' ?></dd>
<dt><?= e(t('icaap.committee_meeting')) ?></dt><dd><?= e(trim(($c['committee_meeting'] ?? '—') . ' ' . fdate($c['committee_date']))) ?></dd>
<dt><?= e(t('icaap.board_decision')) ?></dt><dd><?= e(trim(($c['board_decision'] ?? '—') . ' ' . fdate($c['board_date']))) ?></dd>
<dt><?= e(t('icaap.submitted_on')) ?></dt><dd><?= e(fdate($c['submitted_on']) ?: '—') ?></dd></dl>
<?php if (can('icaap.manage')): ?>
<?php if (!$c['declared_at']): ?><form method="post" action="<?= e(url('icaap/' . $c['id'] . '/approval')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="step" value="declare"><?php if ($alerts): ?><p class="badge warn"><?= e(t('icaap.declare_with_alerts')) ?></p> <?php endif; ?><button class="btn gold" type="submit"><?= e(t('icaap.declare')) ?></button></form>
<?php elseif (!$c['committee_date']): ?><form method="post" action="<?= e(url('icaap/' . $c['id'] . '/approval')) ?>" class="form-grid" novalidate><?= csrf_field() ?><input type="hidden" name="step" value="committee"><?= ff('text', 'committee_meeting', 'icaap.committee_meeting', '', ['required' => true]) ?><?= ff('date', 'committee_date', 'icaap.committee_date', date('Y-m-d'), ['required' => true]) ?><div class="field"><button class="btn gold" type="submit"><?= e(t('icaap.record_committee')) ?></button></div></form>
<?php elseif (!$c['board_date']): ?><form method="post" action="<?= e(url('icaap/' . $c['id'] . '/approval')) ?>" class="form-grid" novalidate><?= csrf_field() ?><input type="hidden" name="step" value="board"><?= ff('text', 'board_decision', 'icaap.board_decision', '', ['required' => true]) ?><?= ff('date', 'board_date', 'icaap.board_date', date('Y-m-d'), ['required' => true]) ?><div class="field"><button class="btn gold" type="submit"><?= e(t('icaap.record_board')) ?></button></div></form>
<?php elseif (!$c['submitted_on']): ?><form method="post" action="<?= e(url('icaap/' . $c['id'] . '/approval')) ?>" class="form-grid" novalidate><?= csrf_field() ?><input type="hidden" name="step" value="submit"><?= ff('date', 'submitted_on', 'icaap.submitted_on', date('Y-m-d'), ['required' => true]) ?><div class="field"><button class="btn gold" type="submit"><?= e(t('icaap.record_submission')) ?></button></div></form>
<?php elseif ($c['status'] === 'submitted'): ?><form method="post" action="<?= e(url('icaap/' . $c['id'] . '/approval')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="step" value="close"><button class="btn secondary" type="submit"><?= e(t('icaap.close_cycle')) ?></button></form><?php endif; ?>
<?php endif; ?></section>
