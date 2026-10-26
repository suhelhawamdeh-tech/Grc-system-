<?php /** @var array $r @var ?array $existing @var array $lists */ ?>
<div class="page-head"><h1><?= icon('search') ?> <?= e($title) ?></h1></div>
<form method="post" class="card" action="<?= e($existing ? url('sharia/reviews/' . $existing['id']) : url('sharia/reviews')) ?>" novalidate><?= csrf_field() ?><div class="form-grid">
<?= ff('text', 'title', 'sha.v_title', $r['title'] ?? '', ['required' => true, 'wide' => true]) ?>
<?= ff('select', 'review_type', 'sha.v_type', $r['review_type'] ?? 'product', ['blank' => false, 'options' => opts(\App\Controllers\ShariaController::REVIEW_TYPES, fn($x) => t('enum.rvtype.' . $x), null)]) ?>
<?= ff('select', 'department_id', 'sha.v_dept', $r['department_id'] ?? '', ['options' => opts($lists['departments'], fn($d) => lname($d))]) ?>
<?= ff('select', 'reviewer_user_id', 'sha.v_reviewer', $r['reviewer_user_id'] ?? '', ['required' => true, 'options' => opts($lists['users'], fn($d) => $d['full_name'])]) ?>
<?= ff('date', 'planned_date', 'sha.v_planned', $r['planned_date'] ?? '', ['required' => true]) ?>
<?= ff('select', 'status', 'sha.v_status', $r['status'] ?? 'planned', ['blank' => false, 'options' => opts(['planned', 'in_progress', 'completed'], fn($x) => t('enum.rvstatus.' . $x), null)]) ?>
<?= ff('date', 'completed_on', 'sha.v_completed', $r['completed_on'] ?? '', ['hint' => t('sha.v_completed_hint')]) ?>
<?= ff('textarea', 'scope', 'sha.v_scope', $r['scope'] ?? '', ['wide' => true]) ?>
<?= ff('textarea', 'summary', 'sha.v_summary', $r['summary'] ?? '', ['wide' => true, 'hint' => t('sha.v_summary_hint'), 'extra' => ' rows="6"']) ?></div>
<div class="actions"><button class="btn" type="submit"><?= e(t('common.save')) ?></button><a class="btn secondary" href="<?= e(url('sharia/reviews')) ?>"><?= e(t('common.cancel')) ?></a></div></form>
