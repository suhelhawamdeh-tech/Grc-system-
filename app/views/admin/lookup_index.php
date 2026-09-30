<div class="page-head"><div><h1><?= e(t($c['title'])) ?></h1></div><div class="actions"><a class="btn gold" href="<?= e(url('admin/' . $entity . '/new')) ?>"><?= e(t('common.add')) ?></a></div></div>
<?php if ($entity === 'categories'): ?><p class="muted"><?= e(t('admin.islamic_note')) ?></p><?php endif; ?>
<div class="table-wrap"><table class="data"><thead><tr>
<?php foreach ($c['fields'] as $f): ?><th><?= e(t($f['label'])) ?></th><?php endforeach; ?><th><?= e(t('common.status')) ?></th><th><?= e(t('admin.in_use')) ?></th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr>
<?php foreach ($c['fields'] as $f): ?><td<?= ($f['dir'] ?? '') === 'rtl' ? ' dir="rtl"' : '' ?>><?= e($f['type'] === 'select' ? enum_label('kind', $r[$f['name']]) : $r[$f['name']]) ?></td><?php endforeach; ?>
<td><?= $r['is_active'] ? '<span class="badge ok">' . e(t('common.active')) . '</span>' : '<span class="badge danger">' . e(t('common.inactive')) . '</span>' ?></td><td class="num"><?= (int)$r['_used'] ?></td>
<td><a class="btn small secondary" href="<?= e(url('admin/' . $entity . '/' . $r['id'] . '/edit')) ?>"><?= e(t('common.edit')) ?></a></td></tr><?php endforeach; ?></tbody></table></div>
<p class="muted"><?= e(t('admin.no_delete_note')) ?></p>
