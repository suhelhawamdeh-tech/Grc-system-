<?php
/** @var array $r */
$canEdit = can('risk.edit') && $r['archived_at'] === null;
$today = date('Y-m-d');
$fieldLabels = ['title' => 'risk.title', 'description' => 'risk.description', 'category_id' => 'risk.category', 'causes' => 'risk.causes', 'consequences' => 'risk.consequences', 'department_id' => 'risk.department',
  'branch_id' => 'risk.branch', 'owner_user_id' => 'risk.owner', 'identified_date' => 'risk.identified_date', 'review_date' => 'risk.review_date', 'inh_likelihood' => 'risk.inh_likelihood', 'inh_impact' => 'risk.inh_impact',
  'est_financial_impact' => 'risk.est_financial_impact', 'controls_description' => 'risk.controls', 'control_owner_id' => 'risk.control_owner', 'control_assessment' => 'risk.control_assessment',
  'res_likelihood' => 'risk.res_likelihood', 'res_impact' => 'risk.res_impact', 'res_rationale' => 'risk.res_rationale', 'treatment_decision' => 'risk.treatment', 'treatment_notes' => 'risk.treatment_notes', 'status' => 'risk.status',
  'deadline' => 'action.deadline', 'action' => 'action.title', 'owner_user_id ' => 'action.owner'];
$disp = static function (string $k, $v): string {
    if ($v === null || $v === '') { return '—'; }
    return match ($k) {
        'category_id' => \App\Support\Ref::name('category', (int)$v), 'department_id' => \App\Support\Ref::name('department', (int)$v), 'branch_id' => \App\Support\Ref::name('branch', (int)$v),
        'owner_user_id', 'control_owner_id' => \App\Support\Ref::name('user', (int)$v),
        'control_assessment' => enum_label('control', (string)$v), 'treatment_decision' => enum_label('treatment', (string)$v), 'status' => enum_label(in_array($v, ['active', 'closed'], true) ? 'risk_status' : 'action_status', (string)$v),
        'est_financial_impact' => money($v),
        default => mb_strimwidth((string)$v, 0, 160, '…'),
    };
};
?>
<div class="page-head">
  <div><h1><?= e($r['risk_code']) ?> — <?= e($r['title']) ?></h1>
    <p class="sub"><?= e(enum_label('risk_status', $r['status'])) ?><?php if ($r['archived_at']): ?> · <span class="badge warn"><?= e(t('risk.archived')) ?> <?= e(fdate($r['archived_at'])) ?></span><?php endif; ?></p></div>
  <div class="actions">
    <a class="btn secondary" href="<?= e(url('risks')) ?>"><?= e(t('common.back')) ?></a>
    <?php if (can('export.run')): ?><a class="btn secondary" href="<?= e(url('risks/' . $r['id'] . '/print')) ?>" target="_blank" rel="noopener"><?= e(t('common.print')) ?></a><?php endif; ?>
    <?php if ($canEdit): ?><a class="btn" href="<?= e(url('risks/' . $r['id'] . '/edit')) ?>"><?= e(t('common.edit')) ?></a><?php endif; ?>
    <?php if (can('risk.archive')): ?>
      <form method="post" class="inline" action="<?= e(url('risks/' . $r['id'] . ($r['archived_at'] ? '/restore' : '/archive'))) ?>" data-confirm="<?= e(t($r['archived_at'] ? 'risk.confirm_restore' : 'risk.confirm_archive')) ?>"><?= csrf_field() ?>
        <button class="btn danger" type="submit"><?= e(t($r['archived_at'] ? 'common.restore' : 'common.archive')) ?></button></form>
    <?php endif; ?>
  </div>
</div>

