<?php /** @var array $d */
$sec = static function (string $titleKey, string $body): string { return '<section class="board-sec"><h2>' . e(t($titleKey)) . '</h2>' . $body . '</section>'; };
$tbl = static function (array $head, array $rows): string { $h = '<div class="table-wrap"><table class="data"><thead><tr>'; foreach ($head as $x) { $h .= '<th>' . e($x) . '</th>'; } $h .= '</tr></thead><tbody>'; foreach ($rows as $r) { $h .= '<tr>'; foreach ($r as $c) { $h .= '<td>' . $c . '</td>'; } $h .= '</tr>'; } return $h . '</tbody></table></div>'; };
$none = '<p class="muted">' . e(t('board.none')) . '</p>'; ?>
<p class="muted"><?= e(t('common.from')) ?> <?= e($d['from']) ?> — <?= e(t('common.to')) ?> <?= e($d['to']) ?></p>
<div class="kpis">
  <div class="kpi"><div class="v"><?= (int)$d['risks_active'] ?></div><div class="l"><?= e(t('dash.active_risks')) ?></div></div>
  <div class="kpi"><div class="v"><?= (int)$d['loss']['n'] ?></div><div class="l"><?= e(t('board.incidents')) ?></div></div>
  <div class="kpi"><div class="v" style="font-size:1.3rem"><?= e(money($d['loss']['net'])) ?></div><div class="l"><?= e(t('dash.net_loss_period')) ?></div></div>
  <div class="kpi"><div class="v"><?= count($d['overdue']) ?></div><div class="l"><?= e(t('dash.overdue_actions')) ?></div></div>
</div>
<?= $sec('board.top_risks', $d['top_risks'] ? $tbl([t('risk.id'), t('risk.title'), t('risk.department'), t('risk.res_score')], array_map(fn($r) => [e($r['risk_code']), e($r['title']), e(lname($r, 'dept')), '<strong>' . (int)$r['res_score'] . '</strong>'], $d['top_risks'])) : $none) ?>
<?= $sec('board.losses', $d['loss_cat'] ? $tbl([t('incident.category'), t('board.incidents'), t('dash.net_loss_period')], array_map(fn($r) => [e(lname($r)), (int)$r['n'], e(money($r['net']))], $d['loss_cat'])) : $none) ?>
<?= $sec('board.overdue', $d['overdue'] ? $tbl([t('action.title'), t('action.owner'), t('action.deadline'), t('risk.id')], array_map(fn($r) => [e($r['title']), e($r['full_name']), e($r['deadline']), e($r['risk_code'])], $d['overdue'])) : $none) ?>
<?php if (isset($d['kris'])) echo $sec('board.kris', $d['kris'] ? $tbl([t('kri.id'), t('kri.name'), t('kri.last_value'), t('kri.last_status')], array_map(fn($r) => [e($r['code']), e($r['name']), e(\App\Core\Money::plain($r['value']) . ' ' . $r['unit']), status_badge($r['status'])], $d['kris'])) : $none); ?>
<?php if (isset($d['breaches'])) echo $sec('board.breaches', $d['breaches'] ? $tbl([t('appetite.title'), t('appetite.detected_on'), t('comp.status')], array_map(fn($r) => [e($r['title']), e($r['detected_on']), status_badge($r['status'])], $d['breaches'])) : $none); ?>
<?php if (isset($d['controls'])) echo $sec('board.controls', $d['controls'] ? $tbl([t('ctl.id'), t('ctl.name'), t('ctl.last_result'), t('ctl.next_test')], array_map(fn($r) => [e($r['code']), e($r['name']), e($r['last_result'] ? t('enum.rating.' . $r['last_result']) : t('ctl.never_tested')), e(fdate($r['next_test_due']))], $d['controls'])) : $none); ?>
<?php if (isset($d['bia'])) echo $sec('board.bia', $d['bia'] ? $tbl([t('bia.id'), t('bia.name'), t('bia.criticality'), t('bia.status')], array_map(fn($r) => [e($r['code']), e($r['name']), e(t('enum.crit.' . $r['criticality'])), e(t('enum.bstatus.' . $r['status']))], $d['bia'])) : $none); ?>
<?php if (isset($d['sharia'])) echo $sec('board.sharia', $d['sharia'] ? $tbl([t('sha.e_code'), t('sha.e_title'), t('sha.e_severity'), t('sha.e_status')], array_map(fn($r) => [e($r['code']), e($r['title']), e(t('enum.sev.' . $r['severity'])), e(t('enum.sevent.' . $r['status']))], $d['sharia'])) : $none); ?>
