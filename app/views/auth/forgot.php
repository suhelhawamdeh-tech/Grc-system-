<div class="card login-card"><h1><?= e(t('reset.title')) ?></h1>
<?php if (!$available): ?><div class="msg info"><?= e(t('reset.unavailable')) ?></div><?php else: ?><p class="sub muted"><?= e(t('reset.intro')) ?></p>
<form method="post" action="<?= e(url('password/forgot')) ?>"><?= csrf_field() ?><div class="field"><label for="identifier"><?= e(t('reset.identifier')) ?></label><input type="text" id="identifier" name="identifier" required autofocus autocapitalize="off"></div><button class="btn" type="submit"><?= e(t('reset.send')) ?></button></form><?php endif; ?>
<p style="margin-top:12px"><a href="<?= e(url('login')) ?>"><?= e(t('reset.back')) ?></a></p></div>
