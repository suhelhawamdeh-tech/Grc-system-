<?php /** @var array $runs @var array $cfg @var bool $hasSecret @var array $maps */ ?>
<div class="page-head"><div><h1><?= icon('link') ?> <?= e(t('t24.title')) ?></h1><p class="sub"><?= e(t('t24.subtitle')) ?></p></div></div>
<div class="card notice"><p><?= e(t('t24.notice')) ?></p></div>
<div class="grid cols-2">
<section class="card" id="load"><h2><?= e(t('t24.load_file')) ?></h2><p class="muted"><?= e(t('t24.load_file_hint')) ?></p>
<form method="post" enctype="multipart/form-data" action="<?= e(url('admin/integrations/upload')) ?>" novalidate><?= csrf_field() ?>
<?= ff('select', 'dataset', 't24.dataset', '', ['required' => true, 'blank' => false, 'options' => ['customers' => t('t24.ds_customers'), 'gl' => t('t24.ds_gl')]]) ?>
<div class="field"><label for="file"><?= e(t('t24.file')) ?> *</label><input type="file" id="file" name="file" accept=".csv,.txt,.json" required><p class="hint"><?= e(t('t24.file_hint')) ?></p></div>
<button class="btn gold" type="submit"><?= e(t('t24.load')) ?></button></form>
<p class="small"><?= e(t('t24.templates')) ?> <a href="<?= e(url('admin/integrations/sample/customers')) ?>"><?= e(t('t24.ds_customers')) ?></a> · <a href="<?= e(url('admin/integrations/sample/gl')) ?>"><?= e(t('t24.ds_gl')) ?></a></p></section>
<section class="card"><h2><?= e(t('t24.load_api')) ?></h2><p class="muted"><?= e($cfg['base'] !== '' ? t('t24.load_api_hint', ['u' => $cfg['base']]) : t('t24.err_no_api')) ?></p>
<?php if ($cfg['base'] !== ''): ?><form method="post" action="<?= e(url('admin/integrations/test')) ?>" class="inline"><?= csrf_field() ?><button class="btn secondary" type="submit"><?= e(t('t24.test')) ?></button></form>
<?php foreach (['customers', 'gl'] as $ds): ?> <form method="post" action="<?= e(url('admin/integrations/pull')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="dataset" value="<?= e($ds) ?>"><button class="btn" type="submit"><?= e(t('t24.pull', ['d' => t(sprintf('t24.ds_%s', $ds))])) ?></button></form><?php endforeach; ?><?php endif; ?>
<h3><?= e(t('t24.schedule')) ?></h3><p class="small"><?= e(t('t24.schedule_hint')) ?></p><pre class="code" dir="ltr">0 2 * * *  php bin/t24-sync.php --dataset=customers --api
0 3 1 * *  php bin/t24-sync.php --dataset=gl --file=/path/to/gl-export.csv</pre></section></div>
<section class="card"><h2><?= e(t('t24.runs')) ?></h2>
<div class="table-wrap"><table class="data"><thead><tr><th>#</th><th><?= e(t('t24.when')) ?></th><th><?= e(t('t24.dataset')) ?></th><th><?= e(t('t24.mode')) ?></th><th><?= e(t('t24.status')) ?></th><th class="num"><?= e(t('t24.read')) ?></th><th class="num"><?= e(t('t24.created')) ?></th><th class="num"><?= e(t('t24.updated')) ?></th><th><?= e(t('t24.by')) ?></th></tr></thead><tbody>
<?php if (!$runs): ?><tr><td colspan="9" class="empty"><?= e(t('t24.no_runs')) ?></td></tr><?php endif; ?>
<?php foreach ($runs as $r): ?><tr><td><a href="<?= e(url('admin/integrations/runs/' . $r['id'])) ?>"><?= (int)$r['id'] ?></a></td><td class="nowrap"><?= e(fdatetime($r['started_at'])) ?></td><td><?= e(t(sprintf('t24.ds_%s', $r['dataset']))) ?></td><td><?= e(t('enum.t24mode.' . $r['mode'])) ?></td>
<td><span class="badge <?= $r['status'] === 'success' ? 'ok' : 'danger' ?>"><?= e(t('enum.t24run.' . $r['status'])) ?></span></td><td class="num"><?= (int)$r['rows_read'] ?></td><td class="num"><?= (int)$r['rows_created'] ?></td><td class="num"><?= (int)$r['rows_updated'] ?></td><td><?= e($r['full_name'] ?? t('t24.scheduled')) ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<section class="card" id="settings"><h2><?= e(t('t24.settings')) ?></h2>
<form method="post" action="<?= e(url('admin/integrations/settings')) ?>" novalidate autocomplete="off"><?= csrf_field() ?><div class="form-grid">
<?= ff('url', 'api_base', 't24.api_base', $cfg['base'], ['dir' => 'ltr', 'hint' => t('t24.api_base_hint'), 'wide' => true]) ?>
<?= ff('text', 'api_user', 't24.api_user', $cfg['user'], ['dir' => 'ltr', 'hint' => t('t24.api_user_hint')]) ?>
<div class="field"><label for="f_api_secret"><?= e(t('t24.api_secret')) ?></label><input type="password" id="f_api_secret" name="api_secret" dir="ltr" autocomplete="new-password" value=""><p class="hint"><?= e($hasSecret ? t('t24.secret_set') : t('t24.secret_hint')) ?></p>
<?php if ($hasSecret): ?><label class="check"><input type="checkbox" name="clear_secret" value="1"> <?= e(t('t24.clear_secret')) ?></label><?php endif; ?></div>
<?= ff('text', 'customers_path', 't24.customers_path', $cfg['customers_path'], ['dir' => 'ltr']) ?><?= ff('text', 'gl_path', 't24.gl_path', $cfg['gl_path'], ['dir' => 'ltr']) ?>
<?= ff('number', 'page_size', 't24.page_size', $cfg['page_size'], ['extra' => ' min="1" max="1000"']) ?><?= ff('number', 'timeout', 't24.timeout', $cfg['timeout'], ['extra' => ' min="5" max="300"']) ?>
<?= ff('textarea', 'map_customers', 't24.map_customers', $maps['customers'], ['dir' => 'ltr', 'extra' => ' rows="10" class="code"', 'hint' => t('t24.map_hint')]) ?>
<?= ff('textarea', 'map_gl', 't24.map_gl', $maps['gl'], ['dir' => 'ltr', 'extra' => ' rows="10" class="code"', 'hint' => t('t24.map_gl_hint')]) ?></div>
<button class="btn" type="submit"><?= e(t('common.save')) ?></button></form>
<details style="margin-top:12px"><summary><?= e(t('t24.values_title')) ?></summary><p><?= e(t('t24.values_text')) ?> <a href="<?= e(url('aml/factors')) ?>"><?= e(t('aml.factors')) ?></a></p></details></section>
