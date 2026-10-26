<?php /** @var array $rows @var array $templates @var array $departments @var array $users */ ?>
<div class="page-head"><div><h1><?= icon('clipboard') ?> <?= e(t('rcsa.schedules')) ?></h1><p class="sub"><?= e(t('rcsa.schedules_hint')) ?></p></div><div class="actions"><a class="btn secondary" href="<?= e(url('rcsa')) ?>"><?= e(t('common.back')) ?></a></div></div>
<section class="card"><h2><?= e(t('rcsa.schedule_new')) ?></h2>
<form method="post" action="<?= e(url('rcsa/schedules')) ?>" class="form-grid" novalidate><?= csrf_field() ?>
<?= ff('select', 'template_id', 'rcsa.template', '', ['required' => true, 'options' => opts($templates, fn($x) => $x['title'])]) ?>
<?= ff('select', 'department_id', 'risk.department', '', ['required' => true, 'options' => opts($departments, fn($d) => lname($d))]) ?>
<?= ff('select', 'assessor_user_id', 'rcsa.assessor', '', ['required' => true, 'options' => opts($users, fn($x) => $x['full_name'])]) ?>
<?= ff('select', 'frequency', 'rcsa.frequency', 'annual', ['blank' => false, 'options' => opts(['quarterly', 'semiannual', 'annual'], fn($x) => t('enum.freq.' . $x), null)]) ?>
<?= ff('date', 'next_run', 'rcsa.next_run', date('Y-m-d', strtotime('+7 days')), ['required' => true, 'hint' => t('rcsa.next_run_hint')]) ?>
<?= ff('text', 'lead_days', 'rcsa.lead_days', '30', ['required' => true, 'extra' => ' inputmode="numeric"', 'hint' => t('rcsa.lead_days_hint')]) ?>
<div class="field wide"><button class="btn" type="submit"><?= e(t('rcsa.schedule_add')) ?></button></div></form><p class="muted"><?= e(t('rcsa.trigger_note')) ?></p></section>
<div class="table-wrap"><table class="data"><thead><tr><th><?= e(t('rcsa.template')) ?></th><th><?= e(t('risk.department')) ?></th><th><?= e(t('rcsa.assessor')) ?></th><th><?= e(t('rcsa.frequency')) ?></th><th><?= e(t('rcsa.next_run')) ?></th><th><?= e(t('rcsa.last_run')) ?></th><th></th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="7" class="empty"><?= e(t('common.no_results')) ?></td></tr><?php endif; ?>
<?php foreach ($rows as $s): ?><tr><td><?= e($s['template_title']) ?></td><td><?= e(lname($s, 'dept')) ?></td><td><?= e($s['assessor_name']) ?></td><td><?= e(t('enum.freq.' . $s['frequency'])) ?></td><td class="nowrap"><?= e(fdate($s['next_run'])) ?></td><td class="nowrap"><?= e(fdate($s['last_run_on']) ?: '—') ?></td>
<td><form method="post" class="inline" action="<?= e(url('rcsa/schedules/' . $s['id'] . '/toggle')) ?>"><?= csrf_field() ?><button class="btn small <?= $s['is_active'] ? 'danger' : '' ?>" type="submit"><?= e(t($s['is_active'] ? 'rcsa.pause' : 'rcsa.resume')) ?></button></form><?= $s['is_active'] ? '' : ' <span class="badge">' . e(t('common.inactive')) . '</span>' ?></td></tr><?php endforeach; ?></tbody></table></div>
