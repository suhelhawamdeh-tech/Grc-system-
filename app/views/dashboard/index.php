<?php
/** @var array $d */
$lik = \App\Support\Ref::levels('likelihood');
$imp = \App\Support\Ref::levels('impact');
$from = $per['from'];
$to = $per['to'];
$incLink = static fn(array $extra = []) => url('incidents', array_merge(['from' => $from, 'to' => $to], $extra));
?>
<div class="page-head">
  <div><h1><?= e(t('nav.dashboard')) ?></h1>
    <p class="sub"><?= e(t('dash.subtitle')) ?><?php if ($scoped): ?> · <strong><?= e(t('dash.scoped_note')) ?></strong><?php endif; ?></p></div>
  <form method="get" action="<?= e(url('')) ?>" class="filters" style="min-width:min(100%,560px)">
    <div class="field"><label for="period"><?= e(t('dash.period')) ?></label>
      <select id="period" name="period">
        <option value=""><?= e(t('dash.custom_range')) ?></option>
        <?php foreach ($periods as $p): ?><option value="<?= (int)$p['id'] ?>"<?= sel($per['id'], $p['id']) ?>><?= e(lname($p)) ?> (<?= e($p['start_date']) ?> → <?= e($p['end_date']) ?>)</option><?php endforeach; ?>
      </select></div>
    <div class="field"><label for="from"><?= e(t('common.from')) ?></label><input type="date" id="from" name="from" value="<?= e($from) ?>"></div>
    <div class="field"><label for="to"><?= e(t('common.to')) ?></label><input type="date" id="to" name="to" value="<?= e($to) ?>"></div>
    <div class="field"><button class="btn secondary" type="submit"><?= e(t('common.apply')) ?></button></div>
  </form>
</div>

<div class="kpis">
  <a class="kpi" href="<?= e(url('risks', ['status' => 'active'])) ?>"><div class="v"><?= (int)$d['active'] ?></div><div class="l"><?= e(t('dash.active_risks')) ?></div></a>
  <a class="kpi <?= $d['high'] ? 'alert' : '' ?>" href="<?= e(url('risks', ['status' => 'active', 'high' => 1])) ?>"><div class="v"><?= (int)$d['high'] ?></div><div class="l"><?= e(t('dash.high_residual')) ?> (≥ <?= (int)$highMin ?>)</div></a>
  <a class="kpi <?= $d['overdue_actions'] ? 'alert' : '' ?>" href="<?= e(url('actions', ['overdue' => 1])) ?>"><div class="v"><?= (int)$d['overdue_actions'] ?></div><div class="l"><?= e(t('dash.overdue_actions')) ?></div></a>
  <a class="kpi <?= $d['review_overdue'] ? 'alert' : '' ?>" href="<?= e(url('risks', ['status' => 'active', 'overdue_review' => 1])) ?>"><div class="v"><?= (int)$d['review_overdue'] ?></div><div class="l"><?= e(t('dash.review_overdue')) ?></div></a>
  <a class="kpi" href="<?= e($incLink()) ?>"><div class="v"><?= (int)$d['inc']['count'] ?></div><div class="l"><?= e(t('dash.incidents_in_period')) ?> (<?= (int)$d['inc_near'] ?> <?= e(t('dash.near_misses')) ?>)</div></a>
  <a class="kpi" href="<?= e($incLink()) ?>"><div class="v" style="font-size:1.35rem"><?= e(money(\App\Core\Money::toDb($d['inc']['net']))) ?></div><div class="l"><?= e(t('dash.net_loss_period')) ?><br><span class="muted"><?= e(t('incident.gross_loss')) ?>: <?= e(money(\App\Core\Money::toDb($d['inc']['gross']))) ?> · <?= e(t('incident.recoveries')) ?>: <?= e(money(\App\Core\Money::toDb($d['inc']['recoveries']))) ?></span></div></a>
</div>

