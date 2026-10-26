<div class="card">
  <h1><?= e(t('error.title')) ?> <span class="muted"><?= 403 ?></span></h1>
  <p><?= e(t('error.403')) ?></p>
  <?php if (!empty($ref)): ?><p class="muted"><?= e(t('error.reference', ['ref' => $ref])) ?></p><?php endif; ?>
  <?php if (!empty($debug)): ?><pre class="pre"><?= e((string)$debug) ?></pre><?php endif; ?>
  <p><a class="btn secondary" href="<?= e(url('')) ?>"><?= e(t('common.home')) ?></a></p>
</div>
