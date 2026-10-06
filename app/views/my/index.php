<?php /** @var array $sections */
$cfg = [
  'actions' => ['icon' => 'check', 'label' => 'my.actions', 'link' => fn($r) => url('risks/' . $r['risk_id'])],
  'decisions' => ['icon' => 'users', 'label' => 'my.decisions', 'link' => fn($r) => url('committees/' . $r['decision_id'])],
  'obligations' => ['icon' => 'scale', 'label' => 'my.obligations', 'link' => fn($r) => url('compliance/' . $r['id'])],
  'kris' => ['icon' => 'pulse', 'label' => 'my.kris', 'link' => fn($r) => url('kris/' . $r['id'])],
  'rcsa' => ['icon' => 'clipboard', 'label' => 'my.rcsa', 'link' => fn($r) => url('rcsa/' . $r['id'])],
  'rcsa_review' => ['icon' => 'clipboard', 'label' => 'my.rcsa_review', 'link' => fn($r) => url('rcsa/' . $r['id'])],
  'control_tests' => ['icon' => 'flask', 'label' => 'my.control_tests', 'link' => fn($r) => url('controls/' . $r['id']) . '#test'],
  'sharia_events' => ['icon' => 'moon', 'label' => 'my.sharia_events', 'link' => fn($r) => url('sharia/events/' . $r['id'])],
  'sharia_reviews' => ['icon' => 'moon', 'label' => 'my.sharia_reviews', 'link' => fn($r) => url('sharia/reviews/' . $r['id'])],
  'circulars' => ['icon' => 'file', 'label' => 'my.circulars', 'link' => fn($r) => url('circulars/' . $r['id']) . '#ack'],
  'circulars_overdue' => ['icon' => 'file', 'label' => 'my.circulars_overdue', 'link' => fn($r) => url('circulars/' . $r['id'])],
  'kri_breaches' => ['icon' => 'pulse', 'label' => 'my.kri_breaches', 'link' => fn($r) => url('kris/' . $r['kri_id']) . '#breaches'],
  'approvals' => ['icon' => 'check', 'label' => 'my.approvals', 'link' => fn($r) => url('approvals')],
  'bia_review' => ['icon' => 'layers', 'label' => 'my.bia_review', 'link' => fn($r) => url('bia/' . $r['id'])],
  'notes' => ['icon' => 'note', 'label' => 'my.notes', 'link' => fn($r) => $r['page_path'] ? base_path() . $r['page_path'] : url('notes')],
];
$total = 0; foreach ($sections as $rows) { $total += count($rows); } ?>
<div class="page-head"><div><h1><?= icon('tasks') ?> <?= e(t('my.title')) ?></h1><p class="sub"><?= e(t('my.subtitle')) ?></p></div>
  <div class="actions"><a class="btn secondary" href="<?= e(url('notes')) ?>"><?= icon('note') ?> <?= e(t('notes.title')) ?></a></div></div>
<?php if ($total === 0): ?>
<div class="card empty big"><div class="emoji" aria-hidden="true">✓</div><h2><?= e(t('my.all_clear')) ?></h2><p><?= e(t('my.all_clear_hint')) ?></p></div>
<?php endif; ?>
<div class="grid cols-2">
<?php foreach ($cfg as $key => $c): $rows = $sections[$key] ?? []; if (!$rows) continue; ?>
<section class="card task-card"><h2><?= icon($c['icon']) ?> <?= e(t($c['label'])) ?> <span class="count"><?= count($rows) ?></span></h2>
  <ul class="task-list"><?php foreach ($rows as $r): ?>
    <li class="<?= $r['overdue'] ? 'overdue' : '' ?>"><a href="<?= e($c['link']($r)) ?>"><?= e($r['title']) ?></a>
      <?php if (!empty($r['due'])): ?><span class="badge <?= $r['overdue'] ? 'danger' : '' ?>"><?= $r['overdue'] ? e(t('my.overdue')) . ' · ' : '' ?><?= e(fdate($r['due'])) ?></span><?php endif; ?>
      <?php if ($key === 'notes'): ?><form method="post" action="<?= e(url('notes/' . $r['id'] . '/done')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="next" value="my"><button class="btn small" type="submit"><?= e(t('notes.mark_done')) ?></button></form><?php endif; ?></li>
  <?php endforeach; ?></ul></section>
<?php endforeach; ?>
</div>
