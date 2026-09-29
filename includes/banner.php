<?php
/**
 * includes/banner.php
 * UTEPE WA KUKARIBISHA (welcome banner) kwenye dashibodi — una picha ya nyuma.
 *
 * Matumizi kwenye dashibodi (baada ya header.php):
 *   $banner_sub = 'Muhtasari wa leo.';
 *   require_once __DIR__ . '/../includes/banner.php';
 */
require_once __DIR__ . '/images.php';

$banner_user     = current_user();
$banner_role     = $banner_user['role'] ?? 'parent';
$banner_defaults = [
    'admin'   => 'Muhtasari wa shule nzima leo.',
    'teacher' => 'Madarasa yako na wanafunzi wako — kazi za leo.',
    'parent'  => 'Watoto wako, matokeo yao, taarifa zao — yote mahali pamoja.',
    'student' => 'Endelea na kujifunza — angalia matokeo na mahudhurio yako.',
];
?>
<section class="welcome-banner"
         style="background-image:linear-gradient(90deg, rgba(10,25,55,.88), rgba(10,25,55,.40)), url('<?= img_url('banner') ?>');">
  <h2>Karibu, <?= e($banner_user['full_name'] ?? 'Mtu mgeni') ?> &#128075;</h2>
  <p><?= e($banner_sub ?? ($banner_defaults[$banner_role] ?? '')) ?></p>
</section>
