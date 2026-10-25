<?php /** @var array $e @var array $wps @var array $findings @var array $controls */
$tab = 'plans'; $st = $e['status']; $mg = can('ia.manage');
$steps = ['planned', 'fieldwork', 'reporting', 'issued']; $cur = array_search($st, $steps, true);
$sev = static fn(string $s) => '<span class="badge ' . ($s === 'high' ? 'danger' : ($s === 'medium' ? 'warn' : '')) . '">' . e(t('enum.sev.' . $s)) . '</span>';
$wpBadge = static fn(string $s) => '<span class="badge ' . ($s === 'reviewed' ? 'ok' : ($s === 'prepared' ? 'warn' : '')) . '">' . e(t('enum.iawp.' . $s)) . '</span>'; ?>
<div class="page-head"><div><h1><?= icon('search') ?> <?= e($e['code']) ?> — <?= e($e['title']) ?></h1><p class="sub"><?= e($e['u_code'] . ' — ' . $e['u_name']) ?> · <?= e(t('ia.lead')) ?>: <?= e($e['lead']) ?></p></div>
  <div class="actions"><a class="btn secondary" href="<?= e(url('ia/plans/' . $e['plan_id'])) ?>"><?= e(t('ia.plan')) ?> <?= (int)$e['plan_year'] ?></a></div></div>
