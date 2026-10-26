<?php /** @var array $r @var ?array $existing @var array $customers */ ?>
<div class="page-head"><h1><?= icon('file') ?> <?= e($title) ?></h1></div>
<form method="post" class="card" action="<?= e($existing ? url('aml/reports/' . $existing['id']) : url('aml/reports')) ?>" novalidate><?= csrf_field() ?><div class="form-grid">
<?= ff('select', 'report_type', 'aml.report_type', $r['report_type'] ?? '', ['required' => true, 'options' => opts(['str', 'ctr', 'periodic', 'ewra', 'other'], fn($x) => t('enum.amlrt.' . $x), null), 'hint' => t('aml.report_type_hint')]) ?>
<?= ff('text', 'title', 'aml.r_title', $r['title'] ?? '', ['required' => true, 'wide' => true]) ?>
<?= ff('select', 'customer_id', 'aml.customer', $r['customer_id'] ?? '', ['options' => opts($customers, fn($c) => $c['customer_ref'] . ' — ' . $c['full_name']), 'hint' => t('aml.customer_hint')]) ?>
<?= ff('text', 'recipient', 'aml.recipient', $r['recipient'] ?? '', ['required' => true, 'hint' => t('aml.recipient_hint')]) ?>
<?= ff('date', 'due_date', 'aml.r_due', $r['due_date'] ?? '', ['required' => true]) ?>
<?= ff('textarea', 'summary', 'aml.r_summary', $r['summary'] ?? '', ['required' => true, 'wide' => true, 'extra' => ' rows="6"']) ?>
<div class="field"><label class="check"><input type="checkbox" name="confidential" value="1"<?= checked(old('confidential', $r['confidential'] ?? 0)) ?>> <?= e(t('aml.confidential')) ?></label><p class="hint"><?= e(t('aml.confidential_hint')) ?></p></div></div>
<div class="actions"><button class="btn" type="submit"><?= e(t('common.save')) ?></button><a class="btn secondary" href="<?= e(url('aml/reports')) ?>"><?= e(t('common.cancel')) ?></a></div></form>
