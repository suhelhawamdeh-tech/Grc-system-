<?php /** @var array $c @var array $sel @var ?array $existing @var array $factors @var array $branches */
$lv = static fn(array $f) => lname($f) . ' (' . t('enum.amlr.' . ['', 'low', 'medium', 'high'][(int)$f['risk_level']]) . ')';
$oldSel = array_map('intval', array_merge((array)old('products', []), (array)old('channels', []))) ?: $sel; ?>
<div class="page-head"><h1><?= icon('users') ?> <?= e($title) ?></h1></div>
<form method="post" class="card" action="<?= e($existing ? url('aml/customers/' . $existing['id']) : url('aml/customers')) ?>" novalidate><?= csrf_field() ?>
<p class="muted"><?= e(t('aml.form_hint')) ?></p><div class="form-grid">
<?= ff('text', 'customer_ref', 'aml.customer_ref', $c['customer_ref'] ?? '', ['required' => true, 'dir' => 'ltr', 'hint' => t('aml.customer_ref_hint')]) ?>
<?= ff('text', 'full_name', 'aml.full_name', $c['full_name'] ?? '', ['required' => true]) ?>
<?= ff('select', 'customer_type_id', 'aml.customer_type', $c['customer_type_id'] ?? '', ['required' => true, 'options' => opts($factors['customer'] ?? [], $lv)]) ?>
<?= ff('select', 'geography_id', 'aml.geography', $c['geography_id'] ?? '', ['required' => true, 'options' => opts($factors['geography'] ?? [], $lv), 'hint' => t('aml.geography_hint')]) ?>
<?= ff('select', 'branch_id', 'aml.branch', $c['branch_id'] ?? '', ['options' => opts($branches, fn($b) => lname($b))]) ?>
<?= ff('date', 'onboarded_on', 'aml.onboarded_on', $c['onboarded_on'] ?? '') ?></div>
<fieldset class="field<?= field_error('products') ? ' has-error' : '' ?>"><legend><?= e(t('aml.products')) ?> *</legend><div class="form-grid"><?php foreach ($factors['product'] ?? [] as $f): ?><label class="check"><input type="checkbox" name="products[]" value="<?= (int)$f['id'] ?>"<?= in_array((int)$f['id'], $oldSel, true) ? ' checked' : '' ?>> <?= e($lv($f)) ?></label><?php endforeach; ?></div><?= field_error('products') ?></fieldset>
<fieldset class="field<?= field_error('channels') ? ' has-error' : '' ?>"><legend><?= e(t('aml.channels')) ?> *</legend><div class="form-grid"><?php foreach ($factors['channel'] ?? [] as $f): ?><label class="check"><input type="checkbox" name="channels[]" value="<?= (int)$f['id'] ?>"<?= in_array((int)$f['id'], $oldSel, true) ? ' checked' : '' ?>> <?= e($lv($f)) ?></label><?php endforeach; ?></div><?= field_error('channels') ?></fieldset>
<div class="form-grid"><div class="field"><label class="check"><input type="checkbox" name="is_pep" value="1"<?= checked(old('is_pep', $c['is_pep'] ?? 0)) ?>> <?= e(t('aml.is_pep')) ?></label><p class="hint"><?= e(t('aml.is_pep_hint')) ?></p></div>
<div class="field"><label class="check"><input type="checkbox" name="sanctions_match" value="1"<?= checked(old('sanctions_match', $c['sanctions_match'] ?? 0)) ?>> <?= e(t('aml.sanctions_match')) ?></label><p class="hint"><?= e(t('aml.sanctions_hint')) ?></p></div>
<?php if ($existing): ?><?= ff('select', 'status', 'aml.status', $c['status'], ['blank' => false, 'options' => ['active' => t('enum.amlst.active'), 'exited' => t('enum.amlst.exited')]]) ?><?php endif; ?></div>
<div class="actions"><button class="btn" type="submit"><?= e(t('aml.save_and_rate')) ?></button><a class="btn secondary" href="<?= e($existing ? url('aml/customers/' . $existing['id']) : url('aml/customers')) ?>"><?= e(t('common.cancel')) ?></a></div></form>
