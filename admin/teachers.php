<?php
/**
 * admin/teachers.php
 * CRUD ya Walimu: kuongeza, kuhariri na kufuta.
 * Kila mwalimu ana user (role='teacher') + record kwenye teachers.
 */
require_once __DIR__ . '/../includes/auth.php';
require_admin();

/**
 * next_employee_no(): kutengeneza namba mpya ya mfanyakazi automatic.
 * Inataa namba kubwa zaidi iliyopo (mf: EMP-003 -> EMP-004).
 */
function next_employee_no(PDO $pdo): string {
    $rows = $pdo->query('SELECT employee_no FROM teachers')->fetchAll(PDO::FETCH_COLUMN);
    $max = 0;
    foreach ($rows as $no) {
        if (preg_match('/(\\d+)\\s*$/', (string)$no, $m)) {
            $max = max($max, (int)$m[1]);
        }
    }
    return 'EMP-' . str_pad((string)($max + 1), 3, '0', STR_PAD_LEFT);
}

$errors = [];
$edit_id = (int)($_GET['edit'] ?? 0);

// Thamani za form (zinatunzwa ukiwa na makosa au unahariri)
$form = [
    'full_name' => '', 'email' => '', 'phone' => '',
    'employee_no' => '', 'teacher_type' => 'subject', 'qualification' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    /* ---------- KUFUTA ---------- */
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);

        // Usalama wa data: usimfute mwalimu anayefundisha masomo (matokeo yangeondoka)
        $chk = $pdo->prepare('SELECT COUNT(*) FROM subjects WHERE teacher_id = ?');
        $chk->execute([$id]);
        if ((int)$chk->fetchColumn() > 0) {
            set_flash('error', 'Hakuwezi kumfuta mwalimu anayefundisha masomo. Hamisha au fute masomo yake kwanza.');
        } else {
            // users deletion inamwondoa teachers kwa CASCADE
            $del = $pdo->prepare("DELETE FROM users WHERE id = (SELECT user_id FROM teachers WHERE id = ?) AND role = 'teacher'");
            $del->execute([$id]);
            set_flash('success', 'Mwalimu amefutwa kikamilifu.');
        }
        redirect('/admin/teachers.php');
    }

    /* ---------- KUHIFADHI (ongeza au hariri) ---------- */
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        foreach ($form as $k => $_) {
            $form[$k] = trim($_POST[$k] ?? '');
        }
        $password = $_POST['password'] ?? '';

        // Uhakiki wa server
        if (mb_strlen($form['full_name']) < 3)               $errors[] = 'Jina kamili linahitaji angalau herufi 3.';
        if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Barua pepe si sahihi.';

        // Namba ya mfanyakazi: ikiliach wazi inajaza automatic (kuwahi zote zilizopo)
        if (!$id && $form['employee_no'] === '') {
            $form['employee_no'] = next_employee_no($pdo);
        }
        if ($form['employee_no'] === '')                     $errors[] = 'Namba ya mfanyakazi inahitajika.';
        if (!array_key_exists($form['teacher_type'], teacher_types()))
                                                             $errors[] = 'Chagua aina ya mwalimu sahihi.';
        if (!$id && strlen($password) < 6)                    $errors[] = 'Nywira inahitaji angalau herufi 6.';
        if ($id && $password !== '' && strlen($password) < 6) $errors[] = 'Nywira mpya inahitaji angalau herufi 6.';

        if (!$errors) {
            try {
                $pdo->beginTransaction();

                if ($id > 0) {
                    // HARIRI: users kwanza, kisha teachers
                    if ($password !== '') {
                        $stmt = $pdo->prepare('UPDATE users SET full_name = ?, email = ?, phone = ?, password = ? WHERE id = ?');
                        $stmt->execute([$form['full_name'], $form['email'], $form['phone'],
                                        password_hash($password, PASSWORD_DEFAULT),
                                        $pdo->query('SELECT user_id FROM teachers WHERE id = ' . $id)->fetchColumn()]);
                    } else {
                        $stmt = $pdo->prepare('UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?');
                        $stmt->execute([$form['full_name'], $form['email'], $form['phone'],
                                        $pdo->query('SELECT user_id FROM teachers WHERE id = ' . $id)->fetchColumn()]);
                    }
                    $stmt = $pdo->prepare('UPDATE teachers SET employee_no = ?, teacher_type = ?, qualification = ? WHERE id = ?');
                    $stmt->execute([$form['employee_no'], $form['teacher_type'], $form['qualification'], $id]);
                    $msg = 'Taarifa za mwalimu zimehifadhiwa.';
                } else {
                    // ONGEZA: user mpya (role teacher) + teachers
                    $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role, phone, status)
                                           VALUES (?, ?, ?, 'teacher', ?, 'active')");
                    $stmt->execute([$form['full_name'], $form['email'],
                                    password_hash($password, PASSWORD_DEFAULT), $form['phone']]);
                    $userId = (int)$pdo->lastInsertId();

                    $stmt = $pdo->prepare('INSERT INTO teachers (user_id, employee_no, teacher_type, qualification)
                                           VALUES (?, ?, ?, ?)');
                    $stmt->execute([$userId, $form['employee_no'], $form['teacher_type'], $form['qualification']]);
                    $msg = 'Mwalimu mpya ameongezwa kikamilifu.';
                }

                $pdo->commit();
                set_flash('success', $msg);
                redirect('/admin/teachers.php');
            } catch (PDOException $ex) {
                $pdo->rollBack();
                $errors[] = ((int)$ex->getCode() === 23000)
                    ? 'Barua pepe au namba ya mfanyakazi imetumika na mwingine.'
                    : 'Samahani, hifadhi haikufanikiwa. Jaribu tena.';
            }
        }
    }
}

