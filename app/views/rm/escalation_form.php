<?php /** @var array $e */ ?>
<div class="page-head"><h1><?= e($title) ?></h1></div>
<form method="post" class="card" action="<?= e(url('rm/escalations')) ?>" novalidate><?= csrf_field() ?><p class="muted"><?= e(t('rm.escalation_form_hint')) ?></p><div class="form-grid">
<?= ff('text', 'title', 'rm.esc_subject', '', ['required' => true, 'wide' => true]) ?>
<?= ff('select', 'source_type', 'rm.source_type', $e['source_type'], ['blank' => false, 'options' => opts(\App\Support\RiskFunction::ESC_SOURCES, fn($x) => t('enum.rmsrc.' . $x), null) ?: []]) ?>
<?= ff('select', 'escalated_to', 'rm.escalated_to', $e['escalated_to'], ['blank' => false, 'options' => opts(\App\Support\RiskFunction::ESC_TO, fn($x) => t('enum.rmescto.' . $x), null) ?: []]) ?>
<?= ff('select', 'section', 'docs.section', '', ['options' => opts(\App\Support\Framework::SECTIONS, fn($x) => t('enum.rmsec.' . $x), null) ?: []]) ?>
<?= ff('textarea', 'detail', 'rm.esc_detail', '', ['required' => true, 'wide' => true, 'hint' => t('rm.esc_detail_hint')]) ?>
<div class="field wide"><label class="check"><input type="checkbox" name="ceo_excluded" value="1"> <?= e(t('rm.ceo_excluded')) ?></label><p class="hint"><?= e(t('rm.ceo_excluded_hint')) ?></p></div>
<?= ff('text', 'ceo_excluded_reason', 'rm.ceo_excluded_reason', '', ['wide' => true]) ?>
</div><div class="actions"><button class="btn danger" type="submit"><?= e(t('rm.escalate')) ?></button><a class="btn secondary" href="<?= e(url('rm/escalations')) ?>"><?= e(t('common.cancel')) ?></a></div></form>
