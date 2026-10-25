<?php /** @var array $f @var array $attachments @var int $me */
$tab = 'findings'; $st = $f['status']; $mg = can('ia.manage'); $owner = (int)$f['owner_user_id'] === $me;
$steps = ['open', 'responded', 'implemented', 'closed']; $cur = array_search($st, $steps, true);
$due = $st === 'open' ? $f['response_due'] : $f['due_date']; ?>
<div class="page-head"><div><h1><?= icon('flag') ?> <?= e($f['code']) ?> — <?= e($f['title']) ?></h1><p class="sub"><?php if (can('ia.view')): ?><a href="<?= e(url('ia/engagements/' . $f['engagement_id'])) ?>"><?= e($f['e_code'] . ' — ' . $f['e_title']) ?></a><?php else: ?><?= e($f['e_code']) ?><?php endif; ?></p></div>
  <div class="actions"><a class="btn secondary" href="<?= e(url('ia/findings')) ?>"><?= e(t('common.back')) ?></a><?php if ($mg && $st === 'draft'): ?><a class="btn" href="<?= e(url('ia/findings/' . $f['id'] . '/edit')) ?>"><?= e(t('common.edit')) ?></a><?php endif; ?></div></div>
<?php require __DIR__ . '/_tabs.php'; ?>
<?php if ($st === 'draft'): ?><div class="card notice"><p><?= e(t('ia.finding_draft_hint')) ?></p></div><?php else: ?>
<ol class="stepper"><?php foreach ($steps as $i => $s): ?><li class="<?= $i < $cur ? 'done' : ($i === $cur ? 'current' : '') ?>"<?= $i === $cur ? ' aria-current="step"' : '' ?>><?= e(t('enum.iafs.' . $s)) ?></li><?php endforeach; ?></ol><?php endif; ?>
<div class="grid cols-2">
<section class="card"><h2><?= e(t('ia.finding')) ?></h2><dl class="details">
<dt><?= e(t('ia.severity')) ?></dt><dd><span class="badge <?= $f['severity'] === 'high' ? 'danger' : ($f['severity'] === 'medium' ? 'warn' : '') ?>"><?= e(t('enum.sev.' . $f['severity'])) ?></span></dd>
<dt><?= e(t('ia.condition')) ?></dt><dd class="pre"><?= e($f['condition_text']) ?></dd>
<?php foreach (['criteria_text' => 'ia.criteria', 'cause_text' => 'ia.cause', 'effect_text' => 'ia.effect'] as $k => $l): if ($f[$k]): ?><dt><?= e(t($l)) ?></dt><dd class="pre"><?= e($f[$k]) ?></dd><?php endif; endforeach; ?>
<dt><?= e(t('ia.recommendation')) ?></dt><dd class="pre"><?= e($f['recommendation']) ?></dd>
<?php if ($f['risk_id']): ?><dt><?= e(t('ia.risk')) ?></dt><dd><a href="<?= e(url('risks/' . $f['risk_id'])) ?>"><?= e($f['risk_code'] . ' — ' . $f['risk_title']) ?></a></dd><?php endif; ?>
<?php if ($f['wp_ref'] && can('ia.view')): ?><dt><?= e(t('ia.workpaper')) ?></dt><dd><a href="<?= e(url('ia/workpapers/' . $f['workpaper_id'])) ?>"><?= e($f['wp_ref']) ?></a></dd><?php endif; ?></dl></section>
<section class="card"><h2><?= e(t('ia.followup')) ?></h2><dl class="details">
<dt><?= e(t('ia.department')) ?></dt><dd><?= e(lname($f, 'dept')) ?></dd><dt><?= e(t('ia.owner')) ?></dt><dd><?= e($f['owner']) ?></dd>
<dt><?= e($st === 'open' ? t('ia.response_due') : t('ia.due_date')) ?></dt><dd><?= e(fdate($due) ?: '—') ?><?= $due && $due < date('Y-m-d') && in_array($st, ['open', 'responded'], true) ? ' <span class="badge danger">' . e(t('my.overdue')) . '</span>' : '' ?><?= (int)$f['extension_count'] ? ' <span class="muted small">(' . e(t('ia.extended_n', ['n' => (int)$f['extension_count']])) . ', ' . e(t('ia.original_due')) . ' ' . e(fdate($f['original_due_date'])) . ')</span>' : '' ?></dd>
<?php if ($f['management_response']): ?><dt><?= e(t('ia.mgmt_response')) ?></dt><dd class="pre"><?= e($f['management_response']) ?></dd><dt><?= e(t('ia.agreed_action')) ?></dt><dd class="pre"><?= e($f['agreed_action']) ?></dd><?php endif; ?>
<?php if ($f['implemented_note']): ?><dt><?= e(t('ia.implemented_note')) ?></dt><dd class="pre"><?= e($f['implemented_note']) ?></dd><?php endif; ?>
<?php if ($f['verification_note']): ?><dt><?= e(t('ia.verification_note')) ?></dt><dd class="pre"><?= e($f['verification_note']) ?><?= $f['verifier'] ? ' — ' . e($f['verifier']) : '' ?></dd><?php endif; ?></dl></section></div>
<?php if ($owner && $st === 'open' && can('ia.respond')): ?><section class="card" id="respond"><h2><?= e(t('ia.respond')) ?></h2><p class="muted"><?= e(t('ia.respond_hint')) ?></p>
<form method="post" action="<?= e(url('ia/findings/' . $f['id'] . '/respond')) ?>" novalidate><?= csrf_field() ?><div class="form-grid">
<?= ff('textarea', 'management_response', 'ia.mgmt_response', '', ['required' => true, 'wide' => true]) ?><?= ff('textarea', 'agreed_action', 'ia.agreed_action', '', ['required' => true, 'wide' => true]) ?>
<?= ff('date', 'due_date', 'ia.due_date', '', ['required' => true]) ?></div><button class="btn gold" type="submit"><?= e(t('ia.send_response')) ?></button></form></section><?php endif; ?>
<?php if ($owner && $st === 'responded' && can('ia.respond')): ?><section class="card" id="implement"><h2><?= e(t('ia.mark_implemented')) ?></h2><p class="muted"><?= e(t('ia.implement_hint')) ?></p>
<form method="post" action="<?= e(url('ia/findings/' . $f['id'] . '/implemented')) ?>" novalidate><?= csrf_field() ?>
<?= ff('textarea', 'implemented_note', 'ia.implemented_note', '', ['required' => true]) ?><button class="btn gold" type="submit"><?= e(t('ia.mark_implemented')) ?></button></form></section><?php endif; ?>
<?php if ($mg && $st === 'implemented'): ?><section class="card" id="verify"><h2><?= e(t('ia.verify')) ?></h2><p class="muted"><?= e(t('ia.verify_hint')) ?></p>
<form method="post" action="<?= e(url('ia/findings/' . $f['id'] . '/verify')) ?>" novalidate><?= csrf_field() ?>
<?= ff('select', 'decision', 'ia.decision', '', ['required' => true, 'options' => ['close' => t('ia.close_finding'), 'return' => t('ia.return_finding')]]) ?><?= ff('textarea', 'verification_note', 'ia.verification_note', '', ['required' => true]) ?>
<button class="btn" type="submit"><?= e(t('ia.submit_review')) ?></button></form></section><?php endif; ?>
<?php if ($mg && $st === 'responded'): ?><details class="card" id="extend"><summary><strong><?= e(t('ia.extend')) ?></strong></summary><form method="post" action="<?= e(url('ia/findings/' . $f['id'] . '/extend')) ?>" novalidate><?= csrf_field() ?><div class="form-grid">
<?= ff('date', 'new_due_date', 'ia.new_due_date', '', ['required' => true]) ?><?= ff('textarea', 'reason', 'ia.extend_reason', '', ['required' => true, 'wide' => true]) ?></div><button class="btn secondary" type="submit"><?= e(t('ia.extend')) ?></button></form></details><?php endif; ?>
<section class="card" id="attachments"><h2><?= e(t('ia.evidence')) ?></h2>
<?php if (!$attachments): ?><p class="muted"><?= e(t('upload.empty')) ?></p><?php else: ?><ul><?php foreach ($attachments as $a): ?><li><a href="<?= e(url('attachments/' . $a['id'])) ?>"><?= e($a['original_name']) ?></a> <span class="muted small"><?= e($a['full_name']) ?></span></li><?php endforeach; ?></ul><?php endif; ?>
<?php if ($mg || ($owner && in_array($st, ['open', 'responded'], true))): ?><form method="post" enctype="multipart/form-data" action="<?= e(url('ia/findings/' . $f['id'] . '/attachments')) ?>"><?= csrf_field() ?><div class="field"><label for="file"><?= e(t('upload.choose')) ?></label><input type="file" id="file" name="file" required></div><button class="btn secondary" type="submit"><?= e(t('upload.submit')) ?></button></form><?php endif; ?></section>
