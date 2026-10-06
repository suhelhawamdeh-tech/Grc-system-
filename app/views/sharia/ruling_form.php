<?php /** @var array $r @var ?array $existing */ ?>
<div class="page-head"><h1><?= icon('book') ?> <?= e($title) ?></h1></div>
<form method="post" class="card" action="<?= e($existing ? url('sharia/rulings/' . $existing['id']) : url('sharia/rulings')) ?>" novalidate><?= csrf_field() ?><div class="form-grid">
<?= ff('text', 'title', 'sha.r_title', $r['title'] ?? '', ['required' => true, 'wide' => true]) ?>
<?= ff('text', 'subject', 'sha.r_subject', $r['subject'] ?? '', ['required' => true, 'wide' => true, 'hint' => t('sha.r_subject_hint')]) ?>
<?= ff('select', 'ruling_type', 'sha.r_type', $r['ruling_type'] ?? 'resolution', ['blank' => false, 'options' => opts(\App\Controllers\ShariaController::RULING_TYPES, fn($x) => t('enum.rtype.' . $x), null)]) ?>
<?= ff('select', 'status', 'sha.r_status', $r['status'] ?? 'active', ['blank' => false, 'options' => opts(\App\Controllers\ShariaController::RULING_STATUS, fn($x) => t('enum.rstatus.' . $x), null)]) ?>
<?= ff('date', 'issued_on', 'sha.r_issued', $r['issued_on'] ?? '', ['required' => true]) ?>
<?= ff('text', 'reference_no', 'sha.r_ref', $r['reference_no'] ?? '') ?>
<?= ff('textarea', 'ruling_text', 'sha.r_text', $r['ruling_text'] ?? '', ['required' => true, 'wide' => true, 'extra' => ' rows="8"']) ?></div>
<div class="actions"><button class="btn" type="submit"><?= e(t('common.save')) ?></button><a class="btn secondary" href="<?= e(url('sharia/rulings')) ?>"><?= e(t('common.cancel')) ?></a></div></form>
