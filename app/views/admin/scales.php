<div class="page-head"><div><h1><?= e(t('scales.title')) ?></h1><p class="sub"><?= e(t('scales.intro')) ?></p></div></div>
<?php if ($approved): ?>
<div class="msg success"><?= e(t('scales.approved_by', ['who' => $approvedBy, 'when' => fdatetime($approvedAt)])) ?></div>
<?php else: ?>
<div class="msg info"><strong><?= e(t('scales.demo_banner')) ?></strong>
  <form method="post" action="<?= e(url('admin/scales/approve')) ?>" class="inline" data-confirm="<?= e(t('scales.confirm_approve')) ?>"><?= csrf_field() ?> <button class="btn small gold" type="submit"><?= e(t('scales.mark_approved')) ?></button></form>
  <p class="hint"><?= e(t('scales.approve_hint')) ?></p></div>
<?php endif; ?>

<form method="post" action="<?= e(url('admin/scales/levels')) ?>" novalidate><?= csrf_field() ?>
<?php foreach (['likelihood' => $lik, 'impact' => $imp] as $scale => $levels): ?>
<section class="card"><h2><?= e(t('scales.' . $scale)) ?></h2>
<div class="table-wrap"><table class="data"><thead><tr><th>#</th><th><?= e(t('scales.label_en')) ?></th><th><?= e(t('scales.label_ar')) ?></th><th><?= e(t('scales.desc_en')) ?></th><th><?= e(t('scales.desc_ar')) ?></th><?php if ($scale === 'impact'): ?><th><?= e(t('scales.financial_min')) ?> (<?= e(\App\Core\Settings::get('currency_code', 'LYD')) ?>)</th><?php endif; ?></tr></thead><tbody>
<?php foreach ($levels as $n => $lv): $k = $scale . '_' . $n; ?><tr class="<?= field_error($k . '_label_en') || field_error($k . '_fin') ? 'has-error' : '' ?>"><td><?= $n ?></td>
  <td><input type="text" name="<?= $k ?>_label_en" value="<?= e(old($k . '_label_en', $lv['label_en'])) ?>" maxlength="80" aria-label="<?= e(t('scales.label_en')) ?> <?= $n ?>"><?= field_error($k . '_label_en') ?></td>
  <td><input type="text" dir="rtl" name="<?= $k ?>_label_ar" value="<?= e(old($k . '_label_ar', $lv['label_ar'])) ?>" maxlength="80" aria-label="<?= e(t('scales.label_ar')) ?> <?= $n ?>"></td>
  <td><textarea name="<?= $k ?>_desc_en" style="min-height:70px" aria-label="<?= e(t('scales.desc_en')) ?> <?= $n ?>"><?= e(old($k . '_desc_en', $lv['description_en'])) ?></textarea><?= field_error($k . '_desc_en') ?></td>
  <td><textarea dir="rtl" name="<?= $k ?>_desc_ar" style="min-height:70px" aria-label="<?= e(t('scales.desc_ar')) ?> <?= $n ?>"><?= e(old($k . '_desc_ar', $lv['description_ar'])) ?></textarea></td>
  <?php if ($scale === 'impact'): ?><td><input type="text" inputmode="decimal" name="<?= $k ?>_fin" value="<?= e(old($k . '_fin', \App\Core\Money::plain($lv['financial_min']))) ?>" aria-label="<?= e(t('scales.financial_min')) ?> <?= $n ?>"><?= field_error($k . '_fin') ?></td><?php endif; ?></tr><?php endforeach; ?>
</tbody></table></div><?php if ($scale === 'impact'): ?><p class="hint"><?= e(t('scales.financial_hint')) ?></p><?php endif; ?></section>
<?php endforeach; ?>
<button class="btn" type="submit"><?= e(t('scales.save_levels')) ?></button>
</form>

