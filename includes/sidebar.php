<?php
/**
 * includes/sidebar.php
 * Menyu ya kushoto — inabadilika kulingana na role ya mtumiaji.
 * (Inaitwa na header.php; usiite moja kwa moja)
 */
$user    = current_user();
$role    = $user['role'] ?? 'parent';
$current = basename($_SERVER['PHP_SELF']);   // kuhusu ku-highlight menyu

// Menyu za kila role
$menus = [
    'admin' => [
        ['/admin/dashboard.php',     '&#128202;', 'Dashboard'],
        ['/admin/teachers.php',      '&#128100;', 'Walimu'],
        ['/admin/classes.php',       '&#127979;', 'Madarasa'],
        ['/admin/subjects.php',      '&#128218;', 'Masomo'],
        ['/admin/students.php',      '&#127891;', 'Wanafunzi'],
        ['/admin/announcements.php', '&#128226;', 'Matangazo'],
    ],
    'teacher' => [
        ['/teacher/dashboard.php',     '&#128202;', 'Dashboard'],
        ['/teacher/results.php',       '&#128221;', 'Matokeo'],
        ['/teacher/attendance.php',    '&#128197;', 'Mahudhurio'],
        ['/teacher/comments.php',      '&#128172;', 'Comments'],
        ['/teacher/upload_photos.php', '&#128248;', 'Picha'],
    ],
    'parent' => [
        ['/parent/dashboard.php',       '&#128104;&#8205;&#128105;&#8205;&#128118;', 'Watoto Wangu'],
        ['/parent/register_child.php',  '&#10133;', 'Sajili Mtoto'],
    ],
    'student' => [
        ['/student/dashboard.php', '&#128202;', 'Dashboard'],
    ],
];

$items = $menus[$role] ?? [];
$panel_names = [
    'admin'   => 'Msimamizi Panel',
    'teacher' => 'Mwalimu Panel',
    'parent'  => 'Mzazi Panel',
    'student' => 'Mwanafunzi Panel',
];
?>

<aside class="sidebar" id="sidebar">
  <div class="brand">SMS <span>Shule</span></div>

  <nav class="menu">
    <?php foreach ($items as [$link, $icon, $label]): ?>
      <a href="<?= url($link) ?>"
         class="menu-item <?= basename($link) === $current ? 'active' : '' ?>">
        <span class="menu-icon"><?= $icon ?></span><?= e($label) ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="sidebar-foot"><?= e($panel_names[$role] ?? ucfirst($role)) ?></div>
</aside>
