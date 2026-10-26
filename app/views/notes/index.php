<?php /** @var array $open @var array $done @var string $page */ ?>
<div class="page-head"><div><h1><?= icon('note') ?> <?= e(t('notes.title')) ?></h1><p class="sub"><?= e(t('notes.subtitle')) ?></p></div></div>
<div class="grid cols-2">
<section class="card"><h2><?= e(t('notes.new')) ?></h2>
  <form method="post" action="<?= e(url('notes')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="page_path" value="<?= e($page) ?>">
    <?= ff('textarea', 'body', 'notes.body', '', ['required' => true, 'extra' => ' maxlength="500" rows="4"']) ?>
    <?= ff('date', 'remind_on', 'notes.remind_on', '', ['hint' => t('notes.remind_hint')]) ?>
    <button class="btn" type="submit"><?= e(t('common.save')) ?></button>
  </form></section>
<section class="card"><h2><?= e(t('notes.open')) ?></h2>
<?php if (!$open): ?><p class="empty"><?= icon('note') ?><br><?= e(t('notes.empty')) ?></p><?php endif; ?>
<ul class="note-list big"><?php foreach ($open as $n): $due = $n['remind_on'] && $n['remind_on'] <= date('Y-m-d'); ?>
<li class="<?= $due ? 'due' : '' ?>"><div><?= e($n['body']) ?></div>
  <div class="meta"><?= $n['remind_on'] ? '<span class="badge ' . ($due ? 'danger' : '') . '">' . e(t('notes.remind_on')) . ': ' . e(fdate($n['remind_on'])) . '</span> ' : '' ?>
  <?= $n['page_path'] ? '<a href="' . e(base_path() . $n['page_path']) . '">' . e(t('notes.go_page')) . '</a>' : '' ?></div>
  <div class="actions">
    <form method="post" action="<?= e(url('notes/' . $n['id'] . '/done')) ?>" class="inline"><?= csrf_field() ?><button class="btn small" type="submit"><?= e(t('notes.mark_done')) ?></button></form>
    <form method="post" action="<?= e(url('notes/' . $n['id'] . '/delete')) ?>" class="inline" data-confirm="<?= e(t('notes.confirm_delete')) ?>"><?= csrf_field() ?><button class="btn small danger" type="submit"><?= e(t('common.delete')) ?></button></form>
  </div></li><?php endforeach; ?></ul>
<?php if ($done): ?><h3><?= e(t('notes.done')) ?></h3><ul class="note-list"><?php foreach ($done as $n): ?><li class="was-done"><?= e($n['body']) ?>
  <form method="post" action="<?= e(url('notes/' . $n['id'] . '/done')) ?>" class="inline"><?= csrf_field() ?><button class="linklike" type="submit"><?= e(t('notes.reopen')) ?></button></form></li><?php endforeach; ?></ul><?php endif; ?>
</section></div>
