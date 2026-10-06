<?php $canEdit = can('incident.edit') && $i['archived_at'] === null; ?>
<div class="page-head">
  <div><h1><?= e($i['incident_code']) ?> — <?= e($i['title']) ?></h1>
    <p class="sub"><?= e(enum_label('event', $i['event_type'])) ?> · <?= e(enum_label('incident_status', $i['status'])) ?><?php if ($i['archived_at']): ?> · <span class="badge warn"><?= e(t('risk.archived')) ?></span><?php endif; ?></p></div>
  <div class="actions">
    <a class="btn secondary" href="<?= e(url('incidents')) ?>"><?= e(t('common.back')) ?></a>
    <?php if ($canEdit): ?><a class="btn" href="<?= e(url('incidents/' . $i['id'] . '/edit')) ?>"><?= e(t('common.edit')) ?></a><?php endif; ?>
    <?php if (can('incident.archive')): ?><form method="post" class="inline" action="<?= e(url('incidents/' . $i['id'] . ($i['archived_at'] ? '/restore' : '/archive'))) ?>" data-confirm="<?= e(t($i['archived_at'] ? 'risk.confirm_restore' : 'risk.confirm_archive')) ?>"><?= csrf_field() ?><button class="btn danger" type="submit"><?= e(t($i['archived_at'] ? 'common.restore' : 'common.archive')) ?></button></form><?php endif; ?>
  </div>
</div>
<div class="grid cols-2">
<section class="card"><h2><?= e(t('incident.section_event')) ?></h2><dl class="details">
  <dt><?= e(t('incident.occurrence_date')) ?></dt><dd><?= e($i['occurrence_date']) ?></dd>
  <dt><?= e(t('incident.discovery_date')) ?></dt><dd><?= e($i['discovery_date']) ?></dd>
  <dt><?= e(t('incident.category')) ?></dt><dd><?= e(lname($i, 'cat')) ?></dd>
  <dt><?= e(t('incident.department')) ?></dt><dd><?= e(lname($i, 'dept')) ?></dd>
  <dt><?= e(t('incident.branch')) ?></dt><dd><?= e(lname($i, 'branch') ?: '—') ?></dd>
  <dt><?= e(t('incident.reported_by')) ?></dt><dd><?= e($i['reporter_name']) ?></dd>
  <dt><?= e(t('incident.description')) ?></dt><dd class="pre"><?= e($i['description']) ?></dd></dl></section>
<section class="card"><h2><?= e(t('incident.section_loss')) ?></h2><dl class="details">
  <dt><?= e(t('incident.gross_loss')) ?></dt><dd class="mono"><?= e(money($i['gross_loss'])) ?></dd>
  <dt><?= e(t('incident.recoveries')) ?></dt><dd class="mono"><?= e(money($i['recoveries'])) ?></dd>
  <dt><?= e(t('incident.net_loss')) ?></dt><dd class="mono"><strong><?= e(money($i['net_loss'])) ?></strong></dd>
  <dt><?= e(t('incident.basel_type')) ?></dt><dd><?= e(lname(['name_en' => $meta['bt_en'], 'name_ar' => $meta['bt_ar']]) ?: '—') ?></dd><dt><?= e(t('incident.business_line')) ?></dt><dd><?= e(lname(['name_en' => $meta['bl_en'], 'name_ar' => $meta['bl_ar']]) ?: '—') ?></dd>
  <dt><?= e(t('incident.product')) ?> / <?= e(t('incident.system_name')) ?></dt><dd><?= e(($i['product'] ?? '—') . ' / ' . ($i['system_name'] ?? '—')) ?></dd>
  <dt><?= e(t('incident.recovery_source')) ?></dt><dd><?= $i['recovery_source'] ? e(t('enum.rsrc.' . $i['recovery_source'])) : '—' ?></dd><dt><?= e(t('incident.non_financial')) ?></dt><dd class="pre"><?= e($i['non_financial_impact'] ?? '—') ?></dd>
  <dt><?= e(t('incident.root_cause')) ?></dt><dd class="pre"><?= e($i['root_cause'] ?? '—') ?></dd>
  <dt><?= e(t('incident.corrective_actions')) ?></dt><dd class="pre"><?= e($i['corrective_actions'] ?? '—') ?></dd></dl></section>
</div>
<div class="grid cols-2">
<section class="card"><h2><?= e(t('incident.linked_risks')) ?></h2>
  <?php if (!$risks): ?><p class="muted"><?= e(t('common.no_data')) ?></p><?php else: ?><ul><?php foreach ($risks as $r): ?><li><a href="<?= e(url('risks/' . $r['id'])) ?>"><?= e($r['risk_code']) ?></a> — <?= e($r['title']) ?></li><?php endforeach; ?></ul><?php endif; ?></section>
<section class="card" id="attachments"><h2><?= e(t('upload.title')) ?></h2>
  <?php if (!$attachments): ?><p class="muted"><?= e(t('upload.empty')) ?></p><?php else: ?><ul><?php foreach ($attachments as $a): ?><li><a href="<?= e(url('attachments/' . $a['id'])) ?>"><?= e($a['original_name']) ?></a> <span class="muted">(<?= e(round($a['size_bytes'] / 1024)) ?> KB · <?= e($a['full_name']) ?> · <?= e(fdate($a['created_at'])) ?>)</span>
    <?php if ($canEdit): ?><form method="post" class="inline" action="<?= e(url('attachments/' . $a['id'] . '/delete')) ?>" data-confirm="<?= e(t('upload.confirm_delete')) ?>"><?= csrf_field() ?><button class="btn small danger" type="submit"><?= e(t('common.delete')) ?></button></form><?php endif; ?></li><?php endforeach; ?></ul><?php endif; ?>
  <?php if ($canEdit): ?><form method="post" enctype="multipart/form-data" action="<?= e(url('incidents/' . $i['id'] . '/attachments')) ?>"><?= csrf_field() ?>
    <div class="field"><label for="file"><?= e(t('upload.choose')) ?></label><input type="file" id="file" name="file" required accept=".pdf,.png,.jpg,.jpeg,.txt,.docx,.xlsx"><p class="hint"><?= e(t('upload.rules', ['max' => '5 MB', 'types' => 'pdf, png, jpg, txt, docx, xlsx'])) ?></p></div>
    <button class="btn secondary" type="submit"><?= e(t('upload.submit')) ?></button></form><?php endif; ?></section>
</div>
<?php if ($i['escalated_at']): ?><div class="msg error" role="alert"><?= e(t('incident.escalated_note', ['at' => fdatetime($i['escalated_at'])])) ?></div><?php endif; ?>
<?php if (count($similar) >= 2): ?><section class="card"><h2><?= icon('alert') ?> <?= e(t('incident.recurrence')) ?></h2><p><?= e(t('incident.recurrence_note', ['n' => count($similar)])) ?></p>
<ul class="task-list"><?php foreach ($similar as $x): ?><li><a href="<?= e(url('incidents/' . $x['id'])) ?>"><?= e($x['incident_code'] . ' — ' . $x['title']) ?></a><span class="badge"><?= e(fdate($x['occurrence_date'])) ?></span></li><?php endforeach; ?></ul></section><?php endif; ?>