<?php if (!empty($d['p2'])): $g = $d['p2']; $tile = static function (string $key, string $label, string $href, bool $alertIfPositive = true) use ($g): string { if (!isset($g[$key])) return ''; return '<a class="kpi ' . ($alertIfPositive && $g[$key] ? 'alert' : '') . '" href="' . e($href) . '"><div class="v">' . (int)$g[$key] . '</div><div class="l">' . e(t($label)) . '</div></a>'; }; ?>
<h2 style="margin-top:8px"><?= e(t('dash.governance')) ?></h2>
<div class="kpis">
<?= $tile('kri_red', 'dash.kri_red', url('kris', ['status' => 'red'])) ?><?= $tile('kri_amber', 'dash.kri_amber', url('kris', ['status' => 'amber'])) ?>
<?= $tile('breaches', 'dash.breaches', url('appetite')) ?><?= $tile('obl_overdue', 'dash.obl_overdue', url('compliance', ['overdue' => 1])) ?>
<?= $tile('dec_overdue', 'dash.dec_overdue', url('committees', ['overdue' => 1])) ?><?= $tile('rcsa_review', 'dash.rcsa_review', url('rcsa', ['status' => 'submitted']), false) ?>
<?= $tile('rcsa_overdue', 'dash.rcsa_overdue', url('rcsa', ['overdue' => 1])) ?><?= $tile('pol_overdue', 'dash.pol_overdue', url('policies', ['review_overdue' => 1])) ?>
<?= $tile('bia_unapproved', 'dash.bia_unapproved', url('bia', ['status' => 'draft'])) ?><?= $tile('bia_gaps', 'dash.bia_gaps', url('bia', ['gap' => 1])) ?><?= $tile('appr_pending', 'dash.appr_pending', url('approvals')) ?>
<?= $tile('sha_open', 'dash.sha_open', url('sharia/events', ['open' => 1])) ?><?= $tile('sha_purify', 'dash.sha_purify', url('sharia/events', ['purification' => 1])) ?>
<?= $tile('unproven', 'dash.unproven', url('risks', ['unproven' => 1])) ?><?= $tile('ctl_weak', 'dash.ctl_weak', url('controls', ['state' => 'ineffective'])) ?><?= $tile('ctl_overdue', 'dash.ctl_overdue', url('controls', ['state' => 'overdue'])) ?>
</div><?php endif; ?>

<div class="grid cols-2">
  <section class="card" aria-labelledby="h-inh"><h2 id="h-inh"><?= e(t('dash.heat_inh')) ?></h2><?= heatmap($d['heat_inh'], 'inh', $lik, $imp) ?></section>
  <section class="card" aria-labelledby="h-res"><h2 id="h-res"><?= e(t('dash.heat_res')) ?></h2><?= heatmap($d['heat_res'], 'res', $lik, $imp) ?></section>
</div>
<div class="card" style="padding-top:8px"><div class="legend" aria-label="<?= e(t('dash.legend')) ?>"><?php foreach (\App\Support\Ref::bands() as $b) echo band_label_chip($b); ?></div></div>

<div class="grid cols-3">
  <section class="card"><h2><?= e(t('dash.by_category')) ?></h2>
    <?= bars(array_map(static fn($r) => [lname($r), (int)$r['n'], url('risks', ['status' => 'active', 'category' => $r['gid']])], $d['by_category'])) ?></section>
  <section class="card"><h2><?= e(t('dash.by_department')) ?></h2>
    <?= bars(array_map(static fn($r) => [lname($r), (int)$r['n'], url('risks', ['status' => 'active', 'department' => $r['gid']])], $d['by_department'])) ?></section>
  <section class="card"><h2><?= e(t('dash.by_branch')) ?></h2>
    <?= bars(array_map(static fn($r) => [$r['gid'] ? lname($r) : t('dash.no_branch'), (int)$r['n'], url('risks', ['status' => 'active', 'branch' => $r['gid'] ?? 0])], $d['by_branch'])) ?></section>
</div>

