<?php /** @var array $c @var array $risks @var array $tests @var array $evidence @var array $linkable @var bool $canTest */
$res = static fn(?string $r) => $r ? '<span class="badge ' . ($r === 'effective' ? 'ok' : ($r === 'ineffective' ? 'danger' : 'warn')) . '">' . e(t('enum.rating.' . $r)) . '</span>' : '<span class="badge">' . e(t('ctl.never_tested')) . '</span>';
$late = $c['next_test_due'] && $c['next_test_due'] < date('Y-m-d') && !$c['archived_at']; ?>
<div class="page-head"><div><h1><?= icon('flask') ?> <?= e($c['code']) ?> — <?= e($c['name']) ?></h1>
  <p class="sub"><?= $res($c['last_result']) ?><?= $c['is_key'] ? ' <span class="badge islamic">' . e(t('ctl.key_badge')) . '</span>' : '' ?><?= $late ? ' <span class="badge danger">' . e(t('ctl.test_overdue')) . '</span>' : '' ?><?= $c['archived_at'] ? ' <span class="badge">' . e(t('common.archived')) . '</span>' : '' ?></p></div>
  <div class="actions"><a class="btn secondary" href="<?= e(url('controls')) ?>"><?= e(t('common.back')) ?></a>
  <?php if (can('control.manage')): ?><a class="btn" href="<?= e(url('controls/' . $c['id'] . '/edit')) ?>"><?= e(t('common.edit')) ?></a>
  <form method="post" class="inline" action="<?= e(url('controls/' . $c['id'] . '/archive')) ?>" data-confirm="<?= e(t($c['archived_at'] ? 'risk.confirm_restore' : 'risk.confirm_archive')) ?>"><?= csrf_field() ?><button class="btn danger" type="submit"><?= e(t($c['archived_at'] ? 'common.restore' : 'common.archive')) ?></button></form><?php endif; ?></div></div>
<div class="grid cols-2">
<section class="card"><h2><?= e(t('ctl.details')) ?></h2><dl class="details">
  <dt><?= e(t('ctl.department')) ?></dt><dd><?= e(lname($c, 'dept')) ?></dd><dt><?= e(t('ctl.owner')) ?></dt><dd><?= e($c['owner_name']) ?></dd>
  <dt><?= e(t('ctl.type')) ?></dt><dd><?= e(t('enum.ctype.' . $c['control_type'])) ?></dd><dt><?= e(t('ctl.nature')) ?></dt><dd><?= e(t('enum.cnature.' . $c['nature'])) ?></dd>
  <dt><?= e(t('ctl.frequency')) ?></dt><dd><?= e(t('enum.cfreq.' . $c['frequency'])) ?></dd><dt><?= e(t('ctl.test_frequency')) ?></dt><dd><?= e(t('enum.freq.' . $c['test_frequency'])) ?></dd>
  <dt><?= e(t('ctl.last_tested')) ?></dt><dd><?= e(fdate($c['last_tested_on']) ?: '—') ?></dd><dt><?= e(t('ctl.next_test')) ?></dt><dd><?= e(fdate($c['next_test_due']) ?: '—') ?></dd>
  <dt><?= e(t('ctl.description')) ?></dt><dd class="pre"><?= e($c['description'] ?? '—') ?></dd></dl></section>
<section class="card" id="risks"><h2><?= icon('link') ?> <?= e(t('ctl.linked_risks')) ?></h2>
<?php if (!$risks): ?><p class="muted"><?= e(t('ctl.no_risks')) ?></p><?php endif; ?>
<ul class="task-list"><?php foreach ($risks as $r): ?><li><a href="<?= e(url('risks/' . $r['id'])) ?>"><?= e($r['risk_code']) ?> — <?= e($r['title']) ?></a>
  <?php if (can('control.manage')): ?><form method="post" class="inline" action="<?= e(url('controls/' . $c['id'] . '/risks/' . $r['id'] . '/remove')) ?>" data-confirm="<?= e(t('ctl.confirm_unlink')) ?>"><?= csrf_field() ?><button class="btn small danger" type="submit"><?= e(t('ctl.unlink')) ?></button></form><?php endif; ?></li><?php endforeach; ?></ul>
