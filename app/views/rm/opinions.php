<?php /** @var array $rows @var array $f @var array $kpi */ $tab = 'opinions'; ?>
<div class="page-head"><div><h1><?= icon('scale') ?> <?= e(t('rm.opinions')) ?></h1><p class="sub"><?= e(t('rm.opinions_hint')) ?></p></div>
<div class="actions"><?php if (can('rm.work')): ?><a class="btn gold" href="<?= e(url('rm/opinions/new')) ?>"><?= e(t('rm.opinion_new')) ?></a><?php endif; ?></div></div>
<?php require __DIR__ . '/_tabs.php'; ?>
<div class="kpis"><div class="kpi"><div class="v"><?= (int)($kpi['n'] ?? 0) ?></div><div class="l"><?= e(t('rm.kpi_issued_year')) ?></div></div>
<div class="kpi"><div class="v"><?= $kpi['days'] !== null ? e(number_format((float)$kpi['days'], 1)) : '—' ?></div><div class="l"><?= e(t('rm.kpi_turnaround')) ?></div></div>
<div class="kpi<?= (int)($kpi['contrary'] ?? 0) ? ' alert' : '' ?>"><div class="v"><?= (int)($kpi['contrary'] ?? 0) ?></div><div class="l"><?= e(t('rm.kpi_contrary')) ?></div></div></div>
<form method="get" action="<?= e(url('rm/opinions')) ?>" class="card filters" role="search">
<?= ff('select', 'status', 'pol.status', $f['status'], ['options' => ['draft' => t('enum.rmos.draft'), 'returned' => t('enum.rmos.returned'), 'submitted' => t('enum.rmos.submitted'), 'reviewed' => t('enum.rmos.reviewed'), 'issued' => t('enum.rmos.issued'), 'decided' => t('enum.rmos.decided'), 'contrary' => t('rm.contrary_only')], 'blank_label' => 'common.all']) ?>
<?= ff('select', 'section', 'docs.section', $f['section'], ['options' => opts(\App\Support\Framework::SECTIONS, fn($x) => t('enum.rmsec.' . $x), null) ?: [], 'blank_label' => 'common.all']) ?>
<?= ff('select', 'subject', 'rm.subject_type', $f['subject'], ['options' => opts(\App\Controllers\RiskFunctionController::SUBJECTS, fn($x) => t('enum.rmsubj.' . $x), null) ?: [], 'blank_label' => 'common.all']) ?>
<div class="field actions"><button class="btn" type="submit"><?= e(t('common.filter')) ?></button><a class="btn secondary" href="<?= e(url('rm/opinions')) ?>"><?= e(t('common.reset')) ?></a></div></form>
<div class="table-wrap"><table class="data"><thead><tr><th><?= e(t('rm.memo_no')) ?></th><th><?= e(t('rm.subject')) ?></th><th><?= e(t('rm.subject_type')) ?></th><th><?= e(t('rm.requesting_department')) ?></th><th><?= e(t('rm.received_on')) ?></th><th><?= e(t('rm.opinion')) ?></th><th><?= e(t('pol.status')) ?></th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="7" class="muted"><?= e(t('common.no_results')) ?></td></tr><?php endif; ?>
<?php foreach ($rows as $o): ?><tr><td class="nowrap" dir="ltr"><a href="<?= e(url('rm/opinions/' . $o['id'])) ?>"><?= e($o['code']) ?></a></td><td><a href="<?= e(url('rm/opinions/' . $o['id'])) ?>"><?= e($o['title']) ?></a><br><span class="muted small"><?= e(t('enum.rmsec.' . $o['section'])) ?> · <?= e($o['full_name']) ?></span></td>
<td><?= e(t('enum.rmsubj.' . $o['subject_type'])) ?></td><td><?= e($o['dept_ar'] ? lname(['name_en' => $o['dept_en'], 'name_ar' => $o['dept_ar']]) : '—') ?></td><td class="nowrap"><?= e(fdate($o['received_on'])) ?></td>
<td><?= $o['opinion'] ? op_badge($o['opinion']) : '—' ?></td><td><span class="badge"><?= e(t('enum.rmos.' . $o['status'])) ?></span><?= $o['contrary'] ? ' <span class="badge danger">' . e(t('rm.contrary')) . '</span>' : '' ?></td></tr><?php endforeach; ?></tbody></table></div>
