<?php /** @var array $p @var ?array $existing @var array $linked @var array $lists */
$scale = static fn(string $k) => ff('select', $k, 'bia.' . $k, $p[$k] ?? '', ['required' => true, 'options' => [1 => '1 — ' . t('bia.lvl1'), 2 => '2', 3 => '3 — ' . t('bia.lvl3'), 4 => '4', 5 => '5 — ' . t('bia.lvl5')], 'hint' => t('bia.' . $k . '_hint')]);
$sel = old('systems', $linked); $sel = array_map('intval', (array)$sel); ?>
<div class="page-head"><h1><?= icon('layers') ?> <?= e($title) ?></h1></div>
<?php if ($existing && $existing['status'] !== 'draft'): ?><p class="msg info"><?= e(t('bia.edit_warning')) ?></p><?php endif; ?>
<form method="post" class="card" action="<?= e($existing ? url('bia/' . $existing['id']) : url('bia')) ?>" novalidate><?= csrf_field() ?>
<fieldset><legend><?= e(t('bia.sec_process')) ?></legend><div class="form-grid">
<?= ff('text', 'name', 'bia.name', $p['name'] ?? '', ['required' => true, 'wide' => true, 'hint' => t('bia.name_hint')]) ?>
<?= ff('textarea', 'description', 'bia.description', $p['description'] ?? '', ['wide' => true]) ?>
<?= ff('select', 'department_id', 'bia.department', $p['department_id'] ?? '', ['required' => true, 'options' => opts($lists['departments'], fn($d) => lname($d))]) ?>
<?= ff('select', 'owner_user_id', 'bia.owner', $p['owner_user_id'] ?? '', ['required' => true, 'options' => opts($lists['users'], fn($d) => $d['full_name'])]) ?></div></fieldset>
<fieldset><legend><?= e(t('bia.sec_impact')) ?></legend><p class="muted"><?= e(t('bia.impact_intro')) ?></p><div class="form-grid">
<?= $scale('impact_financial') ?><?= $scale('impact_operational') ?><?= $scale('impact_legal') ?><?= $scale('impact_reputation') ?></div></fieldset>
<fieldset><legend><?= e(t('bia.sec_recovery')) ?></legend><div class="form-grid">
<?= ff('text', 'mtpd_hours', 'bia.mtpd_hours', isset($p['mtpd_hours']) ? \App\Support\Bia::fmt($p['mtpd_hours']) : '', ['required' => true, 'extra' => ' inputmode="decimal"', 'hint' => t('bia.mtpd_hint')]) ?>
<?= ff('text', 'rto_hours', 'bia.rto_hours', isset($p['rto_hours']) ? \App\Support\Bia::fmt($p['rto_hours']) : '', ['required' => true, 'extra' => ' inputmode="decimal"', 'hint' => t('bia.rto_hint')]) ?>
<?= ff('text', 'rpo_hours', 'bia.rpo_hours', isset($p['rpo_hours']) ? \App\Support\Bia::fmt($p['rpo_hours']) : '', ['required' => true, 'extra' => ' inputmode="decimal"', 'hint' => t('bia.rpo_hint')]) ?></div></fieldset>
<fieldset><legend><?= e(t('bia.sec_dependencies')) ?></legend>
<div class="field"><span class="lbl"><?= e(t('bia.systems')) ?></span><?= field_error('systems') ?>
<?php if (!$lists['systems']): ?><p class="muted"><?= e(t('bia.no_systems')) ?> <a href="<?= e(url('bia/systems/new')) ?>"><?= e(t('bia.system_new')) ?></a></p><?php endif; ?>
<?php foreach ($lists['systems'] as $s): ?><label class="check"><input type="checkbox" name="systems[]" value="<?= (int)$s['id'] ?>"<?= in_array((int)$s['id'], $sel, true) ? ' checked' : '' ?>> <?= e($s['code'] . ' — ' . $s['name']) ?> <span class="muted">(<?= e(t('bia.s_recovery_short')) ?> <?= e(\App\Support\Bia::fmt($s['recovery_hours'])) ?> / <?= e(t('bia.s_loss_short')) ?> <?= e(\App\Support\Bia::fmt($s['data_loss_hours'])) ?>)</span></label><?php endforeach; ?></div>
<?= ff('textarea', 'dependencies', 'bia.dependencies', $p['dependencies'] ?? '', ['hint' => t('bia.dependencies_hint')]) ?></fieldset>
<div class="actions"><button class="btn" type="submit"><?= e(t('common.save')) ?></button><a class="btn secondary" href="<?= e(url('bia')) ?>"><?= e(t('common.cancel')) ?></a></div></form>
