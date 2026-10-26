<?php /** @var array $o @var ?array $esc @var int $uid */ $tab = 'opinions'; $st = $o['status']; $mine = (int)$o['prepared_by'] === $uid;
$steps = ['draft', 'submitted', 'reviewed', 'issued', 'decided']; $cur = array_search($st === 'returned' ? 'draft' : $st, $steps, true); ?>
<div class="page-head"><div><h1><?= icon('scale') ?> <?= e($o['code']) ?> — <?= e($o['title']) ?></h1><p class="sub"><?= e(t('enum.rmsec.' . $o['section'])) ?> · <?= e(t('enum.rmsubj.' . $o['subject_type'])) ?> · <span class="badge"><?= e(t('enum.rmos.' . $st)) ?></span><?= $o['contrary'] ? ' <span class="badge danger">' . e(t('rm.contrary')) . '</span>' : '' ?></p></div>
<div class="actions"><a class="btn secondary" href="<?= e(url('rm/opinions')) ?>"><?= e(t('common.back')) ?></a><a class="btn secondary" href="<?= e(url('rm/opinions/' . $o['id'] . '/print')) ?>"><?= e(t('common.print')) ?></a><a class="btn secondary" href="<?= e(url('rm/opinions/' . $o['id'] . '/docx')) ?>"><?= icon('file') ?> Word</a>
<?php if (can('rm.work') && in_array($st, ['draft', 'returned'], true)): ?><a class="btn" href="<?= e(url('rm/opinions/' . $o['id'] . '/edit')) ?>"><?= e(t('common.edit')) ?></a><?php endif; ?></div></div>
<?php require __DIR__ . '/_tabs.php'; ?>
<ol class="stepper"><?php foreach ($steps as $i => $s): ?><li class="<?= $i < $cur ? 'done' : ($i === $cur ? 'current' : '') ?>"<?= $i === $cur ? ' aria-current="step"' : '' ?>><?= e(t('enum.rmos.' . $s)) ?></li><?php endforeach; ?></ol>
<?php if ($st === 'returned'): ?><div class="card notice"><p><?= e(t('rm.returned_hint')) ?></p></div><?php endif; ?>
<div class="grid cols-2">
<section class="card"><h2><?= e(t('rm.request')) ?></h2><dl class="details">
<dt><?= e(t('rm.requesting_department')) ?></dt><dd><?= e($o['dept_ar'] ? lname(['name_en' => $o['dept_en'], 'name_ar' => $o['dept_ar']]) : '—') ?></dd>
<dt><?= e(t('rm.request_ref')) ?></dt><dd dir="ltr"><?= e($o['request_ref'] ?? '—') ?></dd><dt><?= e(t('rm.received_on')) ?></dt><dd><?= e(fdate($o['received_on'])) ?></dd>
<?php if ($o['due_on']): ?><dt><?= e(t('rm.due_on')) ?></dt><dd><?= e(fdate($o['due_on'])) ?></dd><?php endif; ?>
<dt><?= e(t('rm.summary')) ?></dt><dd class="pre"><?= e($o['summary']) ?></dd>
<?php if ($o['key_risks']): ?><dt><?= e(t('rm.key_risks')) ?></dt><dd class="pre"><?= e($o['key_risks']) ?></dd><?php endif; ?>
<?php if ($o['limits_impact']): ?><dt><?= e(t('rm.limits_impact')) ?></dt><dd class="pre"><?= e($o['limits_impact']) ?></dd><?php endif; ?></dl></section>
<section class="card"><h2><?= e(t('rm.opinion')) ?></h2><dl class="details">
<dt><?= e(t('rm.opinion')) ?></dt><dd><?= $o['opinion'] ? op_badge($o['opinion']) : '—' ?></dd>
<?php if ($o['conditions']): ?><dt><?= e(t('rm.conditions')) ?></dt><dd class="pre"><?= e($o['conditions']) ?></dd><?php endif; ?>
<dt><?= e(t('rm.rationale')) ?></dt><dd class="pre"><?= e($o['rationale'] ?? '—') ?></dd>
<dt><?= e(t('rm.prepared_by')) ?></dt><dd><?= e($o['prep_name']) ?></dd><dt><?= e(t('rm.reviewed_by')) ?></dt><dd><?= e($o['rev_name'] ?? '—') ?> <?= e(fdatetime($o['reviewed_at'])) ?></dd>
<dt><?= e(t('rm.issued_by')) ?></dt><dd><?= e($o['iss_name'] ?? '—') ?> <?= e(fdatetime($o['issued_at'])) ?></dd></dl>
<p class="muted small"><?= icon('lock') ?> <?= e(t('rm.memo_warning')) ?></p></section></div>
<?php if (can('rm.work')): ?>
<?php if (in_array($st, ['draft', 'returned'], true) && $mine): ?><section class="card"><form method="post" action="<?= e(url('rm/opinions/' . $o['id'] . '/step')) ?>"><?= csrf_field() ?><input type="hidden" name="step" value="submit"><p class="muted"><?= e(t('rm.submit_hint')) ?></p><button class="btn gold" type="submit"><?= e(t('rm.submit')) ?></button></form></section><?php endif; ?>
<?php if ($st === 'submitted' && !$mine): ?><section class="card"><p class="muted"><?= e(t('rm.review_hint')) ?></p><form method="post" action="<?= e(url('rm/opinions/' . $o['id'] . '/step')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="step" value="review"><button class="btn gold" type="submit"><?= e(t('rm.review')) ?></button></form></section><?php endif; ?>
<?php if ($st === 'reviewed' && !$mine && can('rm.approve')): ?><section class="card"><p class="muted"><?= e(t('rm.issue_hint')) ?></p><form method="post" action="<?= e(url('rm/opinions/' . $o['id'] . '/step')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="step" value="issue"><button class="btn gold" type="submit"><?= e(t('rm.issue')) ?></button></form></section><?php endif; ?>
<?php if (in_array($st, ['submitted', 'reviewed'], true) && !$mine): ?><details class="card"><summary><strong><?= e(t('rm.return')) ?></strong></summary><form method="post" action="<?= e(url('rm/opinions/' . $o['id'] . '/step')) ?>" novalidate><?= csrf_field() ?><input type="hidden" name="step" value="return">
<?= ff('textarea', 'note', 'rm.return_note', '', ['required' => true]) ?><button class="btn secondary" type="submit"><?= e(t('rm.return')) ?></button></form></details><?php endif; ?>
<?php if ($st === 'issued'): ?><section class="card" id="decision"><h2><?= e(t('rm.record_decision')) ?></h2><p class="muted"><?= e(t('rm.decision_hint')) ?></p>
<form method="post" action="<?= e(url('rm/opinions/' . $o['id'] . '/decision')) ?>" class="form-grid" novalidate><?= csrf_field() ?>
<?= ff('text', 'decision_body', 'rm.decision_body', '', ['required' => true, 'hint' => t('rm.decision_body_hint')]) ?>
<?= ff('select', 'decision', 'rm.decision', '', ['required' => true, 'options' => opts(\App\Controllers\RiskFunctionController::DECISIONS, fn($x) => t('enum.rmdec.' . $x), null) ?: []]) ?>
<?= ff('date', 'decision_on', 'rm.decision_on', date('Y-m-d'), ['required' => true]) ?>
<?php if ($o['opinion'] === 'conditional'): ?><?= ff('select', 'conditions_adopted', 'rm.conditions_adopted', '', ['options' => ['1' => t('common.yes'), '0' => t('common.no')], 'hint' => t('rm.conditions_adopted_hint')]) ?><?php endif; ?>
<?= ff('textarea', 'decision_note', 'rm.decision_note', '', ['wide' => true]) ?><div class="field"><button class="btn gold" type="submit"><?= e(t('rm.record_decision')) ?></button></div></form></section><?php endif; ?>
<?php endif; ?>
<?php if ($st === 'decided'): ?><section class="card"><h2><?= e(t('rm.decision')) ?></h2><dl class="details"><dt><?= e(t('rm.decision_body')) ?></dt><dd><?= e($o['decision_body']) ?></dd><dt><?= e(t('rm.decision')) ?></dt><dd><?= e(t('enum.rmdec.' . $o['decision'])) ?> · <?= e(fdate($o['decision_on'])) ?></dd>
<?php if ($o['conditions_adopted'] !== null): ?><dt><?= e(t('rm.conditions_adopted')) ?></dt><dd><?= e($o['conditions_adopted'] ? t('common.yes') : t('common.no')) ?></dd><?php endif; ?>
<?php if ($o['decision_note']): ?><dt><?= e(t('rm.decision_note')) ?></dt><dd class="pre"><?= e($o['decision_note']) ?></dd><?php endif; ?>
<?php if ($esc): ?><dt><?= e(t('rm.escalation')) ?></dt><dd><a href="<?= e(url('rm/escalations/' . $esc['id'])) ?>" dir="ltr"><?= e($esc['code']) ?></a> <span class="badge"><?= e(t('enum.rmes.' . $esc['status'])) ?></span></dd><?php endif; ?></dl></section><?php endif; ?>
