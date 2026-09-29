<?php
/**
 * parent/dashboard.php
 * Dashibodi ya Mzazi — kadi za watoto wake WOTE + matangazo.
 * Rangi: MACHUNGWA (role-parent)
 * Usalama: mzazi anaona watoto wake TU (WHERE parent_id = ?)
 */
require_once __DIR__ . '/../includes/auth.php';
require_parent();

$user = current_user();

/* ---------- Kupata record ya mzazi ---------- */
$stmt = $pdo->prepare('SELECT id FROM parents WHERE user_id = ? LIMIT 1');
$stmt->execute([$user['id']]);
$parent = $stmt->fetch();

if (!$parent) {
    set_flash('error', 'Taarifa za mzazi hazipatikani. Wasiliana na msimamizi.');
    redirect('/auth/logout.php');
}
$parent_id = (int)$parent['id'];

/* ---------- Watoto wote wa mzazi huyu ---------- */
$stmt = $pdo->prepare(
    "SELECT s.id, s.full_name, s.reg_no, s.age, s.gender, s.photo, s.class_id,
            c.name AS class_name, c.stream
     FROM students s
     JOIN classes c ON c.id = s.class_id
     WHERE s.parent_id = ? AND s.status = 'active'
     ORDER BY s.full_name"
);
$stmt->execute([$parent_id]);
$children = $stmt->fetchAll();

/* ---------- Matangazo yanayomgusa mzazi huyu ----------
   target: 'all' au 'parents' + (shule nzima AU darasa za watoto wake) */
$childClassIds = array_values(array_unique(array_map(
    static fn($ch) => (int)$ch['class_id'],
    $children
)));

$sql = "SELECT title, message, target, created_at, class_id, c.name AS class_name
        FROM announcements a
        LEFT JOIN classes c ON c.id = a.class_id
        WHERE a.target IN ('all','parents')
          AND (a.class_id IS NULL";
$params = [];
if ($childClassIds) {
    $sql .= ' OR a.class_id IN (' . implode(',', $childClassIds) . ')'; // IDs za int — salama
}
$sql .= ") ORDER BY a.created_at DESC LIMIT 8";
$announcements = $pdo->prepare($sql);
$announcements->execute($params);
$announcements = $announcements->fetchAll();

$page_title = 'Watoto Wangu';
require_once __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../includes/banner.php';   // utepe wa kukaribisha (picha)
?>

<div class="stats-grid">
  <div class="stat-card stat-orange">
    <div class="stat-icon">&#128104;&#8205;&#128105;&#8205;&#128118;</div>
    <div><h3><?= count($children) ?></h3><span>Watoto Shuleni</span></div>
  </div>
  <div class="stat-card stat-blue">
    <div class="stat-icon">&#128226;</div>
    <div><h3><?= count($announcements) ?></h3><span>Taarifa za Hivi Karibuni</span></div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h2>&#128104;&#8205;&#128105;&#8205;&#128118; Watoto Wangu</h2>
    <a class="btn btn-sm" href="<?= url('/parent/register_child.php') ?>">&#10133; Sajili Mtoto Mpya</a>
  </div>

  <?php if (!$children): ?>
    <div class="empty-state">
      <div class="icon">&#128118;</div>
      <p><strong>Huna mtoto aliyesajiliwa bado.</strong></p>
      <p>Bofya kitufe "Sajili Mtoto Mpya" hapo juu kuunganisha mtoto wako na shule.</p>
    </div>
  <?php else: ?>
    <div class="child-grid">
      <?php foreach ($children as $ch): ?>
        <div class="child-card">
          <?php if ($ch['photo']): ?>
            <img src="<?= url('/' . ltrim($ch['photo'], '/')) ?>" alt="Picha ya <?= e($ch['full_name']) ?>">
          <?php else: ?>
            <span class="child-photo-ph"><?= e(mb_strtoupper(mb_substr($ch['full_name'], 0, 1))) ?></span>
          <?php endif; ?>

          <h3><?= e($ch['full_name']) ?></h3>
          <p>
            <?= e($ch['class_name']) ?><?= $ch['stream'] ? ' (' . e($ch['stream']) . ')' : '' ?><br>
            <span class="badge badge-muted"><?= e($ch['reg_no']) ?></span>
          </p>
          <a class="btn" href="<?= url('/parent/child_view.php?id=' . (int)$ch['id']) ?>">Fungua &rarr;</a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

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
