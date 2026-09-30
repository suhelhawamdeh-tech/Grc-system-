<?php $v = static fn(string $k, $d = '') => old($k, $u[$k] ?? $d); $err = static fn(string $k) => field_error($k) ? ' has-error' : ''; ?>
<div class="page-head"><div><h1><?= e($title) ?></h1></div></div>
<div class="grid cols-2">
<form method="post" class="card" action="<?= e($existing ? url('admin/users/' . $existing['id']) : url('admin/users')) ?>" novalidate><?= csrf_field() ?>
  <div class="field<?= $err('username') ?>"><label for="username"><?= e(t('admin.username')) ?> *</label>
    <input type="text" id="username" name="username" value="<?= e($v('username')) ?>" <?= $existing ? 'readonly' : 'required' ?> autocomplete="off" autocapitalize="off"><?= field_error('username') ?></div>
  <div class="field<?= $err('full_name') ?>"><label for="full_name"><?= e(t('admin.full_name')) ?> *</label><input type="text" id="full_name" name="full_name" value="<?= e($v('full_name')) ?>" required><?= field_error('full_name') ?></div>
  <div class="field<?= $err('email') ?>"><label for="email"><?= e(t('admin.email')) ?></label><input type="email" id="email" name="email" value="<?= e($v('email')) ?>"><?= field_error('email') ?></div>
  <div class="field<?= $err('role') ?>"><label for="role"><?= e(t('admin.role')) ?> *</label><select id="role" name="role"><?php foreach ($lists['roles'] as $ro): ?><option value="<?= e($ro) ?>"<?= sel($v('role'), $ro) ?>><?= e(enum_label('role', $ro)) ?></option><?php endforeach; ?></select><?= field_error('role') ?>
    <p class="hint"><?= e(t('admin.role_hint')) ?></p></div>
  <div class="field<?= $err('department_id') ?>"><label for="department_id"><?= e(t('admin.department')) ?></label><select id="department_id" name="department_id"><option value=""><?= e(t('common.none')) ?></option><?php foreach ($lists['departments'] as $d): ?><option value="<?= (int)$d['id'] ?>"<?= sel($v('department_id'), $d['id']) ?>><?= e(lname($d)) ?></option><?php endforeach; ?></select><?= field_error('department_id') ?></div>
  <div class="field<?= $err('branch_id') ?>"><label for="branch_id"><?= e(t('admin.branch')) ?></label><select id="branch_id" name="branch_id"><option value=""><?= e(t('common.none')) ?></option><?php foreach ($lists['branches'] as $d): ?><option value="<?= (int)$d['id'] ?>"<?= sel($v('branch_id'), $d['id']) ?>><?= e(lname($d)) ?></option><?php endforeach; ?></select><?= field_error('branch_id') ?></div>
  <div class="field"><label for="lang"><?= e(t('admin.language')) ?></label><select id="lang" name="lang"><option value=""><?= e(t('common.none')) ?></option><option value="ar"<?= sel($v('lang'), 'ar') ?>>العربية</option><option value="en"<?= sel($v('lang'), 'en') ?>>English</option></select></div>
  <?php if (!$existing): ?>
  <div class="field<?= $err('password') ?>"><label for="password"><?= e(t('admin.initial_password')) ?> *</label><input type="password" id="password" name="password" required autocomplete="new-password" minlength="12"><p class="hint"><?= e(t('password.policy')) ?> <?= e(t('admin.must_change_hint')) ?></p><?= field_error('password') ?></div>
  <?php else: ?>
  <div class="field<?= $err('is_active') ?>"><label class="check"><input type="checkbox" name="is_active" value="1"<?= checked($v('is_active', 1)) ?>> <?= e(t('common.active')) ?></label><?= field_error('is_active') ?></div>
  <?php endif; ?>
  <div class="actions"><button class="btn" type="submit"><?= e(t('common.save')) ?></button><a class="btn secondary" href="<?= e(url('admin/users')) ?>"><?= e(t('common.cancel')) ?></a></div>
</form>
<?php if ($existing): ?>
<div>
<form method="post" class="card" action="<?= e(url('admin/users/' . $existing['id'] . '/password')) ?>" autocomplete="off"><?= csrf_field() ?>
  <h2><?= e(t('admin.reset_password')) ?></h2>
  <div class="field<?= $err('reset_password') ?>"><label for="rp"><?= e(t('admin.new_temp_password')) ?></label><input type="password" id="rp" name="password" required autocomplete="new-password" minlength="12"><p class="hint"><?= e(t('admin.must_change_hint')) ?></p><?= field_error('reset_password') ?></div>
  <button class="btn secondary" type="submit"><?= e(t('admin.reset_password')) ?></button>
</form>
<?php if (can('ownership.transfer')): ?>
<form method="post" class="card" action="<?= e(url('admin/users/' . $existing['id'] . '/transfer')) ?>" data-confirm="<?= e(t('admin.confirm_transfer')) ?>"><?= csrf_field() ?>
  <h2><?= e(t('admin.transfer_ownership')) ?></h2><p class="muted"><?= e(t('admin.owned_risks')) ?>: <strong><?= (int)$owned ?></strong></p>
  <div class="field<?= $err('to_user') ?>"><label for="to_user"><?= e(t('admin.transfer_to')) ?></label><select id="to_user" name="to_user"><option value=""><?= e(t('common.select')) ?></option>
    <?php foreach (\App\Support\Ref::users(true) as $x): if ((int)$x['id'] === (int)$existing['id']) continue; ?><option value="<?= (int)$x['id'] ?>"><?= e($x['full_name']) ?></option><?php endforeach; ?></select><?= field_error('to_user') ?></div>
  <button class="btn secondary" type="submit"><?= e(t('admin.transfer')) ?></button>
</form><?php endif; ?>
</div>
<?php endif; ?>
</div>
