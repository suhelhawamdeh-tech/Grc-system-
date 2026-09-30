<?php $flt = []; foreach (['q' => 'common.search', 'status' => 'risk.status'] as $k => $l) if ($f[$k] !== '') $flt[] = t($l) . ': ' . ($k === 'status' ? enum_label('risk_status', $f[$k]) : $f[$k]);
foreach (['category' => ['risk.category', 'categories'], 'department' => ['risk.department', 'departments'], 'branch' => ['risk.branch', 'branches']] as $k => [$l, $set]) if ($f[$k] !== null) $flt[] = t($l) . ': ' . ($f[$k] === 0 ? t('dash.no_branch') : lname($lists[$set][$f[$k]] ?? (\App\Support\Ref::$set())[$f[$k]] ?? null));
if ($f['band']) { foreach ($lists['bands'] as $b) if ((int)$b['id'] === $f['band']) $flt[] = t('risk.res_band') . ': ' . lname($b, 'label'); }
foreach (['inh_l', 'inh_i', 'res_l', 'res_i'] as $k) if ($f[$k]) $flt[] = $k . ' = ' . $f[$k];
if ($f['high']) $flt[] = t('dash.high_residual'); if ($f['overdue_review']) $flt[] = t('dash.review_overdue'); ?>
<p class="print-meta"><?= e(t('report.filters')) ?>: <?= $flt ? e(implode(' · ', $flt)) : e(t('common.all')) ?> — <?= e(t('report.rows', ['shown' => count($rows), 'total' => $total])) ?></p>
<?php if ($total > count($rows)): ?><div class="msg info noprint"><?= e(t('report.truncated', ['n' => count($rows)])) ?></div><?php endif; ?>
<table class="data"><thead><tr><th><?= e(t('risk.id')) ?></th><th><?= e(t('risk.title')) ?></th><th><?= e(t('risk.category')) ?></th><th><?= e(t('risk.department')) ?></th><th><?= e(t('risk.owner')) ?></th>
  <th><?= e(t('risk.inh_score')) ?></th><th><?= e(t('risk.control_assessment')) ?></th><th><?= e(t('risk.res_score')) ?></th><th><?= e(t('risk.treatment')) ?></th><th><?= e(t('risk.review_date')) ?></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td class="nowrap"><?= e($r['risk_code']) ?></td><td><?= e($r['title']) ?></td><td><?= e(lname($r, 'cat')) ?></td><td><?= e(lname($r, 'dept')) ?><?= $r['branch_id'] ? ' / ' . e(lname($r, 'branch')) : '' ?></td><td><?= e($r['owner_name']) ?></td>
  <td><?= band_chip((int)$r['inh_score']) ?></td><td><?= e(enum_label('control', $r['control_assessment'])) ?></td><td><?= band_chip((int)$r['res_score']) ?></td><td><?= e(enum_label('treatment', $r['treatment_decision'])) ?></td><td class="nowrap"><?= e(fdate($r['review_date'])) ?></td></tr><?php endforeach; ?>
</tbody></table>
<p class="print-meta"><?= e(t('scales.print_note')) ?></p>
