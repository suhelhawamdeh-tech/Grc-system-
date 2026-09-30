<?php $mx = max(1.0, ...array_map(fn($r) => (float)$r['value'], $readings ?: [['value' => 1]])); $chart = array_reverse(array_slice($readings, 0, 12)); ?>
<div class="page-head"><div><h1><?= e($k['code']) ?> — <?= e($k['name']) ?></h1><p class="sub"><?= e(lname($k, 'dept')) ?> · <?= e($k['owner_name']) ?> · <?= e(t('enum.kfreq.' . $k['frequency'])) ?></p></div>
  <div class="actions"><a class="btn secondary" href="<?= e(url('kris')) ?>"><?= e(t('common.back')) ?></a><?php if (can('kri.manage')): ?><a class="btn" href="<?= e(url('kris/' . $k['id'] . '/edit')) ?>"><?= e(t('common.edit')) ?></a><?php endif; ?></div></div>
<div class="grid cols-2">
<section class="card"><h2><?= e(t('kri.definition')) ?></h2><dl class="details">
<dt><?= e(t('kri.description')) ?></dt><dd class="pre"><?= e($k['description'] ?? '—') ?></dd>
<dt><?= e(t('kri.direction')) ?></dt><dd><?= e(t('enum.dir.' . $k['direction'])) ?></dd>
<dt><?= e(t('kri.amber')) ?></dt><dd><span class="badge warn"><?= e(\App\Core\Money::plain($k['amber_threshold']) . ' ' . $k['unit']) ?></span></dd>
<dt><?= e(t('kri.red')) ?></dt><dd><span class="badge danger"><?= e(\App\Core\Money::plain($k['red_threshold']) . ' ' . $k['unit']) ?></span></dd>
<dt><?= e(t('kri.linked_risk')) ?></dt><dd><?= $linked ? '<a href="' . e(url('risks/' . $linked['id'])) . '">' . e($linked['risk_code']) . '</a> — ' . e($linked['title']) : '—' ?></dd></dl></section>
<section class="card"><h2><?= e(t('kri.trend')) ?></h2>
<?php if (!$chart): ?><p class="muted"><?= e(t('common.no_data')) ?></p><?php else: ?>
<div class="cols" role="list"><?php foreach ($chart as $r): $c = ['green' => '#2e7d32', 'amber' => '#f9a825', 'red' => '#c62828'][$r['status']]; ?><a role="listitem" href="#r<?= (int)$r['id'] ?>" title="<?= e($r['reading_date'] . ': ' . \App\Core\Money::plain($r['value'])) ?>"><span class="cv"><?= e(\App\Core\Money::plain($r['value'])) ?></span><span class="col" style="height:<?= max(2, (int)round((float)$r['value'] / $mx * 120)) ?>px;background:<?= $c ?>"></span><span><?= e(substr($r['reading_date'], 2, 5)) ?></span></a><?php endforeach; ?></div><?php endif; ?></section>
</div>
<?php if (can('kri.report') && $k['is_active']): ?>
<section class="card" id="reading"><h2><?= e(t('kri.add_reading')) ?></h2>
<form method="post" action="<?= e(url('kris/' . $k['id'] . '/readings')) ?>" class="form-grid" novalidate><?= csrf_field() ?>
<?= ff('date', 'reading_date', 'kri.reading_date', date('Y-m-d'), ['required' => true]) ?>
<?= ff('text', 'value', 'kri.value', '', ['required' => true, 'extra' => ' inputmode="decimal"']) ?>
<?= ff('select', 'period_id', 'kri.period', '', ['options' => opts($periods, fn($p) => lname($p))]) ?>
<?= ff('textarea', 'comment', 'kri.comment', '', ['wide' => true, 'hint' => t('kri.comment_hint')]) ?>
<div class="field"><button class="btn" type="submit"><?= e(t('common.save')) ?></button></div></form></section><?php endif; ?>
<section class="card"><h2><?= e(t('kri.readings')) ?></h2>
<div class="table-wrap"><table class="data"><thead><tr><th><?= e(t('kri.reading_date')) ?></th><th class="num"><?= e(t('kri.value')) ?></th><th><?= e(t('common.status')) ?></th><th><?= e(t('kri.period')) ?></th><th><?= e(t('kri.comment')) ?></th><th><?= e(t('audit.user')) ?></th></tr></thead><tbody>
<?php if (!$readings): ?><tr><td colspan="6" class="muted"><?= e(t('common.no_data')) ?></td></tr><?php endif; ?>
<?php foreach ($readings as $r): ?><tr id="r<?= (int)$r['id'] ?>"><td class="nowrap"><?= e($r['reading_date']) ?></td><td class="num"><?= e(\App\Core\Money::plain($r['value']) . ' ' . $k['unit']) ?></td><td><?= status_badge($r['status']) ?></td><td><?= e(lname($r, 'p')) ?></td><td class="pre"><?= e($r['comment'] ?? '') ?></td><td><?= e($r['full_name']) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
