<div class="page-head"><div><h1><?= e(t('cmt.title_plural')) ?></h1><p class="sub"><?= e(t('common.total_n', ['n' => $total])) ?></p></div><div class="actions"><?php if (can('committee.manage')): ?><a class="btn gold" href="<?= e(url('committees/new')) ?>"><?= e(t('cmt.new')) ?></a><?php endif; ?></div></div>
<form method="get" action="<?= e(url('committees')) ?>" class="card filters" role="search">
<?= ff('text', 'q', 'common.search', $f['q']) ?>
<?= ff('select', 'committee', 'cmt.committee', $f['committee'], ['options' => opts(\App\Controllers\CommitteeController::COMMITTEES, fn($x) => t('enum.committee.' . $x), null) ?: [], 'blank_label' => 'common.all']) ?>
<?= ff('select', 'status', 'cmt.status', $f['status'], ['options' => opts(['open', 'implemented', 'superseded'], fn($x) => t('enum.dstatus.' . $x), null) ?: [], 'blank_label' => 'common.all']) ?>
<div class="field"><label class="check"><input type="checkbox" name="overdue" value="1"<?= checked($f['overdue']) ?>> <?= e(t('cmt.overdue_only')) ?></label></div>
<div class="field actions"><button class="btn" type="submit"><?= e(t('common.filter')) ?></button><a class="btn secondary" href="<?= e(url('committees')) ?>"><?= e(t('common.reset')) ?></a></div></form>
<div class="table-wrap"><table class="data"><thead><tr><th><?= e(t('cmt.id')) ?></th><th><?= e(t('cmt.committee')) ?></th><th><?= e(t('cmt.meeting_date')) ?></th><th><?= e(t('cmt.name')) ?></th><th><?= e(t('cmt.status')) ?></th><th class="num"><?= e(t('cmt.actions')) ?></th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="6" class="muted"><?= e(t('common.no_results')) ?></td></tr><?php endif; ?>
<?php foreach ($rows as $c): ?><tr><td class="nowrap"><a href="<?= e(url('committees/' . $c['id'])) ?>"><?= e($c['code']) ?></a></td><td><?= e(t('enum.committee.' . $c['committee'])) ?></td><td class="nowrap"><?= e($c['meeting_date']) ?></td><td><a href="<?= e(url('committees/' . $c['id'])) ?>"><?= e($c['title']) ?></a></td><td><?= status_badge($c['status']) ?></td>
<td class="num"><?= (int)$c['n_actions'] ?><?= $c['n_overdue'] ? ' <span class="badge danger">' . (int)$c['n_overdue'] . ' ' . e(t('risk.overdue')) . '</span>' : '' ?></td></tr><?php endforeach; ?></tbody></table></div>
<?= pager($total, $page, $per) ?>
