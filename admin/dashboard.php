<?php
/**
 * admin/dashboard.php
 * Dashibodi ya Msimamizi (Admin) — muhtasari wa shule nzima.
 * Rangi: BLUU (role-admin)
 */
require_once __DIR__ . '/../includes/auth.php';
require_admin();

/* ---------- Takwimu (stats) za shule ---------- */
$stats = [
    'students' => (int)$pdo->query("SELECT COUNT(*) FROM students WHERE status = 'active'")->fetchColumn(),
    'teachers' => (int)$pdo->query('SELECT COUNT(*) FROM teachers')->fetchColumn(),
    'classes'  => (int)$pdo->query('SELECT COUNT(*) FROM classes')->fetchColumn(),
    'subjects' => (int)$pdo->query('SELECT COUNT(*) FROM subjects')->fetchColumn(),
    'parents'  => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'parent'")->fetchColumn(),
    'news'     => (int)$pdo->query('SELECT COUNT(*) FROM announcements')->fetchColumn(),
];

/* ---------- Taarifa 5 za hivi karibuni ---------- */
$announcements = $pdo->query(
    "SELECT a.*, c.name AS class_name, u.full_name AS author
     FROM announcements a
     LEFT JOIN classes c ON c.id = a.class_id
     JOIN users u ON u.id = a.created_by
     ORDER BY a.created_at DESC
     LIMIT 5"
)->fetchAll();

/* ---------- Muhtasari wa madarasa (wanafunzi kwa darasa) ---------- */
$classes = $pdo->query(
    "SELECT c.id, c.name, c.stream, c.level,
            u.full_name AS class_teacher,
            (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id AND s.status = 'active') AS total_students
     FROM classes c
     LEFT JOIN teachers t ON t.id = c.class_teacher_id
     LEFT JOIN users u    ON u.id = t.user_id
     ORDER BY c.name"
)->fetchAll();

$page_title = 'Dashboard ya Admin';
require_once __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../includes/banner.php';   // utepe wa kukaribisha (picha)
?>

<div class="stats-grid">
  <div class="stat-card stat-blue">
    <div class="stat-icon">&#127891;</div>
    <div><h3><?= $stats['students'] ?></h3><span>Wanafunzi Hai</span></div>
  </div>
  <div class="stat-card stat-green">
    <div class="stat-icon">&#128100;</div>
    <div><h3><?= $stats['teachers'] ?></h3><span>Walimu</span></div>
  </div>
  <div class="stat-card stat-orange">
    <div class="stat-icon">&#127979;</div>
    <div><h3><?= $stats['classes'] ?></h3><span>Madarasa</span></div>
  </div>
  <div class="stat-card stat-purple">
    <div class="stat-icon">&#128218;</div>
    <div><h3><?= $stats['subjects'] ?></h3><span>Masomo</span></div>
  </div>
  <div class="stat-card stat-red">
    <div class="stat-icon">&#128104;&#8205;&#128105;&#8205;&#128118;</div>
    <div><h3><?= $stats['parents'] ?></h3><span>Wazazi</span></div>
  </div>
  <div class="stat-card stat-blue">
    <div class="stat-icon">&#128226;</div>
    <div><h3><?= $stats['news'] ?></h3><span>Matangazo</span></div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h2>&#128226; Taarifa za Hivi Karibuni</h2>
    <a class="btn btn-sm" href="<?= url('/admin/announcements.php') ?>">Tuma Taarifa</a>
  </div>

  <?php if (!$announcements): ?>
    <div class="empty-state"><div class="icon">&#128226;</div>Hakuna taarifa bado. Bofya "Tuma Taarifa" kuanza.</div>
  <?php else: ?>
    <?php foreach ($announcements as $a): ?>
      <div class="announce-item">
        <h3><?= e($a['title']) ?></h3>
        <div class="announce-meta">
          Kwa: <span class="badge badge-info"><?= e(ucfirst($a['target'])) ?></span>
          <?php if ($a['class_name']): ?> | Darasa: <?= e($a['class_name']) ?><?php endif; ?>
          | <?= e($a['author']) ?> | <?= e(date('d/m/Y', strtotime($a['created_at']))) ?>
        </div>
        <div class="comment-text"><?= e($a['message']) ?></div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-header">
    <h2>&#127979; Muhtasari wa Madarasa</h2>
    <a class="btn btn-sm btn-secondary" href="<?= url('/admin/classes.php') ?>">Simamia Madarasa</a>
  </div>

  <div class="table-wrap">
    <table class="data">
      <thead>
        <tr><th>Darasa</th><th>Kiwango</th><th>Mwalimu wa Taaluma</th><th>Wanafunzi</th><th></th></tr>
      </thead>
      <tbody>
        <?php if (!$classes): ?>
          <tr><td colspan="5"><div class="empty-state">Hakuna madarasa bado.</div></td></tr>
        <?php else: foreach ($classes as $c): ?>
          <tr>
            <td><strong><?= e($c['name']) ?><?= $c['stream'] ? ' (' . e($c['stream']) . ')' : '' ?></strong></td>
            <td><span class="badge badge-muted"><?= e($c['level'] === 'primary' ? 'Msingi' : 'Sekondari') ?></span></td>
            <td><?= e($c['class_teacher'] ?? '— hakuna —') ?></td>
            <td><span class="badge badge-info"><?= (int)$c['total_students'] ?></span></td>
            <td class="table-actions"><a href="<?= url('/admin/classes.php?edit=' . (int)$c['id']) ?>">Hariri</a></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
