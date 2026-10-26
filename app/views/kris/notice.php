<?php /** @var array $k @var array $b @var array $trend */ $u = ' ' . $k['unit']; $red = in_array($b['level'], ['red', 'critical'], true); ?>
<h2><?= e(t('raf.notice_title')) ?></h2>
<table class="data kv"><tbody>
<tr><th><?= e(t('raf.notice_no')) ?></th><td dir="ltr">RAF-<?= (int)$b['id'] ?></td><th><?= e(t('rm.detected_at')) ?></th><td><?= e(fdate($b['reading_date'])) ?></td></tr>
<tr><th><?= e(t('raf.metric')) ?></th><td><?= e($k['code'] . ' — ' . $k['name']) ?></td><th><?= e(t('raf.category')) ?></th><td><?= e($k['raf_category'] ? t('enum.rafcat.' . $k['raf_category']) : '—') ?></td></tr>
<tr><th><?= e(t('raf.state')) ?></th><td colspan="3"><?= $red ? '☑ ' . e(t('raf.state_red')) . ' ☐ ' . e(t('raf.state_amber')) : '☐ ' . e(t('raf.state_red')) . ' ☑ ' . e(t('raf.state_amber')) ?></td></tr>
<tr><th><?= e(t('raf.current_value')) ?></th><td><?= e(\App\Core\Money::plain($b['value']) . $u) ?></td><th><?= e(t('raf.appetite_value')) ?></th><td><?= $k['appetite_value'] !== null ? e(\App\Core\Money::plain($k['appetite_value']) . $u) : '—' ?></td></tr>
<tr><th><?= e(t('kri.amber')) ?></th><td><?= e(\App\Core\Money::plain($k['amber_threshold']) . $u) ?></td><th><?= e(t('kri.red')) ?></th><td><?= e(\App\Core\Money::plain($k['red_threshold']) . $u) ?></td></tr>
<tr><th><?= e(t('raf.trend_last3')) ?></th><td colspan="3"><?= e(implode(' ← ', array_map(fn($r) => \App\Core\Money::plain($r['value']), $trend))) ?></td></tr>
<tr><th><?= e(t('raf.causes')) ?></th><td colspan="3" class="pre"><?= e($b['justification'] ?? '—') ?></td></tr>
<tr><th><?= e(t('raf.plan')) ?></th><td colspan="3" class="pre"><?= e($b['action_plan'] ?? '—') ?></td></tr>
<tr><th><?= e(t('raf.owner_department')) ?></th><td><?= e(lname($k, 'dept')) ?></td><th><?= e(t('raf.escalation')) ?></th><td><?= e($red ? t('raf.escalate_red') . ((int)$k['is_regulatory'] ? ' + ' . t('raf.escalate_cbl') : '') : t('raf.escalate_amber')) ?></td></tr></tbody></table>
<div class="card notice"><p><strong><?= e(t('raf.notice_warning')) ?></strong></p></div>
<table class="data signatures"><thead><tr><th><?= e(t('rm.section_head')) ?></th><th><?= e(t('rm.director')) ?></th></tr></thead><tbody><tr><td><?= e(t('rm.signature')) ?>: ……</td><td><?= e(t('rm.signature')) ?>: ……</td></tr></tbody></table>
