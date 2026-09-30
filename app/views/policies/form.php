<div class="page-head"><h1><?= e($title) ?></h1></div>
<form method="post" class="card" action="<?= e($existing ? url('policies/' . $existing['id']) : url('policies')) ?>" novalidate><?= csrf_field() ?><div class="form-grid">
<?= ff('text', 'title', 'pol.name', $p['title'] ?? '', ['required' => true, 'wide' => true]) ?>
<?= ff('select', 'category', 'pol.category', $p['category'] ?? 'policy', ['blank' => false, 'options' => opts(\App\Controllers\PolicyController::CATEGORIES, fn($x) => t('enum.pcat.' . $x), null) ?: []]) ?>
<?= ff('select', 'department_id', 'pol.department', $p['department_id'] ?? '', ['options' => opts($lists['departments'], fn($d) => lname($d)), 'blank_label' => 'pol.institution_wide']) ?>
<?= ff('select', 'owner_user_id', 'pol.owner', $p['owner_user_id'] ?? '', ['required' => true, 'options' => opts($lists['users'], fn($d) => $d['full_name'])]) ?>
<?= ff('number', 'review_months', 'pol.review_months', $p['review_months'] ?? 12, ['required' => true, 'extra' => ' min="1" max="60"']) ?>
<?php if ($existing): ?><?= ff('select', 'status', 'pol.status', $p['status'] ?? 'draft', ['blank' => false, 'options' => opts(['draft', 'approved', 'retired'], fn($x) => t('enum.pstatus.' . $x), null) ?: []]) ?><?php endif; ?>
</div><div class="actions"><button class="btn" type="submit"><?= e(t('common.save')) ?></button><a class="btn secondary" href="<?= e($existing ? url('policies/' . $existing['id']) : url('policies')) ?>"><?= e(t('common.cancel')) ?></a></div></form>
