<?php /** @var array $x @var array $alerts @var array $late @var array $esc */ $d = \App\Support\Icaap::data(); $loc = locale(); $ar = $loc === 'ar'; $today = date('Y-m-d');
require __DIR__ . '/_tabs.php'; require __DIR__ . '/_summary.php'; ?>
<?php if ($alerts): ?><section class="card notice"><h2><?= icon('alert') ?> <?= e(t('icaap.alerts')) ?></h2><ul><?php foreach ($alerts as $a): ?><li><?= e(t(sprintf('icaap.alert_%s', $a))) ?></li><?php endforeach; ?></ul>
<?php if ($esc): ?><p><?= e(t('rm.escalation')) ?>: <?php foreach ($esc as $e1): ?><a href="<?= e(url('rm/escalations/' . $e1['id'])) ?>" dir="ltr"><?= e($e1['code']) ?></a> <span class="badge"><?= e(t('enum.rmes.' . $e1['status'])) ?></span> <?php endforeach; ?></p><?php endif; ?></section><?php endif; ?>
<section class="card"><h2><?= icon('tasks') ?> <?= e(t('icaap.phases')) ?></h2><p class="muted"><?= e(t('icaap.phases_hint')) ?></p>
<?php foreach ($x['phases'] as $p): $no = (int)$p['phase_no']; $ph = $d['phases'][$no]; $done = array_filter(explode(',', (string)$p['checklist']), 'strlen'); $late1 = $p['end_date'] && $p['end_date'] < $today && $p['status'] !== 'complete'; ?>
<details class="phase" id="phase<?= $no ?>"<?= $p['status'] === 'in_progress' ? ' open' : '' ?>><summary><strong><?= $no ?>. <?= e($ar ? $ph[0] : $ph[1]) ?></strong> <span class="muted small"><?= e($ar ? $ph[2] : $ph[3]) ?> · <?= e(t('icaap.weeks', ['w' => $ph[4]])) ?></span>
<span class="badge <?= $p['status'] === 'complete' ? 'ok' : ($p['status'] === 'in_progress' ? 'warn' : '') ?>"><?= e(t('enum.icph.' . $p['status'])) ?></span><?= $late1 ? ' <span class="badge danger">' . e(t('my.overdue')) . '</span>' : '' ?> <span class="muted small"><?= count($done) ?>/<?= count($ph[5]) ?></span></summary>
<form method="post" action="<?= e(url('icaap/' . $x['c']['id'] . '/phases/' . $no)) ?>" class="form-grid" novalidate><?= csrf_field() ?>
<?= ff('date', 'start_date', 'icaap.start', $p['start_date'] ?? '') ?><?= ff('date', 'end_date', 'icaap.end', $p['end_date'] ?? '') ?>
<?= ff('select', 'status', 'pol.status', $p['status'], ['blank' => false, 'options' => ['not_started' => t('enum.icph.not_started'), 'in_progress' => t('enum.icph.in_progress'), 'complete' => t('enum.icph.complete')]]) ?>
<fieldset class="field wide"><legend><?= e(t('icaap.checklist')) ?></legend><?php foreach ($ph[5] as $i => $item): ?><label class="check"><input type="checkbox" name="check[]" value="<?= $i ?>"<?= in_array((string)$i, $done, true) ? ' checked' : '' ?>> <?= e($ar ? $item[0] : $item[1]) ?></label><br><?php endforeach; ?></fieldset>
<?= ff('textarea', 'note', 'rm.note', $p['note'] ?? '', ['wide' => true]) ?>
<?php if (can('icaap.manage') && !$locked): ?><div class="field"><button class="btn small" type="submit"><?= e(t('common.save')) ?></button></div><?php endif; ?></form></details>
<?php endforeach; ?></section>
<?php if ($late): ?><section class="card"><h2><?= e(t('icaap.late_data')) ?></h2><ul><?php foreach ($late as $l): ?><li><?= e($l['provider']) ?> — <?= e(fdate($l['due_on'])) ?></li><?php endforeach; ?></ul><p class="muted small"><?= e(t('icaap.late_hint')) ?></p></section><?php endif; ?>
