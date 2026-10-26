<?php /** @var array $b @var array $departments @var array $kris */ $exc = $b['kind'] === 'exception'; ?>
<div class="page-head"><h1><?= e($title) ?></h1></div>
<form method="post" class="card" action="<?= e(url('rm/breaches')) ?>" novalidate><?= csrf_field() ?><input type="hidden" name="kind" value="<?= e($b['kind']) ?>">
<p class="muted"><?= e(t($exc ? 'rm.exception_form_hint' : 'rm.breach_form_hint')) ?></p><div class="form-grid">
<?= ff('text', 'limit_name', 'rm.limit_name', $b['limit_name'] ?? '', ['required' => true, 'wide' => true]) ?>
<?= ff('select', 'section', 'docs.section', $b['section'], ['blank' => false, 'options' => opts(\App\Support\Framework::SECTIONS, fn($x) => t('enum.rmsec.' . $x), null) ?: []]) ?>
<?= ff('select', 'limit_type', 'rm.limit_type', $b['limit_type'], ['blank' => false, 'options' => opts(['regulatory', 'internal'], fn($x) => t('enum.rmlt.' . $x), null) ?: [], 'hint' => $exc ? t('rm.exception_regulatory_hint') : '']) ?>
<?= ff('select', 'limit_tier', 'rm.limit_tier', $b['limit_tier'], ['blank' => false, 'options' => opts(['board', 'policy', 'operational'], fn($x) => t('enum.rmtier.' . $x), null) ?: []]) ?>
<?= ff('select', 'kri_id', 'rm.kri', $b['kri_id'] ?? '', ['options' => opts($kris, fn($k) => $k['code'] . ' — ' . $k['name']), 'blank_label' => 'rm.no_kri']) ?>
<?= ff('text', 'subject', 'rm.breach_subject', $b['subject'] ?? '', ['hint' => t('rm.breach_subject_hint')]) ?>
<?= ff('text', 'limit_value', 'rm.limit_value', $b['limit_value'] ?? '', ['dir' => 'ltr', 'extra' => ' inputmode="decimal"']) ?>
<?= ff('text', 'actual_value', 'rm.actual_value', $b['actual_value'] ?? '', ['dir' => 'ltr', 'extra' => ' inputmode="decimal"']) ?>
<?= ff('text', 'unit', 'rm.unit', $b['unit'] ?? '', ['hint' => t('rm.unit_hint')]) ?>
<?= ff('datetime-local', 'detected_at', $exc ? 'rm.requested_at' : 'rm.detected_at', str_replace(' ', 'T', (string)($b['detected_at'] ?? '')), ['required' => true]) ?>
<?= ff('select', 'owner_department_id', $exc ? 'rm.requesting_department' : 'rm.owner_department', $b['owner_department_id'] ?? '', ['required' => true, 'options' => opts($departments, fn($d) => lname($d))]) ?>
<?= ff('textarea', 'cause', $exc ? 'rm.exception_reason' : 'rm.cause', $b['cause'] ?? '', ['wide' => true]) ?>
<?php if ($exc): ?>
<?= ff('select', 'risk_opinion', 'rm.opinion', $b['risk_opinion'] ?? '', ['required' => true, 'options' => opts(\App\Controllers\RiskFunctionController::OPINIONS, fn($x) => t('enum.rmop.' . $x), null) ?: []]) ?>
<?= ff('text', 'approver_body', 'rm.approver_body', $b['approver_body'] ?? '', ['hint' => t('rm.approver_body_hint')]) ?>
<?= ff('date', 'expires_on', 'rm.expires_on', $b['expires_on'] ?? '') ?>
<?php else: ?>
<?= ff('date', 'plan_due', 'rm.plan_due', $b['plan_due'] ?? '', ['hint' => t('rm.plan_due_hint')]) ?>
<div class="field wide"><label class="check"><input type="checkbox" name="material" value="1"> <?= e(t('rm.material_check')) ?></label><p class="hint"><?= e(t('rm.material_hint')) ?></p></div>
<?php endif; ?>
</div><div class="actions"><button class="btn" type="submit"><?= e(t('common.save')) ?></button><a class="btn secondary" href="<?= e(url('rm/breaches')) ?>"><?= e(t('common.cancel')) ?></a></div></form>
