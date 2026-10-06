<?php /** @var array $o @var ?array $existing @var array $users */ ?>
<div class="page-head"><h1><?= icon('target') ?> <?= e($title) ?></h1></div>
<form method="post" class="card" action="<?= e($existing ? url('strategy/' . $existing['id']) : url('strategy')) ?>" novalidate><?= csrf_field() ?><div class="form-grid">
<?= ff('text', 'title', 'strat.o_title', $o['title'] ?? '', ['required' => true, 'wide' => true]) ?>
<?= ff('textarea', 'description', 'strat.o_description', $o['description'] ?? '', ['wide' => true]) ?>
<?= ff('select', 'perspective', 'strat.perspective', $o['perspective'] ?? 'financial', ['blank' => false, 'options' => opts(\App\Controllers\StrategyController::PERSPECTIVES, fn($x) => t('enum.persp.' . $x), null)]) ?>
<?= ff('select', 'owner_user_id', 'strat.owner', $o['owner_user_id'] ?? '', ['required' => true, 'options' => opts($users, fn($x) => $x['full_name'])]) ?>
<?= ff('text', 'unit', 'strat.unit', $o['unit'] ?? '', ['hint' => t('strat.unit_hint')]) ?>
<?= ff('select', 'direction', 'strat.direction', $o['direction'] ?? 'higher_better', ['blank' => false, 'options' => ['higher_better' => t('enum.sdir.higher_better'), 'lower_better' => t('enum.sdir.lower_better')], 'hint' => t('strat.direction_hint')]) ?>
<?= ff('text', 'baseline', 'strat.baseline', $o['baseline'] ?? '', ['required' => true, 'extra' => ' inputmode="decimal"', 'hint' => t('strat.baseline_hint')]) ?>
<?= ff('text', 'target', 'strat.target', $o['target'] ?? '', ['required' => true, 'extra' => ' inputmode="decimal"']) ?>
<?= ff('text', 'current_value', 'strat.current_value', $o['current_value'] ?? '', ['extra' => ' inputmode="decimal"', 'hint' => t('strat.current_hint')]) ?>
<?= ff('date', 'due_date', 'strat.due_date', $o['due_date'] ?? '', ['required' => true]) ?></div>
<div class="actions"><button class="btn" type="submit"><?= e(t('common.save')) ?></button><a class="btn secondary" href="<?= e(url('strategy')) ?>"><?= e(t('common.cancel')) ?></a></div></form>