<div class="grid cols-2">
<section class="card"><h2><?= e(t('risk.section_identification')) ?></h2><dl class="details">
  <dt><?= e(t('risk.category')) ?></dt><dd><?= e(lname($r, 'cat')) ?><?php if ($r['cat_kind'] === 'islamic'): ?> <span class="badge islamic"><?= e(t('enum.kind.islamic')) ?></span><?php endif; ?></dd>
  <dt><?= e(t('risk.department')) ?></dt><dd><?= e(lname($r, 'dept')) ?></dd>
  <dt><?= e(t('risk.branch')) ?></dt><dd><?= e(lname($r, 'branch') ?: '—') ?></dd>
  <dt><?= e(t('risk.owner')) ?></dt><dd><?= e($r['owner_name']) ?></dd>
  <dt><?= e(t('risk.identified_date')) ?></dt><dd><?= e(fdate($r['identified_date'])) ?></dd>
  <dt><?= e(t('risk.review_date')) ?></dt><dd><?= e(fdate($r['review_date']) ?: '—') ?><?php if ($r['review_date'] && $r['review_date'] < $today && $r['status'] === 'active'): ?> <span class="badge danger"><?= e(t('risk.overdue')) ?></span><?php endif; ?></dd>
  <dt><?= e(t('risk.description')) ?></dt><dd class="pre"><?= e($r['description']) ?></dd>
  <dt><?= e(t('risk.causes')) ?></dt><dd class="pre"><?= e($r['causes'] ?? '—') ?></dd>
  <dt><?= e(t('risk.consequences')) ?></dt><dd class="pre"><?= e($r['consequences'] ?? '—') ?></dd>
</dl></section>

<section class="card"><h2><?= e(t('risk.section_assessment')) ?></h2>
  <div class="table-wrap"><table class="data"><thead><tr><th></th><th><?= e(t('risk.likelihood')) ?></th><th><?= e(t('risk.impact')) ?></th><th><?= e(t('risk.score')) ?></th></tr></thead><tbody>
    <tr><th scope="row"><?= e(t('risk.inherent')) ?></th><td><?= (int)$r['inh_likelihood'] ?> — <?= e(lname(\App\Support\Ref::levels('likelihood')[(int)$r['inh_likelihood']] ?? null, 'label')) ?></td><td><?= (int)$r['inh_impact'] ?> — <?= e(lname(\App\Support\Ref::levels('impact')[(int)$r['inh_impact']] ?? null, 'label')) ?></td><td><?= (int)$r['inh_likelihood'] ?> × <?= (int)$r['inh_impact'] ?> = <?= band_chip((int)$r['inh_score']) ?></td></tr>
    <tr><th scope="row"><?= e(t('risk.residual')) ?></th><td><?= (int)$r['res_likelihood'] ?> — <?= e(lname(\App\Support\Ref::levels('likelihood')[(int)$r['res_likelihood']] ?? null, 'label')) ?></td><td><?= (int)$r['res_impact'] ?> — <?= e(lname(\App\Support\Ref::levels('impact')[(int)$r['res_impact']] ?? null, 'label')) ?></td><td><?= (int)$r['res_likelihood'] ?> × <?= (int)$r['res_impact'] ?> = <?= band_chip((int)$r['res_score']) ?></td></tr>
  </tbody></table></div>
  <dl class="details" style="margin-top:12px">
    <dt><?= e(t('risk.res_rationale')) ?></dt><dd class="pre"><?= e($r['res_rationale'] ?? '—') ?></dd>
    <dt><?= e(t('risk.est_financial_impact')) ?></dt><dd><?= $r['est_financial_impact'] !== null ? e(money($r['est_financial_impact'])) : '—' ?>
      <?php if ($suggest !== null && $suggest !== (int)$r['inh_impact']): ?><br><span class="badge warn"><?= e(t('risk.financial_mismatch', ['suggested' => $suggest, 'chosen' => (int)$r['inh_impact']])) ?></span><?php endif; ?></dd>
    <dt><?= e(t('risk.controls')) ?></dt><dd class="pre"><?= e($r['controls_description'] ?? '—') ?></dd>
    <dt><?= e(t('risk.control_owner')) ?></dt><dd><?= e($r['control_owner_id'] ? \App\Support\Ref::name('user', (int)$r['control_owner_id']) : '—') ?></dd>
    <dt><?= e(t('risk.control_assessment')) ?></dt><dd><?= e(enum_label('control', $r['control_assessment'])) ?></dd>
    <dt><?= e(t('risk.treatment')) ?></dt><dd><?= e(enum_label('treatment', $r['treatment_decision'])) ?><?php if ($r['treatment_notes']): ?> — <span class="pre"><?= e($r['treatment_notes']) ?></span><?php endif; ?></dd>
  </dl></section>
