<?php /** @var array $rows @var array $f */ $tab = 'reports'; $today = date('Y-m-d'); ?>
<div class="page-head"><div><h1><?= icon('file') ?> <?= e(t('aml.reports')) ?></h1><p class="sub"><?= e(t('aml.reports_hint')) ?></p></div>
  <div class="actions"><?php if (can('aml.manage')): ?><a class="btn gold" href="<?= e(url('aml/reports/new')) ?>"><?= e(t('aml.report_new')) ?></a><?php endif; ?></div></div>
<?php require __DIR__ . '/_tabs.php'; ?>
<?php if (!can('aml.manage')): ?><div class="card notice"><p><?= e(t('aml.str_hidden')) ?></p></div><?php endif; ?>
<form method="get" action="<?= e(url('aml/reports')) ?>" class="card filters">
<?= ff('select', 'type', 'aml.report_type', $f['type'], ['options' => opts(['str', 'ctr', 'periodic', 'ewra', 'other'], fn($x) => t('enum.amlrt.' . $x), null), 'blank_label' => 'common.all']) ?>
<?= ff('select', 'status', 'aml.status', $f['status'], ['options' => ['draft' => t('enum.amlrs.draft'), 'submitted' => t('enum.amlrs.submitted')], 'blank_label' => 'common.all']) ?>
<div class="field"><label class="check"><input type="checkbox" name="overdue" value="1"<?= checked($f['overdue']) ?>> <?= e(t('aml.overdue_only')) ?></label></div>
<div class="field actions"><button class="btn" type="submit"><?= e(t('common.filter')) ?></button><a class="btn secondary" href="<?= e(url('aml/reports')) ?>"><?= e(t('common.reset')) ?></a></div></form>
<div class="table-wrap"><table class="data"><thead><tr><th><?= e(t('aml.code')) ?></th><th><?= e(t('aml.r_title')) ?></th><th><?= e(t('aml.report_type')) ?></th><th><?= e(t('aml.recipient')) ?></th><th><?= e(t('aml.r_due')) ?></th><th><?= e(t('aml.status')) ?></th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="6" class="empty"><?= icon('file') ?><br><?= e(t('common.no_results')) ?></td></tr><?php endif; ?>
<?php foreach ($rows as $r): $late = $r['status'] === 'draft' && $r['due_date'] < $today; ?><tr><td class="nowrap"><a href="<?= e(url('aml/reports/' . $r['id'])) ?>"><?= e($r['code']) ?></a></td><td><a href="<?= e(url('aml/reports/' . $r['id'])) ?>"><?= e($r['title']) ?></a><?= (int)$r['confidential'] ? ' <span class="badge danger">' . e(t('aml.confidential')) . '</span>' : '' ?></td>
<td><?= e(t('enum.amlrt.' . $r['report_type'])) ?></td><td><?= e($r['recipient']) ?></td><td class="nowrap"><?= e(fdate($r['due_date'])) ?><?= $late ? ' <span class="badge danger">' . e(t('my.overdue')) . '</span>' : '' ?></td>
<td><span class="badge <?= $r['status'] === 'submitted' ? 'ok' : 'warn' ?>"><?= e(t('enum.amlrs.' . $r['status'])) ?></span><?= $r['submitted_on'] ? ' <span class="muted small">' . e(fdate($r['submitted_on'])) . '</span>' : '' ?></td></tr><?php endforeach; ?></tbody></table></div>
