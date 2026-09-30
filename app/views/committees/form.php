<div class="page-head"><h1><?= e($title) ?></h1></div>
<form method="post" class="card" action="<?= e($existing ? url('committees/' . $existing['id']) : url('committees')) ?>" novalidate><?= csrf_field() ?><div class="form-grid">
<?= ff('select', 'committee', 'cmt.committee', $c['committee'] ?? '', ['required' => true, 'options' => opts(\App\Controllers\CommitteeController::COMMITTEES, fn($x) => t('enum.committee.' . $x), null) ?: []]) ?>
<?= ff('date', 'meeting_date', 'cmt.meeting_date', $c['meeting_date'] ?? '', ['required' => true]) ?>
<?= ff('text', 'title', 'cmt.name', $c['title'] ?? '', ['required' => true, 'wide' => true]) ?>
<?= ff('textarea', 'decision_text', 'cmt.decision', $c['decision_text'] ?? '', ['required' => true, 'wide' => true, 'extra' => ' style="min-height:160px"']) ?>
<?= ff('select', 'status', 'cmt.status', $c['status'] ?? 'open', ['blank' => false, 'options' => opts(['open', 'implemented', 'superseded'], fn($x) => t('enum.dstatus.' . $x), null) ?: []]) ?>
</div><div class="actions"><button class="btn" type="submit"><?= e(t('common.save')) ?></button><a class="btn secondary" href="<?= e($existing ? url('committees/' . $existing['id']) : url('committees')) ?>"><?= e(t('common.cancel')) ?></a></div></form>