</div>

<?php if (isset($controls) && $controls !== null): $weak = false; $proven = false; foreach ($controls as $ctl) { if (in_array($ctl['last_result'], ['ineffective', 'partially_effective'], true) || ($ctl['next_test_due'] && $ctl['next_test_due'] < $today)) { $weak = true; } if ($ctl['last_result'] === 'effective') { $proven = true; } } ?>
<section class="card" id="controls"><h2><?= icon('flask') ?> <?= e(t('ctl.risk_panel')) ?></h2>
  <?php if (!$controls): ?><p class="muted"><?= e(t('ctl.risk_panel_empty')) ?></p><?php if (((int)$r['inh_score'] - (int)$r['res_score']) >= \App\Support\RiskFilter::MIN_CLAIMED_REDUCTION): ?><div class="msg error" role="alert"><?= e(t('ctl.overclaim', ['n' => (int)$r['inh_score'] - (int)$r['res_score']])) ?></div><?php endif; ?><?php else: ?>
  <?php if (((int)$r['inh_score'] - (int)$r['res_score']) >= \App\Support\RiskFilter::MIN_CLAIMED_REDUCTION && ($weak || !$proven)): ?><div class="msg error" role="alert"><?= e(t('ctl.overclaim', ['n' => (int)$r['inh_score'] - (int)$r['res_score']])) ?></div><?php endif; ?>
  <?php if ($weak): ?><div class="msg error" role="alert"><?= e(t('ctl.risk_warn')) ?></div><?php elseif (!$proven): ?><div class="msg info"><?= e(t('ctl.risk_unproven')) ?></div><?php endif; ?>
  <ul class="task-list"><?php foreach ($controls as $ctl): ?><li><a href="<?= e(url('controls/' . $ctl['id'])) ?>"><?= e($ctl['code']) ?> — <?= e($ctl['name']) ?></a>
    <span class="badge <?= $ctl['last_result'] === 'effective' ? 'ok' : ($ctl['last_result'] === 'ineffective' ? 'danger' : ($ctl['last_result'] ? 'warn' : '')) ?>"><?= e($ctl['last_result'] ? t('enum.rating.' . $ctl['last_result']) : t('ctl.never_tested')) ?></span></li><?php endforeach; ?></ul><?php endif; ?></section>
<?php endif; ?>

