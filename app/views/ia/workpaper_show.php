<?php /** @var array $w @var array $attachments @var int $me */
$tab = 'plans'; $mg = can('ia.manage'); $editable = $mg && $w['e_status'] === 'fieldwork' && $w['status'] !== 'reviewed'; ?>
<div class="page-head"><div><h1><?= icon('clipboard') ?> <?= e($w['ref']) ?> — <?= e($w['title']) ?></h1><p class="sub"><a href="<?= e(url('ia/engagements/' . $w['engagement_id'])) ?>"><?= e($w['e_code'] . ' — ' . $w['e_title']) ?></a></p></div>
  <div class="actions"><a class="btn secondary" href="<?= e(url('ia/engagements/' . $w['engagement_id'])) ?>"><?= e(t('common.back')) ?></a></div></div>
<?php require __DIR__ . '/_tabs.php'; ?>
<section class="card"><dl class="details"><dt><?= e(t('ia.status')) ?></dt><dd><?= e(t('enum.iawp.' . $w['status'])) ?></dd>
<dt><?= e(t('ia.control')) ?></dt><dd><?= $w['control_id'] ? '<a href="' . e(url('controls/' . $w['control_id'])) . '">' . e($w['ctl_code'] . ' — ' . $w['ctl_name']) . '</a>' : '—' ?></dd>
<dt><?= e(t('ia.procedure')) ?></dt><dd class="pre"><?= e($w['procedure_text']) ?></dd>
<?php if ($w['conclusion']): ?><dt><?= e(t('ia.sample')) ?></dt><dd><?= (int)$w['sample_size'] ?> · <?= e(t('ia.exceptions')) ?>: <?= (int)$w['exceptions'] ?></dd><dt><?= e(t('ia.conclusion')) ?></dt><dd><?= e(t('enum.iaconc.' . $w['conclusion'])) ?></dd><dt><?= e(t('ia.result')) ?></dt><dd class="pre"><?= e($w['result_text']) ?></dd><?php endif; ?>
<dt><?= e(t('ia.prepared_by')) ?></dt><dd><?= e($w['preparer'] ?? '—') ?> <?= $w['prepared_at'] ? '<span class="muted">' . e(fdatetime($w['prepared_at'])) . '</span>' : '' ?></dd>
<dt><?= e(t('ia.reviewed_by')) ?></dt><dd><?= e($w['reviewer'] ?? '—') ?> <?= $w['reviewed_at'] ? '<span class="muted">' . e(fdatetime($w['reviewed_at'])) . '</span>' : '' ?></dd>
<?php if ($w['review_note']): ?><dt><?= e(t('ia.review_note')) ?></dt><dd class="pre"><?= e($w['review_note']) ?></dd><?php endif; ?></dl></section>
<?php if ($editable): ?><section class="card" id="result"><h2><?= e(t('ia.record_result')) ?></h2><form method="post" action="<?= e(url('ia/workpapers/' . $w['id'])) ?>" novalidate><?= csrf_field() ?><div class="form-grid">
<?= ff('number', 'sample_size', 'ia.sample', $w['sample_size'] ?? '', ['required' => true, 'extra' => ' min="0"']) ?><?= ff('number', 'exceptions', 'ia.exceptions', $w['exceptions'] ?? '', ['required' => true, 'extra' => ' min="0"']) ?>
<?= ff('select', 'conclusion', 'ia.conclusion', $w['conclusion'] ?? '', ['required' => true, 'options' => opts(['effective', 'partially_effective', 'ineffective', 'not_applicable'], fn($x) => t('enum.iaconc.' . $x), null)]) ?>
<?= ff('textarea', 'result_text', 'ia.result', $w['result_text'] ?? '', ['required' => true, 'wide' => true, 'extra' => ' rows="5"', 'hint' => t('ia.result_hint')]) ?></div>
<button class="btn" type="submit"><?= e(t('ia.mark_prepared')) ?></button></form></section><?php endif; ?>
<?php if ($mg && $w['status'] === 'prepared' && $w['e_status'] === 'fieldwork'): ?><section class="card" id="review"><h2><?= e(t('ia.review')) ?></h2>
<?php if ((int)$w['prepared_by'] === $me): ?><p class="muted"><?= e(t('ia.err_self_review')) ?></p><?php else: ?>
<form method="post" action="<?= e(url('ia/workpapers/' . $w['id'] . '/review')) ?>" novalidate><?= csrf_field() ?>
<?= ff('select', 'decision', 'ia.decision', '', ['required' => true, 'options' => ['approve' => t('ia.approve'), 'return' => t('ia.return')]]) ?><?= ff('textarea', 'review_note', 'ia.review_note', '', ['hint' => t('ia.review_note_hint')]) ?>
<button class="btn" type="submit"><?= e(t('ia.submit_review')) ?></button></form><?php endif; ?></section><?php endif; ?>
<section class="card" id="attachments"><h2><?= e(t('ia.evidence')) ?></h2>
<?php if (!$attachments): ?><p class="muted"><?= e(t('upload.empty')) ?></p><?php else: ?><ul><?php foreach ($attachments as $a): ?><li><a href="<?= e(url('attachments/' . $a['id'])) ?>"><?= e($a['original_name']) ?></a> <span class="muted small"><?= e($a['full_name']) ?></span></li><?php endforeach; ?></ul><?php endif; ?>
<?php if ($mg && $w['e_status'] === 'fieldwork'): ?><form method="post" enctype="multipart/form-data" action="<?= e(url('ia/workpapers/' . $w['id'] . '/attachments')) ?>"><?= csrf_field() ?><div class="field"><label for="file"><?= e(t('upload.choose')) ?></label><input type="file" id="file" name="file" required></div><button class="btn secondary" type="submit"><?= e(t('upload.submit')) ?></button></form><?php endif; ?></section>
