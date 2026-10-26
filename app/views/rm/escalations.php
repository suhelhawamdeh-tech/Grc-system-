<?php /** @var array $rows @var array $f */ $tab = 'escalations'; ?>
<div class="page-head"><div><h1><?= icon('flag') ?> <?= e(t('rm.escalations')) ?></h1><p class="sub"><?= e(t('rm.escalations_hint')) ?></p></div>
<div class="actions"><?php if (can('rm.work')): ?><a class="btn danger" href="<?= e(url('rm/escalations/new')) ?>"><?= e(t('rm.escalation_new')) ?></a><?php endif; ?></div></div>
<?php require __DIR__ . '/_tabs.php'; ?>
<form method="get" action="<?= e(url('rm/escalations')) ?>" class="card filters" role="search">
<?= ff('select', 'status', 'pol.status', $f['status'], ['options' => opts(['open', 'responded', 'closed'], fn($x) => t('enum.rmes.' . $x), null) ?: [], 'blank_label' => 'common.all']) ?>
<?= ff('select', 'source', 'rm.source_type', $f['source'], ['options' => opts(\App\Support\RiskFunction::ESC_SOURCES, fn($x) => t('enum.rmsrc.' . $x), null) ?: [], 'blank_label' => 'common.all']) ?>
<div class="field actions"><button class="btn" type="submit"><?= e(t('common.filter')) ?></button><a class="btn secondary" href="<?= e(url('rm/escalations')) ?>"><?= e(t('common.reset')) ?></a></div></form>
<div class="table-wrap"><table class="data"><thead><tr><th><?= e(t('rm.notice_no')) ?></th><th><?= e(t('rm.esc_subject')) ?></th><th><?= e(t('rm.source_type')) ?></th><th><?= e(t('rm.escalated_to')) ?></th><th><?= e(t('rm.escalated_at')) ?></th><th><?= e(t('pol.status')) ?></th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="6" class="muted"><?= e(t('common.no_results')) ?></td></tr><?php endif; ?>
<?php foreach ($rows as $x): ?><tr><td class="nowrap" dir="ltr"><a href="<?= e(url('rm/escalations/' . $x['id'])) ?>"><?= e($x['code']) ?></a></td><td><a href="<?= e(url('rm/escalations/' . $x['id'])) ?>"><?= e($x['title']) ?></a><br><span class="muted small"><?= e($x['full_name']) ?><?= $x['ceo_informed'] ? '' : ' · ' . e(t('rm.ceo_not_informed_short')) ?></span></td>
<td><?= e(t('enum.rmsrc.' . $x['source_type'])) ?></td><td><?= e(t('enum.rmescto.' . $x['escalated_to'])) ?></td><td class="nowrap"><?= e(fdatetime($x['escalated_at'])) ?></td><td><span class="badge <?= $x['status'] === 'open' ? 'danger' : ($x['status'] === 'closed' ? 'ok' : 'warn') ?>"><?= e(t('enum.rmes.' . $x['status'])) ?></span></td></tr><?php endforeach; ?></tbody></table></div>
