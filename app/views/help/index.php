<?php /** @var array $topics @var string $slug @var ?array $topic @var array $start @var array $glossary @var string $q */ ?>
<div class="page-head"><div><h1><?= icon('help') ?> <?= e(t('help.title')) ?></h1><p class="sub"><?= e(t('help.subtitle')) ?></p></div>
  <form method="get" action="<?= e(url('help')) ?>" class="help-search" role="search">
    <label class="sr-only" for="help-q"><?= e(t('help.search')) ?></label>
    <input type="search" id="help-q" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('help.search')) ?>" data-filter-target="#help-topics .help-card">
    <button class="btn secondary" type="submit"><?= e(t('common.filter')) ?></button></form></div>

<?php if ($topic): ?>
<article class="card help-topic">
  <p><a href="<?= e(url('help')) ?>">← <?= e(t('help.all_topics')) ?></a></p>
  <h2><?= e($topic['title']) ?></h2>
  <p class="lead"><?= e($topic['summary']) ?></p>
  <h3><?= e(t('help.how_to')) ?></h3>
  <ol class="steps"><?php foreach ($topic['steps'] as $s): ?><li><?= e($s) ?></li><?php endforeach; ?></ol>
  <?php if (!empty($topic['tips'])): ?><div class="tips"><h3><?= icon('bulb') ?> <?= e(t('help.tips')) ?></h3><ul><?php foreach ($topic['tips'] as $s): ?><li><?= e($s) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
  <?php if (!empty($topic['link'])): ?><p><a class="btn" href="<?= e(url($topic['link'])) ?>"><?= e(t('help.open_page')) ?></a></p><?php endif; ?>
</article>
<?php else: ?>
<?php if (!empty($start['steps'])): ?>
<section class="card quickstart">
  <h2><?= icon('flag') ?> <?= e(t('help.your_start')) ?></h2>
  <p class="lead"><?= e($start['intro']) ?></p>
  <ol class="steps"><?php foreach ($start['steps'] as [$sl, $txt]): ?><li><a href="<?= e(url('help', ['t' => $sl])) ?>"><?= e($txt) ?></a></li><?php endforeach; ?></ol>
</section>
<?php endif; ?>
<div class="help-grid" id="help-topics">
<?php foreach ($topics as $sl => $tp):
    if ($q !== '' && mb_stripos($tp['title'] . ' ' . $tp['summary'] . ' ' . implode(' ', $tp['steps']), $q) === false) continue; ?>
  <a class="help-card" href="<?= e(url('help', ['t' => $sl])) ?>"><strong><?= e($tp['title']) ?></strong><span><?= e($tp['summary']) ?></span></a>
<?php endforeach; ?>
</div>
<section class="card glossary"><h2><?= icon('book') ?> <?= e(t('help.glossary')) ?></h2>
  <dl class="details"><?php foreach ($glossary as $term => $def): ?><dt><?= e($term) ?></dt><dd><?= e($def) ?></dd><?php endforeach; ?></dl></section>
<?php endif; ?>
