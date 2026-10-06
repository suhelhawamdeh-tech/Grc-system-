<?php /** @var array $rows @var array $f @var int $total @var int $page @var int $per @var array $departments @var array $stats */
$crit = static fn(string $c) => '<span class="badge ' . ($c === 'critical' ? 'danger' : ($c === 'high' ? 'warn' : '')) . '">' . e(t('enum.crit.' . $c)) . '</span>'; ?>
<div class="page-head"><div><h1><?= icon('layers') ?> <?= e(t('bia.title')) ?></h1><p class="sub"><?= e(t('bia.subtitle')) ?> · <?= e(t('common.total_n', ['n' => $total])) ?></p></div>
  <div class="actions"><a class="btn secondary" href="<?= e(url('bia/systems')) ?>"><?= e(t('bia.systems')) ?></a><a class="btn secondary" href="<?= e(url('bia/report')) ?>"><?= e(t('bia.report')) ?></a><?php if (can('bia.manage')): ?><a class="btn gold" href="<?= e(url('bia/new')) ?>"><?= e(t('bia.new')) ?></a><?php endif; ?></div></div>
<div class="kpis">
  <a class="kpi" href="<?= e(url('bia')) ?>"><div class="v"><?= (int)$stats['total'] ?></div><div class="l"><?= e(t('bia.stat_total')) ?></div></a>
  <a class="kpi" href="<?= e(url('bia', ['criticality' => 'critical'])) ?>"><div class="v"><?= (int)$stats['crit'] ?></div><div class="l"><?= e(t('bia.stat_critical')) ?></div></a>
  <a class="kpi <?= (int)$stats['unapproved'] ? 'alert' : '' ?>" href="<?= e(url('bia', ['status' => 'draft'])) ?>"><div class="v"><?= (int)$stats['unapproved'] ?></div><div class="l"><?= e(t('bia.stat_unapproved')) ?></div></a>
  <a class="kpi <?= (int)$stats['gaps'] ? 'alert' : '' ?>" href="<?= e(url('bia', ['gap' => 1])) ?>"><div class="v"><?= (int)$stats['gaps'] ?></div><div class="l"><?= e(t('bia.stat_gaps')) ?></div></a>
  <a class="kpi <?= (int)$stats['review'] ? 'alert' : '' ?>" href="<?= e(url('bia', ['review' => 1])) ?>"><div class="v"><?= (int)$stats['review'] ?></div><div class="l"><?= e(t('bia.stat_review')) ?></div></a>
</div>
<form method="get" action="<?= e(url('bia')) ?>" class="card filters">
  <?= ff('text', 'q', 'common.search', $f['q']) ?>
  <?= ff('select', 'department', 'bia.department', $f['department'], ['options' => opts($departments, fn($d) => lname($d)), 'blank_label' => 'common.all']) ?>
  <?= ff('select', 'criticality', 'bia.criticality', $f['criticality'], ['options' => opts(\App\Controllers\BiaController::CRIT, fn($x) => t('enum.crit.' . $x), null), 'blank_label' => 'common.all']) ?>
  <?= ff('select', 'status', 'bia.status', $f['status'], ['options' => opts(['draft', 'pending_approval', 'approved'], fn($x) => t('enum.bstatus.' . $x), null), 'blank_label' => 'common.all']) ?>
  <div class="field"><label class="check"><input type="checkbox" name="gap" value="1"<?= checked($f['gap']) ?>> <?= e(t('bia.stat_gaps')) ?></label></div>
  <div class="field actions"><button class="btn" type="submit"><?= e(t('common.filter')) ?></button><a class="btn secondary" href="<?= e(url('bia')) ?>"><?= e(t('common.reset')) ?></a></div></form>
<div class="table-wrap"><table class="data"><thead><tr><th><?= e(t('bia.id')) ?></th><th><?= e(t('bia.name')) ?></th><th><?= e(t('bia.department')) ?></th><th><?= e(t('bia.criticality')) ?></th><th class="num"><?= e(t('bia.mtpd_hours')) ?></th><th class="num"><?= e(t('bia.rto_hours')) ?></th><th class="num"><?= e(t('bia.rpo_hours')) ?></th><th><?= e(t('bia.status')) ?></th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="8" class="empty"><?= icon('layers') ?><br><?= e(t('common.no_results')) ?></td></tr><?php endif; ?>
<?php foreach ($rows as $p): ?><tr><td class="nowrap"><a href="<?= e(url('bia/' . $p['id'])) ?>"><?= e($p['code']) ?></a></td><td><a href="<?= e(url('bia/' . $p['id'])) ?>"><?= e($p['name']) ?></a><?= $p['has_gap'] ? ' <span class="badge danger">' . e(t('bia.gap_badge')) . '</span>' : '' ?></td><td><?= e(lname($p, 'dept')) ?></td><td><?= $crit($p['criticality']) ?></td>
<td class="num"><?= e(\App\Support\Bia::fmt($p['mtpd_hours'])) ?></td><td class="num"><?= e(\App\Support\Bia::fmt($p['rto_hours'])) ?></td><td class="num"><?= e(\App\Support\Bia::fmt($p['rpo_hours'])) ?></td>
<td><span class="badge <?= $p['status'] === 'approved' ? 'ok' : ($p['status'] === 'pending_approval' ? 'warn' : '') ?>"><?= e(t('enum.bstatus.' . $p['status'])) ?></span></td></tr><?php endforeach; ?></tbody></table></div>
<?= pager($total, $page, $per) ?>
