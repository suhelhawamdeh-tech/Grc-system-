<?php /** @var string $slug @var ?array $topic */ ?>
<h2 id="panel-title"><?= icon('help') ?> <?= e($topic['title'] ?? t('help.title')) ?></h2>
<?php if ($topic): ?>
<p class="lead"><?= e($topic['summary']) ?></p>
<ol class="steps"><?php foreach ($topic['steps'] as $s): ?><li><?= e($s) ?></li><?php endforeach; ?></ol>
<?php if (!empty($topic['tips'])): ?><div class="tips"><ul><?php foreach ($topic['tips'] as $s): ?><li><?= e($s) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php endif; ?>
<p><a class="btn small secondary" href="<?= e(url('help', ['t' => $slug])) ?>"><?= e(t('help.full_guide')) ?></a> <a class="btn small secondary" href="<?= e(url('help')) ?>"><?= e(t('help.all_topics')) ?></a></p>
