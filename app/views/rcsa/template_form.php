<div class="page-head"><h1><?= e($title) ?></h1></div>
<form method="post" class="card" action="<?= e($existing ? url('rcsa/templates/' . $existing['id']) : url('rcsa/templates')) ?>" novalidate><?= csrf_field() ?>
<?= ff('text', 'title', 'rcsa.template_title', $t['title'] ?? '', ['required' => true]) ?><?= ff('textarea', 'description', 'rcsa.description', $t['description'] ?? '') ?>
<?php if ($existing): ?><div class="field"><label class="check"><input type="checkbox" name="is_active" value="1"<?= checked(old('is_active', $t['is_active'] ?? 1)) ?>> <?= e(t('common.active')) ?></label></div><?php endif; ?>
<?php if ($questions): ?><h2><?= e(t('rcsa.existing_questions')) ?></h2><ol><?php foreach ($questions as $q): ?><li><?= e($q['question_en']) ?> / <span dir="rtl"><?= e($q['question_ar']) ?></span></li><?php endforeach; ?></ol><?php endif; ?>
<h2><?= e(t($existing ? 'rcsa.add_questions' : 'rcsa.questions')) ?></h2><?= field_error('questions') ?><p class="muted"><?= e(t('rcsa.questions_hint')) ?></p>
<?php $n = $existing ? 4 : 8; for ($i = 0; $i < $n; $i++): $oe = (array)old('q_en', []); $oa = (array)old('q_ar', []); ?><div class="form-grid" style="margin-bottom:8px">
<div class="field"><label for="qe<?= $i ?>"><?= e(t('rcsa.question_en')) ?> <?= $i + 1 ?></label><input type="text" id="qe<?= $i ?>" name="q_en[]" maxlength="500" value="<?= e($oe[$i] ?? '') ?>"></div>
<div class="field"><label for="qa<?= $i ?>"><?= e(t('rcsa.question_ar')) ?> <?= $i + 1 ?></label><input type="text" dir="rtl" id="qa<?= $i ?>" name="q_ar[]" maxlength="500" value="<?= e($oa[$i] ?? '') ?>"></div>
<div class="field"><label for="qc<?= $i ?>"><?= e(t('risk.category')) ?></label><select id="qc<?= $i ?>" name="q_cat[]"><option value=""><?= e(t('common.none')) ?></option><?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>"><?= e(lname($c)) ?></option><?php endforeach; ?></select></div></div><?php endfor; ?>
<div class="actions"><button class="btn" type="submit"><?= e(t('common.save')) ?></button><a class="btn secondary" href="<?= e(url('rcsa')) ?>"><?= e(t('common.cancel')) ?></a></div></form>
