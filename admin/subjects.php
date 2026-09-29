<?php
/**
 * admin/subjects.php
 * CRUD ya Masomo: jina, code, darasa + mwalimu wa somo.
 * Kufuta kunazuiwa kama somo lina matokeo yaliyowekwa.
 */
require_once __DIR__ . '/../includes/auth.php';
require_admin();

$errors = [];
$edit_id = (int)($_GET['edit'] ?? 0);
$form = ['name' => '', 'code' => '', 'class_id' => '', 'teacher_id' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    /* ---------- KUFUTA ---------- */
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $chk = $pdo->prepare('SELECT COUNT(*) FROM results WHERE subject_id = ?');
        $chk->execute([$id]);
        if ((int)$chk->fetchColumn() > 0) {
            set_flash('error', 'Somo lina matokeo yaliyowekwa — huwezi kulifuta. Futa matokeo kwanza au liache litumike.');
        } else {
            $pdo->prepare('DELETE FROM subjects WHERE id = ?')->execute([$id]);
            set_flash('success', 'Somo limefutwa.');
        }
        redirect('/admin/subjects.php');
    }

    /* ---------- KUHIFADHI ---------- */
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $form['name']      = trim($_POST['name'] ?? '');
        $form['code']      = strtoupper(trim($_POST['code'] ?? ''));
        $form['class_id']  = (int)($_POST['class_id'] ?? 0);
        $form['teacher_id'] = (int)($_POST['teacher_id'] ?? 0);

        if (mb_strlen($form['name']) < 2)  $errors[] = 'Jina la somo linahitajika.';
        if ($form['code'] === '')          $errors[] = 'Code ya somo inahitajika (mf: KIS).';
        if (!$form['class_id'])            $errors[] = 'Chagua darasa.';
        if (!$form['teacher_id'])          $errors[] = 'Chagua mwalimu wa somo.';

        if (!$errors) {
            try {
                if ($id > 0) {
                    $stmt = $pdo->prepare('UPDATE subjects SET name = ?, code = ?, class_id = ?, teacher_id = ? WHERE id = ?');
                    $stmt->execute([$form['name'], $form['code'], $form['class_id'], $form['teacher_id'], $id]);
                    set_flash('success', 'Somo limehaririwa.');
                } else {
                    $stmt = $pdo->prepare('INSERT INTO subjects (name, code, class_id, teacher_id) VALUES (?, ?, ?, ?)');
                    $stmt->execute([$form['name'], $form['code'], $form['class_id'], $form['teacher_id']]);
                    set_flash('success', 'Somo jipya limeongezwa.');
                }
                redirect('/admin/subjects.php');
            } catch (PDOException $ex) {
                $errors[] = ((int)$ex->getCode() === 23000)
                    ? 'Somo lenye jina kama hili lipo tayari kwenye darasa hili.'
                    : 'Samahani, hifadhi haikufanikiwa. Jaribu tena.';
            }
        }
    }
}

/* ---------- Kupakia somo analohaririwa ---------- */
if ($edit_id) {
    $stmt = $pdo->prepare('SELECT * FROM subjects WHERE id = ? LIMIT 1');
    $stmt->execute([$edit_id]);
    if ($row = $stmt->fetch()) {
        $form = array_merge($form, array_intersect_key($row, $form));
    } else {
        $edit_id = 0;
    }
}

/* ---------- Madarasa + walimu kwa dropdowns ---------- */
$classes = $pdo->query('SELECT id, name, stream FROM classes ORDER BY name, stream')->fetchAll();
$teachers = $pdo->query(
    'SELECT t.id, u.full_name, t.employee_no
     FROM teachers t JOIN users u ON u.id = t.user_id
     ORDER BY u.full_name'
)->fetchAll();

