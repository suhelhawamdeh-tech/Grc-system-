<div class="page-head"><h1><?= e($title) ?></h1></div>
<form method="post" class="card" action="<?= e($existing ? url('appetite/' . $existing['id']) : url('appetite')) ?>" novalidate><?= csrf_field() ?><div class="form-grid">
<?= ff('text', 'title', 'appetite.name', $s['title'] ?? '', ['required' => true, 'wide' => true]) ?>
<?= ff('textarea', 'statement', 'appetite.statement', $s['statement'] ?? '', ['required' => true, 'wide' => true]) ?>
<?= ff('select', 'metric', 'appetite.metric', $s['metric'] ?? 'max_residual_score', ['blank' => false, 'options' => ['max_residual_score' => t('enum.metric.max_residual_score'), 'max_net_loss' => t('enum.metric.max_net_loss')]]) ?>
<?= ff('text', 'limit_value', 'appetite.limit', $s['limit_value'] ?? '', ['required' => true, 'extra' => ' inputmode="decimal"', 'hint' => t('appetite.limit_hint')]) ?>
<?= ff('select', 'category_id', 'appetite.category', $s['category_id'] ?? '', ['options' => opts($lists['categories'], fn($d) => lname($d)), 'blank_label' => 'appetite.all_categories']) ?>
<?= ff('select', 'department_id', 'appetite.department', $s['department_id'] ?? '', ['options' => opts($lists['departments'], fn($d) => lname($d)), 'blank_label' => 'appetite.all_departments']) ?>
<?= ff('text', 'approved_by', 'appetite.approved_by', $s['approved_by'] ?? '', ['hint' => t('appetite.approved_hint')]) ?>
<?= ff('date', 'approved_on', 'appetite.approved_on', $s['approved_on'] ?? '') ?>
</div><?php if ($existing): ?><div class="field"><label class="check"><input type="checkbox" name="is_active" value="1"<?= checked(old('is_active', $s['is_active'] ?? 1)) ?>> <?= e(t('common.active')) ?></label></div><?php endif; ?>
<p class="muted"><?= e(t('appetite.disclaimer')) ?></p>
<div class="actions"><button class="btn" type="submit"><?= e(t('common.save')) ?></button><a class="btn secondary" href="<?= e(url('appetite')) ?>"><?= e(t('common.cancel')) ?></a></div></form>
