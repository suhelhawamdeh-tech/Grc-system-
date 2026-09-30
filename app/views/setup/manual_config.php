<div class="card">
  <h1><?= e(t('setup.title')) ?></h1>
  <div class="msg info"><?= e(t('setup.manual_config')) ?></div>
  <ol><?php foreach ($paths as $p): ?><li><code dir="ltr"><?= e($p) ?></code></li><?php endforeach; ?></ol>
  <textarea readonly dir="ltr" style="min-height:260px;font-family:monospace"><?= e($source) ?></textarea>
  <p class="hint"><?= e(t('setup.manual_config_after')) ?></p>
  <a class="btn" href="<?= e(url('setup')) ?>"><?= e(t('common.continue')) ?></a>
</div>
