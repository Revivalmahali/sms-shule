<?php
/**
 * teacher/upload_photos.php
 * Ku-upload PICHA za wanafunzi na shughuli za darasa/shule.
 *   Categories: profile | activity | event | academic
 *   Validation: JPG/PNG tu, max 2MB, file rename salama
 *   student_id = NULL  -> picha ya shughuli za jumla (inaonekana kwa wazazi wote)
 * Mwalimu anaona na anaweza kufuta picha ALIZO-upload yeye mwenyewe.
 */
require_once __DIR__ . '/../includes/auth.php';
require_teacher();

$user = current_user();
$stmt = $pdo->prepare('SELECT id, teacher_type FROM teachers WHERE user_id = ? LIMIT 1');
$stmt->execute([$user['id']]);
$teacher = $stmt->fetch();
if (!$teacher) {
    set_flash('error', 'Taarifa za mwalimu hazipatikani.');
    redirect('/auth/logout.php');
}
$teacher_id = (int)$teacher['id'];

/* ---------- Madarasa yanayohusika (isolation) ---------- */
$stmt = $pdo->prepare('SELECT DISTINCT class_id FROM subjects WHERE teacher_id = ?');
$stmt->execute([$teacher_id]);
$classIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
if ($teacher['teacher_type'] === 'class') {
    $stmt = $pdo->prepare('SELECT id FROM classes WHERE class_teacher_id = ?');
    $stmt->execute([$teacher_id]);
    $classIds = array_merge($classIds, array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
}
$classIds = array_values(array_unique($classIds));
$my_classes = [];
if ($classIds) {
    $in = implode(',', $classIds);
    $my_classes = $pdo->query("SELECT id, name, stream FROM classes WHERE id IN ($in) ORDER BY name")->fetchAll();
}

$class_id = (int)($_REQUEST['class_id'] ?? 0);
$valid_class = false;
foreach ($my_classes as $c) {
    if ((int)$c['id'] === $class_id) { $valid_class = true; break; }
}
if (!$valid_class) $class_id = 0;

$students = [];
if ($class_id) {
    $stmt = $pdo->prepare("SELECT id, full_name, reg_no FROM students WHERE class_id = ? AND status = 'active' ORDER BY full_name");
    $stmt->execute([$class_id]);
    $students = $stmt->fetchAll();
}

/* ---------- Helper: ku-upload picha (validate + rename) ---------- */
function handle_activity_photo(array $file): string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Chagua picha sahihi kwanza.');
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('Picha ni kubwa sana. Ruhusa ni hadi 2MB.');
    }
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Aina ya faili hairuhusiwi. Tumia JPG au PNG tu.');
    }
    $relName = 'activities/photo_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $target  = dirname(__DIR__) . '/uploads/' . $relName;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('Picha imeshindikana kuhifadhiwa kwenye server.');
    }
    return 'uploads/' . $relName;
}

/* ---------- KU-UPLOAD ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload') {
    verify_csrf();

    if (!$class_id) {
        set_flash('error', 'Chagua darasa kwanza.');
        redirect('/teacher/upload_photos.php');
    }

    $category   = $_POST['category'] ?? 'activity';
    $caption    = trim($_POST['caption'] ?? '');
    $student_in = (int)($_POST['student_id'] ?? 0);   // 0 = shughuli ya jumla

    if (!in_array($category, ['profile', 'activity', 'event', 'academic'], true)) {
        set_flash('error', 'Chagua category sahihi.');
        redirect("/teacher/upload_photos.php?class_id=$class_id");
    }

    // Mwanafunzi lazima awe wa darasa hili (isolation)
    $student_id = null;
    if ($student_in > 0) {
        $stmt = $pdo->prepare("SELECT id FROM students WHERE id = ? AND class_id = ? AND status = 'active'");
        $stmt->execute([$student_in, $class_id]);
        if ($stmt->fetch()) $student_id = $student_in;
    }

    try {
        $photoUrl = handle_activity_photo($_FILES['photo'] ?? []);
        $stmt = $pdo->prepare(
            'INSERT INTO student_photos (student_id, uploaded_by, photo_url, caption, category)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$student_id, $teacher_id, $photoUrl, $caption !== '' ? $caption : null, $category]);
        set_flash('success', 'Picha imepakiwa na imehifadhiwa kikamilifu!');
    } catch (RuntimeException $ex) {
        set_flash('error', $ex->getMessage());
    }
    redirect("/teacher/upload_photos.php?class_id=$class_id");
}

/* ---------- KUFUTA picha yake mwenyewe ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    verify_csrf();
    $photo_id = (int)($_POST['photo_id'] ?? 0);

    // Isolation: picha lazima iwe aliyo-upload YEYE
    $stmt = $pdo->prepare('SELECT photo_url FROM student_photos WHERE id = ? AND uploaded_by = ?');
    $stmt->execute([$photo_id, $teacher_id]);
    if ($row = $stmt->fetch()) {
        $abs = dirname(__DIR__) . '/' . $row['photo_url'];
        if (is_file($abs)) @unlink($abs);                      // futa faili
        $pdo->prepare('DELETE FROM student_photos WHERE id = ?')->execute([$photo_id]);
        set_flash('success', 'Picha imefutwa.');
    } else {
        set_flash('error', 'Huna kibali cha kufuta picha hii.');
    }
    redirect('/teacher/upload_photos.php' . ($class_id ? "?class_id=$class_id" : ''));
}

/* ---------- Picha zake za hivi karibuni ---------- */
$stmt = $pdo->prepare(
    "SELECT sp.id, sp.photo_url, sp.caption, sp.category, sp.created_at,
            st.full_name AS student, st.reg_no, c.name AS class_name
     FROM student_photos sp
     LEFT JOIN students st ON st.id = sp.student_id
     LEFT JOIN classes c   ON c.id = st.class_id
     WHERE sp.uploaded_by = ?
     ORDER BY sp.created_at DESC
     LIMIT 12"
);
$stmt->execute([$teacher_id]);
$my_photos = $stmt->fetchAll();

