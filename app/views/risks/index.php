<?php /** @var array $f */ ?>
<div class="page-head">
  <div><h1><?= e(t('risk.register')) ?></h1><p class="sub"><?= e(t('common.total_n', ['n' => $total])) ?></p></div>
  <div class="actions">
    <?php if (can('risk.create')): ?><a class="btn gold" href="<?= e(url('risks/new')) ?>"><?= e(t('risk.new')) ?></a><?php endif; ?>
    <?php if (can('export.run')): ?>
      <a class="btn secondary" href="<?= e(url('risks/export') . qs(['page' => null, 'sep' => null])) ?>">CSV</a>
      <a class="btn secondary" href="<?= e(url('risks/export') . qs(['page' => null, 'sep' => null, 'format' => 'xlsx'])) ?>">Excel</a>
      <a class="btn secondary" href="<?= e(url('risks/export') . qs(['page' => null, 'sep' => ';'])) ?>" title="<?= e(t('common.csv_semicolon_hint')) ?>">CSV (;)</a>
      <a class="btn secondary" href="<?= e(url('risks/print') . qs(['page' => null, 'sep' => null])) ?>" target="_blank" rel="noopener"><?= e(t('common.print')) ?></a>
    <?php endif; ?>
  </div>
</div>
<form method="get" action="<?= e(url('risks')) ?>" class="card filters" role="search">
  <div class="field"><label for="q"><?= e(t('common.search')) ?></label><input type="text" id="q" name="q" value="<?= e($f['q']) ?>" maxlength="100"></div>
  <div class="field"><label for="category"><?= e(t('risk.category')) ?></label><select id="category" name="category"><option value=""><?= e(t('common.all')) ?></option>
    <?php foreach ($lists['categories'] as $c): ?><option value="<?= (int)$c['id'] ?>"<?= sel($f['category'], $c['id']) ?>><?= e(lname($c)) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="department"><?= e(t('risk.department')) ?></label><select id="department" name="department"><option value=""><?= e(t('common.all')) ?></option>
    <?php foreach ($lists['departments'] as $c): ?><option value="<?= (int)$c['id'] ?>"<?= sel($f['department'], $c['id']) ?>><?= e(lname($c)) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="branch"><?= e(t('risk.branch')) ?></label><select id="branch" name="branch"><option value=""><?= e(t('common.all')) ?></option><option value="0"<?= sel($f['branch'], 0) ?>><?= e(t('dash.no_branch')) ?></option>
    <?php foreach ($lists['branches'] as $c): ?><option value="<?= (int)$c['id'] ?>"<?= sel($f['branch'], $c['id']) ?>><?= e(lname($c)) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="owner"><?= e(t('risk.owner')) ?></label><select id="owner" name="owner"><option value=""><?= e(t('common.all')) ?></option>
    <?php foreach ($lists['users'] as $c): ?><option value="<?= (int)$c['id'] ?>"<?= sel($f['owner'], $c['id']) ?>><?= e($c['full_name']) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="band"><?= e(t('risk.res_band')) ?></label><select id="band" name="band"><option value=""><?= e(t('common.all')) ?></option>
    <?php foreach ($lists['bands'] as $c): ?><option value="<?= (int)$c['id'] ?>"<?= sel($f['band'], $c['id']) ?>><?= e(lname($c, 'label')) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="status"><?= e(t('risk.status')) ?></label><select id="status" name="status"><option value=""><?= e(t('common.all')) ?></option>
    <?php foreach (['active', 'closed'] as $s): ?><option value="<?= $s ?>"<?= sel($f['status'], $s) ?>><?= e(enum_label('risk_status', $s)) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="archived"><?= e(t('risk.archived')) ?></label><select id="archived" name="archived">
    <option value=""<?= sel($f['archived'], '') ?>><?= e(t('risk.hide_archived')) ?></option><option value="1"<?= sel($f['archived'], '1') ?>><?= e(t('risk.only_archived')) ?></option><option value="all"<?= sel($f['archived'], 'all') ?>><?= e(t('risk.include_archived')) ?></option></select></div>
  <?php foreach (['inh_l', 'inh_i', 'res_l', 'res_i', 'inh_band', 'control', 'overdue_review', 'high'] as $hk): if (!empty($f[$hk])): ?><input type="hidden" name="<?= e($hk) ?>" value="<?= e($f[$hk]) ?>"><?php endif; endforeach; ?>
  <div class="field actions"><button class="btn" type="submit"><?= e(t('common.filter')) ?></button><a class="btn secondary" href="<?= e(url('risks')) ?>"><?= e(t('common.reset')) ?></a></div>
