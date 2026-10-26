<div class="card login-card">
  <h1><?= e(t('setup.title')) ?></h1>
  <p class="muted"><?= e(t('setup.intro')) ?></p>
  <details <?= $step === 'token' ? 'open' : '' ?>><summary><?= e(t('setup.requirements')) ?></summary>
    <table class="data"><tbody><?php foreach ($checks as $k => [$val, $ok]): ?><tr><th scope="row"><?= e($k) ?></th><td><?= e($val) ?></td><td><?= $ok ? '<span class="badge ok">OK</span>' : '<span class="badge danger">!</span>' ?></td></tr><?php endforeach; ?></tbody></table></details>

<?php if ($step === 'token'): ?>
  <h2 style="margin-top:16px"><?= e(t('setup.step_token')) ?></h2>
  <?php if (!$tokenAvailable): ?><div class="msg error"><?= e(t('setup.token_manual', ['file' => $tokenFile])) ?></div>
  <?php else: ?><p><?= e(t('setup.token_where', ['file' => $tokenFile])) ?></p><?php endif; ?>
  <form method="post" action="<?= e(url('setup/token')) ?>" autocomplete="off"><?= csrf_field() ?>
    <div class="field<?= field_error('token') ? ' has-error' : '' ?>"><label for="token"><?= e(t('setup.token')) ?></label><input type="text" id="token" name="token" required autocomplete="off" dir="ltr"><?= field_error('token') ?></div>
    <button class="btn" type="submit"><?= e(t('common.continue')) ?></button></form>

<?php elseif ($step === 'db'): ?>
  <h2 style="margin-top:16px"><?= e(t('setup.step_db')) ?></h2><p class="muted"><?= e(t('setup.db_help')) ?></p>
  <form method="post" action="<?= e(url('setup/db')) ?>" autocomplete="off" novalidate><?= csrf_field() ?>
    <?php foreach ([['db_host', 'setup.db_host', 'localhost', 'text'], ['db_port', 'setup.db_port', '3306', 'text'], ['db_name', 'setup.db_name', '', 'text'], ['db_user', 'setup.db_user', '', 'text'], ['db_pass', 'setup.db_pass', '', 'password']] as [$n, $l, $d, $t]): ?>
    <div class="field<?= field_error($n) ? ' has-error' : '' ?>"><label for="<?= $n ?>"><?= e(t($l)) ?></label><input type="<?= $t ?>" id="<?= $n ?>" name="<?= $n ?>" value="<?= $t === 'password' ? '' : e(old($n, $d)) ?>" dir="ltr" autocomplete="off"><?= field_error($n) ?></div>
    <?php endforeach; ?>
    <div class="field"><label for="timezone"><?= e(t('setup.timezone')) ?></label><input type="text" id="timezone" name="timezone" value="<?= e(old('timezone', 'Africa/Tripoli')) ?>" dir="ltr"></div>
    <div class="field"><label class="check"><input type="checkbox" name="force_https" value="1"> <?= e(t('setup.force_https')) ?></label><p class="hint"><?= e(t('setup.force_https_hint')) ?></p></div>
    <button class="btn" type="submit"><?= e(t('setup.test_save')) ?></button></form>

<?php else: ?>
  <h2 style="margin-top:16px"><?= e(t('setup.step_admin')) ?></h2>
  <?php if ($m = (\App\Core\Session::peek('errors')['form'] ?? null)): ?><div class="msg error" role="alert"><?= e($m) ?></div><?php endif; ?>
  <form method="post" action="<?= e(url('setup/finish')) ?>" autocomplete="off" novalidate><?= csrf_field() ?>
    <?php $f = static fn($n, $l, $type = 'text', $dir = '') => '<div class="field' . (field_error($n) ? ' has-error' : '') . '"><label for="' . $n . '">' . e(t($l)) . '</label><input type="' . $type . '" id="' . $n . '" name="' . $n . '" value="' . ($type === 'password' ? '' : e(old($n))) . '"' . ($dir ? ' dir="' . $dir . '"' : '') . ' autocomplete="off">' . field_error($n) . '</div>'; ?>
    <?= $f('org_name_en', 'settings.org_name_en') ?><?= $f('org_name_ar', 'settings.org_name_ar', 'text', 'rtl') ?>
    <hr>
    <?= $f('full_name', 'admin.full_name') ?><?= $f('username', 'admin.username', 'text', 'ltr') ?><?= $f('email', 'admin.email', 'email', 'ltr') ?>
    <?= $f('password', 'auth.password', 'password') ?><p class="hint"><?= e(t('password.policy')) ?></p><?= $f('password_confirm', 'account.confirm_password', 'password') ?>
    <hr>
    <div class="field"><label class="check"><input type="checkbox" name="load_demo" value="1" <?= checked(old('load_demo')) ?>> <?= e(t('setup.load_demo')) ?></label><p class="hint"><?= e(t('setup.demo_hint')) ?></p></div>
    <?= $f('demo_password', 'setup.demo_password', 'password') ?>
    <button class="btn" type="submit"><?= e(t('setup.install')) ?></button></form>
<?php endif; ?>
</div>
