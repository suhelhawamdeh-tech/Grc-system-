<?php
use App\Core\Settings;

$loc = locale();
$org = \App\Core\Db::ready() ? lname(['name_en' => Settings::get('org_name_en'), 'name_ar' => Settings::get('org_name_ar')]) : '';
$next = $_SERVER['REQUEST_URI'] ?? '/';
?><!doctype html>
<html lang="<?= e($loc) ?>" dir="<?= is_rtl() ? 'rtl' : 'ltr' ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e($title ?? '') ?> — <?= e(t('app.name')) ?></title>
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
</head>
<body class="guest">
<header class="topbar">
  <span class="brand"><?= e($org !== '' ? $org : t('app.name')) ?><small><?= e(t('app.name')) ?></small></span>
  <div class="langsw" role="group" aria-label="<?= e(t('common.language')) ?>">
    <a href="<?= e(url('lang', ['l' => 'ar', 'next' => $next])) ?>" lang="ar" <?= $loc === 'ar' ? 'aria-current="true"' : '' ?>>العربية</a>
    <a href="<?= e(url('lang', ['l' => 'en', 'next' => $next])) ?>" lang="en" <?= $loc === 'en' ? 'aria-current="true"' : '' ?>>English</a>
  </div>
</header>
<main id="main" class="<?= e($mainClass ?? '') ?>">
<?php foreach (\App\Core\Session::messages() as $m): ?>
  <div class="msg <?= e($m['type']) ?>" role="<?= $m['type'] === 'error' ? 'alert' : 'status' ?>"><?= e($m['text']) ?></div>
<?php endforeach; ?>
<?= $content ?>
</main>
</body>
</html>
