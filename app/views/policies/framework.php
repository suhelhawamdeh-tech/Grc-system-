<?php /** @var array $byLevel @var array $missing @var array $stats */ ?>
<div class="page-head"><div><h1><?= icon('layers') ?> <?= e(t('docs.framework')) ?></h1><p class="sub"><?= e(t('docs.framework_hint')) ?></p></div>
<div class="actions"><a class="btn secondary" href="<?= e(url('policies')) ?>"><?= e(t('pol.title_plural')) ?></a><?php if (can('policy.manage')): ?><a class="btn" href="<?= e(url('policies/new')) ?>"><?= e(t('pol.new')) ?></a><?php endif; ?></div></div>
<div class="kpis"><div class="kpi"><div class="v"><?= (int)$stats['total'] ?></div><div class="l"><?= e(t('docs.stat_total')) ?></div></div><div class="kpi"><div class="v"><?= (int)$stats['approved'] ?></div><div class="l"><?= e(t('enum.pstatus.approved')) ?></div></div>
<div class="kpi"><div class="v"><?= (int)$stats['draft'] ?></div><div class="l"><?= e(t('enum.pstatus.draft')) ?></div></div><div class="kpi"><div class="v"><?= count($missing) ?></div><div class="l"><?= e(t('docs.stat_missing')) ?></div></div>
<div class="kpi"><div class="v"><?= (int)$stats['review_due'] ?></div><div class="l"><?= e(t('pol.review_overdue_only')) ?></div></div></div>
<?php if (can('policy.manage')): ?><section class="card"><h2><?= icon('file') ?> <?= e(t('docs.import')) ?></h2><p class="muted"><?= e(t('docs.import_hint')) ?></p>
<form method="post" enctype="multipart/form-data" action="<?= e(url('policies/import')) ?>" class="inline-form"><?= csrf_field() ?><input type="file" name="files[]" multiple required accept=".docx,.xlsx,.pdf" aria-label="<?= e(t('docs.choose_files')) ?>"> <button class="btn gold" type="submit"><?= e(t('docs.import_submit')) ?></button></form>
<p class="muted small"><?= icon('lock') ?> <?= e(t('docs.import_privacy')) ?></p></section><?php endif; ?>
<?php if ($missing): ?><section class="card notice"><h2><?= e(t('docs.missing_title')) ?></h2><p class="muted"><?= e(t('docs.missing_hint')) ?></p><ul>
<?php foreach ($missing as $code => $by): ?><li><span class="code" dir="ltr"><?= e($code) ?></span> — <?= e(t('docs.referenced_by')) ?> <?= implode('، ', array_map(fn($c) => '<span class="code" dir="ltr">' . e($c) . '</span>', $by)) ?></li><?php endforeach; ?></ul></section><?php endif; ?>
<?php if (!$byLevel): ?><section class="card"><p class="muted"><?= e(t('docs.empty')) ?></p></section><?php endif; ?>
<?php foreach (array_merge(\App\Support\Framework::LEVELS, [0]) as $lvl): if (empty($byLevel[$lvl])) continue; ?>
<section class="card level"><h2><span class="lvl">L<?= $lvl ?: '?' ?></span> <?= e($lvl ? t('enum.doclevel.' . $lvl) : t('docs.level_unset')) ?></h2><p class="muted small"><?= e($lvl ? t(sprintf('docs.level_desc_%d', $lvl)) : '') ?></p>
<div class="table-wrap"><table class="data"><thead><tr><th><?= e(t('docs.code')) ?></th><th><?= e(t('pol.name')) ?></th><th><?= e(t('pol.category')) ?></th><th><?= e(t('docs.section')) ?></th><th><?= e(t('pol.version')) ?></th><th><?= e(t('docs.classification')) ?></th><th><?= e(t('pol.status')) ?></th></tr></thead><tbody>
<?php foreach ($byLevel[$lvl] as $d): ?><tr><td class="nowrap" dir="ltr"><a href="<?= e(url('policies/' . $d['id'])) ?>"><?= e($d['code']) ?></a></td><td><?= $d['parent_id'] ? '<span class="muted">↳</span> ' : '' ?><a href="<?= e(url('policies/' . $d['id'])) ?>"><?= e($d['title']) ?></a><?= $d['approval_body'] ? '<br><span class="muted small">' . e(t('docs.approved_by_short', ['b' => $d['approval_body']])) . '</span>' : '' ?></td>
<td><?= e(t('enum.pcat.' . $d['category'])) ?></td><td><?= e($d['rm_section'] ? t('enum.rmsec.' . $d['rm_section']) : '—') ?></td><td><?= e($d['version_label'] ?? '—') ?></td><td><?= cls_badge($d['classification']) ?></td><td><?= status_badge($d['status']) ?></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<?php endforeach; ?>
