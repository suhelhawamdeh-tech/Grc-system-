<?php /** @var array $b @var ?float $excess */ $exc = $b['kind'] === 'exception'; ?>
<h2><?= e(t($exc ? 'rm.exception_title' : 'rm.notice_title')) ?></h2>
<table class="data kv"><tbody>
<tr><th><?= e(t('rm.notice_no')) ?></th><td dir="ltr"><?= e($b['code']) ?></td><th><?= e(t($exc ? 'rm.requested_at' : 'rm.detected_at')) ?></th><td><?= e(fdatetime($b['detected_at'])) ?></td></tr>
<tr><th><?= e(t('rm.limit_type')) ?></th><td><?= e(t('enum.rmlt.' . $b['limit_type'])) ?> · <?= e(t('enum.rmtier.' . $b['limit_tier'])) ?></td><th><?= e(t('rm.limit_name')) ?></th><td><?= e($b['limit_name']) ?></td></tr>
<tr><th><?= e(t('rm.breach_subject')) ?></th><td colspan="3"><?= e($b['subject'] ?? '—') ?></td></tr>
<tr><th><?= e(t('rm.limit_value')) ?></th><td><?= e($b['limit_value'] ?? '—') ?> <?= e($b['unit'] ?? '') ?></td><th><?= e(t('rm.actual_value')) ?></th><td><?= e($b['actual_value'] ?? '—') ?> <?= e($b['unit'] ?? '') ?> (<?= $excess !== null ? e($excess) . '%' : '—' ?>)</td></tr>
<tr><th><?= e(t($exc ? 'rm.exception_reason' : 'rm.cause')) ?></th><td colspan="3" class="pre"><?= e($b['cause'] ?? '—') ?></td></tr>
<tr><th><?= e(t($exc ? 'rm.requesting_department' : 'rm.owner_department')) ?></th><td><?= e(lname(['name_en' => $b['dept_en'], 'name_ar' => $b['dept_ar']])) ?></td><th><?= e(t($exc ? 'rm.expires_on' : 'rm.plan_due')) ?></th><td><?= e(fdate($exc ? $b['expires_on'] : $b['plan_due']) ?: '—') ?></td></tr>
<tr><th><?= e(t('rm.escalate_chair')) ?></th><td colspan="3"><?= e(($b['limit_type'] === 'regulatory' || $b['material']) && !$exc ? t('rm.escalate_yes') : t('common.no')) ?></td></tr></tbody></table>
<div class="card notice"><p><strong><?= e(t($exc ? 'rm.exception_warning' : 'rm.notice_warning')) ?></strong></p></div>
<table class="data signatures"><thead><tr><th><?= e(t('rm.section_head')) ?></th><th><?= e(t('rm.director')) ?></th></tr></thead><tbody><tr><td><?= e(t('rm.signature')) ?>: ……</td><td><?= e(t('rm.signature')) ?>: ……</td></tr></tbody></table>
