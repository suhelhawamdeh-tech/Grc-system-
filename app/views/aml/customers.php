<?php /** @var array $rows @var array $f @var int $total @var int $page @var int $per */
$tab = 'customers'; require __DIR__ . '/_rating.php'; $today = date('Y-m-d'); ?>
<div class="page-head"><div><h1><?= icon('users') ?> <?= e(t('aml.customers')) ?></h1><p class="sub"><?= e(t('aml.customers_hint')) ?> · <?= e(t('common.total_n', ['n' => $total])) ?></p></div>
  <div class="actions"><a class="btn secondary" href="<?= e(url('aml/customers/export') . qs()) ?>"><?= e(t('aml.export')) ?></a><?php if (can('aml.manage')): ?><a class="btn gold" href="<?= e(url('aml/customers/new')) ?>"><?= e(t('aml.customer_new')) ?></a><?php endif; ?></div></div>
<?php require __DIR__ . '/_tabs.php'; ?>
<form method="get" action="<?= e(url('aml/customers')) ?>" class="card filters">
<?= ff('text', 'q', 'common.search', $f['q']) ?>
<?= ff('select', 'rating', 'aml.rating', $f['rating'], ['options' => opts(['high', 'medium', 'low'], fn($x) => t('enum.amlr.' . $x), null), 'blank_label' => 'common.all']) ?>
<?= ff('select', 'source', 'aml.source', $f['source'], ['options' => ['manual' => t('enum.amlsrc.manual'), 't24' => t('enum.amlsrc.t24')], 'blank_label' => 'common.all']) ?>
<?= ff('select', 'status', 'aml.status', $f['status'], ['blank' => false, 'options' => ['active' => t('enum.amlst.active'), 'exited' => t('enum.amlst.exited')]]) ?>
<div class="field"><label class="check"><input type="checkbox" name="pep" value="1"<?= checked($f['pep']) ?>> <?= e(t('aml.pep_only')) ?></label><label class="check"><input type="checkbox" name="sanctions" value="1"<?= checked($f['sanctions']) ?>> <?= e(t('aml.sanctions_only')) ?></label><label class="check"><input type="checkbox" name="review" value="1"<?= checked($f['review']) ?>> <?= e(t('aml.review_overdue_only')) ?></label></div>
<div class="field actions"><button class="btn" type="submit"><?= e(t('common.filter')) ?></button><a class="btn secondary" href="<?= e(url('aml/customers')) ?>"><?= e(t('common.reset')) ?></a></div></form>
<div class="table-wrap"><table class="data"><thead><tr><th><?= e(t('aml.customer_ref')) ?></th><th><?= e(t('aml.full_name')) ?></th><th><?= e(t('aml.customer_type')) ?></th><th><?= e(t('aml.geography')) ?></th><th><?= e(t('aml.score')) ?></th><th><?= e(t('aml.rating')) ?></th><th><?= e(t('aml.cdd')) ?></th><th><?= e(t('aml.next_review')) ?></th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="8" class="empty"><?= icon('users') ?><br><?= e(t('common.no_results')) ?></td></tr><?php endif; ?>
<?php foreach ($rows as $r): $late = $r['next_review_due'] < $today; ?><tr><td class="nowrap code"><a href="<?= e(url('aml/customers/' . $r['id'])) ?>"><?= e($r['customer_ref']) ?></a></td>
<td><a href="<?= e(url('aml/customers/' . $r['id'])) ?>"><?= e($r['full_name']) ?></a><?= (int)$r['is_pep'] ? ' <span class="badge warn">PEP</span>' : '' ?><?= (int)$r['sanctions_match'] ? ' <span class="badge danger">' . e(t('aml.sanctions_flag')) . '</span>' : '' ?></td>
<td><?= e(lname($r, 'type')) ?></td><td><?= e(lname($r, 'geo')) ?></td><td><span class="meter <?= e($r['rating']) ?>"><span class="track"><span class="fill" style="width:<?= (int)round(((float)$r['score'] - 1) / 2 * 100) ?>%"></span></span><b><?= e(number_format((float)$r['score'], 2)) ?></b></span></td>
<td><?= $amlBadge($r['rating']) ?><?= $r['override_rating'] ? ' <span class="muted small">' . e(t('aml.overridden')) . '</span>' : '' ?></td><td><?= e(t('enum.cdd.' . $r['cdd_level'])) ?></td>
<td class="nowrap"><?= e(fdate($r['next_review_due'])) ?><?= $late ? ' <span class="badge danger">' . e(t('my.overdue')) . '</span>' : '' ?></td></tr><?php endforeach; ?></tbody></table></div>
<?= pager($total, $page, $per) ?>
