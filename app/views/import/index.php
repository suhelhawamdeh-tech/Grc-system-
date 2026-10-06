<?php /** @var array $targets @var ?array $preview */ ?>
<div class="page-head"><div><h1><?= icon('file') ?> <?= e(t('import.title')) ?></h1><p class="sub"><?= e(t('import.subtitle')) ?></p></div></div>
<?php if (!$targets): ?><div class="msg info"><?= e(t('import.none')) ?></div><?php else: ?>
<section class="card"><h2><?= e(t('import.step1')) ?></h2><p class="muted"><?= e(t('import.how')) ?></p>
<ul class="task-list"><?php foreach ($targets as $tg): ?><li><span><?= e(t('import.t_' . $tg)) ?></span><a class="btn small secondary" href="<?= e(url('import/template', ['target' => $tg])) ?>"><?= e(t('import.template')) ?></a></li><?php endforeach; ?></ul></section>
<section class="card"><h2><?= e(t('import.step2')) ?></h2>
<form method="post" enctype="multipart/form-data" action="<?= e(url('import/preview')) ?>"><?= csrf_field() ?>
<div class="form-grid"><div class="field"><label for="target"><?= e(t('import.what')) ?></label><select id="target" name="target" required><?php foreach ($targets as $tg): ?><option value="<?= e($tg) ?>"<?= $preview && $preview['target'] === $tg ? ' selected' : '' ?>><?= e(t('import.t_' . $tg)) ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="file"><?= e(t('import.file')) ?></label><input type="file" id="file" name="file" accept=".csv,.txt" required><p class="hint"><?= e(t('import.file_hint')) ?></p></div></div>
<button class="btn" type="submit"><?= e(t('import.check')) ?></button></form></section>
<?php if ($preview): ?><section class="card"><h2><?= e(t('import.step3')) ?></h2>
<?php if ($preview['errors']): ?><div class="msg error" role="alert"><strong><?= e(t('import.errors_found', ['n' => count($preview['errors']), 'total' => $preview['total']])) ?></strong> <?= e(t('import.nothing_saved')) ?></div>
<div class="table-wrap"><table class="data"><thead><tr><th class="num"><?= e(t('import.line')) ?></th><th><?= e(t('import.problem')) ?></th></tr></thead><tbody><?php foreach (array_slice($preview['errors'], 0, 50, true) as $line => $errs): ?><tr><td class="num"><?= (int)$line ?></td><td><?= e(implode(' · ', $errs)) ?></td></tr><?php endforeach; ?></tbody></table></div>
<?php if (count($preview['errors']) > 50): ?><p class="muted"><?= e(t('import.more_errors', ['n' => count($preview['errors']) - 50])) ?></p><?php endif; ?>
<?php else: ?><div class="msg success"><?= e(t('import.all_valid', ['n' => $preview['total']])) ?></div>
<div class="table-wrap"><table class="data"><thead><tr><?php foreach (array_keys(reset($preview['sample'])) as $h): if ($h === '_line') continue; ?><th><?= e($h) ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach ($preview['sample'] as $r): ?><tr><?php foreach ($r as $k => $c): if ($k === '_line') continue; ?><td><?= e(mb_strimwidth((string)$c, 0, 60, '…')) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div>
<form method="post" action="<?= e(url('import/commit')) ?>" style="margin-top:12px" data-confirm="<?= e(t('import.confirm', ['n' => $preview['total']])) ?>"><?= csrf_field() ?><input type="hidden" name="token" value="<?= e($preview['token']) ?>"><button class="btn gold" type="submit"><?= e(t('import.commit', ['n' => $preview['total']])) ?></button></form><?php endif; ?></section><?php endif; ?>
<?php endif; ?>