/* ---------- Orodha ya masomo ---------- */
$subjects = $pdo->query(
    "SELECT s.*, c.name AS class_name, c.stream,
            u.full_name AS teacher_name,
            (SELECT COUNT(*) FROM results r WHERE r.subject_id = s.id) AS results_count
     FROM subjects s
     JOIN classes c  ON c.id = s.class_id
     JOIN teachers t ON t.id = s.teacher_id
     JOIN users u    ON u.id = t.user_id
     ORDER BY c.name, s.name"
)->fetchAll();

$page_title = 'Masomo';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($errors): ?>
  <div class="alert alert-error">
    <?php foreach ($errors as $err): ?><?= e($err) ?><br><?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="card">
  <div class="card-header">
    <h2><?= $edit_id ? '&#9998; Hariri Somo' : '&#10133; Ongeza Somo Jipya' ?></h2>
    <?php if ($edit_id): ?><a class="btn btn-sm btn-secondary" href="<?= url('/admin/subjects.php') ?>">Ghairi / Ongeza mpya</a><?php endif; ?>
  </div>

  <form method="POST" action="">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= $edit_id ?>">

    <div class="form-grid">
      <div class="form-group">
        <label>Jina la Somo *</label>
        <input type="text" name="name" value="<?= e($form['name']) ?>" placeholder="mf: Kiswahili" required>
      </div>
      <div class="form-group">
        <label>Code ya Somo *</label>
        <input type="text" name="code" value="<?= e($form['code']) ?>" placeholder="mf: KIS" maxlength="10" required>
      </div>
      <div class="form-group">
        <label>Darasa *</label>
        <select name="class_id" required>
          <option value="">— chagua darasa —</option>
          <?php foreach ($classes as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= (string)$form['class_id'] === (string)$c['id'] ? 'selected' : '' ?>>
              <?= e($c['name']) ?><?= $c['stream'] ? ' (' . e($c['stream']) . ')' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Mwalimu wa Somo *</label>
        <select name="teacher_id" required>
          <option value="">— chagua mwalimu —</option>
          <?php foreach ($teachers as $t): ?>
            <option value="<?= (int)$t['id'] ?>" <?= (string)$form['teacher_id'] === (string)$t['id'] ? 'selected' : '' ?>>
              <?= e($t['full_name']) ?> (<?= e($t['employee_no']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn"><?= $edit_id ? 'Hifadhi Mabadiliko' : 'Ongeza Somo' ?></button>
    </div>
  </form>
</div>

<div class="card">
  <div class="card-header"><h2>&#128218; Masomo Yote (<?= count($subjects) ?>)</h2></div>

  <div class="table-wrap">
    <table class="data">
      <thead>
        <tr><th>Somo</th><th>Code</th><th>Darasa</th><th>Mwalimu wa Somo</th><th>Matokeo</th><th>Vitendo</th></tr>
      </thead>
      <tbody>
        <?php if (!$subjects): ?>
          <tr><td colspan="6"><div class="empty-state">Hakuna masomo bado.</div></td></tr>
        <?php else: foreach ($subjects as $s): ?>
          <tr>
            <td><strong><?= e($s['name']) ?></strong></td>
            <td><span class="badge badge-muted"><?= e($s['code']) ?></span></td>
            <td><?= e($s['class_name']) ?><?= $s['stream'] ? ' (' . e($s['stream']) . ')' : '' ?></td>
            <td><?= e($s['teacher_name']) ?></td>
            <td><span class="badge badge-info"><?= (int)$s['results_count'] ?></span></td>
            <td class="table-actions">
              <a href="<?= url('/admin/subjects.php?edit=' . (int)$s['id']) ?>">Hariri</a>
              <form method="POST" action="" style="display:inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                <button type="submit" class="del js-confirm" style="background:none;border:0;cursor:pointer;font-weight:600;font-size:13px"
                        data-confirm="Unakaribia KUFUTA somo <?= e($s['name']) ?> (<?= e($s['class_name']) ?>). Una uhakika?">Futa</button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
