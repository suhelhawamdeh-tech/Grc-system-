<?php /** @var array $groups @var array $counts @var int $missing @var array $esc @var array $departments @var int $myDept */
$arrow = ['worse' => '▲', 'better' => '▼', 'stable' => '●']; ?>
<div class="page-head"><div><h1><?= icon('target') ?> <?= e(t('raf.dashboard')) ?></h1><p class="sub"><?= e(t('raf.dashboard_hint')) ?></p></div>
<div class="actions"><a class="btn secondary" href="<?= e(url('appetite')) ?>"><?= e(t('nav.appetite')) ?></a><a class="btn secondary" href="<?= e(url('kris')) ?>"><?= e(t('nav.kris')) ?></a></div></div>
<div class="kpis"><div class="kpi"><div class="v" style="color:#2e7d32"><?= (int)$counts['green'] ?></div><div class="l"><?= e(t('raf.green')) ?></div></div>
<div class="kpi"><div class="v" style="color:#b26a00"><?= (int)$counts['amber'] ?></div><div class="l"><?= e(t('raf.amber')) ?></div></div>
<div class="kpi<?= $counts['red'] ? ' alert' : '' ?>"><div class="v"><?= (int)$counts['red'] ?></div><div class="l"><?= e(t('raf.red')) ?></div></div>
<div class="kpi"><div class="v"><?= (int)$counts['none'] ?></div><div class="l"><?= e(t('raf.no_reading')) ?></div></div>
<div class="kpi"><div class="v"><?= (int)$counts['uncalibrated'] ?></div><div class="l"><?= e(t('raf.uncalibrated')) ?></div></div></div>
<div class="card notice"><p><?= e(t('raf.rules')) ?></p></div>
<?php if ($missing && can('appetite.manage') && can('kri.manage')): ?><section class="card"><h2><?= e(t('raf.template')) ?></h2><p class="muted"><?= e(t('raf.template_hint', ['n' => $missing])) ?></p>
<form method="post" action="<?= e(url('appetite/framework/template')) ?>" class="form-grid" novalidate><?= csrf_field() ?>
<?= ff('select', 'department_id', 'raf.owner_department', $myDept ?: '', ['required' => true, 'options' => opts($departments, fn($d) => lname($d)), 'hint' => t('raf.owner_department_hint')]) ?>
<div class="field"><button class="btn gold" type="submit"><?= e(t('raf.create_template')) ?></button></div></form></section><?php endif; ?>
<?php if (!$groups): ?><section class="card"><p class="muted"><?= e(t('raf.empty')) ?></p></section><?php endif; ?>
<?php foreach ($groups as $cat => $metrics): ?><section class="card"><h2><?= e($cat === 'other' ? t('raf.other') : t('enum.rafcat.' . $cat)) ?></h2>
<div class="table-wrap"><table class="data"><thead><tr><th><?= e(t('raf.metric')) ?></th><th class="num"><?= e(t('raf.appetite_value')) ?></th><th class="num"><?= e(t('kri.amber')) ?></th><th class="num"><?= e(t('kri.red')) ?></th><th class="num"><?= e(t('raf.current_value')) ?></th><th><?= e(t('raf.trend')) ?></th><th><?= e(t('raf.report_frequency')) ?></th><th><?= e(t('docs.section')) ?></th></tr></thead><tbody>
<?php foreach ($metrics as $k): $cal = (int)$k['calibrated'] === 1; $u = ' ' . $k['unit']; ?><tr<?= $cal ? '' : ' class="muted"' ?>><td><a href="<?= e(url('kris/' . $k['id'])) ?>"><span class="code" dir="ltr"><?= e($k['code']) ?></span> <?= e($k['name']) ?></a><?= (int)$k['is_regulatory'] ? ' <span class="badge danger">' . e(t('raf.regulatory')) . '</span>' : '' ?><br><span class="muted small"><?= e(t('enum.dir.' . $k['direction'])) ?></span></td>
<?php if (!$cal): ?><td colspan="3"><span class="badge warn"><?= e(t('raf.uncalibrated')) ?></span></td><?php else: ?>
<td class="num"><?= $k['appetite_value'] !== null ? e(\App\Core\Money::plain($k['appetite_value']) . $u) : '—' ?></td><td class="num"><?= e(\App\Core\Money::plain($k['amber_threshold']) . $u) ?></td><td class="num"><?= e(\App\Core\Money::plain($k['red_threshold']) . $u) ?></td><?php endif; ?>
<td class="num"><?php if ($k['last']): ?><?= status_badge($k['last']['status']) ?> <?= e(\App\Core\Money::plain($k['last']['value']) . $u) ?><br><span class="muted small"><?= e(fdate($k['last']['reading_date'])) ?></span><?php else: ?>—<?php endif; ?></td>
<td><?= $k['trend'] ? '<span class="trend ' . e($k['trend']) . '" title="' . e(t(sprintf('raf.trend_%s', $k['trend']))) . '">' . $arrow[$k['trend']] . ' ' . e(t(sprintf('raf.trend_%s', $k['trend']))) . '</span>' : '—' ?></td>
<td><?= e($k['report_frequency'] ? t('enum.rmfreq.' . $k['report_frequency']) : t('enum.kfreq.' . $k['frequency'])) ?></td><td><?= e($k['monitor_section'] ? t('enum.rmsec.' . $k['monitor_section']) : '—') ?></td></tr><?php endforeach; ?></tbody></table></div></section><?php endforeach; ?>
<?php if ($esc && can('rm.view')): ?><section class="card"><h2><?= e(t('raf.recent_escalations')) ?></h2><ul class="history"><?php foreach ($esc as $x): ?><li><a href="<?= e(url('rm/escalations/' . $x['id'])) ?>"><span class="code" dir="ltr"><?= e($x['code']) ?></span> <?= e($x['title']) ?></a> <span class="muted small"><?= e(fdatetime($x['escalated_at'])) ?> · <?= e(t('enum.rmes.' . $x['status'])) ?></span></li><?php endforeach; ?></ul></section><?php endif; ?>
