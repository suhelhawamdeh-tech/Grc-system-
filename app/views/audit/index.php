<div class="page-head"><div><h1><?= e(t('nav.audit')) ?></h1><p class="sub"><?= e(t('common.total_n', ['n' => $total])) ?></p></div>
  <div class="actions"><a class="btn secondary" href="<?= e(url('audit/export') . qs(['page' => null])) ?>">CSV</a><a class="btn secondary" href="<?= e(url('audit/export') . qs(['page' => null, 'format' => 'xlsx'])) ?>">Excel</a><a class="btn secondary" href="<?= e(url('audit/export') . qs(['page' => null, 'sep' => ';'])) ?>">CSV (;)</a></div></div>
<form method="get" action="<?= e(url('audit')) ?>" class="card filters">
  <div class="field"><label for="action"><?= e(t('audit.action')) ?></label><input type="text" id="action" name="action" value="<?= e($f['action']) ?>" placeholder="risk."></div>
  <div class="field"><label for="user"><?= e(t('audit.user')) ?></label><input type="text" id="user" name="user" value="<?= e($f['user']) ?>"></div>
  <div class="field"><label for="entity"><?= e(t('audit.entity')) ?></label><input type="text" id="entity" name="entity" value="<?= e($f['entity']) ?>"></div>
  <div class="field"><label for="from"><?= e(t('common.from')) ?></label><input type="date" id="from" name="from" value="<?= e($f['from']) ?>"></div>
  <div class="field"><label for="to"><?= e(t('common.to')) ?></label><input type="date" id="to" name="to" value="<?= e($f['to']) ?>"></div>
  <div class="field actions"><button class="btn" type="submit"><?= e(t('common.filter')) ?></button><a class="btn secondary" href="<?= e(url('audit')) ?>"><?= e(t('common.reset')) ?></a></div>
</form>
<div class="table-wrap"><table class="data"><thead><tr><th><?= e(t('audit.time')) ?></th><th><?= e(t('audit.user')) ?></th><th><?= e(t('audit.action')) ?></th><th><?= e(t('audit.entity')) ?></th><th><?= e(t('audit.detail')) ?></th><th>IP</th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="6" class="muted"><?= e(t('common.no_results')) ?></td></tr><?php endif; ?>
<?php foreach ($rows as $r): ?><tr><td class="nowrap"><?= e($r['created_at']) ?></td><td><?= e($r['username'] ?? '—') ?></td><td class="nowrap"><?= e($r['action']) ?></td><td class="nowrap"><?= e(trim(($r['entity_type'] ?? '') . ' ' . ($r['entity_id'] ?? ''))) ?></td><td class="mono" dir="ltr" style="text-align:start;overflow-wrap:anywhere"><?= e($r['detail'] ?? '') ?></td><td class="nowrap"><?= e($r['ip']) ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?= pager($total, $page, $per) ?>
