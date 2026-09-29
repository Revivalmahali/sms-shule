<?php
/**
 * teacher/dashboard.php
 * Dashibodi ya Mwalimu — masomo yake na madarasa yanayohusika TU.
 * Rangi: KIJANI (role-teacher)
 */
require_once __DIR__ . '/../includes/auth.php';
require_teacher();

$user = current_user();

/* ---------- Record ya mwalimu ---------- */
$stmt = $pdo->prepare('SELECT id, teacher_type, employee_no FROM teachers WHERE user_id = ? LIMIT 1');
$stmt->execute([$user['id']]);
$teacher = $stmt->fetch();
if (!$teacher) {
    set_flash('error', 'Taarifa za mwalimu hazipatikani. Wasiliana na msimamizi.');
    redirect('/auth/logout.php');
}
$teacher_id = (int)$teacher['id'];

/* ---------- Madarasa yanayohusika (ya masomo yake + darasa lake kama class teacher) ---------- */
$stmt = $pdo->prepare('SELECT DISTINCT class_id FROM subjects WHERE teacher_id = ?');
$stmt->execute([$teacher_id]);
$classIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

if ($teacher['teacher_type'] === 'class') {
    $stmt = $pdo->prepare('SELECT id FROM classes WHERE class_teacher_id = ?');
    $stmt->execute([$teacher_id]);
    $classIds = array_merge($classIds, array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
}
$classIds = array_values(array_unique($classIds));

/* ---------- Masomo yangu ---------- */
$stmt = $pdo->prepare(
    "SELECT s.id, s.name, s.code, c.id AS class_id, c.name AS class_name, c.stream,
            (SELECT COUNT(*) FROM students st WHERE st.class_id = c.id AND st.status = 'active') AS students_count
     FROM subjects s
     JOIN classes c ON c.id = s.class_id
     WHERE s.teacher_id = ?
     ORDER BY c.name, s.name"
);
$stmt->execute([$teacher_id]);
$my_subjects = $stmt->fetchAll();

/* ---------- Madarasa niliyo mwalimu wa taaluma ---------- */
$my_classes = [];
if ($teacher['teacher_type'] === 'class' && $classIds) {
    $in = implode(',', $classIds);
    $my_classes = $pdo->query(
        "SELECT c.id, c.name, c.stream,
                (SELECT COUNT(*) FROM students st WHERE st.class_id = c.id AND st.status = 'active') AS students_count
         FROM classes c WHERE c.id IN ($in)
         ORDER BY c.name"
    )->fetchAll();
}

/* ---------- Idadi ya wanafunzi nitakayeshughulikia ---------- */
$total_students = 0;
if ($classIds) {
    $in = implode(',', $classIds);
    $total_students = (int)$pdo->query(
        "SELECT COUNT(*) FROM students WHERE class_id IN ($in) AND status = 'active'"
    )->fetchColumn();
}

/* ---------- Taarifa zinazomgusa walimu ---------- */
$ann_sql = "SELECT title, message, created_at, c.name AS class_name
            FROM announcements a
            LEFT JOIN classes c ON c.id = a.class_id
            WHERE a.target IN ('all','teachers')
              AND (a.class_id IS NULL";
$ann_params = [];
if ($classIds) {
    $ann_sql .= ' OR a.class_id IN (' . implode(',', $classIds) . ')';
}
$ann_sql .= ') ORDER BY a.created_at DESC LIMIT 5';
$announcements = $pdo->prepare($ann_sql);
$announcements->execute($ann_params);
$announcements = $announcements->fetchAll();

$page_title = 'Dashboard ya Mwalimu';
require_once __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../includes/banner.php';   // utepe wa kukaribisha (picha)
?>

<div class="stats-grid">
  <div class="stat-card stat-green">
    <div class="stat-icon">&#128218;</div>
    <div><h3><?= count($my_subjects) ?></h3><span>Masomo Yangu</span></div>
  </div>
  <div class="stat-card stat-blue">
    <div class="stat-icon">&#127979;</div>
    <div><h3><?= count($classIds) ?></h3><span>Madarasa Yanayohusika</span></div>
  </div>
  <div class="stat-card stat-orange">
    <div class="stat-icon">&#127891;</div>
    <div><h3><?= $total_students ?></h3><span>Wanafunzi</span></div>
  </div>
  <div class="stat-card stat-purple">
    <div class="stat-icon">&#128203;</div>
    <div><h3><?= e($teacher['teacher_type'] === 'class' ? 'Taaluma' : 'Somo') ?></h3><span>Aina Yangu</span></div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h2>&#128218; Masomo Ninayofundisha</h2>
    <a class="btn btn-sm" href="<?= url('/teacher/results.php') ?>">Weka Matokeo</a>
  </div>

  <?php if (!$my_subjects): ?>
    <div class="empty-state"><div class="icon">&#128218;</div>Hujapewa masomo bado. Wasiliana na msimamizi wa shule.</div>
  <?php else: ?>
    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>Somo</th><th>Code</th><th>Darasa</th><th>Wanafunzi</th><th>Vitendo</th></tr></thead>
        <tbody>
          <?php foreach ($my_subjects as $s): ?>
            <tr>
              <td><strong><?= e($s['name']) ?></strong></td>
              <td><span class="badge badge-muted"><?= e($s['code']) ?></span></td>
              <td><?= e($s['class_name']) ?><?= $s['stream'] ? ' (' . e($s['stream']) . ')' : '' ?></td>
              <td><span class="badge badge-info"><?= (int)$s['students_count'] ?></span></td>
              <td class="table-actions">
                <a href="<?= url('/teacher/results.php?subject_id=' . (int)$s['id']) ?>">Matokeo</a>
                <a href="<?= url('/teacher/attendance.php?class_id=' . (int)$s['class_id']) ?>">Mahudhurio</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php if ($my_classes): ?>
<div class="card">
  <div class="card-header">
    <h2>&#127979; Darasa langu (Mwalimu wa Taaluma)</h2>
    <a class="btn btn-sm btn-secondary" href="<?= url('/teacher/comments.php') ?>">Andika Comments</a>
  </div>
  <div class="table-wrap">
    <table class="data">
      <thead><tr><th>Darasa</th><th>Wanafunzi</th><th>Vitendo</th></tr></thead>
      <tbody>
        <?php foreach ($my_classes as $c): ?>
          <tr>
            <td><strong><?= e($c['name']) ?><?= $c['stream'] ? ' (' . e($c['stream']) . ')' : '' ?></strong></td>
            <td><span class="badge badge-info"><?= (int)$c['students_count'] ?></span></td>
            <td class="table-actions">
              <a href="<?= url('/teacher/attendance.php?class_id=' . (int)$c['id']) ?>">Mahudhurio</a>
              <a href="<?= url('/teacher/comments.php?class_id=' . (int)$c['id']) ?>">Comments</a>
              <a href="<?= url('/teacher/upload_photos.php?class_id=' . (int)$c['id']) ?>">Picha</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-header"><h2>&#128226; Taarifa za Shule</h2></div>
  <?php if (!$announcements): ?>
    <div class="empty-state"><div class="icon">&#128226;</div>Hakuna taarifa kwa sasa.</div>
  <?php else: foreach ($announcements as $a): ?>
    <div class="announce-item">
      <h3><?= e($a['title']) ?></h3>
      <div class="announce-meta">
        <?php if ($a['class_name']): ?>Darasa: <?= e($a['class_name']) ?> |<?php endif; ?>
        <?= e(date('d/m/Y', strtotime($a['created_at']))) ?>
      </div>
      <div class="comment-text"><?= e($a['message']) ?></div>
    </div>
  <?php endforeach; endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
