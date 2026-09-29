<?php
/**
 * teacher/comments.php
 * Kutoa COMMENTS kwa wanafunzi:
 *   subject  -> mwalimu wa somo (inonekana kwa mzazi + mwanafunzi)
 *   general  -> mwalimu wa taaluma (mzazi + mwanafunzi)
 *   behavior -> mwalimu yeyote (MZAZI TU — hauonyeshwi mwanafunzi)
 *   head     -> mwalimu wa taaluma, kwa niaba ya uongozi (mzazi + mwanafunzi)
 * Isolation: mwalimu anachagua madarasa yanayohusika naye TU.
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
$teacher_id   = (int)$teacher['id'];
$is_class_teacher = $teacher['teacher_type'] === 'class';

/* ---------- Madarasa yanayohusika na mwalimu huyu TU ---------- */
$stmt = $pdo->prepare('SELECT DISTINCT class_id FROM subjects WHERE teacher_id = ?');
$stmt->execute([$teacher_id]);
$classIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
if ($is_class_teacher) {
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

$class_id  = (int)($_REQUEST['class_id'] ?? 0);
$valid_class = false;
foreach ($my_classes as $c) {
    if ((int)$c['id'] === $class_id) { $valid_class = true; break; }
}
if (!$valid_class) $class_id = 0;

/* ---------- Masomo yangu ya darasa hili (kwa subject comments) ---------- */
$my_subjects = [];
if ($class_id) {
    $stmt = $pdo->prepare('SELECT id, name, code FROM subjects WHERE teacher_id = ? AND class_id = ? ORDER BY name');
    $stmt->execute([$teacher_id, $class_id]);
    $my_subjects = $stmt->fetchAll();
}

/* ---------- KUHIFADHI COMMENT ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $class_id) {
    verify_csrf();

    $student_id   = (int)($_POST['student_id'] ?? 0);
    $comment_type = $_POST['comment_type'] ?? '';
    $subject_id   = (int)($_POST['subject_id'] ?? 0) ?: null;
    $comment_text = trim($_POST['comment'] ?? '');
    $term         = max(1, min(3, (int)($_POST['term'] ?? 1)));
    $year         = max(2000, min(2100, (int)($_POST['year'] ?? date('Y'))));

    // Mwanafunzi lazima awe wa darasa halali (isolation)
    $stmt = $pdo->prepare("SELECT id FROM students WHERE id = ? AND class_id = ? AND status = 'active'");
    $stmt->execute([$student_id, $class_id]);
    if (!$stmt->fetch()) {
        set_flash('error', 'Mwanafunzi si sahihi au si wa darasa hili.');
        redirect("/teacher/comments.php?class_id=$class_id");
    }

    // Sheria za aina ya comment
    $allowed_types = ['behavior'];
    if ($is_class_teacher) $allowed_types[] = 'general';
    if ($is_class_teacher) $allowed_types[] = 'head';
    if ($my_subjects)      $allowed_types[] = 'subject';

    if (!in_array($comment_type, $allowed_types, true)) {
        set_flash('error', 'Huna kibali cha kuandika aina hii ya comment.');
        redirect("/teacher/comments.php?class_id=$class_id");
    }

    if ($comment_type === 'subject') {
        // Somo lazima liwe chake na liwe la darasa hili
        $ok = false;
        foreach ($my_subjects as $s) {
            if ((int)$s['id'] === (int)$subject_id) { $ok = true; break; }
        }
        if (!$ok) {
            set_flash('error', 'Chagua somo chako sahihi kwa comment ya somo.');
            redirect("/teacher/comments.php?class_id=$class_id");
        }
    } else {
        $subject_id = null;   // general/behavior/head hazina somo
    }

    if (mb_strlen($comment_text) < 5) {
        set_flash('error', 'Comment ni fupi sana (angalau herufi 5).');
        redirect("/teacher/comments.php?class_id=$class_id");
    }

    $stmt = $pdo->prepare(
        'INSERT INTO comments (student_id, teacher_id, subject_id, comment_type, comment, term, year)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$student_id, $teacher_id, $subject_id, $comment_type, $comment_text, $term, $year]);

    set_flash('success', 'Comment imehifadhiwa na kutaonekana kwenye dashibodi ya mzazi.');
    redirect("/teacher/comments.php?class_id=$class_id");
}

/* ---------- Wanafunzi wa darasa ---------- */
$students = [];
if ($class_id) {
    $stmt = $pdo->prepare("SELECT id, full_name, reg_no FROM students WHERE class_id = ? AND status = 'active' ORDER BY full_name");
    $stmt->execute([$class_id]);
    $students = $stmt->fetchAll();
}

/* ---------- Comments alizoandika mwalimu huyu (za hivi karibuni) ---------- */
$stmt = $pdo->prepare(
    "SELECT c.comment_type, c.comment, c.term, c.year, c.created_at,
            st.full_name AS student, st.reg_no, s.name AS subject
     FROM comments c
     JOIN students st ON st.id = c.student_id
     LEFT JOIN subjects s ON s.id = c.subject_id
     WHERE c.teacher_id = ?
     ORDER BY c.created_at DESC
     LIMIT 10"
);
$stmt->execute([$teacher_id]);
$my_comments = $stmt->fetchAll();

$type_labels = ['subject' => 'Somo', 'general' => 'Jumla', 'behavior' => 'Tabia', 'head' => 'Mkuu'];

$page_title = 'Comments';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
  <div class="card-header"><h2>&#128172; Andika Comment Mpya</h2></div>

  <?php if (!$my_classes): ?>
    <div class="empty-state"><div class="icon">&#127979;</div>Huna madarasa yanayohusika bado.</div>
  <?php else: ?>
    <!-- Hatua 1: chagua darasa -->
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

    <?php if ($class_id && $students): ?>
      <!-- Hatua 2: comment form -->
      <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="class_id" value="<?= $class_id ?>">

        <div class="form-grid">
          <div class="form-group">
            <label>Mwanafunzi *</label>
            <select name="student_id" required>
              <option value="">— chagua mwanafunzi —</option>
              <?php foreach ($students as $st): ?>
                <option value="<?= (int)$st['id'] ?>"><?= e($st['full_name']) ?> (<?= e($st['reg_no']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label>Aina ya Comment *</label>
            <select name="comment_type" id="comment_type" required>
              <?php if ($my_subjects): ?><option value="subject">Comment ya Somo</option><?php endif; ?>
              <?php if ($is_class_teacher): ?><option value="general">Comment ya Jumla</option><?php endif; ?>
              <option value="behavior">Comment ya Tabia (mzazi TU)</option>
              <?php if ($is_class_teacher): ?><option value="head">Comment ya Mkuu (uongozi)</option><?php endif; ?>
            </select>
          </div>

          <div class="form-group" id="subject_group">
            <label>Somo (kwa comment ya somo)</label>
            <select name="subject_id">
              <option value="">— si lazima —</option>
              <?php foreach ($my_subjects as $s): ?>
                <option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?> (<?= e($s['code']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label>Term</label>
            <select name="term">
              <?php for ($i = 1; $i <= 3; $i++): ?>
                <option value="<?= $i ?>" <?= $i === 1 ? 'selected' : '' ?>>Term <?= $i ?></option>
              <?php endfor; ?>
            </select>
          </div>

          <div class="form-group">
            <label>Mwaka</label>
            <input type="number" name="year" value="<?= date('Y') ?>" min="2000" max="2100" required>
          </div>

          <div class="form-group full">
            <label>Comment *</label>
            <textarea name="comment" minlength="5" required
                      placeholder="mf: Mwanafunzi anajitahidi sana darasani, aendelee hivyo."></textarea>
            <span class="hint">Comment ya <strong>Tabia</strong> itaonekana kwa MZAZI TU — hairudhishwi mwanafunzi.</span>
          </div>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn">Hifadhi Comment</button>
        </div>
      </form>
    <?php elseif ($class_id): ?>
      <div class="empty-state">Darasa hili halina wanafunzi bado.</div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-header"><h2>&#128203; Comments Zangu za Karibuni (<?= count($my_comments) ?>)</h2></div>

  <?php if (!$my_comments): ?>
    <div class="empty-state"><div class="icon">&#128172;</div>Hujawahi andika comment bado.</div>
  <?php else: foreach ($my_comments as $c): ?>
    <div class="comment-item">
      <div class="comment-meta">
        <span class="badge badge-info"><?= e($type_labels[$c['comment_type']] ?? $c['comment_type']) ?></span>
        <strong><?= e($c['student']) ?></strong> (<?= e($c['reg_no']) ?>)
        <?php if ($c['subject']): ?>| <?= e($c['subject']) ?><?php endif; ?>
        | Term <?= (int)$c['term'] ?>, <?= (int)$c['year'] ?>
        | <?= e(date('d/m/Y', strtotime($c['created_at']))) ?>
      </div>
      <div class="comment-text"><?= e($c['comment']) ?></div>
    </div>
  <?php endforeach; endif; ?>
</div>

<script>
/* Ficha "Somo" ukiandika aina isiyohitaji somo */
var typeSel = document.getElementById('comment_type');
if (typeSel) {
  var subjGroup = document.getElementById('subject_group');
  function toggleSubject() {
    if (subjGroup) subjGroup.style.display = (typeSel.value === 'subject') ? '' : 'none';
  }
  typeSel.addEventListener('change', toggleSubject);
  toggleSubject();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
