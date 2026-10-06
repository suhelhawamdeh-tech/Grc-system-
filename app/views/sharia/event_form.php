<?php /** @var array $e @var ?array $existing @var array $lists */ ?>
<div class="page-head"><h1><?= icon('alert') ?> <?= e($title) ?></h1></div>
<p class="msg info"><?= e(t('sha.event_intro')) ?></p>
<form method="post" class="card" action="<?= e($existing ? url('sharia/events/' . $existing['id']) : url('sharia/events')) ?>" novalidate><?= csrf_field() ?><div class="form-grid">
<?= ff('text', 'title', 'sha.e_title', $e['title'] ?? '', ['required' => true, 'wide' => true]) ?>
<?= ff('textarea', 'description', 'sha.e_desc', $e['description'] ?? '', ['required' => true, 'wide' => true, 'hint' => t('sha.e_desc_hint'), 'extra' => ' rows="6"']) ?>
<?= ff('date', 'detected_on', 'sha.e_detected', $e['detected_on'] ?? '', ['required' => true, 'extra' => ' max="' . date('Y-m-d') . '"']) ?>
<?= ff('select', 'department_id', 'sha.e_dept', $e['department_id'] ?? '', ['required' => true, 'options' => opts($lists['departments'], fn($d) => lname($d))]) ?>
<?= ff('select', 'severity', 'sha.e_severity', $e['severity'] ?? 'medium', ['blank' => false, 'options' => opts(\App\Controllers\ShariaController::SEVERITIES, fn($x) => t('enum.sev.' . $x), null), 'hint' => t('sha.e_severity_hint')]) ?>
<?= ff('select', 'ruling_id', 'sha.e_ruling', $e['ruling_id'] ?? '', ['options' => opts($lists['rulings'], fn($r) => $r['code'] . ' — ' . mb_strimwidth($r['title'], 0, 70, '…')), 'hint' => t('sha.e_ruling_hint')]) ?></div>
<div class="actions"><button class="btn" type="submit"><?= e(t('common.save')) ?></button><a class="btn secondary" href="<?= e(url('sharia/events')) ?>"><?= e(t('common.cancel')) ?></a></div></form>
