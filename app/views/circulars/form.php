<?php /** @var array $c @var ?array $existing @var array $obligations */ ?>
<div class="page-head"><h1><?= icon('file') ?> <?= e($title) ?></h1></div>
<form method="post" class="card" action="<?= e($existing ? url('circulars/' . $existing['id']) : url('circulars')) ?>" novalidate><?= csrf_field() ?><div class="form-grid">
<?= ff('text', 'title', 'cir.c_title', $c['title'] ?? '', ['required' => true, 'wide' => true]) ?>
<?= ff('text', 'issuer', 'cir.issuer', $c['issuer'] ?? '', ['required' => true, 'hint' => t('cir.issuer_hint')]) ?>
<?= ff('text', 'reference_no', 'cir.reference', $c['reference_no'] ?? '') ?>
<?= ff('date', 'issued_on', 'cir.issued_on', $c['issued_on'] ?? '', ['required' => true]) ?>
<?= ff('date', 'received_on', 'cir.received_on', $c['received_on'] ?? '', ['required' => true]) ?>
<?= ff('textarea', 'summary', 'cir.summary', $c['summary'] ?? '', ['required' => true, 'wide' => true, 'extra' => ' rows="6"']) ?>
<?= ff('textarea', 'required_action', 'cir.required_action', $c['required_action'] ?? '', ['wide' => true, 'hint' => t('cir.required_action_hint')]) ?>
<?= ff('date', 'due_date', 'cir.due_date', $c['due_date'] ?? '') ?>
<?= ff('select', 'obligation_id', 'cir.obligation', $c['obligation_id'] ?? '', ['options' => opts($obligations, fn($o) => $o['code'] . ' — ' . mb_strimwidth($o['title'], 0, 70, '…')), 'hint' => t('cir.obligation_hint')]) ?></div>
<div class="actions"><button class="btn" type="submit"><?= e(t('common.save')) ?></button><a class="btn secondary" href="<?= e(url('circulars')) ?>"><?= e(t('common.cancel')) ?></a></div></form>
