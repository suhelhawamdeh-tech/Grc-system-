<?php $lvL = \App\Support\Ref::levels('likelihood'); $lvI = \App\Support\Ref::levels('impact'); ?>
<h2><?= e($r['risk_code']) ?> — <?= e($r['title']) ?></h2>
<dl class="details">
  <dt><?= e(t('risk.category')) ?></dt><dd><?= e(lname($r, 'cat')) ?></dd>
  <dt><?= e(t('risk.department')) ?> / <?= e(t('risk.branch')) ?></dt><dd><?= e(lname($r, 'dept')) ?> / <?= e(lname($r, 'branch') ?: '—') ?></dd>
  <dt><?= e(t('risk.owner')) ?></dt><dd><?= e($r['owner_name']) ?></dd>
  <dt><?= e(t('risk.identified_date')) ?> / <?= e(t('risk.review_date')) ?></dt><dd><?= e(fdate($r['identified_date'])) ?> / <?= e(fdate($r['review_date']) ?: '—') ?></dd>
  <dt><?= e(t('risk.status')) ?></dt><dd><?= e(enum_label('risk_status', $r['status'])) ?></dd>
  <dt><?= e(t('risk.description')) ?></dt><dd class="pre"><?= e($r['description']) ?></dd>
  <dt><?= e(t('risk.causes')) ?></dt><dd class="pre"><?= e($r['causes'] ?? '—') ?></dd>
  <dt><?= e(t('risk.consequences')) ?></dt><dd class="pre"><?= e($r['consequences'] ?? '—') ?></dd>
  <dt><?= e(t('risk.inherent')) ?></dt><dd><?= (int)$r['inh_likelihood'] ?> × <?= (int)$r['inh_impact'] ?> = <?= band_chip((int)$r['inh_score']) ?></dd>
  <dt><?= e(t('risk.controls')) ?></dt><dd class="pre"><?= e($r['controls_description'] ?? '—') ?> (<?= e(enum_label('control', $r['control_assessment'])) ?>)</dd>
  <dt><?= e(t('risk.residual')) ?></dt><dd><?= (int)$r['res_likelihood'] ?> × <?= (int)$r['res_impact'] ?> = <?= band_chip((int)$r['res_score']) ?></dd>
  <dt><?= e(t('risk.res_rationale')) ?></dt><dd class="pre"><?= e($r['res_rationale'] ?? '—') ?></dd>
  <dt><?= e(t('risk.treatment')) ?></dt><dd><?= e(enum_label('treatment', $r['treatment_decision'])) ?> <?= e($r['treatment_notes'] ?? '') ?></dd>
</dl>
<h3><?= e(t('action.title_plural')) ?></h3>
<table class="data"><thead><tr><th><?= e(t('action.title')) ?></th><th><?= e(t('action.owner')) ?></th><th><?= e(t('action.deadline')) ?></th><th><?= e(t('action.status')) ?></th></tr></thead><tbody>
<?php foreach ($actions as $a): ?><tr><td><?= e($a['title']) ?></td><td><?= e($a['owner_name']) ?></td><td><?= e($a['deadline']) ?></td><td><?= e(enum_label('action_status', $a['status'])) ?></td></tr><?php endforeach; ?>
<?php if (!$actions): ?><tr><td colspan="4" class="muted"><?= e(t('action.none')) ?></td></tr><?php endif; ?></tbody></table>
<p class="print-meta"><?= e(t('scales.print_note')) ?></p>
