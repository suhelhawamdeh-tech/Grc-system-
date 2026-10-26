<?php /** @var array $rows @var array $f @var array $standing */ $tab = 'calendar'; $loc = locale(); $today = date('Y-m-d'); ?>
<div class="page-head"><div><h1><?= icon('tasks') ?> <?= e(t('rm.calendar')) ?></h1><p class="sub"><?= e(t('rm.calendar_hint')) ?></p></div></div>
<?php require __DIR__ . '/_tabs.php'; ?>
<form method="get" action="<?= e(url('rm/calendar')) ?>" class="card filters" role="search">
<?= ff('text', 'q', 'common.search', $f['q']) ?>
<?= ff('select', 'section', 'docs.section', $f['section'], ['options' => opts(\App\Support\Framework::SECTIONS, fn($x) => t('enum.rmsec.' . $x), null) ?: [], 'blank_label' => 'common.all']) ?>
<?= ff('select', 'status', 'pol.status', $f['status'], ['blank' => false, 'options' => ['open' => t('enum.rmts.open'), 'overdue' => t('my.overdue'), 'done' => t('enum.rmts.done'), 'not_applicable' => t('enum.rmts.not_applicable'), 'all' => t('common.all')]]) ?>
<div class="field actions"><button class="btn" type="submit"><?= e(t('common.filter')) ?></button><a class="btn secondary" href="<?= e(url('rm/calendar')) ?>"><?= e(t('common.reset')) ?></a></div></form>
<?php if (!$rows): ?><section class="card"><p class="muted"><?= e(t('common.no_results')) ?></p></section><?php endif; ?>
<?php foreach ($rows as $t): $late = $t['status'] === 'open' && $t['due_date'] < $today; ?>
<section class="card task<?= $late ? ' late' : '' ?>" id="t<?= (int)$t['id'] ?>"><h3><span class="code" dir="ltr"><?= e($t['code']) ?></span> <?= e($t['title_' . $loc] ?: $t['title_en']) ?></h3>
<p class="muted small"><?= e(t('enum.rmsec.' . $t['section'])) ?> · <?= e(t('enum.rmfreq.' . $t['frequency'])) ?> · <?= e(t('rm.period')) ?>: <?= e($t['period_key']) ?> · <?= e(t('rm.due')) ?>: <?= e(fdate($t['due_date'])) ?><?= $late ? ' <span class="badge danger">' . e(t('my.overdue')) . '</span>' : '' ?> · <?= e(t('rm.approver')) ?>: <?= e(t('enum.rmappr.' . $t['approver'])) ?><?= $t['full_name'] ? ' · ' . e($t['full_name']) : '' ?></p>
<p><?= e(t('rm.expected_output')) ?>: <?= e($t['output_' . $loc] ?: $t['output_en']) ?></p>
<?php if ($t['status'] !== 'open'): ?><p><span class="badge <?= $t['status'] === 'done' ? 'ok' : '' ?>"><?= e(t('enum.rmts.' . $t['status'])) ?></span> <?= e($t['output_ref'] ?? '') ?> <?= $t['note'] ? '— ' . e($t['note']) : '' ?> <span class="muted small"><?= e($t['done_name'] ?? '') ?> · <?= e(fdatetime($t['completed_at'])) ?></span></p>
<?php elseif (can('rm.work')): ?><form method="post" action="<?= e(url('rm/tasks/' . $t['id'])) ?>" class="form-grid" novalidate><?= csrf_field() ?>
<?= ff('select', 'result', 'rm.result', 'done', ['blank' => false, 'options' => ['done' => t('rm.mark_done'), 'not_applicable' => t('rm.mark_na')]]) ?>
<?= ff('text', 'output_ref', 'rm.output_ref', '', ['hint' => t('rm.output_ref_hint')]) ?><?= ff('text', 'note', 'rm.note', '') ?>
<div class="field"><button class="btn small" type="submit"><?= e(t('common.save')) ?></button></div></form><?php endif; ?></section>
<?php endforeach; ?>
<?php if ($standing): ?><details class="card"><summary><strong><?= e(t('rm.standing')) ?></strong> <span class="muted small">(<?= count($standing) ?>)</span></summary><p class="muted"><?= e(t('rm.standing_hint')) ?></p><ul>
<?php foreach ($standing as $s): ?><li><span class="code" dir="ltr"><?= e($s['code']) ?></span> <?= e($s['title_' . $loc] ?: $s['title_en']) ?> <span class="muted small"><?= e(t('enum.rmfreq.' . $s['frequency'])) ?> · <?= e(t('enum.rmsec.' . $s['section'])) ?></span></li><?php endforeach; ?></ul></details><?php endif; ?>
