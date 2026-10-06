<?php $mx = max(1.0, ...array_map(fn($r) => (float)$r['value'], $readings ?: [['value' => 1]])); $chart = array_reverse(array_slice($readings, 0, 12)); ?>
<div class="page-head"><div><h1><?= e($k['code']) ?> — <?= e($k['name']) ?></h1><p class="sub"><?= e(lname($k, 'dept')) ?> · <?= e($k['owner_name']) ?> · <?= e(t('enum.kfreq.' . $k['frequency'])) ?></p></div>
  <div class="actions"><a class="btn secondary" href="<?= e(url('kris')) ?>"><?= e(t('common.back')) ?></a><?php if (can('kri.manage')): ?><a class="btn" href="<?= e(url('kris/' . $k['id'] . '/edit')) ?>"><?= e(t('common.edit')) ?></a><?php endif; ?></div></div>
<div class="grid cols-2">
<section class="card"><h2><?= e(t('kri.definition')) ?></h2><dl class="details">
<dt><?= e(t('kri.description')) ?></dt><dd class="pre"><?= e($k['description'] ?? '—') ?></dd>
<dt><?= e(t('kri.direction')) ?></dt><dd><?= e(t('enum.dir.' . $k['direction'])) ?></dd>
<dt><?= e(t('kri.amber')) ?></dt><dd><span class="badge warn"><?= e(\App\Core\Money::plain($k['amber_threshold']) . ' ' . $k['unit']) ?></span></dd>
<dt><?= e(t('kri.red')) ?></dt><dd><span class="badge danger"><?= e(\App\Core\Money::plain($k['red_threshold']) . ' ' . $k['unit']) ?></span></dd>
<?php if ($k['critical_threshold'] !== null): ?><dt><?= e(t('kri.critical')) ?></dt><dd><span class="badge danger"><?= e(\App\Core\Money::plain($k['critical_threshold']) . ' ' . $k['unit']) ?></span></dd><?php endif; ?>
<dt><?= e(t('kri.data_source')) ?></dt><dd><?= e(t('enum.kds.' . $k['data_source'])) ?></dd>
<dt><?= e(t('kri.linked_risk')) ?></dt><dd><?= $linked ? '<a href="' . e(url('risks/' . $linked['id'])) . '">' . e($linked['risk_code']) . '</a> — ' . e($linked['title']) : '—' ?></dd></dl></section>
<section class="card"><h2><?= e(t('kri.trend')) ?></h2>
<?php if (!$chart): ?><p class="muted"><?= e(t('common.no_data')) ?></p><?php else: ?>
<div class="cols" role="list"><?php foreach ($chart as $r): $c = ['green' => '#2e7d32', 'amber' => '#f9a825', 'red' => '#c62828', 'critical' => '#6a0f0f'][$r['status']]; ?><a role="listitem" href="#r<?= (int)$r['id'] ?>" title="<?= e($r['reading_date'] . ': ' . \App\Core\Money::plain($r['value'])) ?>"><span class="cv"><?= e(\App\Core\Money::plain($r['value'])) ?></span><span class="col" style="height:<?= max(2, (int)round((float)$r['value'] / $mx * 120)) ?>px;background:<?= $c ?>"></span><span><?= e(substr($r['reading_date'], 2, 5)) ?></span></a><?php endforeach; ?></div><?php endif; ?></section>
</div>
<?php if (can('kri.report') && $k['is_active']): ?>
<section class="card" id="reading"><h2><?= e(t('kri.add_reading')) ?></h2>
<form method="post" action="<?= e(url('kris/' . $k['id'] . '/readings')) ?>" class="form-grid" novalidate><?= csrf_field() ?>
<?= ff('date', 'reading_date', 'kri.reading_date', date('Y-m-d'), ['required' => true]) ?>
<?php if ((int)$k['is_composite'] === 1): ?><div class="field"><span class="lbl"><?= e(t('kri.value')) ?></span><p><strong><?= $compositeNow === null ? e(t('kri.err_components_missing')) : e(\App\Core\Money::plain(\App\Core\Money::toDb($compositeNow)) . ' ' . $k['unit']) ?></strong></p><p class="hint"><?= e(t('kri.composite_auto')) ?></p></div>
<?php else: ?><?= ff('text', 'value', 'kri.value', '', ['required' => true, 'extra' => ' inputmode="decimal"']) ?><?php endif; ?>
<?= ff('select', 'period_id', 'kri.period', '', ['options' => opts($periods, fn($p) => lname($p))]) ?>
<?= ff('textarea', 'comment', 'kri.comment', '', ['wide' => true, 'hint' => t('kri.comment_hint')]) ?>
<div class="field"><button class="btn" type="submit"><?= e(t('common.save')) ?></button></div></form></section><?php endif; ?>
<section class="card"><h2><?= e(t('kri.readings')) ?></h2>
<div class="table-wrap"><table class="data"><thead><tr><th><?= e(t('kri.reading_date')) ?></th><th class="num"><?= e(t('kri.value')) ?></th><th><?= e(t('common.status')) ?></th><th><?= e(t('kri.period')) ?></th><th><?= e(t('kri.comment')) ?></th><th><?= e(t('audit.user')) ?></th></tr></thead><tbody>
<?php if (!$readings): ?><tr><td colspan="6" class="muted"><?= e(t('common.no_data')) ?></td></tr><?php endif; ?>
<?php foreach ($readings as $r): ?><tr id="r<?= (int)$r['id'] ?>"><td class="nowrap"><?= e($r['reading_date']) ?></td><td class="num"><?= e(\App\Core\Money::plain($r['value']) . ' ' . $k['unit']) ?></td><td><?= status_badge($r['status']) ?></td><td><?= e(lname($r, 'p')) ?></td><td class="pre"><?= e($r['comment'] ?? '') ?></td><td><?= e($r['full_name']) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php if ($breaches): ?><section class="card" id="breaches"><h2><?= icon('alert') ?> <?= e(t('kri.breaches')) ?></h2><p class="muted"><?= e(t('kri.breaches_hint')) ?></p>
<?php foreach ($breaches as $b): ?><div class="note-list big"><ul class="note-list big"><li class="<?= $b['status'] === 'open' ? 'due' : 'was-done' ?>"><div><strong><?= e(fdate($b['reading_date'])) ?></strong> — <?= e(\App\Core\Money::plain($b['value']) . ' ' . $k['unit']) ?> <?= status_badge($b['level']) ?> <span class="badge"><?= e(t('kri.breach_' . $b['status'])) ?></span><?= $b['escalated_at'] ? ' <span class="badge danger">' . e(t('kri.escalated')) . '</span>' : '' ?></div>
<?php if ($b['status'] === 'open' && can('kri.report')): ?><form method="post" action="<?= e(url('kris/' . $k['id'] . '/breaches/' . $b['id'])) ?>" novalidate><?= csrf_field() ?>
<div class="field"><label for="j<?= (int)$b['id'] ?>"><?= e(t('kri.justification')) ?> *</label><textarea id="j<?= (int)$b['id'] ?>" name="justification" rows="2"><?= e($b['justification'] ?? '') ?></textarea></div>
<div class="field"><label for="a<?= (int)$b['id'] ?>"><?= e(t('kri.action_plan')) ?> *</label><textarea id="a<?= (int)$b['id'] ?>" name="action_plan" rows="2"><?= e($b['action_plan'] ?? '') ?></textarea></div>
<div class="field"><label class="check"><input type="checkbox" name="resolve" value="1"> <?= e(t('kri.resolve_breach')) ?></label></div>
<div class="field"><label for="n<?= (int)$b['id'] ?>"><?= e(t('kri.resolution_note')) ?></label><textarea id="n<?= (int)$b['id'] ?>" name="resolution_note" rows="2"></textarea></div>
<button class="btn" type="submit"><?= e(t('common.save')) ?></button></form>
<?php else: ?><dl class="details"><dt><?= e(t('kri.justification')) ?></dt><dd class="pre"><?= e($b['justification'] ?? '—') ?></dd><dt><?= e(t('kri.action_plan')) ?></dt><dd class="pre"><?= e($b['action_plan'] ?? '—') ?></dd><dt><?= e(t('kri.resolution_note')) ?></dt><dd class="pre"><?= e($b['resolution_note'] ?? '—') ?></dd></dl><?php endif; ?></li></ul></div><?php endforeach; ?></section><?php endif; ?>
<?php if ($components): ?><section class="card"><h2><?= e(t('kri.components')) ?></h2><div class="table-wrap"><table class="data"><thead><tr><th><?= e(t('kri.id')) ?></th><th><?= e(t('kri.name')) ?></th><th class="num"><?= e(t('kri.weight')) ?></th><th class="num"><?= e(t('kri.last_value')) ?></th></tr></thead><tbody>
<?php foreach ($components as $cm): ?><tr><td><a href="<?= e(url('kris/' . $cm['id'])) ?>"><?= e($cm['code']) ?></a></td><td><?= e($cm['name']) ?></td><td class="num"><?= e(rtrim(rtrim(number_format($cm['weight_milli'] / 1000, 3, '.', ''), '0'), '.')) ?></td><td class="num"><?= $cm['last_value'] !== null ? e(\App\Core\Money::plain($cm['last_value']) . ' ' . $cm['unit']) : '—' ?></td></tr><?php endforeach; ?></tbody></table></div></section><?php endif; ?>