<?php if ($linkable && !$c['archived_at']): ?><form method="post" action="<?= e(url('controls/' . $c['id'] . '/risks')) ?>" class="filters"><?= csrf_field() ?>
  <div class="field"><label for="risk_id"><?= e(t('ctl.link_risk')) ?></label><select id="risk_id" name="risk_id" required><option value=""><?= e(t('common.select')) ?></option><?php foreach ($linkable as $r): ?><option value="<?= (int)$r['id'] ?>"><?= e($r['risk_code'] . ' — ' . mb_strimwidth($r['title'], 0, 70, '…')) ?></option><?php endforeach; ?></select></div>
  <div class="field"><button class="btn secondary" type="submit"><?= e(t('ctl.link')) ?></button></div></form><?php endif; ?></section>
</div>
<section class="card" id="tests"><h2><?= icon('flask') ?> <?= e(t('ctl.tests')) ?></h2>
<?php if (!$tests): ?><p class="muted"><?= e(t('ctl.no_tests')) ?></p><?php else: ?>
<div class="table-wrap"><table class="data"><thead><tr><th><?= e(t('ctl.test_date')) ?></th><th><?= e(t('ctl.test_type')) ?></th><th><?= e(t('ctl.tester')) ?></th><th class="num"><?= e(t('ctl.sample_size')) ?></th><th class="num"><?= e(t('ctl.exceptions')) ?></th><th><?= e(t('ctl.result')) ?></th><th><?= e(t('ctl.conclusion')) ?></th><th><?= e(t('comp.evidence')) ?></th></tr></thead><tbody>
<?php foreach ($tests as $t): ?><tr><td class="nowrap"><?= e(fdate($t['test_date'])) ?></td><td><?= e(t('enum.ttype.' . $t['test_type'])) ?></td><td><?= e($t['tester_name']) ?></td>
<td class="num"><?= $t['sample_size'] !== null ? (int)$t['sample_size'] : '—' ?></td><td class="num"><?= $t['exceptions'] !== null ? (int)$t['exceptions'] : '—' ?></td><td><?= $res($t['result']) ?></td><td class="pre"><?= e($t['conclusion'] ?? '—') ?></td>
<td><?php foreach ($evidence[(int)$t['id']] ?? [] as $a): ?><a href="<?= e(url('attachments/' . $a['id'])) ?>"><?= e($a['original_name']) ?></a><br><?php endforeach; ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
<?php if ($canTest): ?><h3 id="test" style="margin-top:16px"><?= e(t('ctl.record_test')) ?></h3>
<form method="post" enctype="multipart/form-data" action="<?= e(url('controls/' . $c['id'] . '/tests')) ?>" novalidate><?= csrf_field() ?><div class="form-grid">
<?= ff('select', 'test_type', 'ctl.test_type', 'operating', ['blank' => false, 'options' => ['design' => t('enum.ttype.design'), 'operating' => t('enum.ttype.operating')], 'hint' => t('ctl.test_type_hint')]) ?>
<?= ff('date', 'test_date', 'ctl.test_date', date('Y-m-d'), ['required' => true, 'extra' => ' max="' . date('Y-m-d') . '"']) ?>
<?= ff('text', 'sample_size', 'ctl.sample_size', '', ['extra' => ' inputmode="numeric"', 'hint' => t('ctl.sample_hint')]) ?>
<?= ff('text', 'exceptions', 'ctl.exceptions', '', ['extra' => ' inputmode="numeric"', 'hint' => t('ctl.exceptions_hint')]) ?>
<?= ff('select', 'result', 'ctl.result', '', ['required' => true, 'options' => opts(\App\Controllers\ControlController::RESULTS, fn($x) => t('enum.rating.' . $x), null)]) ?>
<?= ff('textarea', 'conclusion', 'ctl.conclusion', '', ['wide' => true, 'hint' => t('ctl.conclusion_hint')]) ?>
<div class="field wide"><label for="f_file"><?= e(t('ctl.evidence_file')) ?></label><input type="file" id="f_file" name="file"><p class="hint"><?= e(t('ctl.evidence_hint')) ?></p><?= field_error('file') ?></div></div>
<button class="btn" type="submit"><?= e(t('ctl.save_test')) ?></button></form>
<?php elseif (can('control.test') && (int)$c['owner_user_id'] === (int)(current_user()['id'] ?? 0)): ?><p class="msg info"><?= e(t('ctl.err_independent')) ?></p><?php endif; ?></section>
