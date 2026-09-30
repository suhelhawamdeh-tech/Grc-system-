<div class="page-head"><div><h1><?= e(t('action.title_plural')) ?></h1><p class="sub"><?= e(t('common.total_n', ['n' => $total])) ?></p></div></div>
<form method="get" action="<?= e(url('actions')) ?>" class="card filters">
  <div class="field"><label for="overdue"><?= e(t('action.overdue_only')) ?></label><select id="overdue" name="overdue"><option value=""><?= e(t('common.all')) ?></option><option value="1"<?= sel($f['overdue'], '1') ?>><?= e(t('common.yes')) ?></option></select></div>
  <div class="field"><label for="status"><?= e(t('action.status')) ?></label><select id="status" name="status"><option value=""><?= e(t('common.all')) ?></option><?php foreach (['not_started', 'in_progress', 'completed', 'cancelled'] as $s): ?><option value="<?= $s ?>"<?= sel($f['status'], $s) ?>><?= e(enum_label('action_status', $s)) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="owner"><?= e(t('action.owner')) ?></label><select id="owner" name="owner"><option value=""><?= e(t('common.all')) ?></option><?php foreach ($users as $x): ?><option value="<?= (int)$x['id'] ?>"<?= sel($f['owner'], $x['id']) ?>><?= e($x['full_name']) ?></option><?php endforeach; ?></select></div>
  <div class="field actions"><button class="btn" type="submit"><?= e(t('common.filter')) ?></button><a class="btn secondary" href="<?= e(url('actions')) ?>"><?= e(t('common.reset')) ?></a></div>
</form>
<div class="table-wrap"><table class="data"><thead><tr><th><?= e(t('risk.id')) ?></th><th><?= e(t('action.title')) ?></th><th><?= e(t('action.owner')) ?></th><th><?= e(t('action.deadline')) ?></th><th><?= e(t('action.status')) ?></th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="5" class="muted"><?= e(t('common.no_results')) ?></td></tr><?php endif; ?>
<?php foreach ($rows as $a): $late = in_array($a['status'], ['not_started', 'in_progress'], true) && $a['deadline'] < date('Y-m-d'); ?>
<tr><td class="nowrap"><a href="<?= e(url('risks/' . $a['risk_id'])) ?>#actions"><?= e($a['risk_code']) ?></a></td><td><?= e($a['title']) ?><br><span class="muted"><?= e($a['risk_title']) ?></span></td><td><?= e($a['owner_name']) ?></td>
<td class="nowrap"><?= e($a['deadline']) ?><?php if ($late): ?> <span class="badge danger"><?= e(t('risk.overdue')) ?></span><?php endif; ?></td><td><?= e(enum_label('action_status', $a['status'])) ?></td></tr>
<?php endforeach; ?></tbody></table></div>
<?= pager($total, $page, $per) ?>
