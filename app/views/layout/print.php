<?php
use App\Core\Settings;

$loc = locale();
$org = lname(['name_en' => Settings::get('org_name_en'), 'name_ar' => Settings::get('org_name_ar')]);
?><!doctype html>
<html lang="<?= e($loc) ?>" dir="<?= is_rtl() ? 'rtl' : 'ltr' ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e($title ?? '') ?> — <?= e($org) ?></title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body class="print-page">
<div class="sheet">
  <div class="noprint actions" style="margin-bottom:12px">
    <button type="button" class="btn" data-print><?= e(t('common.print')) ?></button>
    <a class="btn secondary" href="javascript:history.back()"><?= e(t('common.back')) ?></a>
  </div>
  <div class="print-head">
    <div><h1><?= e($org) ?></h1><div><?= e($title ?? '') ?></div></div>
    <div class="print-meta"><?= e(t('report.generated')) ?>: <?= e(date('Y-m-d H:i')) ?><br><?= e(t('report.by')) ?>: <?= e(\App\Core\Auth::user()['full_name'] ?? '') ?><br><?= e(t('report.confidential')) ?></div>
  </div>
  <?= $content ?>
</div>
</body>
</html>
