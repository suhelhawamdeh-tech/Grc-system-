<?php /** @var array $rows @var array $f @var int $total @var int $page @var int $per */ ?>
<div class="page-head"><div><h1><?= icon('file') ?> <?= e(t('cir.title')) ?></h1><p class="sub"><?= e(t('cir.subtitle')) ?> · <?= e(t('common.total_n', ['n' => $total])) ?></p></div>
  <div class="actions"><?php if (can('circular.manage')): ?><a class="btn gold" href="<?= e(url('circulars/new')) ?>"><?= e(t('cir.new')) ?></a><?php endif; ?></div></div>
<form method="get" action="<?= e(url('circulars')) ?>" class="card filters">
  <?= ff('text', 'q', 'common.search', $f['q']) ?>
  <?= ff('select', 'status', 'cir.status', $f['status'], ['options' => opts(['draft', 'distributed', 'closed'], fn($x) => t('enum.cstatus.' . $x), null), 'blank_label' => 'common.all']) ?>
  <div class="field"><label class="check"><input type="checkbox" name="overdue" value="1"<?= checked($f['overdue']) ?>> <?= e(t('cir.overdue_only')) ?></label></div>
  <div class="field actions"><button class="btn" type="submit"><?= e(t('common.filter')) ?></button><a class="btn secondary" href="<?= e(url('circulars')) ?>"><?= e(t('common.reset')) ?></a></div></form>
<div class="table-wrap"><table class="data"><thead><tr><th><?= e(t('cir.id')) ?></th><th><?= e(t('cir.c_title')) ?></th><th><?= e(t('cir.issuer')) ?></th><th><?= e(t('cir.received_on')) ?></th><th><?= e(t('cir.due_date')) ?></th><th><?= e(t('cir.status')) ?></th><th class="num"><?= e(t('cir.progress')) ?></th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="7" class="empty"><?= icon('file') ?><br><?= e(t('common.no_results')) ?></td></tr><?php endif; ?>
<?php foreach ($rows as $c): $late = $c['status'] === 'distributed' && $c['due_date'] && $c['due_date'] < date('Y-m-d'); ?><tr><td class="nowrap"><a href="<?= e(url('circulars/' . $c['id'])) ?>"><?= e($c['code']) ?></a></td><td><a href="<?= e(url('circulars/' . $c['id'])) ?>"><?= e($c['title']) ?></a></td><td><?= e($c['issuer']) ?></td><td class="nowrap"><?= e(fdate($c['received_on'])) ?></td><td class="nowrap"><?= e(fdate($c['due_date']) ?: '—') ?><?= $late ? ' <span class="badge danger">' . e(t('my.overdue')) . '</span>' : '' ?></td>
<td><span class="badge <?= $c['status'] === 'closed' ? 'ok' : ($c['status'] === 'distributed' ? 'warn' : '') ?>"><?= e(t('enum.cstatus.' . $c['status'])) ?></span></td><td class="num"><?= $c['dist_n'] ? (int)$c['ack_n'] . '/' . (int)$c['dist_n'] : '—' ?></td></tr><?php endforeach; ?></tbody></table></div>
<?= pager($total, $page, $per) ?>
