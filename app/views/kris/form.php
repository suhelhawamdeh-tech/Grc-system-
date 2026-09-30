<div class="page-head"><h1><?= e($title) ?></h1></div>
<form method="post" class="card" action="<?= e($existing ? url('kris/' . $existing['id']) : url('kris')) ?>" novalidate><?= csrf_field() ?>
<div class="form-grid">
<?= ff('text', 'name', 'kri.name', $k['name'] ?? '', ['required' => true, 'wide' => true]) ?>
<?= ff('textarea', 'description', 'kri.description', $k['description'] ?? '', ['wide' => true]) ?>
<?= ff('select', 'department_id', 'kri.department', $k['department_id'] ?? '', ['required' => true, 'options' => opts($lists['departments'], fn($d) => lname($d))]) ?>
<?= ff('select', 'owner_user_id', 'kri.owner', $k['owner_user_id'] ?? '', ['required' => true, 'options' => opts($lists['users'], fn($d) => $d['full_name'])]) ?>
<?= ff('select', 'category_id', 'kri.category', $k['category_id'] ?? '', ['options' => opts($lists['categories'], fn($d) => lname($d))]) ?>
<?= ff('select', 'risk_id', 'kri.linked_risk', $k['risk_id'] ?? '', ['options' => opts($lists['risks'], fn($d) => $d['risk_code'] . ' — ' . mb_strimwidth($d['title'], 0, 60, '…'))]) ?>
<?= ff('text', 'unit', 'kri.unit', $k['unit'] ?? '', ['hint' => t('kri.unit_hint')]) ?>
<?= ff('select', 'frequency', 'kri.frequency', $k['frequency'] ?? 'monthly', ['blank' => false, 'options' => ['monthly' => t('enum.kfreq.monthly'), 'quarterly' => t('enum.kfreq.quarterly')]]) ?>
<?= ff('select', 'direction', 'kri.direction', $k['direction'] ?? 'higher_worse', ['blank' => false, 'options' => ['higher_worse' => t('enum.dir.higher_worse'), 'lower_worse' => t('enum.dir.lower_worse')]]) ?>
<div></div>
<?= ff('text', 'amber_threshold', 'kri.amber', $k['amber_threshold'] ?? '', ['required' => true, 'extra' => ' inputmode="decimal"', 'hint' => t('kri.amber_hint')]) ?>
<?= ff('text', 'red_threshold', 'kri.red', $k['red_threshold'] ?? '', ['required' => true, 'extra' => ' inputmode="decimal"', 'hint' => t('kri.red_hint')]) ?>
</div>
<?php if ($existing): ?><div class="field"><label class="check"><input type="checkbox" name="is_active" value="1"<?= checked(old('is_active', $k['is_active'] ?? 1)) ?>> <?= e(t('common.active')) ?></label></div><?php endif; ?>
<p class="muted"><?= e(t('kri.demo_note')) ?></p>
<div class="actions"><button class="btn" type="submit"><?= e(t('common.save')) ?></button><a class="btn secondary" href="<?= e(url('kris')) ?>"><?= e(t('common.cancel')) ?></a></div></form>
