<?php /** @var array $rows */ $pct = fn($v) => $v === null ? '—' : number_format($v * 100, 2) . '%'; ?>
<div class="page-head"><div><h1><?= icon('scale') ?> <?= e(t('icaap.title')) ?></h1><p class="sub"><?= e(t('icaap.subtitle')) ?></p></div></div>
<div class="card notice"><p><?= e(t('icaap.principle')) ?></p></div>
<?php if (can('icaap.manage')): ?><section class="card"><h2><?= e(t('icaap.new_cycle')) ?></h2><form method="post" action="<?= e(url('icaap')) ?>" class="form-grid" novalidate><?= csrf_field() ?>
<?= ff('number', 'year', 'icaap.year', (int)date('Y') - (date('n') < 7 ? 1 : 0), ['required' => true, 'extra' => ' min="2000" max="2100"']) ?>
<?= ff('date', 'data_date', 'icaap.data_date', ((int)date('Y') - 1) . '-12-31', ['required' => true]) ?>
<?= ff('date', 'regulatory_deadline', 'icaap.deadline', '') ?>
<div class="field"><button class="btn gold" type="submit"><?= e(t('icaap.create')) ?></button></div></form><p class="muted small"><?= e(t('icaap.new_hint')) ?></p></section><?php endif; ?>
<div class="table-wrap"><table class="data"><thead><tr><th><?= e(t('icaap.year')) ?></th><th><?= e(t('icaap.data_date')) ?></th><th><?= e(t('icaap.status')) ?></th><th class="num"><?= e(t('icaap.car')) ?></th><th class="num"><?= e(t('icaap.margin')) ?></th><th class="num"><?= e(t('icaap.min_severe')) ?></th><th><?= e(t('icaap.board_decision')) ?></th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="7" class="muted"><?= e(t('icaap.empty')) ?></td></tr><?php endif; ?>
<?php foreach ($rows as $c): ?><tr><td><a href="<?= e(url('icaap/' . $c['id'])) ?>"><strong><?= (int)$c['year'] ?></strong></a></td><td><?= e(fdate($c['data_date'])) ?></td><td><span class="badge"><?= e(t('enum.icst.' . $c['status'])) ?></span></td>
<td class="num"><?= e($pct($c['res']['car'])) ?></td><td class="num"><?= $c['res']['margin'] === null ? '—' : e(number_format($c['res']['margin'], 0)) ?></td><td class="num"><?= e($pct($c['res']['min']['severe'] ?? null)) ?></td><td><?= e($c['board_decision'] ? $c['board_decision'] . ' · ' . fdate($c['board_date']) : '—') ?></td></tr><?php endforeach; ?></tbody></table></div>
