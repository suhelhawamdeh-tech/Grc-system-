<?php /** @var array $p @var array $eng @var array $candidates @var array $coverage @var array $leads */
$tab = 'plans'; $draft = $p['status'] === 'draft'; $y = (int)$p['plan_year'];
$prio = static fn(string $x) => '<span class="badge ' . ($x === 'high' ? 'danger' : ($x === 'medium' ? 'warn' : 'ok')) . '">' . e(t('enum.iaprio.' . $x)) . '</span>';
$es = static fn(string $s) => '<span class="badge ' . ($s === 'issued' ? 'ok' : ($s === 'cancelled' ? '' : 'warn')) . '">' . e(t('enum.iaes.' . $s)) . '</span>'; ?>
<div class="page-head"><div><h1><?= icon('target') ?> <?= e($p['title']) ?></h1><p class="sub"><?= e(t('ia.plan')) ?> <?= $y ?> · <span class="badge <?= $draft ? 'warn' : 'ok' ?>"><?= e(t('enum.iaplan.' . $p['status'])) ?></span></p></div>
  <div class="actions"><a class="btn secondary" href="<?= e(url('ia/plans')) ?>"><?= e(t('common.back')) ?></a></div></div>
<?php require __DIR__ . '/_tabs.php'; ?>
<?php if (!$draft): ?><div class="card notice"><p><?= icon('check') ?> <?= e(t('ia.approved_by_line', ['body' => $p['approval_body'], 'd' => fdate($p['approved_on']), 'ref' => $p['approval_ref'] ?: '—'])) ?></p></div><?php endif; ?>
<div class="kpis"><div class="kpi <?= $coverage[1] && $coverage[0] < $coverage[1] ? 'alert' : '' ?>"><div class="v"><?= (int)$coverage[0] ?>/<?= (int)$coverage[1] ?></div><div class="l"><?= e(t('ia.coverage')) ?></div></div>
<div class="kpi"><div class="v"><?= count(array_filter($eng, fn($e) => $e['status'] !== 'cancelled')) ?></div><div class="l"><?= e(t('ia.engagements')) ?></div></div></div>
<section class="card"><h2><?= e(t('ia.engagements')) ?></h2>
<div class="table-wrap"><table class="data"><thead><tr><th><?= e(t('ia.code')) ?></th><th><?= e(t('ia.e_title')) ?></th><th><?= e(t('ia.entity')) ?></th><th><?= e(t('ia.lead')) ?></th><th><?= e(t('ia.period')) ?></th><th><?= e(t('ia.score')) ?></th><th><?= e(t('ia.status')) ?></th></tr></thead><tbody>
<?php if (!$eng): ?><tr><td colspan="7" class="empty"><?= e(t('ia.plan_empty')) ?></td></tr><?php endif; ?>
<?php foreach ($eng as $e): ?><tr><td class="nowrap"><a href="<?= e(url('ia/engagements/' . $e['id'])) ?>"><?= e($e['code']) ?></a></td><td><a href="<?= e(url('ia/engagements/' . $e['id'])) ?>"><?= e($e['title']) ?></a></td><td><?= e($e['u_code'] . ' — ' . $e['u_name']) ?></td><td><?= e($e['lead']) ?></td>
<td class="nowrap"><?= e(fdate($e['planned_start'])) ?> → <?= e(fdate($e['planned_end'])) ?></td><td><?= e(number_format((float)$e['priority_score'], 2)) ?></td><td><?= $es($e['status']) ?><?= $e['rating'] ? '<div class="muted small">' . e(t('enum.iarating.' . $e['rating'])) . '</div>' : '' ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php if ($draft && can('ia.manage')): ?>
<section class="card" id="add"><h2><?= e(t('ia.add_engagement')) ?></h2><p class="muted"><?= e(t('ia.add_engagement_hint')) ?></p>
<div class="table-wrap"><table class="data"><thead><tr><th><?= e(t('ia.entity')) ?></th><th><?= e(t('ia.score')) ?></th><th><?= e(t('ia.priority')) ?></th><th><?= e(t('ia.due_in', ['y' => $y])) ?></th></tr></thead><tbody>
<?php foreach ($candidates as $r): ?><tr><td><?= e($r['code'] . ' — ' . $r['name']) ?></td><td><?= e(number_format((float)$r['score'], 2)) ?></td><td><?= $prio($r['priority']) ?></td><td><?= $r['due'] ? '<span class="badge warn">' . e(t('ia.due_yes')) . '</span>' : '<span class="muted">' . e(t('ia.due_no')) . '</span>' ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php if ($candidates): ?><form method="post" action="<?= e(url('ia/plans/' . $p['id'] . '/engagements')) ?>" novalidate style="margin-top:12px"><?= csrf_field() ?><div class="form-grid">
<?= ff('select', 'universe_id', 'ia.entity', '', ['required' => true, 'options' => opts($candidates, fn($r) => $r['code'] . ' — ' . $r['name'] . ' (' . number_format((float)$r['score'], 2) . ')')]) ?>
<?= ff('text', 'title', 'ia.e_title', '', ['required' => true]) ?>
<?= ff('select', 'lead_user_id', 'ia.lead', '', ['required' => true, 'options' => opts($leads, fn($u) => $u['full_name'])]) ?>
<?= ff('date', 'planned_start', 'ia.planned_start', '', ['required' => true]) ?><?= ff('date', 'planned_end', 'ia.planned_end', '', ['required' => true]) ?>
<?= ff('textarea', 'objective', 'ia.objective', '', ['required' => true, 'wide' => true]) ?><?= ff('textarea', 'scope', 'ia.scope', '', ['wide' => true]) ?></div>
<button class="btn gold" type="submit"><?= e(t('ia.add_engagement')) ?></button></form><?php endif; ?></section>
<section class="card" id="approve"><h2><?= e(t('ia.record_approval')) ?></h2><p class="muted"><?= e(t('ia.record_approval_hint')) ?></p>
<form method="post" action="<?= e(url('ia/plans/' . $p['id'] . '/approve')) ?>" novalidate><?= csrf_field() ?><div class="form-grid">
<?= ff('text', 'approval_body', 'ia.approval_body', t('ia.default_body'), ['required' => true]) ?><?= ff('text', 'approval_ref', 'ia.approval_ref', '') ?><?= ff('date', 'approved_on', 'ia.approved_on', date('Y-m-d'), ['required' => true]) ?>
<?= ff('textarea', 'notes', 'ia.notes', '', ['wide' => true]) ?></div><button class="btn" type="submit"><?= e(t('ia.record_approval')) ?></button></form></section>
<?php endif; ?>
