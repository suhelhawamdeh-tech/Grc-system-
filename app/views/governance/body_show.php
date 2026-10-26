<?php /** @var array $b @var array $members @var array $meetings @var array $candidates */
$chairs = count(array_filter($members, fn($m) => $m['member_role'] === 'chair')); ?>
<div class="page-head"><div><h1><?= icon('users') ?> <?= e($b['code']) ?> — <?= e($b['name']) ?></h1><p class="sub"><?= e(t('enum.committee.' . $b['body_type'])) ?> · <?= e(t('gov.b_min')) ?>: <?= (int)$b['min_meetings_per_year'] ?> · <?= e(t('gov.b_quorum')) ?>: <?= (int)$b['quorum_percent'] ?>%</p></div>
  <div class="actions"><a class="btn secondary" href="<?= e(url('governance')) ?>"><?= e(t('common.back')) ?></a><?php if (can('governance.manage')): ?><a class="btn" href="<?= e(url('governance/' . $b['id'] . '/edit')) ?>"><?= e(t('common.edit')) ?></a><?php endif; ?></div></div>
<?php if ($b['purpose']): ?><section class="card"><p class="pre"><?= e($b['purpose']) ?></p></section><?php endif; ?>
<div class="grid cols-2">
<section class="card"><h2><?= e(t('gov.members')) ?> <span class="count badge"><?= count($members) ?></span></h2>
<?php if (!$chairs): ?><div class="msg error"><?= e(t('gov.no_chair_note')) ?></div><?php endif; ?>
<ul class="task-list"><?php foreach ($members as $m): ?><li><span><?= e($m['full_name']) ?> <span class="badge <?= $m['member_role'] === 'chair' ? 'islamic' : '' ?>"><?= e(t('enum.grole.' . $m['member_role'])) ?></span></span>
<?php if (can('governance.manage')): ?><form method="post" class="inline" action="<?= e(url('governance/' . $b['id'] . '/members/' . $m['user_id'] . '/remove')) ?>" data-confirm="<?= e(t('gov.confirm_remove')) ?>"><?= csrf_field() ?><button class="btn small danger" type="submit"><?= e(t('ctl.unlink')) ?></button></form><?php endif; ?></li><?php endforeach; ?></ul>
<?php if (can('governance.manage') && $b['is_active']): ?><form method="post" action="<?= e(url('governance/' . $b['id'] . '/members')) ?>" class="filters"><?= csrf_field() ?>
<div class="field"><label for="user_id"><?= e(t('gov.member')) ?></label><select id="user_id" name="user_id" required><option value=""><?= e(t('common.select')) ?></option><?php foreach ($candidates as $u): ?><option value="<?= (int)$u['id'] ?>"><?= e($u['full_name']) ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="member_role"><?= e(t('gov.role')) ?></label><select id="member_role" name="member_role"><?php foreach (\App\Controllers\GovernanceController::ROLES as $r): ?><option value="<?= $r ?>"><?= e(t('enum.grole.' . $r)) ?></option><?php endforeach; ?></select></div>
<div class="field"><button class="btn secondary" type="submit"><?= e(t('gov.add_member')) ?></button></div></form><?php endif; ?></section>
<section class="card"><h2><?= e(t('gov.meetings')) ?></h2>
<?php if (can('governance.manage') && $b['is_active']): ?><form method="post" action="<?= e(url('governance/' . $b['id'] . '/meetings')) ?>" novalidate><?= csrf_field() ?>
<?= ff('date', 'meeting_date', 'gov.m_date', date('Y-m-d', strtotime('+14 days')), ['required' => true]) ?><?= ff('textarea', 'agenda', 'gov.m_agenda', '', ['extra' => ' rows="3"']) ?>
<button class="btn secondary" type="submit"><?= e(t('gov.schedule')) ?></button></form><?php endif; ?>
<ul class="task-list" style="margin-top:12px"><?php foreach ($meetings as $g): ?><li><a href="<?= e(url('governance/meetings/' . $g['id'])) ?>"><?= e(fdate($g['meeting_date'])) ?></a><span class="badge <?= $g['status'] === 'held' ? ($g['quorum_met'] ? 'ok' : 'danger') : ($g['status'] === 'cancelled' ? '' : 'warn') ?>"><?= e(t('enum.mstatus.' . $g['status'])) ?><?= $g['status'] === 'held' ? ' · ' . (int)$g['present_count'] . '/' . (int)$g['members_count'] : '' ?></span></li><?php endforeach; ?></ul></section></div>
