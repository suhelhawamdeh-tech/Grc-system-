<?php /** @var array $rows */ ?>
<div class="page-head"><div><h1><?= icon('file') ?> <?= e(t('brr.title')) ?></h1><p class="sub"><?= e(t('brr.subtitle')) ?></p></div></div>
<div class="card notice"><p><?= e(t('brr.principle')) ?></p></div>
<?php if (can('rm.work')): ?><section class="card"><h2><?= e(t('brr.new')) ?></h2><form method="post" action="<?= e(url('board-reports')) ?>" class="form-grid" novalidate><?= csrf_field() ?>
<?php $q = (int)ceil(date('n') / 3) - 1; $y = (int)date('Y'); if ($q === 0) { $q = 4; $y--; } ?>
<?= ff('number', 'year', 'icaap.year', $y, ['required' => true, 'extra' => ' min="2000" max="2100"']) ?>
<?= ff('select', 'quarter', 'brr.quarter', $q, ['required' => true, 'blank' => false, 'options' => [1 => 'Q1', 2 => 'Q2', 3 => 'Q3', 4 => 'Q4']]) ?>
<?= ff('text', 'signatory_name', 'brr.signatory_name', $me['full_name'] ?? '') ?>
<?= ff('text', 'signatory_title', 'brr.signatory_title', t('rm.director')) ?>
<div class="field"><button class="btn gold" type="submit"><?= e(t('brr.create')) ?></button></div></form><p class="muted small"><?= e(t('brr.new_hint')) ?></p></section><?php endif; ?>
<div class="table-wrap"><table class="data"><thead><tr><th><?= e(t('brr.code')) ?></th><th><?= e(t('brr.period')) ?></th><th><?= e(t('brr.report_date')) ?></th><th><?= e(t('common.status')) ?></th><th class="num"><?= e(t('brr.n_rec')) ?></th><th><?= e(t('icaap.board_decision')) ?></th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="6" class="muted"><?= e(t('brr.empty')) ?></td></tr><?php endif; ?>
<?php foreach ($rows as $b): ?><tr><td><a href="<?= e(url('board-reports/' . $b['id'])) ?>"><strong><?= e($b['code']) ?></strong></a></td><td><?= e(t('brr.quarter_label', ['q' => $b['quarter'], 'y' => $b['year']])) ?></td><td><?= e(fdate($b['report_date'])) ?></td>
<td><span class="badge<?= $b['status'] === 'approved' ? ' ok' : '' ?>"><?= e(t('enum.brst.' . $b['status'])) ?></span></td><td class="num"><?= (int)$b['n_rec'] ?></td><td><?= e($b['board_decision'] ? $b['board_decision'] . ' · ' . fdate($b['board_date']) : '—') ?></td></tr><?php endforeach; ?></tbody></table></div>
