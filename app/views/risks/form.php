<?php
/** @var array $r */
$v = static fn(string $k, $d = '') => old($k, $r[$k] ?? $d);
$err = static fn(string $k) => field_error($k) ? ' has-error' : '';
$action = $existing ? url('risks/' . $existing['id']) : url('risks');
$select = static function (string $name, string $label, array $opts, $cur, bool $blank = true, bool $required = false, string $nameField = 'name') use ($err) {
    $o = $blank ? '<option value="">' . e(t('common.select')) . '</option>' : '';
    foreach ($opts as $x) { $o .= '<option value="' . (int)$x['id'] . '"' . sel($cur, $x['id']) . '>' . e(isset($x['full_name']) ? $x['full_name'] : lname($x, $nameField)) . '</option>'; }
    return '<div class="field' . $err($name) . '"><label for="' . $name . '">' . e(t($label)) . ($required ? ' *' : '') . '</label><select id="' . $name . '" name="' . $name . '"' . ($required ? ' required' : '') . '>' . $o . '</select>' . field_error($name) . '</div>';
};
$levelSelect = static function (string $name, string $label, array $levels, $cur) use ($err) {
    $o = '<option value="">' . e(t('common.select')) . '</option>';
    foreach ($levels as $n => $lv) { $o .= '<option value="' . $n . '"' . sel($cur, $n) . ' title="' . e(lname($lv, 'description')) . '">' . $n . ' — ' . e(lname($lv, 'label')) . '</option>'; }
    return '<div class="field' . $err($name) . '"><label for="' . $name . '">' . e(t($label)) . ' *</label><select id="' . $name . '" name="' . $name . '" required>' . $o . '</select>' . field_error($name) . '</div>';
};
$text = static function (string $name, string $label, $val, bool $required = false, string $type = 'text', string $hint = '') use ($err) {
    return '<div class="field' . $err($name) . '"><label for="' . $name . '">' . e(t($label)) . ($required ? ' *' : '') . '</label><input type="' . $type . '" id="' . $name . '" name="' . $name . '" value="' . e($val) . '"' . ($required ? ' required' : '') . '>' . ($hint ? '<p class="hint">' . e($hint) . '</p>' : '') . field_error($name) . '</div>';
};
$area = static function (string $name, string $label, $val, bool $required = false, string $hint = '') use ($err) {
    return '<div class="field wide' . $err($name) . '"><label for="' . $name . '">' . e(t($label)) . ($required ? ' *' : '') . '</label><textarea id="' . $name . '" name="' . $name . '"' . ($required ? ' required' : '') . '>' . e($val) . '</textarea>' . ($hint ? '<p class="hint">' . e($hint) . '</p>' : '') . field_error($name) . '</div>';
};
?>
<div class="page-head"><div><h1><?= e($title) ?></h1><?php if ($existing): ?><p class="sub"><?= e($existing['risk_code']) ?></p><?php endif; ?></div></div>
<form method="post" action="<?= e($action) ?>" novalidate>
  <?= csrf_field() ?>
  <fieldset><legend><?= e(t('risk.section_identification')) ?></legend><div class="form-grid">
    <div class="wide"><?= $text('title', 'risk.title', $v('title'), true) ?></div>
    <?= $area('description', 'risk.description', $v('description'), true) ?>
    <?= $select('category_id', 'risk.category', $lists['categories'], $v('category_id'), true, true) ?>
    <?= $select('department_id', 'risk.department', $lists['departments'], $v('department_id'), true, true) ?>
    <?= $select('branch_id', 'risk.branch', $lists['branches'], $v('branch_id')) ?>
    <?= $select('owner_user_id', 'risk.owner', $lists['users'], $v('owner_user_id'), true, true) ?>
    <?= $text('identified_date', 'risk.identified_date', $v('identified_date'), true, 'date') ?>
    <?= $text('review_date', 'risk.review_date', $v('review_date'), false, 'date') ?>
    <div class="field<?= $err('status') ?>"><label for="status"><?= e(t('risk.status')) ?></label><select id="status" name="status"><?php foreach (['active', 'closed'] as $s): ?><option value="<?= $s ?>"<?= sel($v('status', 'active'), $s) ?>><?= e(enum_label('risk_status', $s)) ?></option><?php endforeach; ?></select></div>
    <?= $area('causes', 'risk.causes', $v('causes')) ?>
    <?= $area('consequences', 'risk.consequences', $v('consequences')) ?>
  </div></fieldset>

  <fieldset><legend><?= e(t('risk.section_inherent')) ?></legend><p class="muted"><?= e(t('risk.inherent_help')) ?></p><div class="form-grid">
    <?= $levelSelect('inh_likelihood', 'risk.likelihood', $levelsL, $v('inh_likelihood')) ?>
    <?= $levelSelect('inh_impact', 'risk.impact', $levelsI, $v('inh_impact')) ?>
    <?= $text('est_financial_impact', 'risk.est_financial_impact', $v('est_financial_impact'), false, 'text', t('risk.est_financial_hint', ['cur' => \App\Core\Settings::get('currency_code', 'LYD')])) ?>
  </div>
  <details class="muted"><summary><?= e(t('scales.reference')) ?></summary>
    <div class="table-wrap"><table class="data"><thead><tr><th><?= e(t('risk.impact')) ?></th><th><?= e(t('scales.description')) ?></th><th class="num"><?= e(t('scales.financial_min')) ?></th></tr></thead><tbody>
    <?php foreach ($levelsI as $n => $lv): ?><tr><td><?= $n ?> — <?= e(lname($lv, 'label')) ?></td><td><?= e(lname($lv, 'description')) ?></td><td class="num"><?= $lv['financial_min'] !== null ? e(money($lv['financial_min'])) : '' ?></td></tr><?php endforeach; ?>
    </tbody></table></div></details></fieldset>

  <fieldset><legend><?= e(t('risk.section_controls')) ?></legend><div class="form-grid">
    <?= $area('controls_description', 'risk.controls', $v('controls_description')) ?>
    <?= $select('control_owner_id', 'risk.control_owner', $lists['users'], $v('control_owner_id')) ?>
    <div class="field<?= $err('control_assessment') ?>"><label for="control_assessment"><?= e(t('risk.control_assessment')) ?></label><select id="control_assessment" name="control_assessment">
      <?php foreach (['not_assessed', 'effective', 'partially_effective', 'ineffective'] as $s): ?><option value="<?= $s ?>"<?= sel($v('control_assessment', 'not_assessed'), $s) ?>><?= e(enum_label('control', $s)) ?></option><?php endforeach; ?></select><?= field_error('control_assessment') ?></div>
  </div></fieldset>

  <fieldset><legend><?= e(t('risk.section_residual')) ?></legend><p class="muted"><?= e(t('risk.residual_help')) ?></p><div class="form-grid">
    <?= $levelSelect('res_likelihood', 'risk.likelihood', $levelsL, $v('res_likelihood')) ?>
    <?= $levelSelect('res_impact', 'risk.impact', $levelsI, $v('res_impact')) ?>
    <?= $area('res_rationale', 'risk.res_rationale', $v('res_rationale'), false, t('risk.rationale_hint')) ?>
  </div></fieldset>

  <fieldset><legend><?= e(t('risk.section_treatment')) ?></legend><div class="form-grid">
    <div class="field<?= $err('treatment_decision') ?>"><label for="treatment_decision"><?= e(t('risk.treatment')) ?> *</label><select id="treatment_decision" name="treatment_decision">
      <?php foreach (['mitigate', 'accept', 'transfer', 'avoid'] as $s): ?><option value="<?= $s ?>"<?= sel($v('treatment_decision', 'mitigate'), $s) ?>><?= e(enum_label('treatment', $s)) ?></option><?php endforeach; ?></select></div>
    <?= $area('treatment_notes', 'risk.treatment_notes', $v('treatment_notes')) ?>
  </div><?php if (!$existing): ?><p class="muted"><?= e(t('risk.actions_after_save')) ?></p><?php endif; ?></fieldset>

  <div class="actions"><button class="btn" type="submit"><?= e(t('common.save')) ?></button><a class="btn secondary" href="<?= e($existing ? url('risks/' . $existing['id']) : url('risks')) ?>"><?= e(t('common.cancel')) ?></a></div>
</form>
