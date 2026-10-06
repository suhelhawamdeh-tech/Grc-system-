<?php /** @var array $b @var ?array $existing */ ?>
<div class="page-head"><h1><?= icon('users') ?> <?= e($title) ?></h1></div>
<form method="post" class="card" action="<?= e($existing ? url('governance/' . $existing['id']) : url('governance')) ?>" novalidate><?= csrf_field() ?><div class="form-grid">
<?= ff('text', 'name', 'gov.b_name', $b['name'] ?? '', ['required' => true, 'wide' => true]) ?>
<?= ff('select', 'body_type', 'gov.b_type', $b['body_type'] ?? 'other', ['blank' => false, 'options' => opts(\App\Controllers\GovernanceController::TYPES, fn($x) => t('enum.committee.' . $x), null), 'hint' => t('gov.b_type_hint')]) ?>
<?= ff('date', 'formed_on', 'gov.b_formed', $b['formed_on'] ?? '', ['required' => true]) ?>
<?= ff('text', 'min_meetings_per_year', 'gov.b_min', $b['min_meetings_per_year'] ?? 4, ['required' => true, 'extra' => ' inputmode="numeric"', 'hint' => t('gov.b_min_hint')]) ?>
<?= ff('text', 'quorum_percent', 'gov.b_quorum', $b['quorum_percent'] ?? 50, ['required' => true, 'extra' => ' inputmode="numeric"', 'hint' => t('gov.b_quorum_hint')]) ?>
<?= ff('textarea', 'purpose', 'gov.b_purpose', $b['purpose'] ?? '', ['wide' => true]) ?></div>
<?php if ($existing): ?><div class="field"><label class="check"><input type="checkbox" name="is_active" value="1"<?= checked(old('is_active', $b['is_active'] ?? 1)) ?>> <?= e(t('common.active')) ?></label></div><?php endif; ?>
<div class="actions"><button class="btn" type="submit"><?= e(t('common.save')) ?></button><a class="btn secondary" href="<?= e(url('governance')) ?>"><?= e(t('common.cancel')) ?></a></div></form>
