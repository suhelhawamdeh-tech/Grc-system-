<?php /** @var array $pending @var array $mine @var int $me */ ?>
<div class="page-head"><div><h1><?= icon('check') ?> <?= e(t('appr.title')) ?></h1><p class="sub"><?= e(t('appr.subtitle')) ?></p></div></div>
<section class="card"><h2><?= e(t('appr.pending')) ?> <span class="count badge"><?= count($pending) ?></span></h2>
<?php if (!$pending): ?><p class="empty big"><span class="emoji" aria-hidden="true">✓</span><br><?= e(t('appr.none')) ?></p><?php endif; ?>
<?php foreach ($pending as $a): ?><div class="note-list big"><ul class="note-list big"><li><div><strong><?= $a['link'] ? '<a href="' . e($a['link']) . '">' . e($a['label']) . '</a>' : e($a['label']) ?></strong></div>
<div class="meta"><?= e(t('appr.requested_by', ['name' => $a['requester']])) ?> · <?= e(fdatetime($a['requested_at'])) ?></div>
<?php if ((int)$a['requested_by'] === $me): ?><p class="muted"><?= e(t('appr.err_self')) ?></p><?php else: ?>
<form method="post" action="<?= e(url('approvals/' . $a['id'] . '/decide')) ?>"><?= csrf_field() ?><div class="field"><label for="c<?= (int)$a['id'] ?>"><?= e(t('appr.comment')) ?></label><input type="text" id="c<?= (int)$a['id'] ?>" name="comment" maxlength="500"><p class="hint"><?= e(t('appr.comment_hint')) ?></p></div>
<div class="actions"><button class="btn" name="decision" value="approve" type="submit"><?= e(t('appr.approve')) ?></button><button class="btn danger" name="decision" value="reject" type="submit"><?= e(t('appr.reject')) ?></button></div></form><?php endif; ?></li></ul></div><?php endforeach; ?></section>
<section class="card"><h2><?= e(t('appr.mine')) ?></h2>
<?php if (!$mine): ?><p class="muted"><?= e(t('common.no_results')) ?></p><?php else: ?><ul class="task-list"><?php foreach ($mine as $a): ?><li><?= $a['link'] ? '<a href="' . e($a['link']) . '">' . e($a['label']) . '</a>' : e($a['label']) ?><span class="badge <?= $a['status'] === 'approved' ? 'ok' : ($a['status'] === 'rejected' ? 'danger' : 'warn') ?>"><?= e(t('enum.astatus.' . $a['status'])) ?></span></li><?php endforeach; ?></ul><?php endif; ?></section>
