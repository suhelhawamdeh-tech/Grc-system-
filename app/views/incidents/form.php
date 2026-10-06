<?php
$v = static fn(string $k, $d = '') => old($k, $i[$k] ?? $d);
$err = static fn(string $k) => field_error($k) ? ' has-error' : '';
$action = $existing ? url('incidents/' . $existing['id']) : url('incidents');
$sel = static function (string $name, string $label, array $opts, $cur, bool $required) use ($err) {
    $o = '<option value="">' . e(t('common.select')) . '</option>';
    foreach ($opts as $x) { $o .= '<option value="' . (int)$x['id'] . '"' . sel($cur, $x['id']) . '>' . e(lname($x)) . '</option>'; }
    return '<div class="field' . $err($name) . '"><label for="' . $name . '">' . e(t($label)) . ($required ? ' *' : '') . '</label><select id="' . $name . '" name="' . $name . '">' . $o . '</select>' . field_error($name) . '</div>';
};
$inp = static function (string $name, string $label, $val, string $type = 'text', bool $required = false, string $hint = '') use ($err) {
    return '<div class="field' . $err($name) . '"><label for="' . $name . '">' . e(t($label)) . ($required ? ' *' : '') . '</label><input type="' . $type . '" id="' . $name . '" name="' . $name . '" value="' . e($val) . '"' . ($type === 'text' && str_contains($name, 'loss') || $name === 'recoveries' ? ' inputmode="decimal"' : '') . '>' . ($hint ? '<p class="hint">' . e($hint) . '</p>' : '') . field_error($name) . '</div>';
};
$area = static function (string $name, string $label, $val, bool $required = false) use ($err) {
    return '<div class="field wide' . $err($name) . '"><label for="' . $name . '">' . e(t($label)) . ($required ? ' *' : '') . '</label><textarea id="' . $name . '" name="' . $name . '">' . e($val) . '</textarea>' . field_error($name) . '</div>';
};
$cur = \App\Core\Settings::get('currency_code', 'LYD');
$sel_linked = array_map('intval', (array)old('risk_ids', $linked));
?>
<div class="page-head"><div><h1><?= e($title) ?></h1><?php if ($existing): ?><p class="sub"><?= e($existing['incident_code']) ?></p><?php endif; ?></div></div>
<form method="post" action="<?= e($action) ?>" novalidate>
  <?= csrf_field() ?>
  <fieldset><legend><?= e(t('incident.section_event')) ?></legend><div class="form-grid">
    <div class="field<?= $err('event_type') ?>"><label for="event_type"><?= e(t('incident.type')) ?> *</label><select id="event_type" name="event_type"><?php foreach (['incident', 'near_miss'] as $s): ?><option value="<?= $s ?>"<?= sel($v('event_type', 'incident'), $s) ?>><?= e(enum_label('event', $s)) ?></option><?php endforeach; ?></select><p class="hint"><?= e(t('incident.near_miss_hint')) ?></p></div>
    <?= $inp('title', 'incident.name', $v('title'), 'text', true) ?>
    <?= $area('description', 'incident.description', $v('description'), true) ?>
    <?= $inp('occurrence_date', 'incident.occurrence_date', $v('occurrence_date'), 'date', true) ?>
    <?= $inp('discovery_date', 'incident.discovery_date', $v('discovery_date'), 'date', true) ?>
    <?= $sel('category_id', 'incident.category', $lists['categories'], $v('category_id'), true) ?>
    <?= $sel('department_id', 'incident.department', $lists['departments'], $v('department_id'), true) ?>
    <?= $sel('branch_id', 'incident.branch', $lists['branches'], $v('branch_id'), false) ?>
    <?= $sel('basel_event_type_id', 'incident.basel_type', $basel, $v('basel_event_type_id'), false) ?>
    <?= $sel('business_line_id', 'incident.business_line', $lines, $v('business_line_id'), false) ?>
    <?= $inp('product', 'incident.product', $v('product')) ?>
    <?= $inp('system_name', 'incident.system_name', $v('system_name')) ?>
  </div></fieldset>
  <fieldset><legend><?= e(t('incident.section_loss')) ?> (<?= e($cur) ?>)</legend><div class="form-grid">
    <?= $inp('gross_loss', 'incident.gross_loss', $v('gross_loss', '0'), 'text', true) ?>
    <?= $inp('recoveries', 'incident.recoveries', $v('recoveries', '0'), 'text', true, t('incident.recoveries_hint')) ?>
    <div class="field<?= $err('recovery_source') ?>"><label for="recovery_source"><?= e(t('incident.recovery_source')) ?></label><select id="recovery_source" name="recovery_source"><option value=""><?= e(t('common.select')) ?></option><?php foreach (['insurance', 'customer', 'employee', 'supplier', 'other'] as $rs): ?><option value="<?= $rs ?>"<?= sel($v('recovery_source'), $rs) ?>><?= e(t('enum.rsrc.' . $rs)) ?></option><?php endforeach; ?></select><p class="hint"><?= e(t('incident.recovery_source_hint')) ?></p><?= field_error('recovery_source') ?></div>
    <?= $area('non_financial_impact', 'incident.non_financial', $v('non_financial_impact')) ?>
    <div class="field"><span class="lbl"><?= e(t('incident.net_loss')) ?></span><p class="hint"><?= e(t('incident.net_formula')) ?></p></div>
  </div></fieldset>
  <fieldset><legend><?= e(t('incident.section_analysis')) ?></legend><div class="form-grid">
    <?= $area('root_cause', 'incident.root_cause', $v('root_cause')) ?>
    <?= $area('corrective_actions', 'incident.corrective_actions', $v('corrective_actions')) ?>
    <div class="field<?= $err('status') ?>"><label for="status"><?= e(t('incident.status')) ?></label><select id="status" name="status"><?php foreach (($existing && $existing['status'] === 'pending_closure' ? ['open', 'investigating', 'pending_closure', 'closed'] : ['open', 'investigating', 'closed']) as $s): ?><option value="<?= $s ?>"<?= sel($v('status', 'open'), $s) ?>><?= e(enum_label('incident_status', $s)) ?></option><?php endforeach; ?></select><?= field_error('status') ?></div>
    <div class="field wide<?= $err('risk_ids') ?>"><label for="risk_ids"><?= e(t('incident.linked_risks')) ?></label>
      <select id="risk_ids" name="risk_ids[]" multiple size="6"><?php foreach ($risks as $rk): ?><option value="<?= (int)$rk['id'] ?>"<?= in_array((int)$rk['id'], $sel_linked, true) ? ' selected' : '' ?>><?= e($rk['risk_code'] . ' — ' . $rk['title']) ?></option><?php endforeach; ?></select>
      <p class="hint"><?= e(t('incident.linked_hint')) ?></p><?= field_error('risk_ids') ?></div>
  </div></fieldset>
  <div class="actions"><button class="btn" type="submit"><?= e(t('common.save')) ?></button><a class="btn secondary" href="<?= e($existing ? url('incidents/' . $existing['id']) : url('incidents')) ?>"><?= e(t('common.cancel')) ?></a></div>
</form>