<?php require __DIR__ . '/_tabs.php'; ?>
<?php if ($st === 'cancelled'): ?><div class="card notice"><p><?= e(t('enum.iaes.cancelled')) ?>: <?= e($e['cancel_reason']) ?></p></div><?php else: ?>
<ol class="stepper"><?php foreach ($steps as $i => $s): ?><li class="<?= $i < $cur ? 'done' : ($i === $cur ? 'current' : '') ?>"<?= $i === $cur ? ' aria-current="step"' : '' ?>><?= e(t('enum.iaes.' . $s)) ?></li><?php endforeach; ?></ol><?php endif; ?>
<div class="grid cols-2">
<section class="card"><h2><?= e(t('ia.details')) ?></h2><dl class="details">
<dt><?= e(t('ia.period')) ?></dt><dd><?= e(fdate($e['planned_start'])) ?> → <?= e(fdate($e['planned_end'])) ?></dd>
<dt><?= e(t('ia.actual')) ?></dt><dd><?= e(fdate($e['actual_start']) ?: '—') ?> → <?= e(fdate($e['actual_end']) ?: '—') ?></dd>
<dt><?= e(t('ia.score')) ?></dt><dd><?= e(number_format((float)$e['priority_score'], 2)) ?></dd>
<dt><?= e(t('ia.objective')) ?></dt><dd class="pre"><?= e($e['objective']) ?></dd><dt><?= e(t('ia.scope')) ?></dt><dd class="pre"><?= e($e['scope'] ?? '—') ?></dd>
<?php if ($st === 'issued'): ?><dt><?= e(t('ia.rating')) ?></dt><dd><span class="badge <?= $e['rating'] === 'satisfactory' ? 'ok' : ($e['rating'] === 'unsatisfactory' ? 'danger' : 'warn') ?>"><?= e(t('enum.iarating.' . $e['rating'])) ?></span></dd><dt><?= e(t('ia.report_summary')) ?></dt><dd class="pre"><?= e($e['report_summary']) ?></dd><?php endif; ?></dl></section>
<?php if ($mg && $st !== 'issued' && $st !== 'cancelled'): ?><section class="card"><h2><?= e(t('ia.next_step')) ?></h2>
<?php if ($st === 'planned'): ?><p class="muted"><?= e($e['plan_status'] === 'approved' ? t('ia.start_hint') : t('ia.err_plan_not_approved')) ?></p>
<form method="post" action="<?= e(url('ia/engagements/' . $e['id'] . '/start')) ?>"><?= csrf_field() ?><button class="btn gold" type="submit"<?= $e['plan_status'] !== 'approved' ? ' disabled' : '' ?>><?= e(t('ia.start')) ?></button></form>
<?php elseif ($st === 'fieldwork'): ?><p class="muted"><?= e(t('ia.reporting_hint')) ?></p>
<form method="post" action="<?= e(url('ia/engagements/' . $e['id'] . '/reporting')) ?>"><?= csrf_field() ?><button class="btn gold" type="submit"><?= e(t('ia.to_reporting')) ?></button></form>
<?php elseif ($st === 'reporting'): ?><form method="post" id="issue" action="<?= e(url('ia/engagements/' . $e['id'] . '/issue')) ?>" novalidate><?= csrf_field() ?><p class="muted"><?= e(t('ia.issue_hint')) ?></p>
<?= ff('select', 'rating', 'ia.rating', '', ['required' => true, 'options' => opts(['satisfactory', 'needs_improvement', 'unsatisfactory'], fn($x) => t('enum.iarating.' . $x), null)]) ?>
<?= ff('textarea', 'report_summary', 'ia.report_summary', '', ['required' => true, 'extra' => ' rows="5"']) ?><button class="btn gold" type="submit"><?= e(t('ia.issue')) ?></button></form><?php endif; ?>
<?php if (in_array($st, ['planned', 'fieldwork'], true)): ?><details id="cancel" style="margin-top:12px"><summary><?= e(t('ia.cancel')) ?></summary><form method="post" action="<?= e(url('ia/engagements/' . $e['id'] . '/cancel')) ?>" novalidate><?= csrf_field() ?>
<?= ff('textarea', 'cancel_reason', 'ia.cancel_reason', '', ['required' => true]) ?><button class="btn danger small" type="submit"><?= e(t('ia.cancel')) ?></button></form></details><?php endif; ?></section><?php endif; ?>
</div>
<section class="card"><h2><?= e(t('ia.workpapers')) ?> <span class="count"><?= count($wps) ?></span></h2>
<div class="table-wrap"><table class="data"><thead><tr><th><?= e(t('ia.wp_ref')) ?></th><th><?= e(t('ia.wp_title')) ?></th><th><?= e(t('ia.control')) ?></th><th><?= e(t('ia.conclusion')) ?></th><th><?= e(t('ia.status')) ?></th><th><?= e(t('ia.prepared_reviewed')) ?></th></tr></thead><tbody>
<?php if (!$wps): ?><tr><td colspan="6" class="empty"><?= e(t('ia.wp_empty')) ?></td></tr><?php endif; ?>
<?php foreach ($wps as $w): ?><tr><td class="nowrap"><a href="<?= e(url('ia/workpapers/' . $w['id'])) ?>"><?= e($w['ref']) ?></a></td><td><a href="<?= e(url('ia/workpapers/' . $w['id'])) ?>"><?= e($w['title']) ?></a></td><td><?= e($w['ctl_code'] ?? '—') ?></td>
<td><?= $w['conclusion'] ? e(t('enum.iaconc.' . $w['conclusion'])) . ($w['sample_size'] !== null ? ' <span class="muted">(' . (int)$w['exceptions'] . '/' . (int)$w['sample_size'] . ')</span>' : '') : '—' ?></td><td><?= $wpBadge($w['status']) ?></td><td class="small"><?= e($w['preparer'] ?? '—') ?> / <?= e($w['reviewer'] ?? '—') ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php if ($mg && $st === 'fieldwork'): ?><details id="add-wp" style="margin-top:12px"<?= $wps ? '' : ' open' ?>><summary><strong><?= e(t('ia.wp_new')) ?></strong></summary><form method="post" action="<?= e(url('ia/engagements/' . $e['id'] . '/workpapers')) ?>" novalidate><?= csrf_field() ?><div class="form-grid">
<?= ff('text', 'title', 'ia.wp_title', '', ['required' => true]) ?><?= ff('select', 'control_id', 'ia.control', '', ['options' => opts($controls, fn($c) => $c['code'] . ' — ' . mb_strimwidth($c['name'], 0, 60, '…')), 'hint' => t('ia.control_hint')]) ?>
<?= ff('textarea', 'procedure_text', 'ia.procedure', '', ['required' => true, 'wide' => true, 'hint' => t('ia.procedure_hint')]) ?></div><button class="btn" type="submit"><?= e(t('ia.wp_new')) ?></button></form></details><?php endif; ?></section>
<section class="card"><h2><?= e(t('ia.findings')) ?> <span class="count"><?= count($findings) ?></span></h2>
<?php if ($mg && in_array($st, ['fieldwork', 'reporting'], true)): ?><p><a class="btn gold small" href="<?= e(url('ia/engagements/' . $e['id'] . '/findings/new')) ?>"><?= e(t('ia.finding_new')) ?></a> <span class="muted small"><?= e(t('ia.finding_draft_hint')) ?></span></p><?php endif; ?>
<div class="table-wrap"><table class="data"><thead><tr><th><?= e(t('ia.code')) ?></th><th><?= e(t('ia.f_title')) ?></th><th><?= e(t('ia.severity')) ?></th><th><?= e(t('ia.owner')) ?></th><th><?= e(t('ia.status')) ?></th></tr></thead><tbody>
<?php if (!$findings): ?><tr><td colspan="5" class="empty"><?= e(t('ia.findings_empty')) ?></td></tr><?php endif; ?>
<?php foreach ($findings as $f): ?><tr><td class="nowrap"><a href="<?= e(url('ia/findings/' . $f['id'])) ?>"><?= e($f['code']) ?></a></td><td><?= e($f['title']) ?></td><td><?= $sev($f['severity']) ?></td><td><?= e($f['owner']) ?></td><td><?= e(t('enum.iafs.' . $f['status'])) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
