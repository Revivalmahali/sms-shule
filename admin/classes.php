<?php
/**
 * admin/classes.php
 * CRUD ya Madarasa + kumpa Mwalimu wa Taaluma (class teacher).
 * Kufuta kunazuiwa kama darasa lina wanafunzi (usalama wa data).
 */
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$errors = [];
$edit_id = (int)($_GET['edit'] ?? 0);
$form = ['name' => '', 'level' => 'primary', 'stream' => '', 'class_teacher_id' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    /* ---------- KUFUTA ---------- */
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $chk = $pdo->prepare("SELECT COUNT(*) FROM students WHERE class_id = ? AND status = 'active'");
        $chk->execute([$id]);
        $n = (int)$chk->fetchColumn();
        if ($n > 0) {
            set_flash('error', "Darasa lina wanafunzi $n — hamisha wanafunzi kwenye darasa lingine kwanza kabla ya kulifuta.");
        } else {
            $pdo->prepare('DELETE FROM classes WHERE id = ?')->execute([$id]);
            set_flash('success', 'Darasa limefutwa.');
        }
        redirect('/admin/classes.php');
    }

    /* ---------- KUHIFADHI ---------- */
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $form['name']            = trim($_POST['name'] ?? '');
        $form['level']           = $_POST['level'] ?? 'primary';
        $form['stream']          = strtoupper(trim($_POST['stream'] ?? ''));
        $form['class_teacher_id'] = $_POST['class_teacher_id'] ?? '';

        if (mb_strlen($form['name']) < 2)                          $errors[] = 'Jina la darasa linahitajika (mf: Darasa la Tano).';
        if (!in_array($form['level'], ['primary', 'secondary'], true)) $errors[] = 'Chagua kiwango sahihi.';
        if ($form['class_teacher_id'] !== '' && (int)$form['class_teacher_id'] === 0) $errors[] = 'Mwalimu wa taaluma si sahihi.';

        if (!$errors) {
            try {
                $teacherId = $form['class_teacher_id'] !== '' ? (int)$form['class_teacher_id'] : null;
                if ($id > 0) {
                    $stmt = $pdo->prepare('UPDATE classes SET name = ?, level = ?, stream = ?, class_teacher_id = ? WHERE id = ?');
                    $stmt->execute([$form['name'], $form['level'], $form['stream'] ?: null, $teacherId, $id]);
                    set_flash('success', 'Darasa limehaririwa.');
                } else {
                    $stmt = $pdo->prepare('INSERT INTO classes (name, level, stream, class_teacher_id) VALUES (?, ?, ?, ?)');
                    $stmt->execute([$form['name'], $form['level'], $form['stream'] ?: null, $teacherId]);
                    set_flash('success', 'Darasa jipya limeundwa.');
                }
                redirect('/admin/classes.php');
            } catch (PDOException $ex) {
                $errors[] = ((int)$ex->getCode() === 23000)
                    ? 'Darasa lenye jina na stream kama hili lipo tayari.'
                    : 'Samahani, hifadhi haikufanikiwa. Jaribu tena.';
            }
        }
    }
}

/* ---------- Kupakia darasa analohaririwa ---------- */
if ($edit_id) {
    $stmt = $pdo->prepare('SELECT * FROM classes WHERE id = ? LIMIT 1');
    $stmt->execute([$edit_id]);
    if ($row = $stmt->fetch()) {
        $form = array_merge($form, array_intersect_key($row, $form));
        $form['class_teacher_id'] = (string)($row['class_teacher_id'] ?? '');
    } else {
        $edit_id = 0;
    }
}

/* ---------- Walimu kwa dropdown ---------- */
$teacher_list = $pdo->query(
    "SELECT t.id, u.full_name, t.employee_no
     FROM teachers t JOIN users u ON u.id = t.user_id
     ORDER BY u.full_name"
)->fetchAll();

