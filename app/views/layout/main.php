<?php
use App\Core\Auth;
use App\Core\Settings;

$u = Auth::user();
$loc = locale();
$org = lname(['name_en' => Settings::get('org_name_en'), 'name_ar' => Settings::get('org_name_ar')]);
$path = \App\Core\Request::path();
$nav = static function (string $href, string $label, ?string $match = null, string $ico = '') use ($path): string {
    $m = $match ?? $href;
    $cur = $m === '/' ? $path === '/' : ($path === $m || str_starts_with($path, $m . '/'));
    return '<a href="' . e(url($href)) . '"' . ($cur ? ' aria-current="page"' : '') . '>' . ($ico !== '' ? icon($ico) : '') . '<span>' . e($label) . '</span></a>';
};
$todo = $u ? \App\Support\Attention::count($u) : 0;
$showWelcome = $u && $path === '/' && \App\Support\Prefs::get((int)$u['id'], 'welcome_seen') === null;
?><!doctype html>
<html lang="<?= e($loc) ?>" dir="<?= is_rtl() ? 'rtl' : 'ltr' ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e($title ?? '') ?> — <?= e($org) ?></title>
<link rel="icon" href="data:,">
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
  <div class="tools">
    <a class="tool" href="<?= e(url('my')) ?>" title="<?= e(t('nav.my')) ?>"><?= icon('tasks') ?><span class="sr-only"><?= e(t('nav.my')) ?></span><?php if ($todo): ?><span class="badge-count" aria-label="<?= e(t('my.count', ['n' => $todo])) ?>"><?= (int)$todo ?></span><?php endif; ?></a>
    <a class="tool" href="<?= e(url('notes')) ?>" data-panel="notes" aria-controls="side-panel" title="<?= e(t('notes.title')) ?>"><?= icon('note') ?><span class="sr-only"><?= e(t('notes.title')) ?></span></a>
    <a class="tool" href="<?= e(url('help', ['t' => \App\Support\Help::slugForPath($path) ?? 'start'])) ?>" data-panel="help" aria-controls="side-panel" title="<?= e(t('help.title')) ?>"><?= icon('help') ?><span class="sr-only"><?= e(t('help.title')) ?></span></a>
  </div>
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
  <?php echo $nav('/my', t('nav.my'), null, 'tasks'); ?>
  <?php if (can('dashboard.view')) echo $nav('/', t('nav.dashboard'), null, 'home'); ?>
  <?php if (can('risk.view')) { echo $nav('/risks', t('nav.risks'), null, 'shield'); echo $nav('/actions', t('nav.actions'), null, 'check'); } ?>
  <?php if (can('incident.view')) echo $nav('/incidents', t('nav.incidents'), null, 'alert'); ?>
  <?php if (can('export.run')) echo $nav('/reports', t('nav.reports'), null, 'file'); ?>
  <?php if (can('risk.create') && (can('kri.report') || can('compliance.manage'))) echo $nav('/import', t('nav.import'), null, 'file'); ?>
  <?php if (can('governance.view') || can('circular.view') || can('bia.view') || can('sharia.view') || can('control.view') || can('kri.view') || can('appetite.view') || can('compliance.view') || can('committee.view') || can('policy.view') || can('rcsa.view')): ?>
    <h3><?= e(t('nav.governance')) ?></h3>
    <?php if (can('governance.view')) echo $nav('/governance', t('nav.governance_bodies'), null, 'users'); ?>
    <?php if (can('circular.view')) echo $nav('/circulars', t('nav.circulars'), null, 'file'); ?>
    <?php if (can('bia.view')) echo $nav('/bia', t('nav.bia'), null, 'layers'); ?>
    <?php if (can('approval.decide')) echo $nav('/approvals', t('nav.approvals'), null, 'check'); ?>
    <?php if (can('sharia.view')) echo $nav('/sharia', t('nav.sharia'), null, 'moon'); ?>
    <?php if (can('control.view')) echo $nav('/controls', t('nav.controls'), null, 'flask'); ?>
    <?php if (can('kri.view')) echo $nav('/kris', t('nav.kris'), null, 'pulse'); ?>
    <?php if (can('appetite.view')) echo $nav('/appetite', t('nav.appetite'), null, 'target'); ?>
    <?php if (can('compliance.view')) echo $nav('/compliance', t('nav.compliance'), null, 'scale'); ?>
    <?php if (can('committee.view')) echo $nav('/committees', t('nav.committees'), null, 'users'); ?>
    <?php if (can('policy.view')) echo $nav('/policies', t('nav.policies'), null, 'book'); ?>
    <?php if (can('rcsa.view')) echo $nav('/rcsa', t('nav.rcsa'), null, 'clipboard'); ?>
  <?php endif; ?>
  <?php if (can('audit.view')) echo $nav('/audit', t('nav.audit'), null, 'search'); ?>
  <?php if (can('users.manage') || can('org.manage') || can('scales.manage')): ?>
    <h3><?= e(t('nav.admin')) ?></h3>
    <?php if (can('users.manage')) echo $nav('/admin/users', t('admin.users'), null, 'user'); ?>
    <?php if (can('org.manage')) { echo $nav('/admin/departments', t('admin.departments')); echo $nav('/admin/branches', t('admin.branches')); echo $nav('/admin/categories', t('admin.categories')); echo $nav('/admin/periods', t('admin.periods')); } ?>
    <?php if (can('scales.manage')) echo $nav('/admin/scales', t('scales.title'), null, 'sliders'); ?>
    <?php if (can('settings.manage')) { echo $nav('/admin/settings', t('admin.settings')); echo $nav('/admin/system', t('admin.system')); } ?>
  <?php endif; ?>
</nav>
<?php endif; ?>
<main id="main" tabindex="-1">
<?php foreach (\App\Core\Session::messages() as $m): ?>
  <div class="msg <?= e($m['type']) ?>" role="<?= $m['type'] === 'error' ? 'alert' : 'status' ?>"><?= e($m['text']) ?></div>
<?php endforeach; ?>
<?php if ($showWelcome): $qs = \App\Support\Help::quickStart($u['role']); ?>
<section class="welcome card" aria-labelledby="welcome-h">
  <h2 id="welcome-h"><?= e(t('help.welcome', ['name' => $u['full_name']])) ?></h2>
  <p><?= e($qs['intro']) ?></p>
  <ol class="steps"><?php foreach ($qs['steps'] as [$sl, $txt]): ?><li><a href="<?= e(url('help', ['t' => $sl])) ?>"><?= e($txt) ?></a></li><?php endforeach; ?></ol>
  <div class="actions"><a class="btn gold" href="<?= e(url('help')) ?>"><?= e(t('help.open_center')) ?></a>
    <form method="post" action="<?= e(url('help/dismiss')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="key" value="welcome_seen"><button class="btn secondary" type="submit"><?= e(t('help.dismiss')) ?></button></form></div>
</section>
<?php endif; ?>
<?= $content ?>
</main>
</div>
<?php if ($u): ?>
<aside id="side-panel" class="side-panel" data-base="<?= e(base_path()) ?>" role="dialog" aria-modal="false" aria-labelledby="panel-title" hidden tabindex="-1">
  <button type="button" class="panel-close" data-panel-close aria-label="<?= e(t('common.close')) ?>"><?= icon('close') ?></button>
  <div class="panel-body" data-panel-body></div>
</aside>
<?php endif; ?>
</body>
</html>
