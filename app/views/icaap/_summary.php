<?php /** @var array $x */ $r = $x['r']; $c = $x['c']; $pct = fn($v) => $v === null ? '—' : number_format($v * 100, 2) . '%'; $amt = fn($v) => $v === null ? '—' : number_format((float)$v, 0);
$zc = ['target' => 'ok', 'warning' => 'warn', 'trigger' => 'danger', 'below_min' => 'danger']; $zb = fn($z) => $z ? '<span class="badge ' . ($zc[$z] ?? '') . '">' . e(t('enum.iczone.' . $z)) . '</span>' : '—'; ?>
<div class="kpis">
<div class="kpi<?= in_array($r['zone'], ['trigger', 'below_min'], true) ? ' alert' : '' ?>"><div class="v"><?= e($pct($r['car'])) ?></div><div class="l"><?= e(t('icaap.car')) ?> <?= $zb($r['zone']) ?></div></div>
<div class="kpi"><div class="v"><?= e($pct($r['core'])) ?></div><div class="l"><?= e(t('icaap.core_ratio')) ?></div></div>
<div class="kpi<?= $r['margin'] !== null && $r['margin'] < 0 ? ' alert' : '' ?>"><div class="v small"><?= e($amt($r['margin'])) ?></div><div class="l"><?= e(t('icaap.margin')) ?> <?= $r['margin_pct'] !== null ? '(' . e($pct($r['margin_pct'])) . ')' : '' ?></div></div>
<div class="kpi"><div class="v"><?= (int)$r['material_count'] ?></div><div class="l"><?= e(t('icaap.material_count')) ?></div></div>
<div class="kpi<?= in_array($r['min_zone']['severe'] ?? null, ['trigger', 'below_min'], true) ? ' alert' : '' ?>"><div class="v"><?= e($pct($r['min']['severe'] ?? null)) ?></div><div class="l"><?= e(t('icaap.min_severe')) ?> <?= $zb($r['min_zone']['severe'] ?? null) ?></div></div>
<div class="kpi"><div class="v small"><?= $r['recon'] === null ? '—' : ($r['recon'] === 'match' ? '<span class="badge ok">' . e(t('icaap.recon_match')) . '</span>' : '<span class="badge danger">' . e(t('icaap.recon_difference')) . '</span>') ?></div><div class="l"><?= e(t('icaap.recon')) ?></div></div></div>
