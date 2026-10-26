<?php /** @var array $e @var ?array $src */ $tab = 'escalations'; ?>
<div class="page-head"><div><h1><?= icon('flag') ?> <?= e($e['code']) ?> — <?= e($e['title']) ?></h1><p class="sub"><?= e(t('enum.rmsrc.' . $e['source_type'])) ?> · <?= e(t('enum.rmescto.' . $e['escalated_to'])) ?> · <span class="badge"><?= e(t('enum.rmes.' . $e['status'])) ?></span></p></div>
<div class="actions"><a class="btn secondary" href="<?= e(url('rm/escalations')) ?>"><?= e(t('common.back')) ?></a><a class="btn secondary" href="<?= e(url('rm/escalations/' . $e['id'] . '/docx')) ?>"><?= icon('file') ?> Word</a></div></div>
<?php require __DIR__ . '/_tabs.php'; ?>
<section class="card"><dl class="details">
<dt><?= e(t('rm.escalated_at')) ?></dt><dd><?= e(fdatetime($e['escalated_at'])) ?> · <?= e($e['full_name']) ?></dd>
<?php if ($src): ?><dt><?= e(t('rm.source')) ?></dt><dd><a href="<?= e($src['url']) ?>" dir="ltr"><?= e((string)$src['code']) ?></a></dd><?php endif; ?>
<dt><?= e(t('docs.section')) ?></dt><dd><?= e($e['section'] ? t('enum.rmsec.' . $e['section']) : '—') ?></dd>
<dt><?= e(t('rm.ceo_informed')) ?></dt><dd><?= e($e['ceo_informed'] ? t('common.yes') : t('rm.ceo_not_informed', ['r' => (string)$e['ceo_excluded_reason']])) ?></dd>
<dt><?= e(t('rm.esc_detail')) ?></dt><dd class="pre"><?= e($e['detail']) ?></dd>
<?php if ($e['response']): ?><dt><?= e(t('rm.esc_response')) ?></dt><dd class="pre"><?= e($e['response']) ?> · <?= e(fdate($e['response_on'])) ?></dd><?php endif; ?>
<?php if ($e['outcome']): ?><dt><?= e(t('rm.esc_outcome')) ?></dt><dd class="pre"><?= e($e['outcome']) ?> · <?= e(fdatetime($e['closed_at'])) ?></dd><?php endif; ?></dl></section>
<?php if (can('rm.work') && $e['status'] !== 'closed'): ?><section class="card" id="respond"><h2><?= e(t('rm.record_response')) ?></h2><p class="muted"><?= e(t('rm.response_hint')) ?></p>
<form method="post" action="<?= e(url('rm/escalations/' . $e['id'] . '/respond')) ?>" class="form-grid" novalidate><?= csrf_field() ?>
<?= ff('textarea', 'response', 'rm.esc_response', $e['response'] ?? '', ['required' => true, 'wide' => true]) ?><?= ff('date', 'response_on', 'rm.response_on', date('Y-m-d'), ['required' => true]) ?>
<div class="field"><button class="btn" type="submit"><?= e(t('common.save')) ?></button></div></form></section><?php endif; ?>
<?php if (can('rm.approve') && $e['status'] === 'responded'): ?><section class="card" id="close"><h2><?= e(t('rm.close_escalation')) ?></h2>
<form method="post" action="<?= e(url('rm/escalations/' . $e['id'] . '/close')) ?>" novalidate><?= csrf_field() ?><?= ff('textarea', 'outcome', 'rm.esc_outcome', '', ['required' => true]) ?><button class="btn gold" type="submit"><?= e(t('rm.close_escalation')) ?></button></form></section><?php endif; ?>