/* ---------- Kupakia mwalimu anayehaririwa ---------- */
if ($edit_id) {
    $stmt = $pdo->prepare(
        "SELECT t.id, t.employee_no, t.teacher_type, t.qualification,
                u.full_name, u.email, u.phone
         FROM teachers t JOIN users u ON u.id = t.user_id
         WHERE t.id = ? LIMIT 1"
    );
    $stmt->execute([$edit_id]);
    if ($row = $stmt->fetch()) {
        $form = array_merge($form, array_intersect_key($row, $form));
    } else {
        $edit_id = 0;
    }
}

/* ---------- Namba ya mfanyakazi inayofuata (kwa ajili ya form mpya) ---------- */
$next_emp = next_employee_no($pdo);

/* ---------- Orodha ya walimu wote ---------- */
$teachers = $pdo->query(
    "SELECT t.id, t.employee_no, t.teacher_type, t.qualification,
            u.full_name, u.email, u.phone, u.status,
            (SELECT COUNT(*) FROM subjects s WHERE s.teacher_id = t.id) AS subjects_count,
            (SELECT COUNT(*) FROM classes c WHERE c.class_teacher_id = t.id) AS classes_count
     FROM teachers t JOIN users u ON u.id = t.user_id
     ORDER BY u.full_name"
)->fetchAll();

$page_title = 'Walimu';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($errors): ?>
  <div class="alert alert-error">
    <?php foreach ($errors as $err): ?><?= e($err) ?><br><?php endforeach; ?>
  </div>
<?php endif; ?>

