<?php $v = static fn(string $k, $d = '') => old($k, $row[$k] ?? $d); ?>
<div class="page-head"><div><h1><?= e(t($c['title'])) ?> — <?= e($existing ? t('common.edit') : t('common.add')) ?></h1></div></div>
<form method="post" class="card" style="max-width:640px" action="<?= e($existing ? url('admin/' . $entity . '/' . $existing['id']) : url('admin/' . $entity)) ?>" novalidate><?= csrf_field() ?>
<?php foreach ($c['fields'] as $f): $n = $f['name']; ?>
  <div class="field<?= field_error($n) ? ' has-error' : '' ?>"><label for="<?= e($n) ?>"><?= e(t($f['label'])) ?><?= !empty($f['required']) ? ' *' : '' ?></label>
  <?php if ($f['type'] === 'select'): ?><select id="<?= e($n) ?>" name="<?= e($n) ?>"><?php foreach ($f['options'] as $o): ?><option value="<?= e($o) ?>"<?= sel($v($n), $o) ?>><?= e(t($f['optionLabel'] . $o)) ?></option><?php endforeach; ?></select>
  <?php else: ?><input type="<?= $f['type'] === 'date' ? 'date' : ($f['type'] === 'int' ? 'number' : 'text') ?>" id="<?= e($n) ?>" name="<?= e($n) ?>" value="<?= e($v($n)) ?>"<?= ($f['dir'] ?? '') === 'rtl' ? ' dir="rtl"' : '' ?>><?php endif; ?>
  <?= field_error($n) ?></div>
<?php endforeach; ?>
  <div class="field"><label class="check"><input type="checkbox" name="is_active" value="1"<?= checked($v('is_active', 1)) ?>> <?= e(t('common.active')) ?></label></div>
  <div class="actions"><button class="btn" type="submit"><?= e(t('common.save')) ?></button><a class="btn secondary" href="<?= e(url('admin/' . $entity)) ?>"><?= e(t('common.cancel')) ?></a></div>
</form>
