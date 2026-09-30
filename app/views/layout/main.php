<?php
use App\Core\Auth;
use App\Core\Settings;

$u = Auth::user();
$loc = locale();
$org = lname(['name_en' => Settings::get('org_name_en'), 'name_ar' => Settings::get('org_name_ar')]);
$path = \App\Core\Request::path();
$nav = static function (string $href, string $label, ?string $match = null) use ($path): string {
    $m = $match ?? $href;
    $cur = $m === '/' ? $path === '/' : ($path === $m || str_starts_with($path, $m . '/'));
    return '<a href="' . e(url($href)) . '"' . ($cur ? ' aria-current="page"' : '') . '>' . e($label) . '</a>';
};
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
<body>
<a class="skip" href="#main"><?= e(t('common.skip')) ?></a>
<header class="topbar">
  <button type="button" class="menu-toggle" id="menu-toggle" aria-controls="sidenav" aria-expanded="true"><?= e(t('common.menu')) ?></button>
  <a class="brand" href="<?= e(url('')) ?>"><?= e($org) ?><small><?= e(t('app.name')) ?></small></a>
  <div class="langsw" role="group" aria-label="<?= e(t('common.language')) ?>">
    <a href="<?= e(url('lang', ['l' => 'ar', 'next' => $_SERVER['REQUEST_URI'] ?? '/'])) ?>" lang="ar" <?= $loc === 'ar' ? 'aria-current="true"' : '' ?>>العربية</a>
    <a href="<?= e(url('lang', ['l' => 'en', 'next' => $_SERVER['REQUEST_URI'] ?? '/'])) ?>" lang="en" <?= $loc === 'en' ? 'aria-current="true"' : '' ?>>English</a>
  </div>
  <?php if ($u): ?>
  <div class="user">
    <span><?= e($u['full_name']) ?> · <?= e(enum_label('role', $u['role'])) ?></span>
    <a href="<?= e(url('account')) ?>"><?= e(t('nav.account')) ?></a>
    <form method="post" action="<?= e(url('logout')) ?>" class="inline"><?= csrf_field() ?><button class="linklike" type="submit"><?= e(t('auth.logout')) ?></button></form>
  </div>
  <?php endif; ?>
</header>
<?php if ($u && Settings::get('thresholds_approved') !== '1'): ?>
<div class="banner" role="note"><?= e(t('scales.demo_banner')) ?>
  <?php if (can('scales.manage')): ?> <a href="<?= e(url('admin/scales')) ?>"><?= e(t('scales.review_link')) ?></a><?php endif; ?></div>
<?php endif; ?>
<div class="shell">
<?php if ($u): ?>
<nav class="sidenav" id="sidenav" aria-label="<?= e(t('common.main_nav')) ?>">
  <?php if (can('dashboard.view')) echo $nav('/', t('nav.dashboard')); ?>
  <?php if (can('risk.view')) { echo $nav('/risks', t('nav.risks')); echo $nav('/actions', t('nav.actions')); } ?>
  <?php if (can('incident.view')) echo $nav('/incidents', t('nav.incidents')); ?>
  <?php if (can('export.run')) echo $nav('/reports', t('nav.reports')); ?>
  <?php if (can('kri.view') || can('appetite.view') || can('compliance.view') || can('committee.view') || can('policy.view') || can('rcsa.view')): ?>
    <h3><?= e(t('nav.governance')) ?></h3>
    <?php if (can('kri.view')) echo $nav('/kris', t('nav.kris')); ?>
    <?php if (can('appetite.view')) echo $nav('/appetite', t('nav.appetite')); ?>
    <?php if (can('compliance.view')) echo $nav('/compliance', t('nav.compliance')); ?>
    <?php if (can('committee.view')) echo $nav('/committees', t('nav.committees')); ?>
    <?php if (can('policy.view')) echo $nav('/policies', t('nav.policies')); ?>
    <?php if (can('rcsa.view')) echo $nav('/rcsa', t('nav.rcsa')); ?>
  <?php endif; ?>
  <?php if (can('audit.view')) echo $nav('/audit', t('nav.audit')); ?>
  <?php if (can('users.manage') || can('org.manage') || can('scales.manage')): ?>
    <h3><?= e(t('nav.admin')) ?></h3>
    <?php if (can('users.manage')) echo $nav('/admin/users', t('admin.users')); ?>
    <?php if (can('org.manage')) { echo $nav('/admin/departments', t('admin.departments')); echo $nav('/admin/branches', t('admin.branches')); echo $nav('/admin/categories', t('admin.categories')); echo $nav('/admin/periods', t('admin.periods')); } ?>
    <?php if (can('scales.manage')) echo $nav('/admin/scales', t('scales.title')); ?>
    <?php if (can('settings.manage')) { echo $nav('/admin/settings', t('admin.settings')); echo $nav('/admin/system', t('admin.system')); } ?>
  <?php endif; ?>
</nav>
<?php endif; ?>
<main id="main" tabindex="-1">
<?php foreach (\App\Core\Session::messages() as $m): ?>
  <div class="msg <?= e($m['type']) ?>" role="<?= $m['type'] === 'error' ? 'alert' : 'status' ?>"><?= e($m['text']) ?></div>
<?php endforeach; ?>
<?= $content ?>
</main>
</div>
</body>
</html>
