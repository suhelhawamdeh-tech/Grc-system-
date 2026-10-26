<div class="card login-card">
  <h1><?= e(t('mfa.login_title')) ?></h1>
  <p class="sub muted"><?= e(t('mfa.login_intro')) ?></p>
  <?= field_error('login') ? '<div class="msg error" role="alert">' . e((\App\Core\Session::peek('errors')['login'] ?? '')) . '</div>' : '' ?>
  <form method="post" action="<?= e(url('login/mfa')) ?>" autocomplete="off">
    <?= csrf_field() ?><input type="hidden" name="next" value="<?= e($next) ?>">
    <div class="field"><label for="code"><?= e(t('mfa.code')) ?></label><input type="text" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" required autofocus maxlength="12"><p class="hint"><?= e(t('mfa.code_hint')) ?></p></div>
    <button class="btn" type="submit"><?= e(t('auth.login')) ?></button> <a class="btn secondary" href="<?= e(url('login')) ?>"><?= e(t('common.cancel')) ?></a>
  </form>
</div>