$cat_labels = ['profile' => 'Profile', 'activity' => 'Shughuli', 'event' => 'Tukio', 'academic' => 'Kimasomo'];

$page_title = 'Picha';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
  <div class="card-header"><h2>&#128248; Pakia Picha Mpya</h2></div>

  <?php if (!$my_classes): ?>
    <div class="empty-state"><div class="icon">&#127979;</div>Huna madarasa yanayohusika bado.</div>
  <?php else: ?>
    <form method="GET" action="">
      <div class="filter-bar">
        <div class="form-group" style="max-width:280px;">
          <label>Darasa *</label>
          <select name="class_id" required>
            <?php foreach ($my_classes as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= $class_id === (int)$c['id'] ? 'selected' : '' ?>>
                <?= e($c['name']) ?><?= $c['stream'] ? ' (' . e($c['stream']) . ')' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn">Fungua</button>
      </div>
    </form>

    <?php if ($class_id): ?>
      <form method="POST" action="" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="upload">
        <input type="hidden" name="class_id" value="<?= $class_id ?>">

        <div class="form-grid">
          <div class="form-group">
            <label>Mwanafunzi (hiari)</label>
            <select name="student_id">
              <option value="">— shughuli ya darasa (kwa wote) —</option>
              <?php foreach ($students as $st): ?>
                <option value="<?= (int)$st['id'] ?>"><?= e($st['full_name']) ?> (<?= e($st['reg_no']) ?>)</option>
              <?php endforeach; ?>
            </select>
            <span class="hint">Acha wazi kama ni picha ya shughuli za darasa mzima.</span>
          </div>

          <div class="form-group">
            <label>Category *</label>
            <select name="category" required>
              <option value="activity">Shughuli</option>
              <option value="event">Tukio</option>
              <option value="academic">Kimasomo</option>
              <option value="profile">Profile</option>
            </select>
          </div>

          <div class="form-group">
            <label>Picha (JPG/PNG, max 2MB) *</label>
            <input type="file" name="photo" accept=".jpg,.jpeg,.png,image/jpeg,image/png" required>
          </div>

          <div class="form-group full">
            <label>Maelezo (Caption)</label>
            <input type="text" name="caption" maxlength="255" placeholder="mf: Wanafunzi darasani wakifanya mazoezi ya sayansi">
          </div>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn">Pakia Picha</button>
        </div>
      </form>
    <?php endif; ?>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-header"><h2>&#128444; Picha Zangu za Karibuni (<?= count($my_photos) ?>)</h2></div>

  <?php if (!$my_photos): ?>
    <div class="empty-state"><div class="icon">&#128248;</div>Hujawahi pakia picha bado.</div>
  <?php else: ?>
    <div class="photo-grid">
      <?php foreach ($my_photos as $p): ?>
        <figure>
          <img src="<?= url('/' . ltrim($p['photo_url'], '/')) ?>" alt="<?= e($p['caption'] ?: 'Picha') ?>">
          <figcaption>
            <span class="badge badge-muted"><?= e($cat_labels[$p['category']] ?? $p['category']) ?></span><br>
            <?php if ($p['student']): ?><strong><?= e($p['student']) ?></strong><br><?php endif; ?>
            <?= e($p['caption'] ?: '—') ?><br>
            <small><?= e(date('d/m/Y', strtotime($p['created_at']))) ?></small><br>
            <form method="POST" action="" style="margin-top:6px">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="photo_id" value="<?= (int)$p['id'] ?>">
              <?php if ($class_id): ?><input type="hidden" name="class_id" value="<?= $class_id ?>"><?php endif; ?>
              <button type="submit" class="del js-confirm" style="background:none;border:0;cursor:pointer;font-weight:600;font-size:12.5px;color:#dc2626"
                      data-confirm="Futa picha hii kabisa?">Futa</button>
            </form>
          </figcaption>
        </figure>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
