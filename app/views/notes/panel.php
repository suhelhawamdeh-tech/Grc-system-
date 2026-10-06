<?php /** @var array $open */ ?>
<h2 id="panel-title"><?= icon('note') ?> <?= e(t('notes.title')) ?></h2>
<form method="post" action="<?= e(url('notes')) ?>" class="note-form">
  <?= csrf_field() ?>
  <input type="hidden" name="page_path" value="" data-current-path>
  <div class="field"><label for="np_body"><?= e(t('notes.body')) ?></label><textarea id="np_body" name="body" maxlength="500" required rows="4" placeholder="<?= e(t('notes.placeholder')) ?>"></textarea></div>
  <div class="field"><label for="np_remind"><?= e(t('notes.remind_on')) ?></label><input type="date" id="np_remind" name="remind_on" min="<?= e(date('Y-m-d')) ?>"><p class="hint"><?= e(t('notes.remind_hint')) ?></p></div>
  <button class="btn" type="submit"><?= e(t('common.save')) ?></button>
</form>
<?php if ($open): ?><h3><?= e(t('notes.latest')) ?></h3><ul class="note-list"><?php foreach ($open as $n): ?><li><?= e($n['body']) ?><?= $n['remind_on'] ? ' <span class="badge">' . e(fdate($n['remind_on'])) . '</span>' : '' ?></li><?php endforeach; ?></ul><?php endif; ?>
<p><a class="btn small secondary" href="<?= e(url('notes')) ?>"><?= e(t('notes.all')) ?></a></p>
