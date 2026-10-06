<?php /** @var array $rows @var array $f @var int $total @var int $page @var int $per @var array $departments @var array $stats */
$res = static fn(?string $r) => $r ? '<span class="badge ' . ($r === 'effective' ? 'ok' : ($r === 'ineffective' ? 'danger' : 'warn')) . '">' . e(t('enum.rating.' . $r)) . '</span>' : '<span class="badge">' . e(t('ctl.never_tested')) . '</span>'; ?>
<div class="page-head"><div><h1><?= icon('flask') ?> <?= e(t('ctl.title_plural')) ?></h1><p class="sub"><?= e(t('ctl.subtitle')) ?> · <?= e(t('common.total_n', ['n' => $total])) ?></p></div>
  <div class="actions"><?php if (can('control.manage')): ?><a class="btn gold" href="<?= e(url('controls/new')) ?>"><?= e(t('ctl.new')) ?></a><?php endif; ?></div></div>
<div class="kpis">
  <a class="kpi" href="<?= e(url('controls')) ?>"><div class="v"><?= (int)$stats['total'] ?></div><div class="l"><?= e(t('ctl.stat_total')) ?></div></a>
  <a class="kpi <?= (int)$stats['untested'] ? 'alert' : '' ?>" href="<?= e(url('controls', ['state' => 'untested'])) ?>"><div class="v"><?= (int)$stats['untested'] ?></div><div class="l"><?= e(t('ctl.stat_untested')) ?></div></a>
  <a class="kpi <?= (int)$stats['overdue'] ? 'alert' : '' ?>" href="<?= e(url('controls', ['state' => 'overdue'])) ?>"><div class="v"><?= (int)$stats['overdue'] ?></div><div class="l"><?= e(t('ctl.stat_overdue')) ?></div></a>
  <a class="kpi <?= (int)$stats['weak'] ? 'alert' : '' ?>" href="<?= e(url('controls', ['state' => 'ineffective'])) ?>"><div class="v"><?= (int)$stats['weak'] ?></div><div class="l"><?= e(t('ctl.stat_weak')) ?></div></a>
</div>
<form method="get" action="<?= e(url('controls')) ?>" class="card filters">
  <?= ff('text', 'q', 'common.search', $f['q']) ?>
  <?= ff('select', 'department', 'ctl.department', $f['department'], ['options' => opts($departments, fn($d) => lname($d)), 'blank_label' => 'common.all']) ?>
  <?= ff('select', 'type', 'ctl.type', $f['type'], ['options' => opts(\App\Controllers\ControlController::TYPES, fn($x) => t('enum.ctype.' . $x), null), 'blank_label' => 'common.all']) ?>
  <?= ff('select', 'state', 'ctl.state', $f['state'], ['options' => ['untested' => t('ctl.stat_untested'), 'overdue' => t('ctl.stat_overdue'), 'ineffective' => t('ctl.stat_weak'), 'unlinked' => t('ctl.unlinked')], 'blank_label' => 'common.all']) ?>
  <div class="field"><label class="check"><input type="checkbox" name="key" value="1"<?= checked($f['key']) ?>> <?= e(t('ctl.is_key')) ?></label></div>
  <div class="field actions"><button class="btn" type="submit"><?= e(t('common.filter')) ?></button><a class="btn secondary" href="<?= e(url('controls')) ?>"><?= e(t('common.reset')) ?></a></div>
</form>
<div class="table-wrap"><table class="data"><thead><tr><th><?= e(t('ctl.id')) ?></th><th><?= e(t('ctl.name')) ?></th><th><?= e(t('ctl.department')) ?></th><th><?= e(t('ctl.owner')) ?></th><th><?= e(t('ctl.type')) ?></th><th class="num"><?= e(t('ctl.risks')) ?></th><th><?= e(t('ctl.last_result')) ?></th><th><?= e(t('ctl.next_test')) ?></th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="8" class="empty"><?= icon('flask') ?><br><?= e(t('common.no_results')) ?></td></tr><?php endif; ?>
<?php foreach ($rows as $c): $late = $c['next_test_due'] && $c['next_test_due'] < date('Y-m-d'); ?>
<tr><td class="nowrap"><a href="<?= e(url('controls/' . $c['id'])) ?>"><?= e($c['code']) ?></a></td>
<td><a href="<?= e(url('controls/' . $c['id'])) ?>"><?= e($c['name']) ?></a><?= $c['is_key'] ? ' <span class="badge islamic">' . e(t('ctl.key_badge')) . '</span>' : '' ?><?= $c['archived_at'] ? ' <span class="badge">' . e(t('common.archived')) . '</span>' : '' ?></td>
<td><?= e(lname($c, 'dept')) ?></td><td><?= e($c['owner_name']) ?></td><td><?= e(t('enum.ctype.' . $c['control_type'])) ?></td><td class="num"><?= (int)$c['risk_count'] ?></td>
<td><?= $res($c['last_result']) ?></td><td class="nowrap"><?= e(fdate($c['next_test_due']) ?: '—') ?><?= $late && !$c['archived_at'] ? ' <span class="badge danger">' . e(t('my.overdue')) . '</span>' : '' ?></td></tr>
<?php endforeach; ?></tbody></table></div>
<?= pager($total, $page, $per) ?>
