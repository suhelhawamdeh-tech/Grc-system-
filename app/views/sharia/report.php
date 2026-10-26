<?php /** @var int $year @var array $tot @var array $bySev @var array $reviews @var int $rulings @var int $activeRulings */ ?>
<div class="page-head"><div><h1><?= icon('file') ?> <?= e(t('sha.report')) ?> — <?= (int)$year ?></h1><p class="sub"><?= e(t('sha.report_hint')) ?></p></div>
  <form method="get" action="<?= e(url('sharia/report')) ?>" class="filters"><div class="field"><label for="year"><?= e(t('sha.year')) ?></label><input type="number" id="year" name="year" min="2000" max="2100" value="<?= (int)$year ?>"></div><div class="field"><button class="btn secondary" type="submit"><?= e(t('common.apply')) ?></button></div></form>
  <div class="actions noprint"><a class="btn secondary" href="<?= e(url('sharia')) ?>"><?= e(t('common.back')) ?></a><button class="btn" type="button" data-print><?= e(t('common.print')) ?></button></div></div>
<div class="kpis">
  <div class="kpi"><div class="v"><?= $rulings ?></div><div class="l"><?= e(t('sha.rep_rulings_year')) ?> (<?= $activeRulings ?> <?= e(t('sha.rep_active')) ?>)</div></div>
  <div class="kpi"><div class="v"><?= (int)($reviews['done'] ?? 0) ?>/<?= (int)($reviews['total'] ?? 0) ?></div><div class="l"><?= e(t('sha.rep_reviews')) ?></div></div>
  <div class="kpi"><div class="v"><?= (int)$tot['n'] ?></div><div class="l"><?= e(t('sha.rep_events')) ?></div></div>
</div>
<section class="card"><h2><?= e(t('sha.rep_by_severity')) ?></h2><table class="data"><thead><tr><th><?= e(t('sha.e_severity')) ?></th><th class="num"><?= e(t('sha.rep_count')) ?></th></tr></thead><tbody>
<?php foreach (\App\Controllers\ShariaController::SEVERITIES as $s): ?><tr><td><?= e(t('enum.sev.' . $s)) ?></td><td class="num"><?= (int)($bySev[$s] ?? 0) ?></td></tr><?php endforeach; ?></tbody></table></section>
<section class="card"><h2><?= e(t('sha.rep_purification')) ?></h2><table class="data"><tbody>
<tr><th scope="row"><?= e(t('sha.rep_income')) ?></th><td class="num"><?= e(money($tot['income'])) ?></td></tr>
<tr><th scope="row"><?= e(t('sha.rep_purified')) ?></th><td class="num"><?= e(money($tot['purified'])) ?></td></tr>
<tr><th scope="row"><?= e(t('sha.rep_pending')) ?></th><td class="num"><?= e(money($tot['pending'])) ?></td></tr></tbody></table>
<p class="muted"><?= e(t('sha.rep_note')) ?></p></section>