<form method="post" action="<?= e(url('admin/scales/bands')) ?>" novalidate style="margin-top:24px"><?= csrf_field() ?>
<section class="card"><h2><?= e(t('scales.bands')) ?></h2><p class="muted"><?= e(t('scales.bands_hint')) ?></p>
<?= field_error('bands') ?>
<div class="table-wrap"><table class="data"><thead><tr><th><?= e(t('scales.min')) ?></th><th><?= e(t('scales.max')) ?></th><th><?= e(t('scales.label_en')) ?></th><th><?= e(t('scales.label_ar')) ?></th><th><?= e(t('scales.color')) ?></th><th><?= e(t('scales.desc_en')) ?></th><th><?= e(t('scales.desc_ar')) ?></th><th><?= e(t('scales.is_high')) ?></th></tr></thead><tbody>
<?php $rows = array_values($bands); for ($i = 0; $i < 8; $i++): $b = $rows[$i] ?? ['id' => '', 'min_score' => '', 'max_score' => '', 'label_en' => '', 'label_ar' => '', 'color' => '#888888', 'description_en' => '', 'description_ar' => '', 'is_high' => 0]; ?>
<tr class="<?= field_error('band_' . $i) ? 'has-error' : '' ?>"><td><input type="hidden" name="band[<?= $i ?>][id]" value="<?= e($b['id']) ?>"><input type="number" min="1" max="25" name="band[<?= $i ?>][min]" value="<?= e($b['min_score']) ?>" style="width:5em" aria-label="<?= e(t('scales.min')) ?>"><?= field_error('band_' . $i) ?></td>
<td><input type="number" min="1" max="25" name="band[<?= $i ?>][max]" value="<?= e($b['max_score']) ?>" style="width:5em" aria-label="<?= e(t('scales.max')) ?>"></td>
<td><input type="text" name="band[<?= $i ?>][label_en]" value="<?= e($b['label_en']) ?>" maxlength="60" aria-label="<?= e(t('scales.label_en')) ?>"></td><td><input type="text" dir="rtl" name="band[<?= $i ?>][label_ar]" value="<?= e($b['label_ar']) ?>" maxlength="60" aria-label="<?= e(t('scales.label_ar')) ?>"></td>
<td><input type="color" name="band[<?= $i ?>][color]" value="<?= e(safe_color((string)$b['color'])) ?>" aria-label="<?= e(t('scales.color')) ?>"></td>
<td><textarea name="band[<?= $i ?>][desc_en]" style="min-height:50px" aria-label="<?= e(t('scales.desc_en')) ?>"><?= e($b['description_en']) ?></textarea></td><td><textarea dir="rtl" name="band[<?= $i ?>][desc_ar]" style="min-height:50px" aria-label="<?= e(t('scales.desc_ar')) ?>"><?= e($b['description_ar']) ?></textarea></td>
<td><input type="checkbox" name="band[<?= $i ?>][is_high]" value="1"<?= checked($b['is_high']) ?> aria-label="<?= e(t('scales.is_high')) ?>"></td></tr>
<?php endfor; ?></tbody></table></div>
<p style="margin-top:12px"><?= e(t('scales.preview')) ?>: <?php foreach ($bands as $b) echo band_label_chip($b) . ' '; ?></p></section>
<button class="btn" type="submit"><?= e(t('scales.save_bands')) ?></button>
</form>

<section class="card" style="margin-top:24px"><h2><?= e(t('scales.matrix_preview')) ?></h2>
<div class="table-wrap"><table class="heat"><thead><tr><th></th><?php for ($l = 1; $l <= 5; $l++): ?><th><?= $l ?> <?= e(lname($lik[$l] ?? null, 'label')) ?></th><?php endfor; ?></tr></thead><tbody>
<?php for ($i = 5; $i >= 1; $i--): ?><tr><th><?= $i ?> <?= e(lname($imp[$i] ?? null, 'label')) ?></th><?php for ($l = 1; $l <= 5; $l++): $s = $l * $i; $bb = \App\Support\Ref::band($s); $bg = safe_color((string)($bb['color'] ?? '#cccccc')); ?><td style="background:<?= e($bg) ?>;color:<?= contrast_text($bg) ?>"><span class="n"><?= $s ?></span><br><span class="s"><?= e(lname($bb, 'label')) ?></span></td><?php endfor; ?></tr><?php endfor; ?></tbody></table></div>
<p class="muted"><?= e(t('scales.formula_note')) ?></p></section>
