<?php /** @var ?array $ctx @var ?int $ctxId @var ?array $answer @var array $history @var bool $enabled @var bool $llm @var int $used @var int $limit @var array $stats */
$tab = 'ask'; $loc = locale(); $prefill = $ctx['question'] ?? (string)($_GET['q'] ?? '');
$examples = ['ai.ex_1', 'ai.ex_2', 'ai.ex_3', 'ai.ex_4', 'ai.ex_5', 'ai.ex_6']; ?>
<div class="page-head"><div><h1><?= icon('spark') ?> <?= e(t('ai.title')) ?></h1><p class="sub"><?= e(t('ai.subtitle', ['s' => $stats['sources'], 'p' => $stats['passages']])) ?></p></div></div>
<?php require __DIR__ . '/_tabs.php'; ?>
<?php if (!$enabled): ?><div class="card notice"><p><?= e(t('ai.disabled')) ?></p></div><?php else: ?>
<div class="card notice ai-mode"><p><?= icon($llm ? 'spark' : 'shield') ?> <strong><?= e($llm ? t('ai.mode_llm') : t('ai.mode_library')) ?></strong> — <?= e($llm ? t('ai.mode_llm_hint') : t('ai.mode_library_hint')) ?></p></div>
<details class="card"><summary><strong><?= icon('lock') ?> <?= e(t('ai.safety_title')) ?></strong></summary><ul class="safety">
<?php foreach (['ai.safety_1', 'ai.safety_2', 'ai.safety_3', 'ai.safety_4', 'ai.safety_5', 'ai.safety_6'] as $k): ?><li><?= e(t($k)) ?></li><?php endforeach; ?></ul></details>
<?php if ($ctx): ?><section class="card"><h2><?= e(t('ai.context_title')) ?></h2><p class="muted"><?= e(t('ai.context_hint')) ?></p><pre class="code ctx" dir="ltr"><?= e($ctx['text']) ?></pre></section><?php endif; ?>
<form method="post" action="<?= e(url('assistant/ask')) ?>" class="card ask-form" novalidate><?= csrf_field() ?>
<?php if ($ctx): ?><input type="hidden" name="context" value="<?= e($ctx['type']) ?>"><input type="hidden" name="context_id" value="<?= (int)$ctxId ?>"><?php endif; ?>
<?= ff('textarea', 'question', 'ai.question', $prefill, ['required' => true, 'extra' => ' rows="3" maxlength="1000"', 'hint' => t('ai.question_hint')]) ?>
<div class="actions"><button class="btn gold" type="submit"<?= $used >= $limit ? ' disabled' : '' ?>><?= icon('spark') ?> <?= e(t('ai.ask')) ?></button><span class="muted small"><?= e(t('ai.usage', ['u' => $used, 'l' => $limit])) ?></span></div>
<?php if (!$ctx): ?><div class="examples"><span class="muted small"><?= e(t('ai.examples')) ?></span><?php foreach ($examples as $k): ?><a class="chip" href="<?= e(url('assistant', ['q' => t($k)])) ?>"><?= e(t($k)) ?></a><?php endforeach; ?></div><?php endif; ?>
</form>
<?php endif; ?>
<?php if ($answer): $cits = $answer['citations']; ?>
<section class="card answer" id="answer" aria-live="polite"><p class="q"><?= icon('user') ?> <?= e($answer['question']) ?></p>
<?php if ($answer['status'] === 'refused' || $answer['status'] === 'no_sources' || $answer['status'] === 'error'): ?><p><span class="badge <?= $answer['status'] === 'error' ? 'danger' : 'warn' ?>"><?= e(t('enum.aistat.' . $answer['status'])) ?></span></p><?php endif; ?>
<div class="a"><?= \App\Support\Assistant::render((string)$answer['answer'], count($cits)) ?></div>
<?php if ($cits): ?><h3><?= e(t('ai.sources_used')) ?></h3><ol class="citations"><?php foreach ($cits as $i => $c): ?>
<li id="src-<?= $i + 1 ?>"><a href="<?= e(url('assistant/library/' . ($c['source_id'] ?? $c['sid']))) ?>"><?= e(lname($c, 'title')) ?></a><?= $c['ref'] ? ' · <span class="code">' . e($c['ref']) . '</span>' : '' ?> — <?= e(lname($c, 'heading')) ?><?php if (!empty($c['url'])): ?> · <a href="<?= e($c['url']) ?>" rel="noopener noreferrer" target="_blank"><?= e(t('ai.original')) ?></a><?php endif; ?></li><?php endforeach; ?></ol><?php endif; ?>
<p class="muted small disclaimer"><?= e(t('ai.disclaimer')) ?> <?= e($answer['mode'] === 'llm' ? t('ai.answered_by_llm', ['m' => (string)$answer['model']]) : t('ai.answered_by_library')) ?></p></section>
<?php endif; ?>
<?php if ($history): ?><section class="card"><h2><?= e(t('ai.history')) ?></h2><ul class="history"><?php foreach ($history as $h): ?><li><a href="<?= e(url('assistant/history/' . $h['id'])) ?>"><?= e(mb_strimwidth($h['question'], 0, 110, '…')) ?></a> <span class="muted small"><?= e(fdatetime($h['created_at'])) ?> · <?= e(t('enum.aimode.' . $h['mode'])) ?></span></li><?php endforeach; ?></ul></section><?php endif; ?>
