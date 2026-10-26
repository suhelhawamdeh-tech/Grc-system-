<?php /** @var array $usage @var array $top @var bool $hasKey @var array $models @var array $s @var string $version */ $tab = 'settings'; ?>
<div class="page-head"><div><h1><?= icon('sliders') ?> <?= e(t('ai.settings')) ?></h1><p class="sub"><?= e(t('ai.settings_hint')) ?></p></div></div>
<?php require __DIR__ . '/_tabs.php'; ?>
<div class="kpis"><div class="kpi"><div class="v"><?= (int)$usage['n'] ?></div><div class="l"><?= e(t('ai.stat_questions')) ?></div></div>
<div class="kpi"><div class="v"><?= (int)$usage['nosrc'] ?></div><div class="l"><?= e(t('ai.stat_nosrc')) ?></div></div>
<div class="kpi <?= (int)$usage['errors'] ? 'alert' : '' ?>"><div class="v"><?= (int)$usage['errors'] + (int)$usage['refused'] ?></div><div class="l"><?= e(t('ai.stat_errors')) ?></div></div>
<div class="kpi"><div class="v"><?= (int)$usage['red'] ?></div><div class="l"><?= e(t('ai.stat_redactions')) ?></div></div>
<div class="kpi"><div class="v small"><?= number_format((int)$usage['inp']) ?> / <?= number_format((int)$usage['outp']) ?></div><div class="l"><?= e(t('ai.stat_tokens')) ?></div></div></div>
<section class="card"><h2><?= e(t('ai.settings')) ?></h2>
<p><?= $hasKey ? '<span class="badge ok ai-key-ok">' . e(t('ai.key_present')) . '</span>' : '<span class="badge warn">' . e(t('ai.key_missing')) . '</span>' ?> <span class="muted small"><?= e(t('ai.key_hint')) ?></span></p>
<form method="post" action="<?= e(url('admin/assistant')) ?>" novalidate><?= csrf_field() ?><div class="form-grid">
<div class="field"><label class="check"><input type="checkbox" name="enabled" value="1"<?= checked($s['enabled'] === '1') ?>> <?= e(t('ai.enabled')) ?></label></div>
<?= ff('select', 'mode', 'ai.mode', $s['mode'], ['blank' => false, 'options' => ['library' => t('ai.mode_library'), 'llm' => t('ai.mode_llm')], 'hint' => t('ai.mode_hint')]) ?>
<?= ff('select', 'model', 'ai.model', $s['model'], ['blank' => false, 'options' => opts($models, fn($m) => $m, null), 'dir' => 'ltr', 'hint' => t('ai.model_hint')]) ?>
<?= ff('select', 'effort', 'ai.effort', $s['effort'], ['blank' => false, 'options' => ['low' => t('ai.effort_low'), 'medium' => t('ai.effort_medium'), 'high' => t('ai.effort_high')]]) ?>
<?= ff('number', 'rate_limit', 'ai.rate_limit', $s['limit'], ['extra' => ' min="1" max="500"']) ?></div>
<div class="actions"><button class="btn" type="submit"><?= e(t('common.save')) ?></button></div></form>
<?php if ($hasKey): ?><form method="post" action="<?= e(url('admin/assistant/test')) ?>" class="inline"><?= csrf_field() ?><button class="btn secondary" type="submit"><?= e(t('ai.test')) ?></button></form><?php endif; ?></section>
<section class="card"><h2><?= e(t('ai.library')) ?></h2><p><?= e(t('ai.library_version', ['v' => $version])) ?></p>
<form method="post" action="<?= e(url('admin/assistant/sync')) ?>" class="inline"><?= csrf_field() ?><button class="btn secondary" type="submit"><?= e(t('ai.sync')) ?></button></form></section>
<section class="card"><h2><?= e(t('ai.policy_title')) ?></h2><ul><?php foreach (['ai.policy_1', 'ai.policy_2', 'ai.policy_3', 'ai.policy_4', 'ai.policy_5'] as $k): ?><li><?= e(t($k)) ?></li><?php endforeach; ?></ul></section>
