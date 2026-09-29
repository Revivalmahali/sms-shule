<?php
/**
 * admin/announcements.php
 * Admin kutuma na kusimamia TAARIFA/MATANGAZO.
 * Target: all | parents | teachers | students (+ darasa maalum au shule nzima)
 */
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$errors = [];
$edit_id = (int)($_GET['edit'] ?? 0);
$form = ['title' => '', 'message' => '', 'target' => 'all', 'class_id' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    /* ---------- KUFUTA ---------- */
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare('DELETE FROM announcements WHERE id = ?')->execute([$id]);
        set_flash('success', 'Taarifa imefutwa.');
        redirect('/admin/announcements.php');
    }

    /* ---------- KUHIFADHI ---------- */
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $form['title']   = trim($_POST['title'] ?? '');
        $form['message'] = trim($_POST['message'] ?? '');
        $form['target']  = $_POST['target'] ?? 'all';
        $form['class_id'] = $_POST['class_id'] ?? '';

        if (mb_strlen($form['title']) < 3)   $errors[] = 'Kichwa cha taarifa kinahitajika.';
        if (mb_strlen($form['message']) < 5) $errors[] = 'Ujumbe wa taarifa ni mfupi sana (angalau herufi 5).';
        if (!in_array($form['target'], ['all', 'parents', 'teachers', 'students'], true))
                                             $errors[] = 'Chagua watalaumi sahihi.';

        if (!$errors) {
            $classId = $form['class_id'] !== '' ? (int)$form['class_id'] : null;
            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE announcements SET title = ?, message = ?, target = ?, class_id = ? WHERE id = ?');
                $stmt->execute([$form['title'], $form['message'], $form['target'], $classId, $id]);
                set_flash('success', 'Taarifa imehaririwa.');
            } else {
                $stmt = $pdo->prepare('INSERT INTO announcements (title, message, target, class_id, created_by) VALUES (?, ?, ?, ?, ?)');
                $stmt->execute([$form['title'], $form['message'], $form['target'], $classId, current_user()['id']]);
                set_flash('success', 'Taarifa imetumwa kikamilifu!');
            }
            redirect('/admin/announcements.php');
        }
    }
}

/* ---------- Kupakia taarifa inayohaririwa ---------- */
if ($edit_id) {
    $stmt = $pdo->prepare('SELECT * FROM announcements WHERE id = ? LIMIT 1');
    $stmt->execute([$edit_id]);
    if ($row = $stmt->fetch()) {
        $form = array_merge($form, array_intersect_key($row, $form));
        $form['class_id'] = (string)($row['class_id'] ?? '');
    } else {
        $edit_id = 0;
    }
}

/* ---------- Madarasa kwa dropdown ---------- */
$classes = $pdo->query('SELECT id, name, stream FROM classes ORDER BY name, stream')->fetchAll();

/* ---------- Orodha ya taarifa zote ---------- */
$announcements = $pdo->query(
    "SELECT a.*, c.name AS class_name, u.full_name AS author
     FROM announcements a
     LEFT JOIN classes c ON c.id = a.class_id
     JOIN users u ON u.id = a.created_by
     ORDER BY a.created_at DESC"
)->fetchAll();

$page_title = 'Matangazo';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($errors): ?>
  <div class="alert alert-error">
    <?php foreach ($errors as $err): ?><?= e($err) ?><br><?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="card">
  <div class="card-header">
    <h2><?= $edit_id ? '&#9998; Hariri Taarifa' : '&#128226; Tuma Taarifa Mpya' ?></h2>
    <?php if ($edit_id): ?><a class="btn btn-sm btn-secondary" href="<?= url('/admin/announcements.php') ?>">Ghairi / Tuma mpya</a><?php endif; ?>
  </div>

  <form method="POST" action="">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= $edit_id ?>">

    <div class="form-grid">
      <div class="form-group full">
        <label>Kichwa cha Taarifa *</label>
        <input type="text" name="title" value="<?= e($form['title']) ?>" placeholder="mf: Mkutano wa Wazazi" required>
      </div>
      <div class="form-group">
        <label>Watalaumi *</label>
        <select name="target" required>
          <option value="all"      <?= $form['target'] === 'all'      ? 'selected' : '' ?>>Wote (shule nzima)</option>
          <option value="parents"  <?= $form['target'] === 'parents'  ? 'selected' : '' ?>>Wazazi tu</option>
          <option value="teachers" <?= $form['target'] === 'teachers' ? 'selected' : '' ?>>Walimu tu</option>
          <option value="students" <?= $form['target'] === 'students' ? 'selected' : '' ?>>Wanafunzi tu</option>
        </select>
      </div>
      <div class="form-group">
        <label>Darasa (hiari)</label>
        <select name="class_id">
          <option value="">— shule nzima —</option>
          <?php foreach ($classes as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= (string)$form['class_id'] === (string)$c['id'] ? 'selected' : '' ?>>
              <?= e($c['name']) ?><?= $c['stream'] ? ' (' . e($c['stream']) . ')' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group full">
        <label>Ujumbe *</label>
        <textarea name="message" required><?= e($form['message']) ?></textarea>
      </div>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn"><?= $edit_id ? 'Hifadhi Mabadiliko' : 'Tuma Taarifa' ?></button>
    </div>
  </form>
</div>

<div class="card">
  <div class="card-header"><h2>&#128226; Taarifa Zote (<?= count($announcements) ?>)</h2></div>

  <?php if (!$announcements): ?>
    <div class="empty-state"><div class="icon">&#128226;</div>Hakuna taarifa zilizotumwa bado.</div>
  <?php else: foreach ($announcements as $a): ?>
    <div class="announce-item">
      <h3><?= e($a['title']) ?></h3>
      <div class="announce-meta">
        Kwa: <span class="badge badge-info"><?= e(ucfirst($a['target'])) ?></span>
        <?php if ($a['class_name']): ?> | Darasa: <?= e($a['class_name']) ?><?php endif; ?>
        | <?= e($a['author']) ?> | <?= e(date('d/m/Y H:i', strtotime($a['created_at']))) ?>
        | <a href="<?= url('/admin/announcements.php?edit=' . (int)$a['id']) ?>">Hariri</a>
        <form method="POST" action="" style="display:inline">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
          <button type="submit" class="del js-confirm" style="background:none;border:0;cursor:pointer;font-weight:600;font-size:13px;color:#dc2626"
                  data-confirm="Futa taarifa '<?= e($a['title']) ?>'?">Futa</button>
        </form>
      </div>
      <div class="comment-text"><?= e($a['message']) ?></div>
    </div>
  <?php endforeach; endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
