<?php /** @var array $o @var ?array $existing @var array $departments */ ?>
<div class="page-head"><h1><?= e($title) ?></h1></div>
<form method="post" class="card" action="<?= e($existing ? url('rm/opinions/' . $existing['id']) : url('rm/opinions')) ?>" novalidate><?= csrf_field() ?>
<p class="muted"><?= e(t('rm.opinion_form_hint')) ?></p><div class="form-grid">
<?= ff('text', 'title', 'rm.subject', $o['title'] ?? '', ['required' => true, 'wide' => true]) ?>
<?= ff('select', 'section', 'docs.section', $o['section'] ?? 'credit', ['blank' => false, 'options' => opts(\App\Support\Framework::SECTIONS, fn($x) => t('enum.rmsec.' . $x), null) ?: []]) ?>
<?= ff('select', 'subject_type', 'rm.subject_type', $o['subject_type'] ?? 'financing', ['blank' => false, 'options' => opts(\App\Controllers\RiskFunctionController::SUBJECTS, fn($x) => t('enum.rmsubj.' . $x), null) ?: []]) ?>
<?= ff('select', 'requesting_department_id', 'rm.requesting_department', $o['requesting_department_id'] ?? '', ['options' => opts($departments, fn($d) => lname($d))]) ?>
<?= ff('text', 'request_ref', 'rm.request_ref', $o['request_ref'] ?? '', ['dir' => 'ltr']) ?>
<?= ff('date', 'received_on', 'rm.received_on', $o['received_on'] ?? date('Y-m-d'), ['required' => true]) ?>
<?= ff('date', 'due_on', 'rm.due_on', $o['due_on'] ?? '') ?>
<?= ff('textarea', 'summary', 'rm.summary', $o['summary'] ?? '', ['required' => true, 'wide' => true, 'hint' => t('rm.summary_hint')]) ?>
<?= ff('textarea', 'key_risks', 'rm.key_risks', $o['key_risks'] ?? '', ['wide' => true]) ?>
<?= ff('textarea', 'limits_impact', 'rm.limits_impact', $o['limits_impact'] ?? '', ['wide' => true, 'hint' => t('rm.limits_impact_hint')]) ?>
<?= ff('select', 'opinion', 'rm.opinion', $o['opinion'] ?? '', ['options' => opts(\App\Controllers\RiskFunctionController::OPINIONS, fn($x) => t('enum.rmop.' . $x), null) ?: [], 'hint' => t('rm.opinion_hint')]) ?>
<?= ff('textarea', 'conditions', 'rm.conditions', $o['conditions'] ?? '', ['wide' => true, 'hint' => t('rm.conditions_hint')]) ?>
<?= ff('textarea', 'rationale', 'rm.rationale', $o['rationale'] ?? '', ['wide' => true]) ?>
</div><div class="actions"><button class="btn" type="submit"><?= e(t('common.save')) ?></button><a class="btn secondary" href="<?= e($existing ? url('rm/opinions/' . $existing['id']) : url('rm/opinions')) ?>"><?= e(t('common.cancel')) ?></a></div></form>
