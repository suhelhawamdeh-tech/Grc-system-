<?php /** @var array $by */ $tab = 'factors'; require __DIR__ . '/_rating.php'; ?>
<div class="page-head"><div><h1><?= icon('sliders') ?> <?= e(t('aml.factors')) ?></h1><p class="sub"><?= e(t('aml.factors_hint')) ?></p></div></div>
<?php require __DIR__ . '/_tabs.php'; ?>
<div class="card notice"><p><?= e(t('aml.factors_notice')) ?></p></div>
<?php foreach (\App\Support\AmlScoring::DIMENSIONS as $d): ?><section class="card"><h2><?= e(t('enum.amld.' . $d)) ?> <span class="muted small">(<?= (int)\App\Support\AmlScoring::WEIGHTS[$d] ?>%)</span></h2>
<div class="table-wrap"><table class="data"><thead><tr><th><?= e(t('aml.f_code')) ?></th><th><?= e(t('aml.f_name')) ?></th><th><?= e(t('aml.level')) ?></th><th><?= e(t('aml.t24_code')) ?></th><th class="num"><?= e(t('aml.used_by')) ?></th><?php if (can('aml.manage')): ?><th></th><?php endif; ?></tr></thead><tbody>
<?php foreach ($by[$d] ?? [] as $f): ?><tr class="<?= (int)$f['is_active'] ? '' : 'muted' ?>"><td class="nowrap code"><?= e($f['code']) ?></td><td><?= e(lname($f)) ?><?= (int)$f['is_default'] ? ' <span class="badge">' . e(t('aml.default')) . '</span>' : '' ?><?= (int)$f['is_active'] ? '' : ' <span class="badge">' . e(t('aml.inactive')) . '</span>' ?></td>
<td><?= $amlBadge($lvlName((int)$f['risk_level'])) ?></td><td class="code"><?= e($f['t24_code'] ?? '—') ?></td><td class="num"><?= (int)$f['used'] ?></td>
<?php if (can('aml.manage')): ?><td><a class="btn small secondary" href="<?= e(url('aml/factors/' . $f['id'] . '/edit')) ?>"><?= e(t('common.edit')) ?></a></td><?php endif; ?></tr><?php endforeach; ?></tbody></table></div>
<?php if (can('aml.manage')): ?><p><a class="btn small" href="<?= e(url('aml/factors/new', ['dimension' => $d])) ?>"><?= e(t('aml.factor_new')) ?></a></p><?php endif; ?></section><?php endforeach; ?>
