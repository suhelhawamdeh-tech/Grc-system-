<?php /** @var ?array $plan @var array $eng @var array $sev @var int $overdue @var int $closed @var int $issued @var int $onTime @var array $ratings @var int $year */
$tab = 'ia'; $planned = array_sum(array_map('intval', array_diff_key($eng, ['cancelled' => 0]))); $done = (int)($eng['issued'] ?? 0); ?>
<div class="page-head"><div><h1><?= icon('search') ?> <?= e(t('ia.title')) ?></h1><p class="sub"><?= e(t('ia.subtitle')) ?></p></div>
  <div class="actions"><?php if ($plan): ?><a class="btn secondary" href="<?= e(url('ia/plans/' . $plan['id'])) ?>"><?= e(t('ia.plan')) ?> <?= (int)$year ?></a><?php endif; ?></div></div>
<?php require __DIR__ . '/_tabs.php'; ?>
<?php if (!$plan): ?><div class="card notice"><p><?= e(t('ia.no_plan', ['y' => $year])) ?></p><?php if (can('ia.manage')): ?><a class="btn gold" href="<?= e(url('ia/plans')) ?>"><?= e(t('ia.plan_create')) ?></a><?php endif; ?></div><?php endif; ?>
<div class="kpis">
  <a class="kpi" href="<?= e($plan ? url('ia/plans/' . $plan['id']) : url('ia/plans')) ?>"><div class="v"><?= $done ?>/<?= $planned ?></div><div class="l"><?= e(t('ia.stat_plan_progress', ['y' => $year])) ?></div></a>
  <a class="kpi <?= !empty($sev['high']) ? 'alert' : '' ?>" href="<?= e(url('ia/findings', ['status' => 'not_closed', 'severity' => 'high'])) ?>"><div class="v"><?= (int)($sev['high'] ?? 0) ?></div><div class="l"><?= e(t('ia.stat_high_open')) ?></div></a>
  <a class="kpi" href="<?= e(url('ia/findings', ['status' => 'not_closed'])) ?>"><div class="v"><?= array_sum($sev) ?></div><div class="l"><?= e(t('ia.stat_open')) ?></div></a>
  <a class="kpi <?= $overdue ? 'alert' : '' ?>" href="<?= e(url('ia/findings', ['overdue' => 1])) ?>"><div class="v"><?= $overdue ?></div><div class="l"><?= e(t('ia.stat_overdue')) ?></div></a>
  <div class="kpi"><div class="v"><?= $closed ? (int)round(100 * $onTime / $closed) . '%' : '—' ?></div><div class="l"><?= e(t('ia.stat_on_time')) ?></div></div>
</div>
<div class="grid cols-2">
<section class="card"><h2><?= e(t('ia.engagements_status', ['y' => $year])) ?></h2>
<?php if (!$eng): ?><p class="muted"><?= e(t('ia.none')) ?></p><?php else: ?><?= bars(array_map(fn($s) => [t('enum.iaes.' . $s), (int)($eng[$s] ?? 0), url('ia/plans/' . $plan['id'])], ['planned', 'fieldwork', 'reporting', 'issued', 'cancelled'])) ?><?php endif; ?></section>
<section class="card"><h2><?= e(t('ia.ratings_year', ['y' => $year])) ?></h2>
<?php if (!$ratings): ?><p class="muted"><?= e(t('ia.none')) ?></p><?php else: ?><?= bars(array_map(fn($s) => [t('enum.iarating.' . $s), (int)($ratings[$s] ?? 0), url('ia/plans')], ['satisfactory', 'needs_improvement', 'unsatisfactory'])) ?><?php endif; ?></section>
</div>
<div class="grid cols-3" style="margin-top:16px">
  <a class="help-card" href="<?= e(url('ia/universe')) ?>"><strong><?= icon('layers') ?> <?= e(t('ia.universe')) ?></strong><span><?= e(t('ia.universe_hint')) ?></span></a>
  <a class="help-card" href="<?= e(url('ia/plans')) ?>"><strong><?= icon('target') ?> <?= e(t('ia.plans')) ?></strong><span><?= e(t('ia.plans_hint')) ?></span></a>
  <a class="help-card" href="<?= e(url('ia/findings')) ?>"><strong><?= icon('flag') ?> <?= e(t('ia.findings')) ?></strong><span><?= e(t('ia.findings_hint')) ?></span></a>
</div>
<?= kb_refs('ia') ?>
