<?php /** @var array $dims @var array $byRating @var array $stats @var array $controls */
$tab = 'aml'; require __DIR__ . '/_rating.php'; ?>
<div class="page-head"><div><h1><?= icon('eye') ?> <?= e(t('aml.title')) ?></h1><p class="sub"><?= e(t('aml.subtitle')) ?></p></div>
  <div class="actions"><?php if (can('aml.manage')): ?><a class="btn gold" href="<?= e(url('aml/customers/new')) ?>"><?= e(t('aml.customer_new')) ?></a><?php endif; ?></div></div>
<?php require __DIR__ . '/_tabs.php'; ?>
<div class="kpis">
  <a class="kpi" href="<?= e(url('aml/customers')) ?>"><div class="v"><?= (int)$stats['customers'] ?></div><div class="l"><?= e(t('aml.stat_customers')) ?></div></a>
  <a class="kpi <?= $stats['high'] ? 'alert' : '' ?>" href="<?= e(url('aml/customers', ['rating' => 'high'])) ?>"><div class="v"><?= (int)$stats['high'] ?></div><div class="l"><?= e(t('aml.stat_high')) ?></div></a>
  <a class="kpi" href="<?= e(url('aml/customers', ['pep' => 1])) ?>"><div class="v"><?= (int)$stats['pep'] ?></div><div class="l"><?= e(t('aml.stat_pep')) ?></div></a>
  <a class="kpi <?= $stats['sanctions'] ? 'alert' : '' ?>" href="<?= e(url('aml/customers', ['sanctions' => 1])) ?>"><div class="v"><?= (int)$stats['sanctions'] ?></div><div class="l"><?= e(t('aml.stat_sanctions')) ?></div></a>
  <a class="kpi <?= $stats['review_overdue'] ? 'alert' : '' ?>" href="<?= e(url('aml/customers', ['review' => 1])) ?>"><div class="v"><?= (int)$stats['review_overdue'] ?></div><div class="l"><?= e(t('aml.stat_review_overdue')) ?></div></a>
  <a class="kpi <?= $stats['reports_overdue'] ? 'alert' : '' ?>" href="<?= e(url('aml/reports', ['overdue' => 1])) ?>"><div class="v"><?= (int)$stats['reports_overdue'] ?></div><div class="l"><?= e(t('aml.stat_reports_overdue')) ?></div></a>
</div>
<section class="card"><h2><?= e(t('aml.ewra')) ?></h2><p class="muted"><?= e(t('aml.ewra_hint')) ?></p>
<div class="factor-grid"><?php foreach ($dims as $d => $x): ?>
<div class="dim-card <?= e($x['residual_level'] ?? '') ?>"><h3><?= e(t('enum.amld.' . $d)) ?></h3>
<div class="lvls"><span class="badge ok"><?= e(t('enum.amlr.low')) ?> <?= (int)$x['dist'][1] ?></span><span class="badge warn"><?= e(t('enum.amlr.medium')) ?> <?= (int)$x['dist'][2] ?></span><span class="badge danger"><?= e(t('enum.amlr.high')) ?> <?= (int)$x['dist'][3] ?></span></div>
<dl class="details compact"><dt><?= e(t('aml.inherent')) ?></dt><dd><?= $amlBadge($x['inherent_level']) ?> <span class="muted small"><?= $x['inherent'] !== null ? e(number_format($x['inherent'], 2)) : '' ?></span></dd>
<dt><?= e(t('aml.control_eff')) ?></dt><dd><?= $x['effectiveness'] === null ? '<span class="muted">' . e(t('aml.no_controls')) . '</span>' : (int)$x['effectiveness'] . '%' ?></dd>
<dt><?= e(t('aml.residual')) ?></dt><dd><?= $amlBadge($x['residual_level']) ?> <span class="muted small"><?= $x['residual'] !== null ? e(number_format($x['residual'], 2)) : '' ?></span></dd></dl></div><?php endforeach; ?></div>
<details style="margin-top:12px"><summary><?= icon('help') ?> <?= e(t('aml.how_ewra')) ?></summary><p><?= e(t('aml.how_ewra_text')) ?></p></details></section>
<section class="card" id="controls"><h2><?= e(t('aml.controls')) ?></h2><p class="muted"><?= e(t('aml.controls_hint')) ?></p>
<div class="table-wrap"><table class="data"><thead><tr><th><?= e(t('aml.dimension')) ?></th><th><?= e(t('aml.control')) ?></th><th><?= e(t('aml.last_result')) ?></th><th><?= e(t('aml.next_test')) ?></th><?php if (can('aml.manage')): ?><th></th><?php endif; ?></tr></thead><tbody>
<?php $any = false; foreach ($dims as $d => $x): foreach ($x['controls'] as $c): $any = true; ?><tr><td><?= e(t('enum.amld.' . $d)) ?></td><td><a href="<?= e(url('controls/' . $c['id'])) ?>"><?= e($c['code'] . ' — ' . $c['name']) ?></a></td><td><?= $c['last_result'] ? status_badge($c['last_result']) : '<span class="muted">' . e(t('aml.untested')) . '</span>' ?></td><td class="nowrap"><?= e(fdate($c['next_test_due']) ?: '—') ?></td>
<?php if (can('aml.manage')): ?><td><form method="post" class="inline" action="<?= e(url('aml/controls/remove')) ?>"><?= csrf_field() ?><input type="hidden" name="dimension" value="<?= e($d) ?>"><input type="hidden" name="control_id" value="<?= (int)$c['id'] ?>"><button class="btn small secondary" type="submit"><?= e(t('aml.unlink')) ?></button></form></td><?php endif; ?></tr><?php endforeach; endforeach; ?>
<?php if (!$any): ?><tr><td colspan="5" class="empty"><?= e(t('aml.no_controls')) ?></td></tr><?php endif; ?></tbody></table></div>
<?php if (can('aml.manage') && $controls): ?><form method="post" action="<?= e(url('aml/controls')) ?>" class="form-grid" style="margin-top:12px"><?= csrf_field() ?>
<?= ff('select', 'dimension', 'aml.dimension', '', ['required' => true, 'options' => opts(\App\Support\AmlScoring::DIMENSIONS, fn($x) => t('enum.amld.' . $x), null)]) ?>
<?= ff('select', 'control_id', 'aml.control', '', ['required' => true, 'options' => opts($controls, fn($c) => $c['code'] . ' — ' . mb_strimwidth($c['name'], 0, 60, '…'))]) ?>
<div class="field actions"><button class="btn" type="submit"><?= e(t('aml.link')) ?></button></div></form><?php endif; ?></section>
<section class="card"><h2><?= e(t('aml.rules')) ?></h2><ul>
<li><?= e(t('aml.rule_weights')) ?></li><li><?= e(t('aml.rule_bands')) ?></li><li><?= e(t('aml.rule_pep')) ?></li><li><?= e(t('aml.rule_floor')) ?></li><li><?= e(t('aml.rule_cdd')) ?></li></ul></section>
