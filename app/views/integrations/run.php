<?php /** @var array $r */ ?>
<div class="page-head"><div><h1><?= icon('link') ?> <?= e($title) ?></h1><p class="sub"><?= e(t(sprintf('t24.ds_%s', $r['dataset']))) ?> · <?= e(t('enum.t24mode.' . $r['mode'])) ?><?= $r['file_name'] ? ' · ' . e($r['file_name']) : '' ?></p></div>
  <div class="actions"><a class="btn secondary" href="<?= e(url('admin/integrations')) ?>"><?= e(t('common.back')) ?></a></div></div>
<section class="card"><dl class="details"><dt><?= e(t('t24.status')) ?></dt><dd><span class="badge <?= $r['status'] === 'success' ? 'ok' : 'danger' ?>"><?= e(t('enum.t24run.' . $r['status'])) ?></span></dd>
<dt><?= e(t('t24.when')) ?></dt><dd><?= e(fdatetime($r['started_at'])) ?> → <?= e(fdatetime($r['finished_at'])) ?></dd><dt><?= e(t('t24.by')) ?></dt><dd><?= e($r['full_name'] ?? t('t24.scheduled')) ?></dd>
<dt><?= e(t('t24.read')) ?></dt><dd><?= (int)$r['rows_read'] ?></dd><dt><?= e(t('t24.created')) ?></dt><dd><?= (int)$r['rows_created'] ?></dd><dt><?= e($r['dataset'] === 'gl' ? t('t24.replaced') : t('t24.updated')) ?></dt><dd><?= (int)$r['rows_updated'] ?></dd></dl>
<?php if ($r['status'] === 'success'): ?><p><?= e(t('t24.success_next')) ?> <a href="<?= e(url($r['dataset'] === 'gl' ? 'incidents/reconciliation' : 'aml/customers')) ?>"><?= e($r['dataset'] === 'gl' ? t('t24.open_recon') : t('t24.open_customers')) ?></a></p><?php endif; ?></section>
<?php if ($r['error_text']): ?><section class="card"><h2><?= e(t('t24.errors')) ?></h2><p class="muted"><?= e(t('t24.errors_hint')) ?></p><pre class="pre"><?= e($r['error_text']) ?></pre></section><?php endif; ?>
