<?php /** @var array $rows @var array $inactive @var int $year */
$tab = 'universe'; $prio = static fn(string $p) => '<span class="badge ' . ($p === 'high' ? 'danger' : ($p === 'medium' ? 'warn' : 'ok')) . '">' . e(t('enum.iaprio.' . $p)) . '</span>'; ?>
<div class="page-head"><div><h1><?= icon('layers') ?> <?= e(t('ia.universe')) ?></h1><p class="sub"><?= e(t('ia.universe_sub', ['y' => $year])) ?></p></div>
  <div class="actions"><?php if (can('ia.manage')): ?><a class="btn gold" href="<?= e(url('ia/universe/new')) ?>"><?= e(t('ia.universe_new')) ?></a><?php endif; ?></div></div>
<?php require __DIR__ . '/_tabs.php'; ?>
<details class="card"><summary><strong><?= icon('help') ?> <?= e(t('ia.how_score')) ?></strong></summary>
<p><?= e(t('ia.how_score_intro')) ?></p><ul><?php foreach (\App\Support\AuditPlanner::WEIGHTS as $k => $w): ?><li><strong><?= e(t(sprintf('ia.factor_%s', $k))) ?> (<?= (int)$w ?>%)</strong>: <?= e(t(sprintf('ia.factor_%s_hint', $k))) ?></li><?php endforeach; ?></ul>
<p><?= e(t('ia.how_score_bands')) ?></p></details>
<div class="table-wrap"><table class="data"><thead><tr><th><?= e(t('ia.code')) ?></th><th><?= e(t('ia.u_name')) ?></th><th><?= e(t('ia.department')) ?></th><th><?= e(t('ia.score')) ?></th><th><?= e(t('ia.priority')) ?></th><th><?= e(t('ia.last_audit')) ?></th><th><?= e(t('ia.due_in', ['y' => $year])) ?></th><?php if (can('ia.manage')): ?><th></th><?php endif; ?></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="8" class="empty"><?= icon('layers') ?><br><?= e(t('ia.universe_empty')) ?></td></tr><?php endif; ?>
<?php foreach ($rows as $r): $f = $r['factors']; ?><tr>
<td class="nowrap"><?= e($r['code']) ?></td><td><?= e($r['name']) ?><div class="muted small"><?php foreach ($f as $k => $val): ?><span title="<?= e(t(sprintf('ia.factor_%s', $k))) ?>"><?= e(t(sprintf('ia.factor_%s', $k))) ?> <?= (int)$val ?></span><?= $k !== 'rating' ? ' · ' : '' ?><?php endforeach; ?></div></td>
<td><?= e(lname($r, 'dept') ?: '—') ?></td>
<td><span class="meter <?= e($r['priority']) ?>"><span class="track"><span class="fill" style="width:<?= (int)round($r['score'] / 5 * 100) ?>%"></span></span><b><?= e(number_format((float)$r['score'], 2)) ?></b></span></td>
<td><?= $prio($r['priority']) ?></td><td class="nowrap"><?= e(fdate($r['last_audit_date']) ?: t('ia.never')) ?><?= $r['last_rating'] ? '<div class="muted small">' . e(t('enum.iarating.' . $r['last_rating'])) . '</div>' : '' ?></td>
<td><?= $r['due'] ? '<span class="badge warn">' . e(t('ia.due_yes')) . '</span>' : '<span class="muted">' . e(t('ia.due_no')) . '</span>' ?></td>
<?php if (can('ia.manage')): ?><td><a class="btn small secondary" href="<?= e(url('ia/universe/' . $r['id'] . '/edit')) ?>"><?= e(t('common.edit')) ?></a></td><?php endif; ?></tr><?php endforeach; ?></tbody></table></div>
<?php if ($inactive): ?><details class="card"><summary><?= e(t('ia.inactive_n', ['n' => count($inactive)])) ?></summary><ul><?php foreach ($inactive as $r): ?><li><?= e($r['code'] . ' — ' . $r['name']) ?><?php if (can('ia.manage')): ?> · <a href="<?= e(url('ia/universe/' . $r['id'] . '/edit')) ?>"><?= e(t('common.edit')) ?></a><?php endif; ?></li><?php endforeach; ?></ul></details><?php endif; ?>
