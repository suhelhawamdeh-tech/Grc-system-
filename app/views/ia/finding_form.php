<?php /** @var array $e @var array $f @var ?array $existing @var array $owners @var array $risks @var array $wps */ ?>
<div class="page-head"><div><h1><?= icon('flag') ?> <?= e($title) ?></h1><p class="sub"><?= e($e['code'] . ' — ' . $e['title']) ?></p></div></div>
<form method="post" class="card" action="<?= e($existing ? url('ia/findings/' . $existing['id']) : url('ia/engagements/' . $e['id'] . '/findings')) ?>" novalidate><?= csrf_field() ?>
<p class="muted"><?= e(t('ia.ccce_hint')) ?></p><div class="form-grid">
<?= ff('text', 'title', 'ia.f_title', $f['title'] ?? '', ['required' => true, 'wide' => true]) ?>
<?= ff('textarea', 'condition_text', 'ia.condition', $f['condition_text'] ?? '', ['required' => true, 'wide' => true, 'hint' => t('ia.condition_hint')]) ?>
<?= ff('textarea', 'criteria_text', 'ia.criteria', $f['criteria_text'] ?? '', ['hint' => t('ia.criteria_hint')]) ?>
<?= ff('textarea', 'cause_text', 'ia.cause', $f['cause_text'] ?? '', ['hint' => t('ia.cause_hint')]) ?>
<?= ff('textarea', 'effect_text', 'ia.effect', $f['effect_text'] ?? '', ['hint' => t('ia.effect_hint')]) ?>
<?= ff('textarea', 'recommendation', 'ia.recommendation', $f['recommendation'] ?? '', ['required' => true]) ?>
<?= ff('select', 'severity', 'ia.severity', $f['severity'] ?? '', ['required' => true, 'options' => opts(['high', 'medium', 'low'], fn($x) => t('enum.sev.' . $x), null), 'hint' => t('ia.severity_hint')]) ?>
<?= ff('select', 'department_id', 'ia.department', $f['department_id'] ?? '', ['required' => true, 'options' => opts(\App\Support\Ref::departments(true), fn($d) => lname($d))]) ?>
<?= ff('select', 'owner_user_id', 'ia.owner', $f['owner_user_id'] ?? '', ['required' => true, 'options' => opts($owners, fn($u) => $u['full_name']), 'hint' => t('ia.owner_hint')]) ?>
<?= ff('select', 'risk_id', 'ia.risk', $f['risk_id'] ?? '', ['options' => opts($risks, fn($r) => $r['risk_code'] . ' — ' . mb_strimwidth($r['title'], 0, 60, '…'))]) ?>
<?= ff('select', 'workpaper_id', 'ia.workpaper', $f['workpaper_id'] ?? '', ['options' => opts($wps, fn($w) => $w['ref'] . ' — ' . $w['title'])]) ?></div>
<div class="actions"><button class="btn" type="submit"><?= e(t('common.save')) ?></button><a class="btn secondary" href="<?= e(url('ia/engagements/' . $e['id'])) ?>"><?= e(t('common.cancel')) ?></a></div></form>
