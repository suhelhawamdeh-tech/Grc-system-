<div class="card login-card">
  <h1><?= e(t('auth.login')) ?></h1>
  <p class="sub muted"><?= e(t('auth.login_intro')) ?></p>
  <?php if (!empty($expired)): ?><div class="msg info"><?= e(t('auth.session_expired')) ?></div><?php endif; ?>
  <?= field_error('login') ? '<div class="msg error" role="alert">' . e((\App\Core\Session::peek('errors')['login'] ?? '')) . '</div>' : '' ?>
  <form method="post" action="<?= e(url('login')) ?>" autocomplete="on">
    <?= csrf_field() ?>
    <input type="hidden" name="next" value="<?= e($next) ?>">
    <div class="field"><label for="username"><?= e(t('auth.username')) ?></label>
      <input type="text" id="username" name="username" autocomplete="username" required autofocus value="<?= e(old('username')) ?>" autocapitalize="off" spellcheck="false"></div>
    <div class="field"><label for="password"><?= e(t('auth.password')) ?></label>
      <input type="password" id="password" name="password" autocomplete="current-password" required></div>
    <button class="btn" type="submit"><?= e(t('auth.login')) ?></button>
  </form>
  <?php if (\App\Controllers\AuthController::resetAvailable()): ?><p style="margin-top:12px"><a href="<?= e(url('password/forgot')) ?>"><?= e(t('reset.link')) ?></a></p><?php endif; ?>
</div>