<?php if (($r['acceptance_status'] ?? 'none') !== 'none'): ?><div class="msg <?= $r['acceptance_status'] === 'approved' ? 'success' : ($r['acceptance_status'] === 'rejected' ? 'error' : 'info') ?>"><strong><?= e(t('risk.acceptance_' . $r['acceptance_status'])) ?></strong> — <?= e(t('risk.acceptance_note', ['n' => \App\Controllers\RiskController::level2Threshold()])) ?></div><?php endif; ?>
<section class="card" id="actions"><h2><?= e(t('action.title_plural')) ?></h2>
  <?php if (!$actions): ?><p class="muted"><?= e(t('action.none')) ?></p><?php else: ?>
  <div class="table-wrap"><table class="data"><thead><tr><th><?= e(t('action.title')) ?></th><th><?= e(t('action.owner')) ?></th><th><?= e(t('action.deadline')) ?></th><th><?= e(t('action.status')) ?></th><?php if ($canEdit): ?><th></th><?php endif; ?></tr></thead><tbody>
  <?php foreach ($actions as $a): $late = in_array($a['status'], ['not_started', 'in_progress'], true) && $a['deadline'] < $today; ?>
    <tr><td><?= e($a['title']) ?><?php if ($a['description']): ?><br><span class="muted"><?= e($a['description']) ?></span><?php endif; ?></td>
    <?php if ($canEdit): ?>
      <td colspan="3"><form method="post" action="<?= e(url('risks/' . $r['id'] . '/actions/' . $a['id'])) ?>" class="filters"><?= csrf_field() ?>
        <div class="field"><select name="owner_user_id" aria-label="<?= e(t('action.owner')) ?>"><?php foreach ($users as $x): ?><option value="<?= (int)$x['id'] ?>"<?= sel($a['owner_user_id'], $x['id']) ?>><?= e($x['full_name']) ?></option><?php endforeach; ?><?php if (!isset($users[(int)$a['owner_user_id']])): ?><option value="<?= (int)$a['owner_user_id'] ?>" selected><?= e($a['owner_name']) ?></option><?php endif; ?></select></div>
        <div class="field"><input type="date" name="deadline" value="<?= e($a['deadline']) ?>" aria-label="<?= e(t('action.deadline')) ?>"><?php if ($late): ?><span class="badge danger"><?= e(t('risk.overdue')) ?></span><?php endif; ?></div>
        <div class="field"><select name="status" aria-label="<?= e(t('action.status')) ?>"><?php foreach (['not_started', 'in_progress', 'completed', 'cancelled'] as $s): ?><option value="<?= $s ?>"<?= sel($a['status'], $s) ?>><?= e(enum_label('action_status', $s)) ?></option><?php endforeach; ?></select></div>
        <div class="field"><button class="btn small secondary" type="submit"><?= e(t('common.update')) ?></button></div></form></td><td></td>
    <?php else: ?>
      <td><?= e($a['owner_name']) ?></td><td class="nowrap"><?= e($a['deadline']) ?><?php if ($late): ?> <span class="badge danger"><?= e(t('risk.overdue')) ?></span><?php endif; ?></td><td><?= e(enum_label('action_status', $a['status'])) ?></td>
    <?php endif; ?></tr>
  <?php endforeach; ?></tbody></table></div><?php endif; ?>
  <?php if ($canEdit): ?>
  <details style="margin-top:12px"><summary><strong><?= e(t('action.add')) ?></strong></summary>
  <form method="post" action="<?= e(url('risks/' . $r['id'] . '/actions')) ?>" class="form-grid" style="margin-top:8px"><?= csrf_field() ?>
    <div class="field wide<?= field_error('title') ? ' has-error' : '' ?>"><label for="a_title"><?= e(t('action.title')) ?> *</label><input type="text" id="a_title" name="title" required value="<?= e(old('title')) ?>"><?= field_error('title') ?></div>
    <div class="field wide"><label for="a_desc"><?= e(t('action.description')) ?></label><textarea id="a_desc" name="description" style="min-height:60px"><?= e(old('description')) ?></textarea></div>
    <div class="field"><label for="a_owner"><?= e(t('action.owner')) ?> *</label><select id="a_owner" name="owner_user_id"><?php foreach ($users as $x): ?><option value="<?= (int)$x['id'] ?>"<?= sel(old('owner_user_id', $r['owner_user_id']), $x['id']) ?>><?= e($x['full_name']) ?></option><?php endforeach; ?></select></div>
    <div class="field<?= field_error('deadline') ? ' has-error' : '' ?>"><label for="a_deadline"><?= e(t('action.deadline')) ?> *</label><input type="date" id="a_deadline" name="deadline" required value="<?= e(old('deadline')) ?>"><?= field_error('deadline') ?></div>
    <div class="field"><label for="a_status"><?= e(t('action.status')) ?></label><select id="a_status" name="status"><?php foreach (['not_started', 'in_progress', 'completed', 'cancelled'] as $s): ?><option value="<?= $s ?>"><?= e(enum_label('action_status', $s)) ?></option><?php endforeach; ?></select></div>
    <div class="field"><button class="btn" type="submit"><?= e(t('action.add')) ?></button></div>
  </form></details><?php endif; ?>
