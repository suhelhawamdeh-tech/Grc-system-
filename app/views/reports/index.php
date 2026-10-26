<div class="page-head"><div><h1><?= e(t('nav.reports')) ?></h1><p class="sub"><?= e(t('report.intro')) ?></p></div></div>
<section class="card board-card"><h2><?= icon('file') ?> <?= e(t('board.title')) ?></h2><p><?= e(t('board.intro')) ?></p>
<form method="get" action="<?= e(url('reports/board')) ?>" target="_blank" class="filters"><div class="field"><label for="b_from"><?= e(t('common.from')) ?></label><input type="date" id="b_from" name="from"></div><div class="field"><label for="b_to"><?= e(t('common.to')) ?></label><input type="date" id="b_to" name="to"></div>
<div class="field"><button class="btn gold" type="submit"><?= e(t('board.open')) ?></button></div></form><p class="hint"><?= e(t('board.pdf_hint')) ?></p></section>
<div class="grid cols-2">
<section class="card"><h2><?= e(t('report.risk_register')) ?></h2>
<form method="get" action="<?= e(url('risks/print')) ?>" target="_blank" class="filters" style="grid-template-columns:1fr 1fr">
  <div class="field"><label for="r_category"><?= e(t('risk.category')) ?></label><select id="r_category" name="category"><option value=""><?= e(t('common.all')) ?></option><?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e(lname($c)) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="r_department"><?= e(t('risk.department')) ?></label><select id="r_department" name="department"><option value=""><?= e(t('common.all')) ?></option><?php foreach ($departments as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e(lname($c)) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="r_branch"><?= e(t('risk.branch')) ?></label><select id="r_branch" name="branch"><option value=""><?= e(t('common.all')) ?></option><?php foreach ($branches as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e(lname($c)) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="r_band"><?= e(t('risk.res_band')) ?></label><select id="r_band" name="band"><option value=""><?= e(t('common.all')) ?></option><?php foreach ($bands as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e(lname($c, 'label')) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="r_status"><?= e(t('risk.status')) ?></label><select id="r_status" name="status"><option value=""><?= e(t('common.all')) ?></option><option value="active"><?= e(enum_label('risk_status', 'active')) ?></option><option value="closed"><?= e(enum_label('risk_status', 'closed')) ?></option></select></div>
  <div class="field actions" style="grid-column:1/-1"><button class="btn" type="submit"><?= e(t('report.open_printable')) ?></button>
    <button class="btn secondary" type="submit" formaction="<?= e(url('risks/export')) ?>" formtarget="_self">CSV</button>
    <button class="btn secondary" type="submit" formaction="<?= e(url('risks/export')) ?>" formtarget="_self" name="format" value="xlsx">Excel</button>
    <button class="btn secondary" type="submit" formaction="<?= e(url('risks/export')) ?>" formtarget="_self" name="sep" value=";">CSV (;)</button></div>
</form></section>
<section class="card"><h2><?= e(t('report.incident_report')) ?></h2>
<form method="get" action="<?= e(url('incidents/print')) ?>" target="_blank" class="filters" style="grid-template-columns:1fr 1fr">
  <div class="field"><label for="i_from"><?= e(t('common.from')) ?></label><input type="date" id="i_from" name="from"></div>
  <div class="field"><label for="i_to"><?= e(t('common.to')) ?></label><input type="date" id="i_to" name="to"></div>
  <div class="field"><label for="i_category"><?= e(t('incident.category')) ?></label><select id="i_category" name="category"><option value=""><?= e(t('common.all')) ?></option><?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e(lname($c)) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="i_department"><?= e(t('incident.department')) ?></label><select id="i_department" name="department"><option value=""><?= e(t('common.all')) ?></option><?php foreach ($departments as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e(lname($c)) ?></option><?php endforeach; ?></select></div>
  <div class="field"><label for="i_type"><?= e(t('incident.type')) ?></label><select id="i_type" name="type"><option value=""><?= e(t('common.all')) ?></option><option value="incident"><?= e(enum_label('event', 'incident')) ?></option><option value="near_miss"><?= e(enum_label('event', 'near_miss')) ?></option></select></div>
  <div class="field"><label for="i_period"><?= e(t('dash.period')) ?></label><select id="i_period" disabled aria-describedby="pp"><option><?= e(t('report.use_dates')) ?></option></select></div>
  <div class="field actions" style="grid-column:1/-1"><button class="btn" type="submit"><?= e(t('report.open_printable')) ?></button>
    <button class="btn secondary" type="submit" formaction="<?= e(url('incidents/export')) ?>" formtarget="_self">CSV</button>
    <button class="btn secondary" type="submit" formaction="<?= e(url('incidents/export')) ?>" formtarget="_self" name="format" value="xlsx">Excel</button>
    <button class="btn secondary" type="submit" formaction="<?= e(url('incidents/export')) ?>" formtarget="_self" name="sep" value=";">CSV (;)</button></div>
</form>
<?php if ($periods): ?><p class="hint" id="pp"><?= e(t('report.periods_hint')) ?>: <?php foreach ($periods as $p): ?><span class="badge"><?= e(lname($p)) ?>: <?= e($p['start_date']) ?> → <?= e($p['end_date']) ?></span> <?php endforeach; ?></p><?php endif; ?></section>
</div>
<p class="muted"><?= e(t('report.csv_note')) ?></p>