/* ---------- Orodha ya madarasa ---------- */
$classes = $pdo->query(
    "SELECT c.*,
            u.full_name AS class_teacher,
            (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id AND s.status = 'active') AS total_students,
            (SELECT COUNT(*) FROM subjects sub WHERE sub.class_id = c.id) AS total_subjects
     FROM classes c
     LEFT JOIN teachers t ON t.id = c.class_teacher_id
     LEFT JOIN users u    ON u.id = t.user_id
     ORDER BY c.name, c.stream"
)->fetchAll();

$page_title = 'Madarasa';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($errors): ?>
  <div class="alert alert-error">
    <?php foreach ($errors as $err): ?><?= e($err) ?><br><?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="card">
  <div class="card-header">
    <h2><?= $edit_id ? '&#9998; Hariri Darasa' : '&#10133; Unda Darasa Jipya' ?></h2>
    <?php if ($edit_id): ?><a class="btn btn-sm btn-secondary" href="<?= url('/admin/classes.php') ?>">Ghairi / Unda mpya</a><?php endif; ?>
  </div>

  <form method="POST" action="">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= $edit_id ?>">

    <div class="form-grid">
      <div class="form-group">
        <label>Jina la Darasa *</label>
        <input type="text" name="name" value="<?= e($form['name']) ?>" placeholder="mf: Darasa la Tano / Form One" required>
      </div>
      <div class="form-group">
        <label>Kiwango *</label>
        <select name="level" required>
          <option value="primary"   <?= $form['level'] === 'primary'   ? 'selected' : '' ?>>Msingi</option>
          <option value="secondary" <?= $form['level'] === 'secondary' ? 'selected' : '' ?>>Sekondari</option>
        </select>
      </div>
      <div class="form-group">
        <label>Stream</label>
        <input type="text" name="stream" value="<?= e($form['stream']) ?>" placeholder="mf: A" maxlength="10">
      </div>
      <div class="form-group">
        <label>Mwalimu wa Taaluma</label>
        <select name="class_teacher_id">
          <option value="">— hakuna —</option>
          <?php foreach ($teacher_list as $t): ?>
            <option value="<?= (int)$t['id'] ?>" <?= (string)$form['class_teacher_id'] === (string)$t['id'] ? 'selected' : '' ?>>
              <?= e($t['full_name']) ?> (<?= e($t['employee_no']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
        <span class="hint">Mwalimu wa taaluma ndiye atakayeandika general comments.</span>
      </div>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn"><?= $edit_id ? 'Hifadhi Mabadiliko' : 'Unda Darasa' ?></button>
    </div>
  </form>
</div>

<div class="card">
  <div class="card-header"><h2>&#127979; Madarasa Yote (<?= count($classes) ?>)</h2></div>

  <div class="table-wrap">
    <table class="data">
      <thead>
        <tr><th>Darasa</th><th>Kiwango</th><th>Mwalimu wa Taaluma</th><th>Wanafunzi</th><th>Masomo</th><th>Vitendo</th></tr>
      </thead>
      <tbody>
        <?php if (!$classes): ?>
          <tr><td colspan="6"><div class="empty-state">Hakuna madarasa bado.</div></td></tr>
        <?php else: foreach ($classes as $c): ?>
          <tr>
            <td><strong><?= e($c['name']) ?><?= $c['stream'] ? ' (' . e($c['stream']) . ')' : '' ?></strong></td>
            <td><span class="badge badge-muted"><?= $c['level'] === 'primary' ? 'Msingi' : 'Sekondari' ?></span></td>
            <td><?= e($c['class_teacher'] ?? '— hakuna —') ?></td>
            <td><span class="badge badge-info"><?= (int)$c['total_students'] ?></span></td>
            <td><span class="badge badge-muted"><?= (int)$c['total_subjects'] ?></span></td>
            <td class="table-actions">
              <a href="<?= url('/admin/classes.php?edit=' . (int)$c['id']) ?>">Hariri</a>
              <form method="POST" action="" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
                <button type="submit" class="del js-confirm" style="background:none;border:0;cursor:pointer;font-weight:600;font-size:13px"
                        data-confirm="Unakaribia KUFUTA darasa <?= e($c['name']) ?>. Una uhakika?">Futa</button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
