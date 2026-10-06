<div class="page-head"><div><h1><?= e(t('account.title')) ?></h1>
  <p class="sub"><?= e($user['full_name']) ?> · <?= e($user['username']) ?> · <?= e(enum_label('role', $user['role'])) ?></p></div></div>
<?php if ((int)$user['must_change_password'] === 1): ?><div class="msg info"><?= e(t('account.must_change')) ?></div><?php endif; ?>
<div class="grid cols-2">
<div class="card">
  <h2><?= e(t('account.change_password')) ?></h2>
  <p class="muted"><?= e(t('password.policy')) ?></p>
  <form method="post" action="<?= e(url('account/password')) ?>" autocomplete="off">
    <?= csrf_field() ?>
    <div class="field <?= field_error('current_password') ? 'has-error' : '' ?>"><label for="cp"><?= e(t('account.current_password')) ?></label>
      <input type="password" id="cp" name="current_password" required autocomplete="current-password"><?= field_error('current_password') ?></div>
    <div class="field <?= field_error('new_password') ? 'has-error' : '' ?>"><label for="np"><?= e(t('account.new_password')) ?></label>
      <input type="password" id="np" name="new_password" required autocomplete="new-password" minlength="12"><?= field_error('new_password') ?></div>
    <div class="field <?= field_error('confirm_password') ? 'has-error' : '' ?>"><label for="cf"><?= e(t('account.confirm_password')) ?></label>
      <input type="password" id="cf" name="confirm_password" required autocomplete="new-password"><?= field_error('confirm_password') ?></div>
    <button class="btn" type="submit"><?= e(t('common.save')) ?></button>
  </form>
</div>
<div class="card">
  <h2><?= e(t('account.language')) ?></h2>
  <form method="post" action="<?= e(url('account/language')) ?>">
    <?= csrf_field() ?>
    <div class="field"><label for="lang"><?= e(t('account.preferred_language')) ?></label>
      <select id="lang" name="lang"><option value="ar"<?= sel($user['lang'] ?? locale(), 'ar') ?>>العربية</option><option value="en"<?= sel($user['lang'] ?? locale(), 'en') ?>>English</option></select></div>
    <button class="btn secondary" type="submit"><?= e(t('common.save')) ?></button>
  </form>
</div>
</div>
<section class="card"><h2><?= e(t('account.email_notifications')) ?></h2>
<form method="post" action="<?= e(url('account/notifications')) ?>"><?= csrf_field() ?>
<div class="field"><label class="check"><input type="checkbox" name="email_on" value="1"<?= checked(\App\Support\Prefs::get((int)$user['id'], 'email_notifications') !== '0') ?>> <?= e(t('account.email_on')) ?></label></div>
<button class="btn secondary" type="submit"><?= e(t('common.save')) ?></button></form></section>
<section class="card"><h2><?= e(t('mfa.title')) ?></h2><p><span class="badge <?= !empty($user['totp_enabled']) ? 'ok' : 'warn' ?>"><?= e(t(!empty($user['totp_enabled']) ? 'mfa.on' : 'mfa.setup')) ?></span> <a class="btn secondary small" href="<?= e(url('account/mfa')) ?>"><?= e(t('mfa.title')) ?></a></p></section>
