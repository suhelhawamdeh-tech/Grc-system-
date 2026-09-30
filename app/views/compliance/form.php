<div class="page-head"><h1><?= e($title) ?></h1></div>
<form method="post" class="card" action="<?= e($existing ? url('compliance/' . $existing['id']) : url('compliance')) ?>" novalidate><?= csrf_field() ?><div class="form-grid">
<?= ff('text', 'title', 'comp.name', $o['title'] ?? '', ['required' => true, 'wide' => true]) ?>
<?= ff('text', 'source', 'comp.source', $o['source'] ?? '', ['required' => true, 'wide' => true, 'hint' => t('comp.source_hint')]) ?>
<?= ff('textarea', 'description', 'comp.description', $o['description'] ?? '', ['wide' => true]) ?>
<?= ff('select', 'department_id', 'comp.department', $o['department_id'] ?? '', ['required' => true, 'options' => opts($lists['departments'], fn($d) => lname($d))]) ?>
<?= ff('select', 'owner_user_id', 'comp.owner', $o['owner_user_id'] ?? '', ['required' => true, 'options' => opts($lists['users'], fn($d) => $d['full_name'])]) ?>
<?= ff('select', 'frequency', 'comp.frequency', $o['frequency'] ?? 'annual', ['blank' => false, 'options' => opts(['once', 'monthly', 'quarterly', 'semiannual', 'annual'], fn($x) => t('enum.freq.' . $x), null) ?: []]) ?>
<?= ff('date', 'due_date', 'comp.due_date', $o['due_date'] ?? '', ['required' => true]) ?>
</div><div class="actions"><button class="btn" type="submit"><?= e(t('common.save')) ?></button><a class="btn secondary" href="<?= e(url('compliance')) ?>"><?= e(t('common.cancel')) ?></a></div></form>
