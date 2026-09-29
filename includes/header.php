<?php
/**
 * includes/header.php
 * Kichwa cha kila ukurasa wa dashibodi (hatua ya kwanza ya layout)
 *
 * Matumizi kwenye ukurasa:
 *   $page_title = 'Dashboard';          // hiari
 *   require_once __DIR__ . '/../includes/header.php';
 *
 * Hii inaanza HTML, inaonyesha topbar (jina, role, logout)
 * na inaunganisha sidebar.php kiotomatiki.
 */
require_once __DIR__ . '/../includes/auth.php';
require_login();

$user        = current_user();
$page_title  = $page_title ?? 'Dashboard';

// Majina ya role kwa Kiswahili (badge ya topbar)
$role_labels = [
    'admin'   => 'Msimamizi',
    'teacher' => 'Mwalimu',
    'parent'  => 'Mzazi',
    'student' => 'Mwanafunzi',
];
?>
<!DOCTYPE html>
<html lang="sw">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($page_title) ?> — SMS Shule</title>
<link rel="stylesheet" href="<?= url('/assets/css/style.css') ?>">
</head>
<body class="role-<?= e($user['role']) ?>">

<!-- Overlay ya kufunga sidebar kwenye simu -->
<div class="overlay" id="overlay"></div>

<?php require __DIR__ . '/sidebar.php'; ?>

<div class="main-wrap">

  <!-- ============ TOPBAR ============ -->
  <header class="topbar">
    <button class="hamburger" id="hamburger" aria-label="Fungua menyu">&#9776;</button>

    <h1 class="page-title"><?= e($page_title) ?></h1>

    <div class="user-box">
      <?php if (!empty($user['photo'])): ?>
        <img class="avatar" src="<?= url('/' . ltrim($user['photo'], '/')) ?>" alt="Picha ya <?= e($user['full_name']) ?>">
      <?php else: ?>
        <span class="avatar avatar-initials"><?= e(mb_strtoupper(mb_substr($user['full_name'], 0, 1))) ?></span>
      <?php endif; ?>

      <div class="user-meta">
        <strong><?= e($user['full_name']) ?></strong>
        <span class="role-badge"><?= e($role_labels[$user['role']] ?? $user['role']) ?></span>
      </div>

      <a class="btn-logout" href="<?= url('/auth/logout.php') ?>" title="Toka kwenye mfumo">Toka &#9211;</a>
    </div>
  </header>

  <!-- ============ MALENGO (CONTENT) ============ -->
  <main class="content">
    <div class="flashes"><?= render_flashes() ?></div>
