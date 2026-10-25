<?php /** @var array $f @var ?array $existing */ ?>
<div class="page-head"><h1><?= icon('sliders') ?> <?= e($title) ?></h1></div>
<form method="post" class="card" action="<?= e($existing ? url('aml/factors/' . $existing['id']) : url('aml/factors')) ?>" novalidate><?= csrf_field() ?><div class="form-grid">
<?php if ($existing): ?><div class="field"><label><?= e(t('aml.dimension')) ?></label><p><?= e(t('enum.amld.' . $f['dimension'])) ?> · <span class="code"><?= e($f['code']) ?></span></p></div>
<?php else: ?><?= ff('select', 'dimension', 'aml.dimension', $f['dimension'] ?? '', ['required' => true, 'options' => opts(\App\Support\AmlScoring::DIMENSIONS, fn($x) => t('enum.amld.' . $x), null)]) ?>
<?= ff('text', 'code', 'aml.f_code', '', ['required' => true, 'dir' => 'ltr', 'hint' => t('aml.f_code_hint')]) ?><?php endif; ?>
<?= ff('text', 'name_ar', 'aml.f_name_ar', $f['name_ar'] ?? '', ['required' => true]) ?><?= ff('text', 'name_en', 'aml.f_name_en', $f['name_en'] ?? '', ['required' => true, 'dir' => 'ltr']) ?>
<?= ff('select', 'risk_level', 'aml.level', $f['risk_level'] ?? 2, ['required' => true, 'blank' => false, 'options' => ['1' => t('enum.amlr.low'), '2' => t('enum.amlr.medium'), '3' => t('enum.amlr.high')], 'hint' => $existing ? t('aml.level_change_hint') : '']) ?>
<?= ff('text', 't24_code', 'aml.t24_code', $f['t24_code'] ?? '', ['dir' => 'ltr', 'hint' => t('aml.t24_code_hint')]) ?>
<div class="field"><label class="check"><input type="checkbox" name="is_default" value="1"<?= checked(old('is_default', $f['is_default'] ?? 0)) ?>> <?= e(t('aml.is_default')) ?></label><p class="hint"><?= e(t('aml.is_default_hint')) ?></p><?= field_error('is_default') ?></div>
<div class="field"><label class="check"><input type="checkbox" name="is_active" value="1"<?= checked(old('is_active', $f['is_active'] ?? 1)) ?>> <?= e(t('aml.active')) ?></label><?= field_error('is_active') ?></div></div>
<div class="actions"><button class="btn" type="submit"><?= e(t('common.save')) ?></button><a class="btn secondary" href="<?= e(url('aml/factors')) ?>"><?= e(t('common.cancel')) ?></a></div></form>
