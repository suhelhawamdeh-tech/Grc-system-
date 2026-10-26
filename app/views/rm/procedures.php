<?php /** @var array $rows @var array $users */ $tab = 'procedures'; $loc = locale(); $cur = null; ?>
<div class="page-head"><div><h1><?= icon('clipboard') ?> <?= e(t('rm.procedures')) ?></h1><p class="sub"><?= e(t('rm.procedures_hint')) ?></p></div></div>
<?php require __DIR__ . '/_tabs.php'; ?>
<?php foreach ($rows as $p): if ($p['section'] !== $cur): if ($cur !== null): ?></tbody></table></div></section><?php endif; $cur = $p['section']; ?>
<section class="card"><h2><?= e(t('enum.rmsec.' . $cur)) ?></h2><div class="table-wrap"><table class="data"><thead><tr><th><?= e(t('docs.code')) ?></th><th><?= e(t('rm.procedure')) ?></th><th><?= e(t('rm.frequency')) ?></th><th><?= e(t('rm.approver')) ?></th><th><?= e(t('rm.assignee')) ?></th><th><?= e(t('rm.done_tasks')) ?></th></tr></thead><tbody><?php endif; ?>
<tr id="p<?= (int)$p['id'] ?>"<?= $p['is_active'] ? '' : ' class="muted"' ?>><td class="nowrap" dir="ltr"><?= e($p['code']) ?></td><td><?= e($p['title_' . $loc] ?: $p['title_en']) ?><br><span class="muted small"><?= e(t('rm.expected_output')) ?>: <?= e($p['output_' . $loc] ?: $p['output_en']) ?></span>
<?php if (can('rm.approve')): ?><details><summary class="small"><?= e(t('common.edit')) ?></summary><form method="post" action="<?= e(url('rm/procedures/' . $p['id'])) ?>" class="form-grid" novalidate><?= csrf_field() ?>
<?= ff('select', 'frequency', 'rm.frequency', $p['frequency'], ['blank' => false, 'options' => opts(\App\Support\RiskFunction::FREQUENCIES, fn($x) => t('enum.rmfreq.' . $x), null) ?: []]) ?>
<?= ff('select', 'approver', 'rm.approver', $p['approver'], ['blank' => false, 'options' => opts(\App\Support\RiskFunction::APPROVERS, fn($x) => t('enum.rmappr.' . $x), null) ?: []]) ?>
<?= ff('select', 'assignee_user_id', 'rm.assignee', $p['assignee_user_id'] ?? '', ['options' => opts($users, fn($u) => $u['full_name']), 'blank_label' => 'rm.unassigned']) ?>
<?= ff('number', 'due_days', 'rm.due_days', $p['due_days'] ?? '', ['extra' => ' min="0" max="180"', 'hint' => t('rm.due_days_hint')]) ?>
<div class="field"><label class="check"><input type="checkbox" name="is_active" value="1"<?= checked((string)$p['is_active']) ?>> <?= e(t('rm.active')) ?></label></div>
<div class="field"><button class="btn small" type="submit"><?= e(t('common.save')) ?></button></div></form></details><?php endif; ?></td>
<td><?= e(t('enum.rmfreq.' . $p['frequency'])) ?></td><td><?= e(t('enum.rmappr.' . $p['approver'])) ?></td><td><?= e($p['full_name'] ?? '—') ?></td><td><?= (int)$p['done_n'] ?></td></tr>
<?php endforeach; if ($cur !== null): ?></tbody></table></div></section><?php endif; ?>
