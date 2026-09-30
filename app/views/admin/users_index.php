<div class="page-head"><div><h1><?= e(t('admin.users')) ?></h1><p class="sub"><?= e(t('common.total_n', ['n' => $total])) ?></p></div>
  <div class="actions"><a class="btn gold" href="<?= e(url('admin/users/new')) ?>"><?= e(t('admin.user_new')) ?></a></div></div>
<form method="get" action="<?= e(url('admin/users')) ?>" class="card filters">
  <div class="field"><label for="q"><?= e(t('common.search')) ?></label><input type="text" id="q" name="q" value="<?= e($q) ?>"></div>
  <div class="field"><label for="role"><?= e(t('admin.role')) ?></label><select id="role" name="role"><option value=""><?= e(t('common.all')) ?></option><?php foreach (\App\Core\Perm::ROLES as $ro): ?><option value="<?= e($ro) ?>"<?= sel($role, $ro) ?>><?= e(enum_label('role', $ro)) ?></option><?php endforeach; ?></select></div>
  <div class="field actions"><button class="btn" type="submit"><?= e(t('common.filter')) ?></button></div>
</form>
<div class="table-wrap"><table class="data"><thead><tr><th><?= e(t('admin.username')) ?></th><th><?= e(t('admin.full_name')) ?></th><th><?= e(t('admin.role')) ?></th><th><?= e(t('admin.scope')) ?></th><th><?= e(t('admin.owned_risks')) ?></th><th><?= e(t('admin.last_login')) ?></th><th><?= e(t('common.status')) ?></th><th></th></tr></thead><tbody>
<?php foreach ($rows as $u): ?><tr><td class="nowrap"><?= e($u['username']) ?></td><td><?= e($u['full_name']) ?></td><td><?= e(enum_label('role', $u['role'])) ?></td>
<td><?= e(\App\Support\Ref::name('department', $u['department_id'] ? (int)$u['department_id'] : null)) ?> <?= e(\App\Support\Ref::name('branch', $u['branch_id'] ? (int)$u['branch_id'] : null)) ?></td>
<td class="num"><?= (int)$u['risk_count'] ?></td><td class="nowrap"><?= e(fdatetime($u['last_login_at'])) ?></td>
<td><?= $u['is_active'] ? '<span class="badge ok">' . e(t('common.active')) . '</span>' : '<span class="badge danger">' . e(t('common.inactive')) . '</span>' ?></td>
<td><a class="btn small secondary" href="<?= e(url('admin/users/' . $u['id'] . '/edit')) ?>"><?= e(t('common.edit')) ?></a></td></tr><?php endforeach; ?></tbody></table></div>
<?= pager($total, $page, $per) ?>
