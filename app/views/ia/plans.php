<?php /** @var array $rows */ $tab = 'plans'; ?>
<div class="page-head"><div><h1><?= icon('target') ?> <?= e(t('ia.plans')) ?></h1><p class="sub"><?= e(t('ia.plans_hint')) ?></p></div></div>
<?php require __DIR__ . '/_tabs.php'; ?>
<div class="table-wrap"><table class="data"><thead><tr><th><?= e(t('ia.plan_year')) ?></th><th><?= e(t('ia.plan_title')) ?></th><th><?= e(t('ia.status')) ?></th><th class="num"><?= e(t('ia.engagements')) ?></th><th class="num"><?= e(t('ia.issued_n')) ?></th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="5" class="empty"><?= icon('target') ?><br><?= e(t('ia.none')) ?></td></tr><?php endif; ?>
<?php foreach ($rows as $p): ?><tr><td><a href="<?= e(url('ia/plans/' . $p['id'])) ?>"><?= (int)$p['plan_year'] ?></a></td><td><a href="<?= e(url('ia/plans/' . $p['id'])) ?>"><?= e($p['title']) ?></a></td>
<td><span class="badge <?= $p['status'] === 'approved' ? 'ok' : 'warn' ?>"><?= e(t('enum.iaplan.' . $p['status'])) ?></span></td><td class="num"><?= (int)$p['n'] ?></td><td class="num"><?= (int)$p['done'] ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php if (can('ia.manage')): ?><section class="card"><h2><?= e(t('ia.plan_create')) ?></h2><form method="post" action="<?= e(url('ia/plans')) ?>" novalidate><?= csrf_field() ?><div class="form-grid">
<?= ff('number', 'plan_year', 'ia.plan_year', date('Y'), ['required' => true, 'extra' => ' min="2000" max="2100"']) ?>
<?= ff('text', 'title', 'ia.plan_title', t('ia.plan_default_title', ['y' => date('Y')]), ['required' => true, 'wide' => true]) ?></div>
<button class="btn gold" type="submit"><?= e(t('ia.plan_create')) ?></button></form></section><?php endif; ?>
