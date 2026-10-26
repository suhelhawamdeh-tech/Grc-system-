<?php $levels = []; foreach (\App\Support\Framework::LEVELS as $l) { $levels[$l] = t('enum.doclevel.' . $l); } ?>
<div class="page-head"><h1><?= e($title) ?></h1></div>
<form method="post" class="card" action="<?= e($existing ? url('policies/' . $existing['id']) : url('policies')) ?>" novalidate><?= csrf_field() ?><div class="form-grid">
<?= ff('text', 'code', 'docs.code', $p['code'] ?? '', ['dir' => 'ltr', 'hint' => t('docs.code_hint'), 'extra' => ' maxlength="40"']) ?>
<?= ff('text', 'title', 'pol.name', $p['title'] ?? '', ['required' => true, 'wide' => true]) ?>
<?= ff('text', 'title_en', 'docs.title_en', $p['title_en'] ?? '', ['wide' => true, 'dir' => 'ltr']) ?>
<?= ff('select', 'category', 'pol.category', $p['category'] ?? 'policy', ['blank' => false, 'options' => opts(\App\Controllers\PolicyController::CATEGORIES, fn($x) => t('enum.pcat.' . $x), null) ?: []]) ?>
<?= ff('select', 'doc_level', 'docs.level', $p['doc_level'] ?? '', ['options' => $levels, 'blank_label' => 'docs.level_auto', 'hint' => t('docs.level_hint')]) ?>
<?= ff('select', 'classification', 'docs.classification', $p['classification'] ?? 'internal', ['blank' => false, 'options' => opts(\App\Support\Framework::CLASSIFICATIONS, fn($x) => t('enum.cls.' . $x), null) ?: [], 'hint' => t('docs.classification_hint')]) ?>
<?= ff('select', 'rm_section', 'docs.section', $p['rm_section'] ?? '', ['options' => opts(\App\Support\Framework::SECTIONS, fn($x) => t('enum.rmsec.' . $x), null) ?: [], 'blank_label' => 'docs.no_section']) ?>
<?= ff('select', 'parent_id', 'docs.parent', $p['parent_id'] ?? '', ['options' => opts(array_filter($lists['parents'], fn($x) => (int)$x['id'] !== (int)($existing['id'] ?? 0)), fn($d) => $d['code'] . ' — ' . mb_strimwidth($d['title'], 0, 70, '…')), 'blank_label' => 'docs.no_parent']) ?>
<?= ff('text', 'approval_body', 'docs.approval_body', $p['approval_body'] ?? '', ['hint' => t('docs.approval_body_hint')]) ?>
<?= ff('select', 'department_id', 'pol.department', $p['department_id'] ?? '', ['options' => opts($lists['departments'], fn($d) => lname($d)), 'blank_label' => 'pol.institution_wide']) ?>
<?= ff('select', 'owner_user_id', 'pol.owner', $p['owner_user_id'] ?? '', ['required' => true, 'options' => opts($lists['users'], fn($d) => $d['full_name'])]) ?>
<?= ff('number', 'review_months', 'pol.review_months', $p['review_months'] ?? 12, ['required' => true, 'extra' => ' min="1" max="60"', 'hint' => t('docs.review_hint')]) ?>
<?php if ($existing): ?><?= ff('select', 'status', 'pol.status', $p['status'] ?? 'draft', ['blank' => false, 'options' => opts(['draft', 'approved', 'retired'], fn($x) => t('enum.pstatus.' . $x), null) ?: []]) ?><?php endif; ?>
</div><div class="actions"><button class="btn" type="submit"><?= e(t('common.save')) ?></button><a class="btn secondary" href="<?= e($existing ? url('policies/' . $existing['id']) : url('policies')) ?>"><?= e(t('common.cancel')) ?></a></div></form>
