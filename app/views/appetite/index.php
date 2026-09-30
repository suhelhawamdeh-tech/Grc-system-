<div class="page-head"><div><h1><?= e(t('appetite.title')) ?></h1><p class="sub"><?= e(t('appetite.subtitle')) ?></p></div>
<div class="actions"><?php if (can('appetite.manage')): ?><a class="btn gold" href="<?= e(url('appetite/new')) ?>"><?= e(t('appetite.new')) ?></a>
<form method="post" class="inline" action="<?= e(url('appetite/evaluate')) ?>"><?= csrf_field() ?><button class="btn secondary" type="submit"><?= e(t('appetite.evaluate')) ?></button></form><?php endif; ?></div></div>
<div class="msg info"><?= e(t('appetite.disclaimer')) ?></div>
<div class="table-wrap"><table class="data"><thead><tr><th><?= e(t('appetite.id')) ?></th><th><?= e(t('appetite.name')) ?></th><th><?= e(t('appetite.metric')) ?></th><th class="num"><?= e(t('appetite.limit')) ?></th><th class="num"><?= e(t('appetite.current')) ?></th><th><?= e(t('common.status')) ?></th><th><?= e(t('appetite.open_breaches')) ?></th><th></th></tr></thead><tbody>
<?php if (!$stmts): ?><tr><td colspan="8" class="muted"><?= e(t('common.no_results')) ?></td></tr><?php endif; ?>
<?php foreach ($stmts as $s): $isScore = $s['metric'] === 'max_residual_score'; ?>
<tr><td class="nowrap"><?= e($s['code']) ?></td><td><strong><?= e($s['title']) ?></strong><br><span class="muted pre"><?= e(mb_strimwidth($s['statement'], 0, 220, '…')) ?></span><br><span class="muted"><?= e(($s['category_id'] ? \App\Support\Ref::name('category', (int)$s['category_id']) : t('appetite.all_categories')) . ' · ' . ($s['department_id'] ? \App\Support\Ref::name('department', (int)$s['department_id']) : t('appetite.all_departments'))) ?><?= $s['approved_by'] ? ' · ' . e(t('appetite.approved_by')) . ': ' . e($s['approved_by']) . ' ' . e(fdate($s['approved_on'])) : ' · ' . e(t('appetite.not_approved')) ?></span></td>
<td><?= e(t('enum.metric.' . $s['metric'])) ?></td>
<td class="num"><?= $isScore ? (int)((int)\App\Core\Money::fromDb($s['limit_value']) / 1000) : e(money($s['limit_value'])) ?></td>
<td class="num"><?= $isScore ? (int)$s['m']['value'] : e(money(\App\Core\Money::toDb($s['m']['value']))) ?><br><span class="muted"><?= $isScore ? e(t('appetite.n_over', ['n' => $s['m']['detail']])) : e($s['m']['detail']) ?></span></td>
<td><?= !$s['is_active'] ? '<span class="badge">' . e(t('common.inactive')) . '</span>' : ($s['m']['breach'] ? '<span class="badge danger">' . e(t('appetite.breached')) . '</span>' : '<span class="badge ok">' . e(t('appetite.within')) . '</span>') ?></td>
<td class="num"><?= (int)$s['open_breaches'] ?></td>
<td><?php if (can('appetite.manage')): ?><a class="btn small secondary" href="<?= e(url('appetite/' . $s['id'] . '/edit')) ?>"><?= e(t('common.edit')) ?></a><?php endif; ?></td></tr>
<?php if (can('appetite.manage') && $s['is_active']): ?><tr><td></td><td colspan="7"><details><summary><?= e(t('appetite.record_breach')) ?></summary>
<form method="post" action="<?= e(url('appetite/' . $s['id'] . '/breaches')) ?>" class="form-grid" novalidate><?= csrf_field() ?>
<?= ff('date', 'detected_on', 'appetite.detected_on', date('Y-m-d'), ['required' => true]) ?><?= ff('select', 'owner_user_id', 'appetite.owner', '', ['options' => opts($users, fn($x) => $x['full_name'])]) ?>
<?= ff('textarea', 'description', 'appetite.breach_description', '', ['required' => true, 'wide' => true]) ?><div class="field"><button class="btn small" type="submit"><?= e(t('common.save')) ?></button></div></form></details></td></tr><?php endif; ?>
<?php endforeach; ?></tbody></table></div>

<h2 style="margin-top:24px"><?= e(t('appetite.breaches')) ?></h2>
<form method="get" action="<?= e(url('appetite')) ?>" class="card filters"><?= ff('select', 'status', 'appetite.breach_status', $status, ['options' => array_combine(['open', 'escalated', 'accepted', 'resolved'], array_map(fn($x) => t('enum.status.' . $x), ['open', 'escalated', 'accepted', 'resolved'])), 'blank_label' => 'appetite.open_and_escalated']) ?><div class="field actions"><button class="btn" type="submit"><?= e(t('common.filter')) ?></button></div></form>
<?php if (!$breaches): ?><p class="muted"><?= e(t('common.no_results')) ?></p><?php endif; ?>
<?php foreach ($breaches as $b): ?><section class="card"><h3><?= e($b['s_code']) ?> — <?= e($b['s_title']) ?> <?= status_badge($b['status']) ?> <span class="badge"><?= e(t('enum.bsrc.' . $b['source'])) ?></span></h3>
<p class="pre"><?= e($b['description']) ?></p><p class="muted"><?= e(t('appetite.detected_on')) ?>: <?= e($b['detected_on']) ?><?= $b['measured_value'] !== null ? ' · ' . e(t('appetite.measured')) . ': ' . e(\App\Core\Money::plain($b['measured_value'])) : '' ?><?= $b['owner_name'] ? ' · ' . e($b['owner_name']) : '' ?><?= $b['resolved_on'] ? ' · ' . e(t('appetite.resolved_on')) . ': ' . e($b['resolved_on']) : '' ?></p>
<?php if ($b['resolution_note']): ?><p class="pre"><strong><?= e(t('appetite.resolution_note')) ?>:</strong> <?= e($b['resolution_note']) ?></p><?php endif; ?>
<?php if (can('appetite.manage')): ?><form method="post" action="<?= e(url('appetite/breaches/' . $b['id'])) ?>" class="filters" novalidate><?= csrf_field() ?>
<?= ff('select', 'status', 'appetite.breach_status', $b['status'], ['blank' => false, 'options' => array_combine(['open', 'escalated', 'accepted', 'resolved'], array_map(fn($x) => t('enum.status.' . $x), ['open', 'escalated', 'accepted', 'resolved']))]) ?>
<?= ff('select', 'owner_user_id', 'appetite.owner', $b['owner_user_id'], ['options' => opts($users, fn($x) => $x['full_name'])]) ?>
<?= ff('text', 'resolution_note', 'appetite.resolution_note', $b['resolution_note'] ?? '') ?><div class="field"><button class="btn small secondary" type="submit"><?= e(t('common.update')) ?></button></div></form><?php endif; ?></section><?php endforeach; ?>
