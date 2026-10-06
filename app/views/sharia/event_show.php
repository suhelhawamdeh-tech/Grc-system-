<?php /** @var array $e @var array $attachments @var bool $canManage */
$sev = '<span class="badge ' . ($e['severity'] === 'high' ? 'danger' : ($e['severity'] === 'medium' ? 'warn' : '')) . '">' . e(t('enum.sev.' . $e['severity'])) . '</span>';
$steps = ['reported', 'under_review', 'decided', 'closed']; $cur = array_search($e['status'], $steps, true); ?>
<div class="page-head"><div><h1><?= icon('alert') ?> <?= e($e['code']) ?> — <?= e($e['title']) ?></h1><p class="sub"><?= $sev ?> <?= $e['escalated_at'] ? '<span class="badge danger">' . e(t('sha.escalated')) . ' · ' . e(fdatetime($e['escalated_at'])) . '</span>' : '' ?></p></div>
  <div class="actions"><a class="btn secondary" href="<?= e(url('sharia/events')) ?>"><?= e(t('common.back')) ?></a><?php if ($canManage && $e['status'] !== 'closed'): ?><a class="btn" href="<?= e(url('sharia/events/' . $e['id'] . '/edit')) ?>"><?= e(t('common.edit')) ?></a><?php endif; ?></div></div>
<ol class="stepper" aria-label="<?= e(t('sha.e_status')) ?>"><?php foreach ($steps as $i => $s): ?><li class="<?= $i < $cur ? 'done' : ($i === $cur ? 'current' : '') ?>"<?= $i === $cur ? ' aria-current="step"' : '' ?>><?= e(t('enum.sevent.' . $s)) ?></li><?php endforeach; ?></ol>
<div class="grid cols-2">
<section class="card"><h2><?= e(t('sha.details')) ?></h2><dl class="details"><dt><?= e(t('sha.e_dept')) ?></dt><dd><?= e(lname($e, 'dept')) ?></dd><dt><?= e(t('sha.e_detected')) ?></dt><dd><?= e(fdate($e['detected_on'])) ?></dd><dt><?= e(t('sha.reporter')) ?></dt><dd><?= e($e['reporter_name']) ?></dd>
<dt><?= e(t('sha.e_ruling')) ?></dt><dd><?= $e['ruling_id'] ? '<a href="' . e(url('sharia/rulings/' . $e['ruling_id'])) . '">' . e($e['ruling_code'] . ' — ' . $e['ruling_title']) . '</a>' : '—' ?></dd><dt><?= e(t('sha.e_desc')) ?></dt><dd class="pre"><?= e($e['description']) ?></dd></dl></section>
<section class="card" id="decision"><h2><?= e(t('sha.decision')) ?></h2>
<?php if ($e['decided_on']): ?><dl class="details"><dt><?= e(t('sha.e_decided_on')) ?></dt><dd><?= e(fdate($e['decided_on'])) ?></dd><dt><?= e(t('sha.e_decision')) ?></dt><dd class="pre"><?= e($e['decision_text']) ?></dd><dt><?= e(t('sha.e_income')) ?></dt><dd><?= (float)$e['impermissible_income'] > 0 ? e(money($e['impermissible_income'])) : e(t('sha.none')) ?></dd>
<dt><?= e(t('sha.purification')) ?></dt><dd><span class="badge <?= $e['purification_status'] === 'purified' ? 'ok' : ($e['purification_status'] === 'pending' ? 'warn' : '') ?>"><?= e(t('enum.pur.' . $e['purification_status'])) ?></span><?= $e['purified_on'] ? ' ' . e(fdate($e['purified_on'])) . ' — ' . e($e['beneficiary']) : '' ?></dd></dl>
<?php elseif ($canManage && in_array($e['status'], ['reported', 'under_review'], true)): ?>
<?php if ($e['status'] === 'reported'): ?><form method="post" action="<?= e(url('sharia/events/' . $e['id'] . '/review')) ?>" class="inline"><?= csrf_field() ?><button class="btn secondary" type="submit"><?= e(t('sha.start_review')) ?></button></form><?php endif; ?>
<form method="post" action="<?= e(url('sharia/events/' . $e['id'] . '/decision')) ?>" novalidate><?= csrf_field() ?>
<?= ff('date', 'decided_on', 'sha.e_decided_on', date('Y-m-d'), ['required' => true, 'extra' => ' max="' . date('Y-m-d') . '"']) ?>
<?= ff('textarea', 'decision_text', 'sha.e_decision', '', ['required' => true, 'hint' => t('sha.e_decision_hint'), 'extra' => ' rows="5"']) ?>
<?= ff('text', 'impermissible_income', 'sha.e_income', '', ['extra' => ' inputmode="decimal"', 'hint' => t('sha.e_income_hint')]) ?>
<button class="btn" type="submit"><?= e(t('sha.record_decision')) ?></button></form>
<?php else: ?><p class="muted"><?= e(t('sha.awaiting_decision')) ?></p><?php endif; ?></section></div>
<?php if ($canManage && $e['status'] === 'decided' && $e['purification_status'] === 'pending'): ?>
<section class="card" id="purification"><h2><?= e(t('sha.purify_title')) ?></h2><p><?= e(t('sha.purify_intro', ['amount' => money($e['impermissible_income'])])) ?></p>
<form method="post" action="<?= e(url('sharia/events/' . $e['id'] . '/purify')) ?>" novalidate class="form-grid"><?= csrf_field() ?>
<?= ff('date', 'purified_on', 'sha.e_purified_on', date('Y-m-d'), ['required' => true, 'extra' => ' max="' . date('Y-m-d') . '"']) ?>
<?= ff('text', 'beneficiary', 'sha.e_beneficiary', '', ['required' => true, 'hint' => t('sha.e_beneficiary_hint')]) ?>
<div class="field wide"><button class="btn" type="submit"><?= e(t('sha.record_purification')) ?></button></div></form></section><?php endif; ?>
<?php if ($canManage && $e['status'] === 'decided' && $e['purification_status'] !== 'pending'): ?>
<section class="card"><form method="post" action="<?= e(url('sharia/events/' . $e['id'] . '/close')) ?>" data-confirm="<?= e(t('sha.confirm_close')) ?>"><?= csrf_field() ?><button class="btn gold" type="submit"><?= e(t('sha.close_event')) ?></button></form></section><?php endif; ?>
<section class="card" id="attachments"><h2><?= e(t('sha.evidence')) ?></h2>
<?php if (!$attachments): ?><p class="muted"><?= e(t('upload.empty')) ?></p><?php else: ?><ul><?php foreach ($attachments as $a): ?><li><a href="<?= e(url('attachments/' . $a['id'])) ?>"><?= e($a['original_name']) ?></a></li><?php endforeach; ?></ul><?php endif; ?>
<?php if (can('sharia.report') && $e['status'] !== 'closed'): ?><form method="post" enctype="multipart/form-data" action="<?= e(url('sharia/events/' . $e['id'] . '/attachments')) ?>"><?= csrf_field() ?><div class="field"><label for="file"><?= e(t('upload.choose')) ?></label><input type="file" id="file" name="file" required></div><button class="btn secondary" type="submit"><?= e(t('upload.submit')) ?></button></form><?php endif; ?></section>
