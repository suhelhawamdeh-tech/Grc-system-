<div class="page-head"><div><h1><?= e(t('comp.title_plural')) ?></h1><p class="sub"><?= e(t('comp.subtitle')) ?> · <?= e(t('common.total_n', ['n' => $total])) ?></p></div>
<div class="actions"><?php if (can('compliance.manage')): ?><a class="btn gold" href="<?= e(url('compliance/new')) ?>"><?= e(t('comp.new')) ?></a><?php endif; ?>
<?php if (can('export.run')): ?><a class="btn secondary" href="<?= e(url('compliance/export') . qs(['page' => null])) ?>">CSV</a><a class="btn secondary" href="<?= e(url('compliance/export') . qs(['page' => null, 'format' => 'xlsx'])) ?>">Excel</a><a class="btn secondary" href="<?= e(url('compliance/export') . qs(['page' => null, 'sep' => ';'])) ?>">CSV (;)</a><?php endif; ?></div></div>
<div class="msg info"><?= e(t('comp.disclaimer')) ?></div>
<form method="get" action="<?= e(url('compliance')) ?>" class="card filters" role="search">
<?= ff('text', 'q', 'common.search', $f['q']) ?>
<?= ff('select', 'status', 'comp.status', $f['status'], ['options' => opts(['not_started', 'in_progress', 'compliant', 'non_compliant', 'not_applicable'], fn($x) => t('enum.obl_status.' . $x), null) ?: [], 'blank_label' => 'common.all']) ?>
<?= ff('select', 'department', 'comp.department', $f['department'], ['options' => opts($departments, fn($d) => lname($d)), 'blank_label' => 'common.all']) ?>
<div class="field"><label class="check"><input type="checkbox" name="overdue" value="1"<?= checked($f['overdue']) ?>> <?= e(t('comp.overdue_only')) ?></label></div>
<div class="field actions"><button class="btn" type="submit"><?= e(t('common.filter')) ?></button><a class="btn secondary" href="<?= e(url('compliance')) ?>"><?= e(t('common.reset')) ?></a></div></form>
<div class="table-wrap"><table class="data"><thead><tr><th><?= e(t('comp.id')) ?></th><th><?= e(t('comp.name')) ?></th><th><?= e(t('comp.source')) ?></th><th><?= e(t('comp.department')) ?></th><th><?= e(t('comp.owner')) ?></th><th><?= e(t('comp.due_date')) ?></th><th><?= e(t('comp.status')) ?></th><th><?= e(t('comp.evidence')) ?></th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="8" class="muted"><?= e(t('common.no_results')) ?></td></tr><?php endif; ?>
<?php foreach ($rows as $o): $late = in_array($o['status'], ['not_started', 'in_progress', 'non_compliant'], true) && $o['due_date'] < date('Y-m-d'); ?>
<tr><td class="nowrap"><a href="<?= e(url('compliance/' . $o['id'])) ?>"><?= e($o['code']) ?></a></td><td><a href="<?= e(url('compliance/' . $o['id'])) ?>"><?= e($o['title']) ?></a></td><td><?= e($o['source']) ?></td><td><?= e(lname($o, 'dept')) ?></td><td><?= e($o['owner_name']) ?></td>
<td class="nowrap"><?= e($o['due_date']) ?><?= $late ? ' <span class="badge danger">' . e(t('risk.overdue')) . '</span>' : '' ?></td><td><?= status_badge($o['status']) ?></td><td class="num"><?= (int)$o['evidence_count'] ?></td></tr><?php endforeach; ?></tbody></table></div>
<?= pager($total, $page, $per) ?>
<?= kb_refs('compliance') ?>
