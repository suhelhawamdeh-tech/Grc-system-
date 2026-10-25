<?php /** @var array $stats @var int $rulings @var int $reviewsOpen @var int $reviewsLate @var array $events */
$sev = static fn(string $s) => '<span class="badge ' . ($s === 'high' ? 'danger' : ($s === 'medium' ? 'warn' : '')) . '">' . e(t('enum.sev.' . $s)) . '</span>'; ?>
<div class="page-head"><div><h1><?= icon('moon') ?> <?= e(t('sha.title')) ?></h1><p class="sub"><?= e(t('sha.subtitle')) ?></p></div>
  <div class="actions"><a class="btn secondary" href="<?= e(url('sharia/report')) ?>"><?= e(t('sha.report')) ?></a>
  <?php if (can('sharia.report')): ?><a class="btn gold" href="<?= e(url('sharia/events/new')) ?>"><?= e(t('sha.event_new')) ?></a><?php endif; ?></div></div>
<div class="kpis">
  <a class="kpi <?= (int)$stats['open_n'] ? 'alert' : '' ?>" href="<?= e(url('sharia/events', ['open' => 1])) ?>"><div class="v"><?= (int)$stats['open_n'] ?></div><div class="l"><?= e(t('sha.stat_open')) ?></div></a>
  <a class="kpi <?= (int)$stats['high_n'] ? 'alert' : '' ?>" href="<?= e(url('sharia/events', ['open' => 1, 'severity' => 'high'])) ?>"><div class="v"><?= (int)$stats['high_n'] ?></div><div class="l"><?= e(t('sha.stat_high')) ?></div></a>
  <a class="kpi <?= (int)$stats['pending_n'] ? 'alert' : '' ?>" href="<?= e(url('sharia/events', ['purification' => 1])) ?>"><div class="v"><?= (int)$stats['pending_n'] ?></div><div class="l"><?= e(t('sha.stat_purify')) ?></div></a>
  <a class="kpi" href="<?= e(url('sharia/rulings', ['status' => 'active'])) ?>"><div class="v"><?= $rulings ?></div><div class="l"><?= e(t('sha.stat_rulings')) ?></div></a>
  <a class="kpi <?= $reviewsLate ? 'alert' : '' ?>" href="<?= e(url('sharia/reviews', ['late' => 1])) ?>"><div class="v"><?= $reviewsOpen ?></div><div class="l"><?= e(t('sha.stat_reviews')) ?><?= $reviewsLate ? ' (' . $reviewsLate . ' ' . e(t('my.overdue')) . ')' : '' ?></div></a>
</div>
<div class="grid cols-3">
  <a class="help-card" href="<?= e(url('sharia/rulings')) ?>"><strong><?= icon('book') ?> <?= e(t('sha.rulings')) ?></strong><span><?= e(t('sha.rulings_hint')) ?></span></a>
  <a class="help-card" href="<?= e(url('sharia/reviews')) ?>"><strong><?= icon('search') ?> <?= e(t('sha.reviews')) ?></strong><span><?= e(t('sha.reviews_hint')) ?></span></a>
  <a class="help-card" href="<?= e(url('sharia/events')) ?>"><strong><?= icon('alert') ?> <?= e(t('sha.events')) ?></strong><span><?= e(t('sha.events_hint')) ?></span></a>
</div>
<section class="card" style="margin-top:16px"><h2><?= e(t('sha.open_events')) ?></h2>
<?php if (!$events): ?><p class="empty"><?= e(t('sha.none_open')) ?></p><?php else: ?>
<div class="table-wrap"><table class="data"><thead><tr><th><?= e(t('sha.e_code')) ?></th><th><?= e(t('sha.e_title')) ?></th><th><?= e(t('sha.e_dept')) ?></th><th><?= e(t('sha.e_severity')) ?></th><th><?= e(t('sha.e_status')) ?></th></tr></thead><tbody>
<?php foreach ($events as $e): ?><tr><td class="nowrap"><a href="<?= e(url('sharia/events/' . $e['id'])) ?>"><?= e($e['code']) ?></a></td><td><?= e($e['title']) ?></td><td><?= e(lname($e, 'dept')) ?></td><td><?= $sev($e['severity']) ?></td><td><?= e(t('enum.sevent.' . $e['status'])) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<?= kb_refs('sharia') ?>