<div class="card">
  <div class="card-header">
    <h2><?= $edit_id ? '&#9998; Hariri Mwalimu' : '&#10133; Ongeza Mwalimu Mpya' ?></h2>
    <?php if ($edit_id): ?><a class="btn btn-sm btn-secondary" href="<?= url('/admin/teachers.php') ?>">Ghairi / Ongeza mpya</a><?php endif; ?>
  </div>

  <form method="POST" action="">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="id" value="<?= $edit_id ?>">

    <div class="form-grid">
      <div class="form-group">
        <label>Jina Kamili *</label>
        <input type="text" name="full_name" value="<?= e($form['full_name']) ?>" minlength="3" required>
      </div>
      <div class="form-group">
        <label>Barua Pepe *</label>
        <input type="email" name="email" value="<?= e($form['email']) ?>" required>
      </div>
      <div class="form-group">
        <label>Namba ya Simu</label>
        <input type="tel" name="phone" value="<?= e($form['phone']) ?>" placeholder="mf: +255700123456">
      </div>
      <div class="form-group">
        <label>Namba ya Mfanyakazi <?= $edit_id ? '*' : '(inajaza automatic)' ?></label>
        <input type="text" name="employee_no" value="<?= e($edit_id ? $form['employee_no'] : ($form['employee_no'] ?: $next_emp)) ?>"
               placeholder="mf: EMP-003" required>
        <span class="hint"><?= $edit_id ? 'Badilisha kwa makusudi tu.' : 'Imetaenguliwa kiotomatiki — unaweza kubadilisha kama inahitaji.' ?></span>
      </div>
      <div class="form-group">
        <label>Aina ya Mwalimu *</label>
        <select name="teacher_type" required>
          <?php foreach (teacher_types() as $tkey => $tlabel): ?>
            <option value="<?= e($tkey) ?>" <?= $form['teacher_type'] === $tkey ? 'selected' : '' ?>><?= e($tlabel) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Elimu / Qualification</label>
        <input type="text" name="qualification" value="<?= e($form['qualification']) ?>" placeholder="mf: Shahada ya Ualimu">
      </div>
      <div class="form-group">
        <label>Nywira <?= $edit_id ? '(acha wazi kama hutaki kubadilisha)' : '*' ?></label>
        <input type="password" name="password" minlength="6" <?= $edit_id ? '' : 'required' ?>>
      </div>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn"><?= $edit_id ? 'Hifadhi Mabadiliko' : 'Ongeza Mwalimu' ?></button>
    </div>
  </form>
</div>

<div class="card">
  <div class="card-header"><h2>&#128100; Orodha ya Walimu (<?= count($teachers) ?>)</h2></div>

  <div class="table-wrap">
    <table class="data">
      <thead>
        <tr><th>Jina</th><th>Barua Pepe</th><th>Emp. No</th><th>Aina</th><th>Masomo</th><th>Darasa</th><th>Hali</th><th>Vitendo</th></tr>
      </thead>
      <tbody>
        <?php if (!$teachers): ?>
          <tr><td colspan="8"><div class="empty-state">Hakuna walimu bado.</div></td></tr>
        <?php else: foreach ($teachers as $t): ?>
          <tr>
            <td><strong><?= e($t['full_name']) ?></strong></td>
            <td><?= e($t['email']) ?></td>
            <td><?= e($t['employee_no']) ?></td>
            <td><span class="badge <?= $t['teacher_type'] === 'subject' ? 'badge-muted' : 'badge-info' ?>">
                  <?= e(teacher_type_label($t['teacher_type'])) ?></span></td>
            <td><?= (int)$t['subjects_count'] ?></td>
            <td><?= (int)$t['classes_count'] ?></td>
            <td><span class="badge <?= $t['status'] === 'active' ? 'att-present' : 'badge-muted' ?>">
                  <?= $t['status'] === 'active' ? 'Hai' : 'Imezimwa' ?></span></td>
            <td class="table-actions">
              <a href="<?= url('/admin/teachers.php?edit=' . (int)$t['id']) ?>">Hariri</a>
              <form method="POST" action="" style="display:inline" >
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
                <button type="submit" class="del js-confirm" style="background:none;border:0;cursor:pointer;font-weight:600;font-size:13px"
                        data-confirm="Unakaribia KUMFUTA mwalimu <?= e($t['full_name']) ?>. Una uhakika?">Futa</button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
