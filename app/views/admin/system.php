<div class="page-head"><div><h1><?= e(t('admin.system')) ?></h1><p class="sub"><?= e(t('app.name')) ?> v<?= e($version) ?></p></div></div>
<section class="card"><h2><?= e(t('admin.environment')) ?></h2>
<div class="table-wrap"><table class="data"><tbody><?php foreach ($checks as $k => [$val, $ok]): ?><tr><th scope="row"><?= e(t('sys.' . $k)) ?></th><td><?= e($val) ?></td><td><?= $ok ? '<span class="badge ok">OK</span>' : '<span class="badge danger">' . e(t('sys.attention')) . '</span>' ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<section class="card"><h2><?= e(t('admin.migrations')) ?></h2>
<p><?= e(t('admin.applied')) ?>: <?= e(implode(', ', $applied) ?: '—') ?></p>
<?php if ($pending): ?><div class="msg info"><?= e(t('admin.pending')) ?>: <?= e(implode(', ', $pending)) ?></div>
<form method="post" action="<?= e(url('admin/system/migrate')) ?>" data-confirm="<?= e(t('admin.confirm_migrate')) ?>"><?= csrf_field() ?><button class="btn" type="submit"><?= e(t('admin.apply_migrations')) ?></button></form>
<p class="hint"><?= e(t('admin.backup_first')) ?></p>
<?php else: ?><p class="badge ok"><?= e(t('admin.up_to_date')) ?></p><?php endif; ?></section>
<section class="card"><h2><?= icon('note') ?> <?= e(t('mail.card')) ?></h2>
<p><span class="badge <?= $mailOn ? 'ok' : 'warn' ?>"><?= e(t($mailOn ? 'mail.status_on' : 'mail.status_off')) ?></span></p><p class="hint"><?= e(t('mail.cron_hint')) ?></p>
<?php if ($mailOn): ?><form method="post" action="<?= e(url('admin/system/test-mail')) ?>" class="filters"><?= csrf_field() ?><div class="field"><label for="to"><?= e(t('mail.test_to')) ?></label><input type="email" id="to" name="to" value="<?= e(current_user()['email'] ?? '') ?>"></div><div class="field"><button class="btn secondary" type="submit"><?= e(t('mail.test_send')) ?></button></div></form><?php endif; ?>
<?php if ($mailLog): ?><h3><?= e(t('mail.recent')) ?></h3><div class="table-wrap"><table class="data"><tbody><?php foreach ($mailLog as $m): ?><tr><td class="nowrap"><?= e(fdatetime($m['created_at'])) ?></td><td><?= e($m['kind']) ?></td><td><?= e($m['recipient']) ?></td><td><span class="badge <?= $m['status'] === 'sent' ? 'ok' : 'danger' ?>"><?= e($m['status']) ?></span> <?= e($m['error'] ?? '') ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
