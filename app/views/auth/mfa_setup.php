<?php /** @var array $user @var ?string $secret @var ?string $uri @var ?array $codes @var bool $required */ ?>
<div class="page-head"><div><h1><?= icon('shield') ?> <?= e(t('mfa.title')) ?></h1><p class="sub"><?= e(t('mfa.subtitle')) ?></p></div><div class="actions"><a class="btn secondary" href="<?= e(url('account')) ?>"><?= e(t('common.back')) ?></a></div></div>
<?php if ($required && !$user['totp_enabled']): ?><div class="msg info"><?= e(t('mfa.required_note')) ?></div><?php endif; ?>
<?php if ($codes): ?><section class="card" style="border-top:4px solid var(--gold)"><h2><?= icon('flag') ?> <?= e(t('mfa.recovery_title')) ?></h2><p><?= e(t('mfa.recovery_hint')) ?></p><div class="form-grid" dir="ltr"><?php foreach ($codes as $c): ?><code style="font-size:1.1rem"><?= e($c) ?></code><?php endforeach; ?></div></section><?php endif; ?>
<?php if ($user['totp_enabled']): ?><section class="card"><h2><?= e(t('mfa.is_on')) ?> <span class="badge ok"><?= e(t('mfa.on')) ?></span></h2>
<?php if (!$required): ?><form method="post" action="<?= e(url('account/mfa/disable')) ?>" data-confirm="<?= e(t('mfa.confirm_disable')) ?>"><?= csrf_field() ?>
<div class="field <?= field_error('password') ? 'has-error' : '' ?>"><label for="pw"><?= e(t('account.current_password')) ?></label><input type="password" id="pw" name="password" required autocomplete="current-password"><?= field_error('password') ?></div>
<button class="btn danger" type="submit"><?= e(t('mfa.disable')) ?></button></form><?php else: ?><p class="muted"><?= e(t('mfa.err_required')) ?></p><?php endif; ?></section>
<?php else: ?><section class="card"><h2><?= e(t('mfa.setup')) ?></h2>
<ol class="steps"><li><?= e(t('mfa.step1')) ?></li><li><?= e(t('mfa.step2')) ?><div dir="ltr" style="margin:8px 0"><code style="font-size:1.2rem;letter-spacing:.1em"><?= e(trim(chunk_split((string)$secret, 4, ' '))) ?></code></div><p class="hint"><a href="<?= e((string)$uri) ?>"><?= e(t('mfa.open_app')) ?></a></p></li><li><?= e(t('mfa.step3')) ?></li></ol>
<form method="post" action="<?= e(url('account/mfa/enable')) ?>" autocomplete="off"><?= csrf_field() ?>
<div class="field <?= field_error('code') ? 'has-error' : '' ?>"><label for="code"><?= e(t('mfa.code')) ?></label><input type="text" id="code" name="code" inputmode="numeric" required maxlength="8" autocomplete="one-time-code"><?= field_error('code') ?></div>
<button class="btn" type="submit"><?= e(t('mfa.enable')) ?></button></form></section><?php endif; ?>
