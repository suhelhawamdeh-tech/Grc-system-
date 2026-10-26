<?php /** @var array $rows @var array $f @var int $total @var int $page @var int $per */ ?>
<div class="page-head"><div><h1><?= icon('book') ?> <?= e(t('sha.rulings')) ?></h1><p class="sub"><?= e(t('sha.rulings_hint')) ?> · <?= e(t('common.total_n', ['n' => $total])) ?></p></div>
  <div class="actions"><a class="btn secondary" href="<?= e(url('sharia')) ?>"><?= e(t('common.back')) ?></a><?php if (can('sharia.manage')): ?><a class="btn gold" href="<?= e(url('sharia/rulings/new')) ?>"><?= e(t('sha.ruling_new')) ?></a><?php endif; ?></div></div>
<form method="get" action="<?= e(url('sharia/rulings')) ?>" class="card filters">
  <?= ff('text', 'q', 'common.search', $f['q']) ?>
  <?= ff('select', 'type', 'sha.r_type', $f['type'], ['options' => opts(\App\Controllers\ShariaController::RULING_TYPES, fn($x) => t('enum.rtype.' . $x), null), 'blank_label' => 'common.all']) ?>
  <?= ff('select', 'status', 'sha.r_status', $f['status'], ['options' => opts(\App\Controllers\ShariaController::RULING_STATUS, fn($x) => t('enum.rstatus.' . $x), null), 'blank_label' => 'common.all']) ?>
  <div class="field actions"><button class="btn" type="submit"><?= e(t('common.filter')) ?></button><a class="btn secondary" href="<?= e(url('sharia/rulings')) ?>"><?= e(t('common.reset')) ?></a></div></form>
<div class="table-wrap"><table class="data"><thead><tr><th><?= e(t('sha.e_code')) ?></th><th><?= e(t('sha.r_title')) ?></th><th><?= e(t('sha.r_subject')) ?></th><th><?= e(t('sha.r_type')) ?></th><th><?= e(t('sha.r_issued')) ?></th><th><?= e(t('sha.r_status')) ?></th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="6" class="empty"><?= e(t('common.no_results')) ?></td></tr><?php endif; ?>
<?php foreach ($rows as $r): ?><tr><td class="nowrap"><a href="<?= e(url('sharia/rulings/' . $r['id'])) ?>"><?= e($r['code']) ?></a></td><td><a href="<?= e(url('sharia/rulings/' . $r['id'])) ?>"><?= e($r['title']) ?></a></td><td><?= e($r['subject']) ?></td><td><?= e(t('enum.rtype.' . $r['ruling_type'])) ?></td><td class="nowrap"><?= e(fdate($r['issued_on'])) ?></td><td><span class="badge <?= $r['status'] === 'active' ? 'ok' : '' ?>"><?= e(t('enum.rstatus.' . $r['status'])) ?></span></td></tr><?php endforeach; ?></tbody></table></div>
<?= pager($total, $page, $per) ?>
