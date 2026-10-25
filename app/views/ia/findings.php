<?php /** @var array $rows @var array $f @var int $total @var int $page @var int $per */
$tab = 'findings'; $today = date('Y-m-d');
$sev = static fn(string $s) => '<span class="badge ' . ($s === 'high' ? 'danger' : ($s === 'medium' ? 'warn' : '')) . '">' . e(t('enum.sev.' . $s)) . '</span>'; ?>
<div class="page-head"><div><h1><?= icon('flag') ?> <?= e(t('ia.findings')) ?></h1><p class="sub"><?= e(t('ia.findings_hint')) ?> · <?= e(t('common.total_n', ['n' => $total])) ?></p></div></div>
<?php require __DIR__ . '/_tabs.php'; ?>
<form method="get" action="<?= e(url('ia/findings')) ?>" class="card filters">
<?= ff('select', 'status', 'ia.status', $f['status'], ['options' => ['not_closed' => t('ia.not_closed')] + opts(array_merge(can('ia.manage') ? ['draft'] : [], ['open', 'responded', 'implemented', 'closed']), fn($x) => t('enum.iafs.' . $x), null), 'blank_label' => 'common.all']) ?>
<?= ff('select', 'severity', 'ia.severity', $f['severity'], ['options' => opts(['high', 'medium', 'low'], fn($x) => t('enum.sev.' . $x), null), 'blank_label' => 'common.all']) ?>
<?php if (can('ia.view')): ?><?= ff('select', 'department', 'ia.department', $f['department'] ?? '', ['options' => opts(\App\Support\Ref::departments(), fn($d) => lname($d)), 'blank_label' => 'common.all']) ?><?php endif; ?>
<div class="field"><label class="check"><input type="checkbox" name="overdue" value="1"<?= checked($f['overdue']) ?>> <?= e(t('ia.overdue_only')) ?></label></div>
<div class="field actions"><button class="btn" type="submit"><?= e(t('common.filter')) ?></button><a class="btn secondary" href="<?= e(url('ia/findings')) ?>"><?= e(t('common.reset')) ?></a></div></form>
<div class="table-wrap"><table class="data"><thead><tr><th><?= e(t('ia.code')) ?></th><th><?= e(t('ia.f_title')) ?></th><th><?= e(t('ia.severity')) ?></th><th><?= e(t('ia.department')) ?></th><th><?= e(t('ia.owner')) ?></th><th><?= e(t('ia.due_date')) ?></th><th><?= e(t('ia.status')) ?></th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="7" class="empty"><?= icon('flag') ?><br><?= e(t('common.no_results')) ?></td></tr><?php endif; ?>
<?php foreach ($rows as $r): $due = $r['status'] === 'open' ? $r['response_due'] : ($r['status'] === 'responded' ? $r['due_date'] : null); $late = $due && $due < $today; ?><tr>
<td class="nowrap"><a href="<?= e(url('ia/findings/' . $r['id'])) ?>"><?= e($r['code']) ?></a><div class="muted small"><?= e($r['e_code']) ?></div></td><td><a href="<?= e(url('ia/findings/' . $r['id'])) ?>"><?= e($r['title']) ?></a></td><td><?= $sev($r['severity']) ?></td>
<td><?= e(lname($r, 'dept')) ?></td><td><?= e($r['owner']) ?></td><td class="nowrap"><?= e(fdate($due) ?: '—') ?><?= $late ? ' <span class="badge danger">' . e(t('my.overdue')) . '</span>' : '' ?><?= (int)$r['extension_count'] ? ' <span class="muted small">(' . e(t('ia.extended_n', ['n' => (int)$r['extension_count']])) . ')</span>' : '' ?></td>
<td><span class="badge <?= $r['status'] === 'closed' ? 'ok' : ($r['status'] === 'draft' ? '' : 'warn') ?>"><?= e(t('enum.iafs.' . $r['status'])) ?></span></td></tr><?php endforeach; ?></tbody></table></div>
<?= pager($total, $page, $per) ?>
