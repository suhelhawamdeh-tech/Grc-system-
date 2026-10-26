<div class="page-head"><h1><?= e($title) ?></h1></div>
<form method="post" class="card" style="max-width:720px" action="<?= e(url('rcsa/launch')) ?>" novalidate><?= csrf_field() ?><div class="form-grid">
<?= ff('select', 'template_id', 'rcsa.template', '', ['required' => true, 'options' => opts($templates, fn($x) => $x['title'])]) ?>
<?= ff('select', 'period_id', 'dash.period', '', ['options' => opts($periods, fn($x) => lname($x))]) ?>
<?= ff('select', 'department_id', 'risk.department', '', ['required' => true, 'options' => opts($departments, fn($x) => lname($x))]) ?>
<?= ff('select', 'branch_id', 'risk.branch', '', ['options' => opts($branches, fn($x) => lname($x))]) ?>
<?= ff('select', 'assessor_user_id', 'rcsa.assessor', '', ['required' => true, 'options' => opts($users, fn($x) => $x['full_name'])]) ?>
<?= ff('date', 'due_date', 'rcsa.due_date', date('Y-m-d', strtotime('+30 days')), ['required' => true]) ?></div>
<div class="actions"><button class="btn" type="submit"><?= e(t('rcsa.launch')) ?></button><a class="btn secondary" href="<?= e(url('rcsa')) ?>"><?= e(t('common.cancel')) ?></a></div></form>
