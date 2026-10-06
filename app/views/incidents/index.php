<div class="page-head">
  <div><h1><?= e(t('incident.title_plural')) ?></h1><p class="sub"><?= e(t('common.total_n', ['n' => $total])) ?></p></div>
  <div class="actions">
    <?php if (can('incident.create')): ?><a class="btn gold" href="<?= e(url('incidents/new')) ?>"><?= e(t('incident.new')) ?></a><?php endif; ?>
    <?php if (can('export.run')): ?>
      <a class="btn secondary" href="<?= e(url('incidents/patterns')) ?>"><?= e(t('incident.patterns')) ?></a>
      <?php if (can('recon.view')): ?><a class="btn secondary" href="<?= e(url('incidents/reconciliation')) ?>"><?= e(t('recon.title')) ?></a><?php endif; ?>
      <a class="btn secondary" href="<?= e(url('incidents/export') . qs(['page' => null, 'sep' => null])) ?>">CSV</a>
      <a class="btn secondary" href="<?= e(url('incidents/export') . qs(['page' => null, 'sep' => null, 'format' => 'xlsx'])) ?>">Excel</a>
      <a class="btn secondary" href="<?= e(url('incidents/export') . qs(['page' => null, 'sep' => ';'])) ?>">CSV (;)</a>
      <a class="btn secondary" href="<?= e(url('incidents/print') . qs(['page' => null, 'sep' => null])) ?>" target="_blank" rel="noopener"><?= e(t('common.print')) ?></a>
    <?php endif; ?>
  </div>
</div>
<form method="get" action="<?= e(url('incidents')) ?>" class="card filters" role="search">
  <div class="field"><label for="q"><?= e(t('common.search')) ?></label><input type="text" id="q" name="q" value="<?= e($f['q']) ?>" maxlength="100"></div>
  <div class="field"><label for="type"><?= e(t('incident.type')) ?></label><select id="type" name="type"><option value=""><?= e(t('common.all')) ?></option><?php foreach (['incident', 'near_miss'] as $s): ?><option value="<?= $s ?>"<?= sel($f['type'], $s) ?>><?= e(enum_label('event', $s)) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="category"><?= e(t('incident.category')) ?></label><select id="category" name="category"><option value=""><?= e(t('common.all')) ?></option><?php foreach ($lists['categories'] as $c): ?><option value="<?= (int)$c['id'] ?>"<?= sel($f['category'], $c['id']) ?>><?= e(lname($c)) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="department"><?= e(t('incident.department')) ?></label><select id="department" name="department"><option value=""><?= e(t('common.all')) ?></option><?php foreach ($lists['departments'] as $c): ?><option value="<?= (int)$c['id'] ?>"<?= sel($f['department'], $c['id']) ?>><?= e(lname($c)) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="branch"><?= e(t('incident.branch')) ?></label><select id="branch" name="branch"><option value=""><?= e(t('common.all')) ?></option><option value="0"<?= sel($f['branch'], 0) ?>><?= e(t('dash.no_branch')) ?></option><?php foreach ($lists['branches'] as $c): ?><option value="<?= (int)$c['id'] ?>"<?= sel($f['branch'], $c['id']) ?>><?= e(lname($c)) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="status"><?= e(t('incident.status')) ?></label><select id="status" name="status"><option value=""><?= e(t('common.all')) ?></option><?php foreach (['open', 'investigating', 'closed'] as $s): ?><option value="<?= $s ?>"<?= sel($f['status'], $s) ?>><?= e(enum_label('incident_status', $s)) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="risk"><?= e(t('incident.linked_risk')) ?></label><select id="risk" name="risk"><option value=""><?= e(t('common.all')) ?></option><?php foreach ($risks as $c): ?><option value="<?= (int)$c['id'] ?>"<?= sel($f['risk'], $c['id']) ?>><?= e($c['risk_code']) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="from"><?= e(t('common.from')) ?></label><input type="date" id="from" name="from" value="<?= e($f['from']) ?>"></div>
  <div class="field"><label for="to"><?= e(t('common.to')) ?></label><input type="date" id="to" name="to" value="<?= e($f['to']) ?>"></div>
  <div class="field"><label for="archived"><?= e(t('risk.archived')) ?></label><select id="archived" name="archived"><option value=""<?= sel($f['archived'], '') ?>><?= e(t('risk.hide_archived')) ?></option><option value="1"<?= sel($f['archived'], '1') ?>><?= e(t('risk.only_archived')) ?></option><option value="all"<?= sel($f['archived'], 'all') ?>><?= e(t('risk.include_archived')) ?></option></select></div>
  <div class="field actions"><button class="btn" type="submit"><?= e(t('common.filter')) ?></button><a class="btn secondary" href="<?= e(url('incidents')) ?>"><?= e(t('common.reset')) ?></a></div>
</form>
<div class="table-wrap"><table class="data">
  <thead><tr><th><?= sort_link(t('incident.id'), 'code', $f) ?></th><th><?= e(t('incident.type')) ?></th><th><?= sort_link(t('incident.name'), 'title', $f) ?></th><th><?= sort_link(t('incident.occurrence_date'), 'occurred', $f) ?></th>
    <th><?= e(t('incident.category')) ?></th><th><?= e(t('incident.department')) ?> / <?= e(t('incident.branch')) ?></th><th class="num"><?= sort_link(t('incident.gross_loss'), 'gross', $f) ?></th><th class="num"><?= e(t('incident.recoveries')) ?></th><th class="num"><?= sort_link(t('incident.net_loss'), 'net', $f) ?></th><th><?= e(t('incident.status')) ?></th></tr></thead>
  <tbody>
  <?php if (!$rows): ?><tr><td colspan="10" class="muted"><?= e(t('common.no_results')) ?></td></tr><?php endif; ?>
  <?php foreach ($rows as $r): ?><tr>
    <td class="nowrap"><a href="<?= e(url('incidents/' . $r['id'])) ?>"><?= e($r['incident_code']) ?></a></td>
    <td><?= $r['event_type'] === 'near_miss' ? '<span class="badge warn">' . e(enum_label('event', 'near_miss')) . '</span>' : e(enum_label('event', 'incident')) ?></td>
    <td><a href="<?= e(url('incidents/' . $r['id'])) ?>"><?= e($r['title']) ?></a></td><td class="nowrap"><?= e($r['occurrence_date']) ?></td><td><?= e(lname($r, 'cat')) ?></td>
    <td><?= e(lname($r, 'dept')) ?><?php if ($r['branch_id']): ?><br><span class="muted"><?= e(lname($r, 'branch')) ?></span><?php endif; ?></td>
    <td class="num"><?= e(money($r['gross_loss'])) ?></td><td class="num"><?= e(money($r['recoveries'])) ?></td><td class="num"><strong><?= e(money($r['net_loss'])) ?></strong></td>
    <td><?= e(enum_label('incident_status', $r['status'])) ?><?php if ($r['archived_at']): ?> <span class="badge warn"><?= e(t('risk.archived')) ?></span><?php endif; ?></td></tr><?php endforeach; ?>
  </tbody>
  <tfoot><tr><td colspan="6"><?= e(t('incident.totals_filtered', ['n' => $sums['count']])) ?></td><td class="num"><?= e(money(\App\Core\Money::toDb($sums['gross']))) ?></td><td class="num"><?= e(money(\App\Core\Money::toDb($sums['recoveries']))) ?></td><td class="num"><?= e(money(\App\Core\Money::toDb($sums['net']))) ?></td><td></td></tr></tfoot>
</table></div>
<?= pager($total, $page, $per) ?>
