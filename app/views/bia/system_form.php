<?php /** @var array $s @var ?array $existing @var array $lists */ ?>
<div class="page-head"><h1><?= icon('layers') ?> <?= e($title) ?></h1></div>
<form method="post" class="card" action="<?= e($existing ? url('bia/systems/' . $existing['id']) : url('bia/systems')) ?>" novalidate><?= csrf_field() ?><div class="form-grid">
<?= ff('text', 'name', 'bia.s_name', $s['name'] ?? '', ['required' => true, 'wide' => true]) ?>
<?= ff('textarea', 'description', 'bia.description', $s['description'] ?? '', ['wide' => true]) ?>
<?= ff('select', 'department_id', 'bia.department', $s['department_id'] ?? '', ['required' => true, 'options' => opts($lists['departments'], fn($d) => lname($d))]) ?>
<?= ff('select', 'owner_user_id', 'bia.owner', $s['owner_user_id'] ?? '', ['required' => true, 'options' => opts($lists['users'], fn($d) => $d['full_name'])]) ?>
<?= ff('text', 'recovery_hours', 'bia.s_recovery', isset($s['recovery_hours']) ? \App\Support\Bia::fmt($s['recovery_hours']) : '', ['required' => true, 'extra' => ' inputmode="decimal"', 'hint' => t('bia.s_recovery_hint')]) ?>
<?= ff('text', 'data_loss_hours', 'bia.s_loss', isset($s['data_loss_hours']) ? \App\Support\Bia::fmt($s['data_loss_hours']) : '', ['required' => true, 'extra' => ' inputmode="decimal"', 'hint' => t('bia.s_loss_hint')]) ?></div>
<?php if ($existing): ?><div class="field"><label class="check"><input type="checkbox" name="is_active" value="1"<?= checked(old('is_active', $s['is_active'] ?? 1)) ?>> <?= e(t('common.active')) ?></label></div><?php endif; ?>
<div class="actions"><button class="btn" type="submit"><?= e(t('common.save')) ?></button><a class="btn secondary" href="<?= e(url('bia/systems')) ?>"><?= e(t('common.cancel')) ?></a></div></form>
