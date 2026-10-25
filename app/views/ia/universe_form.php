<?php /** @var array $u @var ?array $existing */
$scale = ['1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5']; ?>
<div class="page-head"><h1><?= icon('layers') ?> <?= e($title) ?></h1></div>
<form method="post" class="card" action="<?= e($existing ? url('ia/universe/' . $existing['id']) : url('ia/universe')) ?>" novalidate><?= csrf_field() ?><div class="form-grid">
<?= ff('text', 'name', 'ia.u_name', $u['name'] ?? '', ['required' => true, 'wide' => true, 'hint' => t('ia.u_name_hint')]) ?>
<?= ff('select', 'department_id', 'ia.department', $u['department_id'] ?? '', ['options' => opts(\App\Support\Ref::departments(true), fn($d) => lname($d)), 'hint' => t('ia.u_dept_hint')]) ?>
<?= ff('select', 'materiality', 'ia.materiality', $u['materiality'] ?? 3, ['options' => $scale, 'blank' => false, 'hint' => t('ia.materiality_hint')]) ?>
<?= ff('select', 'regulatory', 'ia.regulatory', $u['regulatory'] ?? 3, ['options' => $scale, 'blank' => false, 'hint' => t('ia.regulatory_hint')]) ?>
<?= ff('select', 'complexity', 'ia.complexity', $u['complexity'] ?? 3, ['options' => $scale, 'blank' => false, 'hint' => t('ia.complexity_hint')]) ?>
<?= ff('date', 'last_audit_date', 'ia.last_audit', $u['last_audit_date'] ?? '', ['hint' => t('ia.last_audit_hint')]) ?>
<?= ff('select', 'last_rating', 'ia.last_rating', $u['last_rating'] ?? '', ['options' => opts(['satisfactory', 'needs_improvement', 'unsatisfactory'], fn($x) => t('enum.iarating.' . $x), null)]) ?>
<?= ff('textarea', 'description', 'ia.u_description', $u['description'] ?? '', ['wide' => true]) ?>
<div class="field"><label class="check"><input type="checkbox" name="is_active" value="1"<?= checked(old('is_active', $u['is_active'] ?? 1)) ?>> <?= e(t('ia.active')) ?></label></div></div>
<div class="actions"><button class="btn" type="submit"><?= e(t('common.save')) ?></button><a class="btn secondary" href="<?= e(url('ia/universe')) ?>"><?= e(t('common.cancel')) ?></a></div></form>
