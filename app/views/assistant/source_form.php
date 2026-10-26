<?php /** @var array $d */ $tab = 'add'; ?>
<div class="page-head"><div><h1><?= icon('book') ?> <?= e(t('ai.source_new')) ?></h1><p class="sub"><?= e(t('ai.source_new_hint')) ?></p></div></div>
<?php require __DIR__ . '/_tabs.php'; ?>
<form method="post" class="card" action="<?= e(url('assistant/library')) ?>" novalidate><?= csrf_field() ?><div class="form-grid">
<?= ff('text', 'title_ar', 'ai.s_title_ar', '', ['wide' => true]) ?><?= ff('text', 'title_en', 'ai.s_title_en', '', ['wide' => true, 'dir' => 'ltr']) ?>
<?= ff('text', 'issuer', 'ai.s_issuer', '', ['required' => true, 'hint' => t('ai.s_issuer_hint')]) ?><?= ff('number', 'year', 'ai.s_year', '', ['extra' => ' min="1950" max="' . date('Y') . '"']) ?>
<?= ff('select', 'category', 'ai.s_category', $d['category'], ['required' => true, 'blank' => false, 'options' => opts(\App\Support\KbLibrary::CATEGORIES, fn($x) => t('enum.kbcat.' . $x), null)]) ?>
<?= ff('select', 'jurisdiction', 'ai.s_jurisdiction', $d['jurisdiction'], ['required' => true, 'blank' => false, 'options' => opts(\App\Support\KbLibrary::JURISDICTIONS, fn($x) => t('enum.kbjur.' . $x), null)]) ?>
<?= ff('select', 'authority', 'ai.s_authority', $d['authority'], ['required' => true, 'blank' => false, 'options' => opts(\App\Support\KbLibrary::AUTHORITIES, fn($x) => t('enum.kbauth.' . $x), null)]) ?>
<?= ff('url', 'url', 'ai.s_url', '', ['dir' => 'ltr']) ?>
<fieldset class="field wide"><legend><?= e(t('ai.s_modules')) ?></legend><div class="form-grid"><?php foreach (\App\Support\KbLibrary::MODULES as $m): ?><label class="check"><input type="checkbox" name="modules[]" value="<?= e($m) ?>"> <?= e(t('enum.kbmod.' . $m)) ?></label><?php endforeach; ?></div></fieldset>
<?= ff('textarea', 'note', 'ai.s_note', '', ['wide' => true, 'extra' => ' rows="2"']) ?>
<?= ff('textarea', 'text', 'ai.s_text', '', ['required' => true, 'wide' => true, 'extra' => ' rows="14"', 'hint' => t('ai.s_text_hint')]) ?>
<div class="field wide"><label class="check"><input type="checkbox" name="restricted" value="1"> <?= e(t('ai.s_restricted')) ?></label><p class="hint"><?= e(t('ai.s_restricted_hint')) ?></p></div></div>
<div class="actions"><button class="btn gold" type="submit"><?= e(t('ai.add_to_library')) ?></button><a class="btn secondary" href="<?= e(url('assistant/library')) ?>"><?= e(t('common.cancel')) ?></a></div></form>
