<div class="card login-card"><h1><?= e(t('reset.new_title')) ?></h1>
<?php if (!$valid): ?><div class="msg error"><?= e(t('reset.invalid')) ?></div><p><a href="<?= e(url('password/forgot')) ?>"><?= e(t('reset.title')) ?></a></p><?php else: ?><p class="muted"><?= e(t('password.policy')) ?></p>
<form method="post" action="<?= e(url('password/reset')) ?>" autocomplete="off"><?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>">
<div class="field <?= field_error('new_password') ? 'has-error' : '' ?>"><label for="np"><?= e(t('account.new_password')) ?></label><input type="password" id="np" name="new_password" required minlength="12" autocomplete="new-password"><?= field_error('new_password') ?></div>
<div class="field <?= field_error('confirm_password') ? 'has-error' : '' ?>"><label for="cf"><?= e(t('account.confirm_password')) ?></label><input type="password" id="cf" name="confirm_password" required autocomplete="new-password"><?= field_error('confirm_password') ?></div>
<button class="btn" type="submit"><?= e(t('common.save')) ?></button></form><?php endif; ?></div>