</section>

<div class="grid cols-2">
<section class="card" id="attachments"><h2><?= e(t('upload.title')) ?></h2>
  <?php if (!$attachments): ?><p class="muted"><?= e(t('upload.empty')) ?></p><?php else: ?>
  <ul><?php foreach ($attachments as $a): ?><li><a href="<?= e(url('attachments/' . $a['id'])) ?>"><?= e($a['original_name']) ?></a> <span class="muted">(<?= e(round($a['size_bytes'] / 1024)) ?> KB · <?= e($a['full_name']) ?> · <?= e(fdate($a['created_at'])) ?>)</span>
    <?php if ($canEdit): ?><form method="post" class="inline" action="<?= e(url('attachments/' . $a['id'] . '/delete')) ?>" data-confirm="<?= e(t('upload.confirm_delete')) ?>"><?= csrf_field() ?><button class="btn small danger" type="submit"><?= e(t('common.delete')) ?></button></form><?php endif; ?></li><?php endforeach; ?></ul><?php endif; ?>
  <?php if ($canEdit): ?><form method="post" enctype="multipart/form-data" action="<?= e(url('risks/' . $r['id'] . '/attachments')) ?>"><?= csrf_field() ?>
    <div class="field"><label for="file"><?= e(t('upload.choose')) ?></label><input type="file" id="file" name="file" required accept=".pdf,.png,.jpg,.jpeg,.txt,.docx,.xlsx"><p class="hint"><?= e(t('upload.rules', ['max' => '5 MB', 'types' => 'pdf, png, jpg, txt, docx, xlsx'])) ?></p></div>
    <button class="btn secondary" type="submit"><?= e(t('upload.submit')) ?></button></form><?php endif; ?>
</section>
<section class="card"><h2><?= e(t('risk.linked_incidents')) ?></h2>
  <?php if (!$incidents): ?><p class="muted"><?= e(t('common.no_data')) ?></p><?php else: ?>
  <ul><?php foreach ($incidents as $i): ?><li><a href="<?= e(url('incidents/' . $i['id'])) ?>"><?= e($i['incident_code']) ?></a> — <?= e($i['title']) ?> <span class="muted">(<?= e($i['occurrence_date']) ?> · <?= e(money($i['net_loss'])) ?>)</span></li><?php endforeach; ?></ul><?php endif; ?>
</section>
</div>

<section class="card"><h2><?= e(t('risk.history')) ?></h2>
  <?php if (!$history): ?><p class="muted"><?= e(t('common.no_data')) ?></p><?php else: ?>
  <div class="table-wrap"><table class="data"><thead><tr><th><?= e(t('audit.time')) ?></th><th><?= e(t('audit.user')) ?></th><th><?= e(t('audit.action')) ?></th><th><?= e(t('risk.history_changes')) ?></th></tr></thead><tbody>
  <?php foreach ($history as $h): $ch = $h['changes'] ? json_decode($h['changes'], true) : []; ?>
    <tr><td class="nowrap"><?= e(fdatetime($h['created_at'])) ?></td><td><?= e($h['full_name'] ?? '—') ?></td><td><?= e(t('history.' . $h['event'])) ?></td>
    <td class="diff"><?php foreach ((array)$ch as $k => $pair): ?>
      <?php if (is_array($pair)): ?><div><strong><?= e(isset($fieldLabels[$k]) ? t($fieldLabels[$k]) : $k) ?>:</strong> <span class="old"><?= e($disp($k, $pair[0] ?? null)) ?></span> → <span class="new"><?= e($disp($k, $pair[1] ?? null)) ?></span></div>
      <?php else: ?><div><strong><?= e(isset($fieldLabels[$k]) ? t($fieldLabels[$k]) : $k) ?>:</strong> <?= e((string)$pair) ?></div><?php endif; ?>
    <?php endforeach; ?></td></tr>
  <?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>
