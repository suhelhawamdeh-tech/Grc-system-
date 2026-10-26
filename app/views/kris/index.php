<div class="page-head"><div><h1><?= e(t('kri.title_plural')) ?></h1><p class="sub"><?= e(t('kri.subtitle')) ?> · <?= e(t('common.total_n', ['n' => $total])) ?></p></div>
  <div class="actions"><?php if (can('kri.manage')): ?><a class="btn gold" href="<?= e(url('kris/new')) ?>"><?= e(t('kri.new')) ?></a><?php endif; ?></div></div>
<form method="get" action="<?= e(url('kris')) ?>" class="card filters">
  <?= ff('select', 'department', 'kri.department', $f['department'], ['options' => opts($departments, fn($d) => lname($d)), 'blank_label' => 'common.all']) ?>
  <?= ff('select', 'status', 'kri.last_status', $f['status'], ['options' => ['green' => t('enum.status.green'), 'amber' => t('enum.status.amber'), 'red' => t('enum.status.red'), 'critical' => t('enum.status.critical')], 'blank_label' => 'common.all']) ?>
  <div class="field"><label class="check"><input type="checkbox" name="alert" value="1"<?= checked($f['alert']) ?>> <?= e(t('kri.alerts_only')) ?></label></div>
  <div class="field"><label class="check"><input type="checkbox" name="missing" value="1"<?= checked($f['missing']) ?>> <?= e(t('kri.missing_only')) ?></label></div>
  <div class="field actions"><button class="btn" type="submit"><?= e(t('common.filter')) ?></button><a class="btn secondary" href="<?= e(url('kris')) ?>"><?= e(t('common.reset')) ?></a></div>
</form>
<div class="table-wrap"><table class="data"><thead><tr><th><?= e(t('kri.id')) ?></th><th><?= e(t('kri.name')) ?></th><th><?= e(t('kri.department')) ?></th><th><?= e(t('kri.owner')) ?></th><th class="num"><?= e(t('kri.last_value')) ?></th><th><?= e(t('kri.last_status')) ?></th><th><?= e(t('kri.thresholds')) ?></th><th><?= e(t('kri.frequency')) ?></th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="8" class="muted"><?= e(t('common.no_results')) ?></td></tr><?php endif; ?>
<?php foreach ($rows as $k): $stale = !$k['last_date'] || $k['last_date'] < \App\Support\Kri::cycleStart($k['frequency']); ?>
<tr><td class="nowrap"><a href="<?= e(url('kris/' . $k['id'])) ?>"><?= e($k['code']) ?></a></td><td><a href="<?= e(url('kris/' . $k['id'])) ?>"><?= e($k['name']) ?></a><?= $k['is_active'] ? '' : ' <span class="badge">' . e(t('common.inactive')) . '</span>' ?></td><td><?= e(lname($k, 'dept')) ?></td><td><?= e($k['owner_name']) ?></td>
<td class="num"><?= $k['last_date'] ? e(\App\Core\Money::plain($k['last_value']) . ' ' . $k['unit']) : '—' ?><br><span class="muted"><?= e(fdate($k['last_date'])) ?></span></td>
<td><?= $k['last_status'] ? status_badge($k['last_status']) : '—' ?><?php if ($stale && $k['is_active']): ?> <span class="badge warn"><?= e(t('kri.missing')) ?></span><?php endif; ?></td>
<td class="nowrap"><span class="badge warn"><?= e(\App\Core\Money::plain($k['amber_threshold'])) ?></span> <span class="badge danger"><?= e(\App\Core\Money::plain($k['red_threshold'])) ?></span> <span class="muted"><?= e(t('enum.dir.' . $k['direction'])) ?></span></td>
<td><?= e(t('enum.kfreq.' . $k['frequency'])) ?></td></tr><?php endforeach; ?></tbody></table></div>
<?= pager($total, $page, $per) ?>
<?= kb_refs('kri') ?>
