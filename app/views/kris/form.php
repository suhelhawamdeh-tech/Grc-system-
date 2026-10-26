<div class="page-head"><h1><?= e($title) ?></h1></div>
<form method="post" class="card" action="<?= e($existing ? url('kris/' . $existing['id']) : url('kris')) ?>" novalidate><?= csrf_field() ?>
<div class="form-grid">
<?= ff('text', 'name', 'kri.name', $k['name'] ?? '', ['required' => true, 'wide' => true]) ?>
<?= ff('textarea', 'description', 'kri.description', $k['description'] ?? '', ['wide' => true]) ?>
<?= ff('select', 'department_id', 'kri.department', $k['department_id'] ?? '', ['required' => true, 'options' => opts($lists['departments'], fn($d) => lname($d))]) ?>
<?= ff('select', 'owner_user_id', 'kri.owner', $k['owner_user_id'] ?? '', ['required' => true, 'options' => opts($lists['users'], fn($d) => $d['full_name'])]) ?>
<?= ff('select', 'category_id', 'kri.category', $k['category_id'] ?? '', ['options' => opts($lists['categories'], fn($d) => lname($d))]) ?>
<?= ff('select', 'risk_id', 'kri.linked_risk', $k['risk_id'] ?? '', ['options' => opts($lists['risks'], fn($d) => $d['risk_code'] . ' — ' . mb_strimwidth($d['title'], 0, 60, '…'))]) ?>
<?= ff('text', 'unit', 'kri.unit', $k['unit'] ?? '', ['hint' => t('kri.unit_hint')]) ?>
<?= ff('select', 'frequency', 'kri.frequency', $k['frequency'] ?? 'monthly', ['blank' => false, 'options' => ['monthly' => t('enum.kfreq.monthly'), 'quarterly' => t('enum.kfreq.quarterly')]]) ?>
<?= ff('select', 'direction', 'kri.direction', $k['direction'] ?? 'higher_worse', ['blank' => false, 'options' => ['higher_worse' => t('enum.dir.higher_worse'), 'lower_worse' => t('enum.dir.lower_worse')]]) ?>
<div></div>
<?php $cal = (int)($k['calibrated'] ?? 1) === 1; ?>
<?= ff('text', 'amber_threshold', 'kri.amber', $cal ? ($k['amber_threshold'] ?? '') : '', ['required' => true, 'extra' => ' inputmode="decimal"', 'hint' => t('kri.amber_hint')]) ?>
<?= ff('text', 'red_threshold', 'kri.red', $cal ? ($k['red_threshold'] ?? '') : '', ['required' => true, 'extra' => ' inputmode="decimal"', 'hint' => t('kri.red_hint')]) ?>
<?= ff('text', 'critical_threshold', 'kri.critical', isset($k['critical_threshold']) ? \App\Core\Money::plain($k['critical_threshold']) : '', ['extra' => ' inputmode="decimal"', 'hint' => t('kri.critical_hint')]) ?>
<?= ff('select', 'data_source', 'kri.data_source', $k['data_source'] ?? 'manual', ['blank' => false, 'options' => ['manual' => t('enum.kds.manual'), 'import' => t('enum.kds.import'), 'api' => t('enum.kds.api')], 'hint' => t('kri.data_source_hint')]) ?>
</div>
<fieldset class="raf"><legend><?= e(t('raf.card')) ?></legend><p class="hint"><?= e(t('raf.card_hint')) ?></p><div class="form-grid">
<?= ff('select', 'tier', 'raf.tier', $k['tier'] ?? 'kri', ['blank' => false, 'options' => ['kri' => t('enum.kritier.kri'), 'board' => t('enum.kritier.board'), 'policy' => t('enum.kritier.policy'), 'operational' => t('enum.kritier.operational')]]) ?>
<?= ff('select', 'raf_category', 'raf.category', $k['raf_category'] ?? '', ['options' => opts(\App\Support\Appetite::categories(), fn($x) => t('enum.rafcat.' . $x), null) ?: []]) ?>
<?= ff('text', 'appetite_value', 'raf.appetite_value', isset($k['appetite_value']) ? \App\Core\Money::plain($k['appetite_value']) : '', ['extra' => ' inputmode="decimal"', 'hint' => t('raf.appetite_hint')]) ?>
<?= ff('select', 'report_frequency', 'raf.report_frequency', $k['report_frequency'] ?? '', ['options' => opts(\App\Support\RiskFunction::FREQUENCIES, fn($x) => t('enum.rmfreq.' . $x), null) ?: []]) ?>
<?= ff('select', 'monitor_section', 'docs.section', $k['monitor_section'] ?? '', ['options' => opts(\App\Support\Framework::SECTIONS, fn($x) => t('enum.rmsec.' . $x), null) ?: []]) ?>
<?= ff('text', 'source_note', 'raf.source', $k['source_note'] ?? '', ['hint' => t('raf.source_hint')]) ?>
<?= ff('textarea', 'formula', 'raf.formula', $k['formula'] ?? '', ['wide' => true, 'hint' => t('raf.formula_hint')]) ?>
<?= ff('textarea', 'action_amber', 'raf.action_amber', $k['action_amber'] ?? '', []) ?>
<?= ff('textarea', 'action_red', 'raf.action_red', $k['action_red'] ?? '', []) ?>
<?= ff('date', 'card_reviewed_on', 'raf.card_reviewed_on', $k['card_reviewed_on'] ?? '') ?>
<div class="field"><label class="check"><input type="checkbox" name="is_regulatory" value="1"<?= checked(old('is_regulatory', $k['is_regulatory'] ?? 0)) ?>> <?= e(t('raf.is_regulatory')) ?></label><p class="hint"><?= e(t('raf.is_regulatory_hint')) ?></p></div>
</div><?php if (!$cal): ?><p class="badge warn"><?= e(t('raf.calibrate_note')) ?></p><?php endif; ?></fieldset>
<fieldset><legend><?= e(t('kri.composite')) ?></legend><div class="field"><label class="check"><input type="checkbox" name="is_composite" value="1"<?= checked(old('is_composite', $k['is_composite'] ?? 0)) ?>> <?= e(t('kri.is_composite')) ?></label><p class="hint"><?= e(t('kri.composite_hint')) ?></p><?= field_error('components') ?></div>
<?php $oldW = old('weight', null); foreach ($lists['components'] as $cm): if ($existing && (int)$cm['id'] === (int)$existing['id']) continue;
  $wv = is_array($oldW) ? (string)($oldW[$cm['id']] ?? '') : (isset($k['weights'][$cm['id']]) ? rtrim(rtrim(number_format($k['weights'][$cm['id']] / 1000, 3, '.', ''), '0'), '.') : ''); ?>
<div class="field"><label for="w<?= (int)$cm['id'] ?>"><?= e($cm['code'] . ' — ' . $cm['name']) ?></label><input type="text" id="w<?= (int)$cm['id'] ?>" name="weight[<?= (int)$cm['id'] ?>]" value="<?= e($wv) ?>" inputmode="decimal" placeholder="<?= e(t('kri.weight')) ?>"></div><?php endforeach; ?></fieldset>
<?php if ($existing): ?><div class="field"><label class="check"><input type="checkbox" name="is_active" value="1"<?= checked(old('is_active', $k['is_active'] ?? 1)) ?>> <?= e(t('common.active')) ?></label></div><?php endif; ?>
<p class="muted"><?= e(t('kri.demo_note')) ?></p>
<div class="actions"><button class="btn" type="submit"><?= e(t('common.save')) ?></button><a class="btn secondary" href="<?= e(url('kris')) ?>"><?= e(t('common.cancel')) ?></a></div></form>
