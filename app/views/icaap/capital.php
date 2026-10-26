<?php /** @var array $x */ $c = $x['c']; $r = $x['r']; require __DIR__ . '/_tabs.php'; require __DIR__ . '/_summary.php';
$p = fn($v) => $v === null ? '' : rtrim(rtrim(number_format((float)$v * 100, 4, '.', ''), '0'), '.'); $a = fn($v) => $v === null ? '' : \App\Core\Money::plain($v); $ro = !can('icaap.manage') || $locked ? ' readonly' : ''; ?>
<form method="post" action="<?= e(url('icaap/' . $c['id'] . '/capital')) ?>" class="card" novalidate><?= csrf_field() ?>
<h2><?= e(t('icaap.inputs')) ?></h2><p class="muted"><?= e(t('icaap.inputs_hint')) ?></p><div class="form-grid">
<?= ff('date', 'data_date', 'icaap.data_date', $c['data_date'], ['required' => true]) ?><?= ff('date', 'regulatory_deadline', 'icaap.deadline', $c['regulatory_deadline'] ?? '') ?>
<?php foreach (['tier1' => 'icaap.tier1', 'tier2' => 'icaap.tier2', 'rwa_credit' => 'icaap.rwa_credit', 'rwa_market' => 'icaap.rwa_market', 'rwa_operational' => 'icaap.rwa_operational'] as $f => $l): ?>
<?= ff('text', $f, $l, $a($c[$f]), ['dir' => 'ltr', 'extra' => ' inputmode="decimal"' . $ro]) ?><?php endforeach; ?></div>
<h3><?= e(t('icaap.levels')) ?></h3><p class="muted small"><?= e(t('icaap.levels_hint')) ?></p><div class="form-grid">
<?php foreach (['reg_min' => 'icaap.reg_min', 'trigger_ratio' => 'icaap.trigger', 'warning_ratio' => 'icaap.warning', 'target_ratio' => 'icaap.target', 'last_reg_ratio' => 'icaap.last_reg_ratio', 'alpha' => 'icaap.alpha'] as $f => $l): ?>
<?= ff('text', $f, $l, $p($c[$f]), ['dir' => 'ltr', 'extra' => ' inputmode="decimal"' . $ro, 'hint' => '%']) ?><?php endforeach; ?></div>
<?php if (!$ro): ?><div class="actions"><button class="btn" type="submit"><?= e(t('common.save')) ?></button></div><?php endif; ?></form>
<section class="card"><h2><?= e(t('icaap.pillar1')) ?></h2><div class="table-wrap"><table class="data"><thead><tr><th><?= e(t('icaap.item')) ?></th><th class="num"><?= e(t('icaap.amount')) ?></th></tr></thead><tbody>
<tr><td><?= e(t('icaap.available')) ?></td><td class="num"><?= $r['available'] === null ? '—' : e(number_format($r['available'], 0)) ?></td></tr>
<tr><td><?= e(t('icaap.rwa')) ?></td><td class="num"><?= $r['rwa'] === null ? '—' : e(number_format($r['rwa'], 0)) ?></td></tr>
<tr><td><?= e(t('icaap.p1')) ?></td><td class="num"><?= $r['p1'] === null ? '—' : e(number_format($r['p1'], 0)) ?></td></tr></tbody></table></div>
<p class="muted small"><?= e(t('icaap.recon_hint')) ?></p></section>
