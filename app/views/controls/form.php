<?php /** @var array $c @var ?array $existing @var array $lists */ ?>
<div class="page-head"><h1><?= icon('flask') ?> <?= e($title) ?></h1></div>
<form method="post" class="card" action="<?= e($existing ? url('controls/' . $existing['id']) : url('controls')) ?>" novalidate><?= csrf_field() ?>
<div class="form-grid">
<?= ff('text', 'name', 'ctl.name', $c['name'] ?? '', ['required' => true, 'wide' => true, 'hint' => t('ctl.name_hint')]) ?>
<?= ff('textarea', 'description', 'ctl.description', $c['description'] ?? '', ['wide' => true, 'hint' => t('ctl.description_hint')]) ?>
<?= ff('select', 'department_id', 'ctl.department', $c['department_id'] ?? '', ['required' => true, 'options' => opts($lists['departments'], fn($d) => lname($d))]) ?>
<?= ff('select', 'owner_user_id', 'ctl.owner', $c['owner_user_id'] ?? '', ['required' => true, 'options' => opts($lists['users'], fn($d) => $d['full_name']), 'hint' => t('ctl.owner_hint')]) ?>
<?= ff('select', 'control_type', 'ctl.type', $c['control_type'] ?? 'preventive', ['blank' => false, 'options' => opts(\App\Controllers\ControlController::TYPES, fn($x) => t('enum.ctype.' . $x), null)]) ?>
<?= ff('select', 'nature', 'ctl.nature', $c['nature'] ?? 'manual', ['blank' => false, 'options' => opts(\App\Controllers\ControlController::NATURES, fn($x) => t('enum.cnature.' . $x), null)]) ?>
<?= ff('select', 'frequency', 'ctl.frequency', $c['frequency'] ?? 'monthly', ['blank' => false, 'options' => opts(\App\Controllers\ControlController::FREQS, fn($x) => t('enum.cfreq.' . $x), null), 'hint' => t('ctl.frequency_hint')]) ?>
<?= ff('select', 'test_frequency', 'ctl.test_frequency', $c['test_frequency'] ?? 'annual', ['blank' => false, 'options' => opts(\App\Controllers\ControlController::TEST_FREQS, fn($x) => t('enum.freq.' . $x), null), 'hint' => t('ctl.test_frequency_hint')]) ?>
</div>
<div class="field"><label class="check"><input type="checkbox" name="is_key" value="1"<?= checked(old('is_key', $c['is_key'] ?? 0)) ?>> <?= e(t('ctl.is_key')) ?></label><p class="hint"><?= e(t('ctl.is_key_hint')) ?></p></div>
<div class="actions"><button class="btn" type="submit"><?= e(t('common.save')) ?></button><a class="btn secondary" href="<?= e(url('controls')) ?>"><?= e(t('common.cancel')) ?></a></div></form>