<div class="grid cols-2">
  <section class="card"><h2><?= e(t('dash.top_residual')) ?></h2>
    <?php if (!$d['top_residual']): ?><p class="muted"><?= e(t('common.no_data')) ?></p><?php else: ?>
    <div class="table-wrap"><table class="data"><thead><tr><th><?= e(t('risk.id')) ?></th><th><?= e(t('risk.title')) ?></th><th><?= e(t('risk.department')) ?></th><th><?= e(t('risk.res_score')) ?></th></tr></thead><tbody>
    <?php foreach ($d['top_residual'] as $r): ?><tr><td class="nowrap"><a href="<?= e(url('risks/' . $r['id'])) ?>"><?= e($r['risk_code']) ?></a></td><td><?= e($r['title']) ?></td><td><?= e(lname($r, 'dept')) ?></td><td><?= band_chip((int)$r['res_score']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <p><a href="<?= e(url('risks', ['status' => 'active', 'high' => 1, 'sort' => 'res'])) ?>"><?= e(t('dash.view_all')) ?></a></p><?php endif; ?></section>
  <section class="card"><h2><?= e(t('dash.overdue_actions')) ?></h2>
    <?php if (!$d['overdue_list']): ?><p class="muted"><?= e(t('common.no_data')) ?></p><?php else: ?>
    <div class="table-wrap"><table class="data"><thead><tr><th><?= e(t('risk.id')) ?></th><th><?= e(t('action.title')) ?></th><th><?= e(t('action.owner')) ?></th><th><?= e(t('action.deadline')) ?></th></tr></thead><tbody>
    <?php foreach ($d['overdue_list'] as $a): ?><tr><td class="nowrap"><a href="<?= e(url('risks/' . $a['risk_id'])) ?>#actions"><?= e($a['risk_code']) ?></a></td><td><?= e($a['title']) ?></td><td><?= e($a['owner_name']) ?></td><td class="nowrap"><span class="badge danger"><?= e($a['deadline']) ?></span></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <p><a href="<?= e(url('actions', ['overdue' => 1])) ?>"><?= e(t('dash.view_all')) ?> (<?= (int)$d['overdue_actions'] ?>)</a></p><?php endif; ?></section>
</div>

<div class="grid cols-2">
  <section class="card"><h2><?= e(t('dash.incidents_by_category')) ?> <span class="muted" style="font-size:.85rem">(<?= e(t('incident.net_loss')) ?>, <?= e($per['label']) ?>)</span></h2>
    <?= bars(array_map(static fn($r) => [lname($r), \App\Core\Money::fromDb($r['net']) / 1000, $incLink(['category' => $r['gid']]), money($r['net']) . ' · ' . (int)$r['n']], $d['inc_by_category'])) ?></section>
  <section class="card"><h2><?= e(t('dash.incidents_by_month')) ?></h2>
    <?php if (!$d['inc_by_month']): ?><p class="muted"><?= e(t('common.no_data')) ?></p><?php else:
      $mx = max(1, max(array_map(static fn($r) => (int)$r['n'], $d['inc_by_month']))); ?>
    <div class="cols" role="list"><?php foreach ($d['inc_by_month'] as $m):
      $ms = $m['m'] . '-01'; $me = date('Y-m-t', strtotime($ms)); ?>
      <a role="listitem" href="<?= e(url('incidents', ['from' => max($from, $ms), 'to' => min($to, $me)])) ?>" title="<?= e($m['m'] . ': ' . $m['n'] . ' · ' . money($m['net'])) ?>">
        <span class="cv"><?= (int)$m['n'] ?></span><span class="col" style="height:<?= max(2, (int)round($m['n'] / $mx * 120)) ?>px"></span><span><?= e(substr($m['m'], 2)) ?></span></a>
    <?php endforeach; ?></div><p class="axis-note"><?= e(t('dash.incidents_count_note')) ?></p><?php endif; ?></section>
</div>
<p class="muted"><?= e(t('dash.data_note')) ?></p>
