<div class="page-head"><div><h1><?= e(t('admin.system')) ?></h1><p class="sub"><?= e(t('app.name')) ?> v<?= e($version) ?></p></div></div>
<section class="card"><h2><?= e(t('admin.environment')) ?></h2>
<div class="table-wrap"><table class="data"><tbody><?php foreach ($checks as $k => [$val, $ok]): ?><tr><th scope="row"><?= e(t('sys.' . $k)) ?></th><td><?= e($val) ?></td><td><?= $ok ? '<span class="badge ok">OK</span>' : '<span class="badge danger">' . e(t('sys.attention')) . '</span>' ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<section class="card"><h2><?= e(t('admin.migrations')) ?></h2>
<p><?= e(t('admin.applied')) ?>: <?= e(implode(', ', $applied) ?: '—') ?></p>
<?php if ($pending): ?><div class="msg info"><?= e(t('admin.pending')) ?>: <?= e(implode(', ', $pending)) ?></div>
<form method="post" action="<?= e(url('admin/system/migrate')) ?>" data-confirm="<?= e(t('admin.confirm_migrate')) ?>"><?= csrf_field() ?><button class="btn" type="submit"><?= e(t('admin.apply_migrations')) ?></button></form>
<p class="hint"><?= e(t('admin.backup_first')) ?></p>
<?php else: ?><p class="badge ok"><?= e(t('admin.up_to_date')) ?></p><?php endif; ?></section>