</form>
<?php $chips = []; foreach (['inh_l' => 'risk.inh_likelihood', 'inh_i' => 'risk.inh_impact', 'res_l' => 'risk.res_likelihood', 'res_i' => 'risk.res_impact'] as $k => $lbl) if ($f[$k]) $chips[] = t($lbl) . ' = ' . $f[$k];
if ($f['high']) $chips[] = t('dash.high_residual'); if ($f['overdue_review']) $chips[] = t('dash.review_overdue'); if ($f['control']) $chips[] = enum_label('control', $f['control']);
if ($chips): ?><p><?= e(t('common.active_filters')) ?>: <?php foreach ($chips as $c): ?><span class="badge"><?= e($c) ?></span> <?php endforeach; ?></p><?php endif; ?>

<div class="table-wrap"><table class="data">
  <thead><tr>
    <th><?= sort_link(t('risk.id'), 'code', $f) ?></th><th><?= sort_link(t('risk.title'), 'title', $f) ?></th><th><?= e(t('risk.category')) ?></th><th><?= e(t('risk.department')) ?> / <?= e(t('risk.branch')) ?></th>
    <th><?= e(t('risk.owner')) ?></th><th><?= sort_link(t('risk.inh_score'), 'inh', $f) ?></th><th><?= sort_link(t('risk.res_score'), 'res', $f) ?></th><th><?= sort_link(t('risk.review_date'), 'review', $f) ?></th><th><?= e(t('risk.status')) ?></th>
  </tr></thead>
  <tbody>
  <?php if (!$rows): ?><tr><td colspan="9" class="muted"><?= e(t('common.no_results')) ?></td></tr><?php endif; ?>
  <?php foreach ($rows as $r): $overdue = $r['review_date'] && $r['review_date'] < date('Y-m-d') && $r['status'] === 'active'; ?>
    <tr>
      <td class="nowrap"><a href="<?= e(url('risks/' . $r['id'])) ?>"><?= e($r['risk_code']) ?></a></td>
      <td><a href="<?= e(url('risks/' . $r['id'])) ?>"><?= e($r['title']) ?></a></td>
      <td><?= e(lname($r, 'cat')) ?><?php if ($r['cat_kind'] === 'islamic'): ?> <span class="badge islamic"><?= e(t('enum.kind.islamic')) ?></span><?php endif; ?></td>
      <td><?= e(lname($r, 'dept')) ?><?php if ($r['branch_id']): ?><br><span class="muted"><?= e(lname($r, 'branch')) ?></span><?php endif; ?></td>
      <td><?= e($r['owner_name']) ?></td>
      <td><?= band_chip((int)$r['inh_score']) ?></td><td><?= band_chip((int)$r['res_score']) ?></td>
      <td class="nowrap"><?= e(fdate($r['review_date'])) ?><?php if ($overdue): ?> <span class="badge danger"><?= e(t('risk.overdue')) ?></span><?php endif; ?></td>
      <td><?= e(enum_label('risk_status', $r['status'])) ?><?php if ($r['archived_at']): ?> <span class="badge warn"><?= e(t('risk.archived')) ?></span><?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?= pager($total, $page, $per) ?>
