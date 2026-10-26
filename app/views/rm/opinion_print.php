<?php /** @var array $o */ ?>
<h2><?= e(t('rm.memo_title')) ?></h2>
<table class="data kv"><tbody>
<tr><th><?= e(t('rm.memo_no')) ?></th><td dir="ltr"><?= e($o['code']) ?></td><th><?= e(t('rm.received_on')) ?></th><td><?= e(fdate($o['received_on'])) ?></td></tr>
<tr><th><?= e(t('rm.requesting_department')) ?></th><td><?= e($o['dept_ar'] ? lname(['name_en' => $o['dept_en'], 'name_ar' => $o['dept_ar']]) : '—') ?></td><th><?= e(t('rm.request_ref')) ?></th><td dir="ltr"><?= e($o['request_ref'] ?? '—') ?></td></tr>
<tr><th><?= e(t('docs.section')) ?></th><td><?= e(t('enum.rmsec.' . $o['section'])) ?></td><th><?= e(t('rm.subject_type')) ?></th><td><?= e(t('enum.rmsubj.' . $o['subject_type'])) ?></td></tr></tbody></table>
<h3><?= e(t('rm.subject')) ?></h3><p><strong><?= e($o['title']) ?></strong></p>
<h3><?= e(t('rm.summary')) ?></h3><p class="pre"><?= e($o['summary']) ?></p>
<?php if ($o['key_risks']): ?><h3><?= e(t('rm.key_risks')) ?></h3><p class="pre"><?= e($o['key_risks']) ?></p><?php endif; ?>
<?php if ($o['limits_impact']): ?><h3><?= e(t('rm.limits_impact')) ?></h3><p class="pre"><?= e($o['limits_impact']) ?></p><?php endif; ?>
<h3><?= e(t('rm.opinion')) ?>: <?= e($o['opinion'] ? t('enum.rmop.' . $o['opinion']) : '—') ?></h3>
<?php if ($o['conditions']): ?><p class="pre"><strong><?= e(t('rm.conditions')) ?>:</strong> <?= e($o['conditions']) ?></p><?php endif; ?>
<p class="pre"><strong><?= e(t('rm.rationale')) ?>:</strong> <?= e($o['rationale'] ?? '—') ?></p>
<div class="card notice"><p><strong><?= e(t('rm.memo_warning')) ?></strong></p></div>
<table class="data signatures"><thead><tr><th><?= e(t('rm.prepared_by')) ?></th><th><?= e(t('rm.reviewed_by')) ?></th><th><?= e(t('rm.issued_by')) ?></th></tr></thead><tbody>
<tr><td><?= e($o['prep_name']) ?></td><td><?= e($o['rev_name'] ?? '') ?></td><td><?= e($o['iss_name'] ?? '') ?></td></tr><tr><td><?= e(t('rm.signature')) ?>: ……</td><td><?= e(t('rm.signature')) ?>: ……</td><td><?= e(t('rm.signature')) ?>: ……</td></tr></tbody></table>
