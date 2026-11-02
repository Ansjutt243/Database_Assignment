<?php
/**
 * Common page header: system name, screen title, logged-in user and role,
 * and the role-based menu (Design Document Section 9.1 - consistency).
 * @var string $title
 */
$user = Auth::user();
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? '') ?> | <?= e(config('app_name')) ?></title>
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<header class="topbar">
  <div class="brand"><?= e(config('app_name')) ?> <span>|</span> <?= e($title ?? '') ?></div>
  <?php if ($user): ?>
  <div class="who">
    <?= e($user['name']) ?> (<?= e(Auth::ROLE_LABELS[$user['role']]) ?>)
    <a href="<?= e(url('logout.php')) ?>">Log out</a>
  </div>
  <?php endif; ?>
</header>
<?php if ($user): ?>
<nav class="menu">
  <a href="<?= e(url('main.php')) ?>">Main Control Page</a>
  <?php if (Auth::can('call.log')): ?><a href="<?= e(url('calls/new.php')) ?>">Log New Call</a><?php endif; ?>
  <?php if (Auth::can('call.followup')): ?><a href="<?= e(url('calls/followup.php')) ?>">Follow-up Call</a><?php endif; ?>
  <?php if (Auth::can('call.assign')): ?><a href="<?= e(url('calls/assign.php')) ?>">Assign Technician</a><?php endif; ?>
  <?php if (Auth::can('call.resolve')): ?><a href="<?= e(url('calls/resolve.php')) ?>">Resolve Problem</a><?php endif; ?>
  <?php if (Auth::can('call.list')): ?><a href="<?= e(url('calls/open.php')) ?>"><?= Auth::role() === 'Technician' ? 'My Open Jobs' : 'Open Problems' ?></a><?php endif; ?>
  <?php if (Auth::can('reports.view')): ?><a href="<?= e(url('reports/index.php')) ?>">Management Reports</a><?php endif; ?>
  <?php if (Auth::can('registers.view')): ?><a href="<?= e(url('registers/index.php')) ?>">Registers</a><?php endif; ?>
  <a href="<?= e(url('maintenance/index.php')) ?>">Maintenance</a>
  <?php if (Auth::can('users.manage')): ?><a href="<?= e(url('users/index.php')) ?>">User Accounts</a><?php endif; ?>
</nav>
<?php endif; ?>
<main class="page">
<?php foreach (take_flashes() as $f): ?>
  <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
<?php endforeach; ?>
