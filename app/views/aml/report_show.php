<?php /** @var array $r @var array $attachments */ $tab = 'reports'; ?>
<div class="page-head"><div><h1><?= icon('file') ?> <?= e($r['code']) ?> — <?= e($r['title']) ?></h1><p class="sub"><?= e(t('enum.amlrt.' . $r['report_type'])) ?><?= (int)$r['confidential'] ? ' · <span class="badge danger">' . e(t('aml.confidential')) . '</span>' : '' ?></p></div>
  <div class="actions"><a class="btn secondary" href="<?= e(url('aml/reports')) ?>"><?= e(t('common.back')) ?></a><?php if (can('aml.manage') && $r['status'] === 'draft'): ?><a class="btn" href="<?= e(url('aml/reports/' . $r['id'] . '/edit')) ?>"><?= e(t('common.edit')) ?></a><?php endif; ?></div></div>
<?php require __DIR__ . '/_tabs.php'; ?>
<section class="card"><dl class="details"><dt><?= e(t('aml.status')) ?></dt><dd><span class="badge <?= $r['status'] === 'submitted' ? 'ok' : 'warn' ?>"><?= e(t('enum.amlrs.' . $r['status'])) ?></span></dd>
<dt><?= e(t('aml.recipient')) ?></dt><dd><?= e($r['recipient']) ?></dd><dt><?= e(t('aml.r_due')) ?></dt><dd><?= e(fdate($r['due_date'])) ?></dd>
<?php if ($r['customer_id']): ?><dt><?= e(t('aml.customer')) ?></dt><dd><a href="<?= e(url('aml/customers/' . $r['customer_id'])) ?>"><?= e($r['customer_ref'] . ' — ' . $r['full_name']) ?></a></dd><?php endif; ?>
<?php if ($r['status'] === 'submitted'): ?><dt><?= e(t('aml.submitted_on')) ?></dt><dd><?= e(fdate($r['submitted_on'])) ?> — <?= e($r['submitter']) ?></dd><dt><?= e(t('aml.reference')) ?></dt><dd><?= e($r['reference_no'] ?? '—') ?></dd><?php endif; ?>
<dt><?= e(t('aml.r_summary')) ?></dt><dd class="pre"><?= e($r['summary']) ?></dd></dl></section>
<?php if (can('aml.manage') && $r['status'] === 'draft'): ?><section class="card" id="submit"><h2><?= e(t('aml.mark_submitted')) ?></h2><p class="muted"><?= e(t('aml.mark_submitted_hint')) ?></p>
<form method="post" action="<?= e(url('aml/reports/' . $r['id'] . '/submit')) ?>" novalidate><?= csrf_field() ?><div class="form-grid"><?= ff('date', 'submitted_on', 'aml.submitted_on', date('Y-m-d'), ['required' => true]) ?><?= ff('text', 'reference_no', 'aml.reference', '') ?></div>
<button class="btn gold" type="submit"><?= e(t('aml.mark_submitted')) ?></button></form></section><?php endif; ?>
<section class="card" id="attachments"><h2><?= e(t('upload.title')) ?></h2>
<?php if (!$attachments): ?><p class="muted"><?= e(t('upload.empty')) ?></p><?php else: ?><ul><?php foreach ($attachments as $a): ?><li><a href="<?= e(url('attachments/' . $a['id'])) ?>"><?= e($a['original_name']) ?></a></li><?php endforeach; ?></ul><?php endif; ?>
<?php if (can('aml.manage')): ?><form method="post" enctype="multipart/form-data" action="<?= e(url('aml/reports/' . $r['id'] . '/attachments')) ?>"><?= csrf_field() ?><div class="field"><label for="file"><?= e(t('upload.choose')) ?></label><input type="file" id="file" name="file" required></div><button class="btn secondary" type="submit"><?= e(t('upload.submit')) ?></button></form><?php endif; ?></section>
