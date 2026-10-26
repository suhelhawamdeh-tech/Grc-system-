<?php /** @var array $s @var array $chunks */ $tab = 'library'; ?>
<div class="page-head"><div><h1><?= icon('book') ?> <?= e(lname($s, 'title')) ?></h1><p class="sub"><?= e($s['issuer']) ?><?= $s['pub_year'] ? ' · ' . (int)$s['pub_year'] : '' ?> · <?= e(t('enum.kbauth.' . $s['authority'])) ?> · <?= e(t('enum.kbjur.' . $s['jurisdiction'])) ?></p></div>
  <div class="actions"><a class="btn secondary" href="<?= e(url('assistant/library')) ?>"><?= e(t('common.back')) ?></a><?php if ($s['url']): ?><a class="btn" href="<?= e($s['url']) ?>" rel="noopener noreferrer" target="_blank"><?= e(t('ai.original')) ?></a><?php endif; ?>
  <?php if (can('kb.manage') && !(int)$s['is_builtin']): ?><form method="post" class="inline" action="<?= e(url('assistant/library/' . $s['id'] . '/retire')) ?>"><?= csrf_field() ?><button class="btn secondary" type="submit"><?= e($s['status'] === 'active' ? t('ai.retire') : t('ai.restore')) ?></button></form><?php endif; ?></div></div>
<?php require __DIR__ . '/_tabs.php'; ?>
<?php if ($s['status'] !== 'active'): ?><div class="card notice"><p><?= e(t('ai.retired_note')) ?></p></div><?php endif; ?>
<?php $note = lname($s, 'note'); if ($note !== ''): ?><div class="card notice"><p><?= e($note) ?></p></div><?php endif; ?>
<?php if ($s['circular_id']): ?><p><a href="<?= e(url('circulars/' . $s['circular_id'])) ?>"><?= e(t('ai.from_circular')) ?></a></p><?php endif; ?>
<?php foreach ($chunks as $c): ?><section class="card passage" id="c<?= (int)$c['id'] ?>"><h2><?= $c['ref'] ? '<span class="code">' . e($c['ref']) . '</span> ' : '' ?><?= e(lname($c, 'heading')) ?></h2><div class="pre"><?= e(lname($c, 'body')) ?></div></section><?php endforeach; ?>
<p class="muted small"><?= e((int)$s['is_builtin'] ? t('ai.builtin_note') : t('ai.added_by', ['n' => (string)($s['full_name'] ?? '—'), 'd' => fdate(substr((string)$s['created_at'], 0, 10))])) ?></p>
