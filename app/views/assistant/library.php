<?php /** @var array $sources @var array $f @var array $hits */ $tab = 'library'; ?>
<div class="page-head"><div><h1><?= icon('book') ?> <?= e(t('ai.library')) ?></h1><p class="sub"><?= e(t('ai.library_hint')) ?></p></div>
  <div class="actions"><?php if (can('kb.manage')): ?><a class="btn gold" href="<?= e(url('assistant/library/new')) ?>"><?= e(t('ai.source_new')) ?></a><?php endif; ?></div></div>
<?php require __DIR__ . '/_tabs.php'; ?>
<div class="card notice"><p><?= e(t('ai.library_notice')) ?></p></div>
<form method="get" action="<?= e(url('assistant/library')) ?>" class="card filters">
<?= ff('text', 'q', 'ai.search_library', $f['q']) ?>
<?= ff('select', 'category', 'ai.s_category', $f['category'], ['options' => opts(\App\Support\KbLibrary::CATEGORIES, fn($x) => t('enum.kbcat.' . $x), null), 'blank_label' => 'common.all']) ?>
<?= ff('select', 'jurisdiction', 'ai.s_jurisdiction', $f['jurisdiction'], ['options' => opts(\App\Support\KbLibrary::JURISDICTIONS, fn($x) => t('enum.kbjur.' . $x), null), 'blank_label' => 'common.all']) ?>
<div class="field actions"><button class="btn" type="submit"><?= e(t('common.filter')) ?></button><a class="btn secondary" href="<?= e(url('assistant/library')) ?>"><?= e(t('common.reset')) ?></a></div></form>
<?php if ($f['q'] !== ''): ?><section class="card"><h2><?= e(t('ai.search_results')) ?></h2><?php if (!$hits): ?><p class="muted"><?= e(t('common.no_results')) ?></p><?php else: ?><ol class="citations"><?php foreach ($hits as $h): ?>
<li><a href="<?= e(url('assistant/library/' . $h['source_id'])) ?>#c<?= (int)$h['id'] ?>"><?= e(lname($h, 'title')) ?></a><?= $h['ref'] ? ' · <span class="code">' . e($h['ref']) . '</span>' : '' ?> — <strong><?= e(lname($h, 'heading')) ?></strong><div class="muted small"><?= e(mb_strimwidth(lname($h, 'body'), 0, 260, '…')) ?></div></li><?php endforeach; ?></ol><?php endif; ?></section><?php endif; ?>
<div class="table-wrap"><table class="data"><thead><tr><th><?= e(t('ai.s_title')) ?></th><th><?= e(t('ai.s_issuer')) ?></th><th><?= e(t('ai.s_category')) ?></th><th><?= e(t('ai.s_authority')) ?></th><th><?= e(t('ai.s_year')) ?></th><th class="num"><?= e(t('ai.passages')) ?></th></tr></thead><tbody>
<?php if (!$sources): ?><tr><td colspan="6" class="empty"><?= e(t('common.no_results')) ?></td></tr><?php endif; ?>
<?php foreach ($sources as $s): ?><tr><td><a href="<?= e(url('assistant/library/' . $s['id'])) ?>"><?= e(lname($s, 'title')) ?></a><?= (int)$s['is_builtin'] ? '' : ' <span class="badge">' . e(t('ai.bank_added')) . '</span>' ?><?= (int)$s['restricted'] ? ' <span class="badge danger">' . e(t('ai.restricted')) . '</span>' : '' ?></td>
<td><?= e($s['issuer']) ?><div class="muted small"><?= e(t('enum.kbjur.' . $s['jurisdiction'])) ?></div></td><td><?= e(t('enum.kbcat.' . $s['category'])) ?></td><td><?= e(t('enum.kbauth.' . $s['authority'])) ?></td><td><?= $s['pub_year'] ? (int)$s['pub_year'] : '—' ?></td><td class="num"><?= (int)$s['n'] ?></td></tr><?php endforeach; ?></tbody></table></div>
