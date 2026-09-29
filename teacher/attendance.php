<?php
/**
 * teacher/attendance.php
 * Kuweka MAHUDHURIO: mwalimu anachagua darasa lake + tarehe,
 * anaweka status (present/absent/late/sick) na remarks kwa kila mwanafunzi.
 * Upsert: mwanafunzi mmoja siku moja = record moja (ON DUPLICATE KEY UPDATE).
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

/* ---------- Madarasa yanayohusika na mwalimu huyu TU ---------- */
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

$class_id = (int)($_GET['class_id'] ?? $_POST['class_id'] ?? 0);
$valid_class = false;
foreach ($my_classes as $c) {
    if ((int)$c['id'] === $class_id) { $valid_class = true; break; }   // ISOLATION
}
if (!$valid_class) $class_id = 0;

$date = $_GET['date'] ?? $_POST['date'] ?? date('Y-m-d');
$dt   = DateTime::createFromFormat('Y-m-d', $date);
if (!$dt || $dt->format('Y-m-d') !== $date || $dt > new DateTime()) {
    $date = date('Y-m-d');   // tarehe batili au ijayo → leo
}

/* ---------- KUHIFADHI MAHUDHURIO ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $class_id) {
    verify_csrf();

    $statuses = ['present', 'absent', 'late', 'sick'];
    $posted   = $_POST['status'] ?? [];
    $remarks  = $_POST['remarks'] ?? [];
    $saved    = 0;

    // Wanafunzi halali wa darasa hili
    $stmt = $pdo->prepare("SELECT id FROM students WHERE class_id = ? AND status = 'active'");
    $stmt->execute([$class_id]);
    $allowed = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    $stmt = $pdo->prepare(
        "INSERT INTO attendance (student_id, date, status, marked_by, remarks)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE status = VALUES(status), remarks = VALUES(remarks), marked_by = VALUES(marked_by)"
    );

    foreach ($posted as $studentId => $status) {
        $studentId = (int)$studentId;
        if (!in_array($studentId, $allowed, true) || !in_array($status, $statuses, true)) continue;
        $remark = trim((string)($remarks[$studentId] ?? ''));
        $stmt->execute([$studentId, $date, $status, $teacher_id, $remark !== '' ? $remark : null]);
        $saved++;
    }

    set_flash('success', "Mahudhurio ya $saved wanafunzi yamehifadhiwa kwa tarehe " . date('d/m/Y', strtotime($date)) . '.');
    redirect("/teacher/attendance.php?class_id=$class_id&date=$date");
}

/* ---------- Wanafunzi + mahudhurio ya tarehe hiyo (kuprefill) ---------- */
$students = [];
$existing = [];
if ($class_id) {
    $stmt = $pdo->prepare(
        "SELECT id, full_name, reg_no FROM students
         WHERE class_id = ? AND status = 'active'
         ORDER BY full_name"
    );
    $stmt->execute([$class_id]);
    $students = $stmt->fetchAll();

    $stmt = $pdo->prepare('SELECT student_id, status, remarks FROM attendance WHERE date = ?');
    $stmt->execute([$date]);
    foreach ($stmt->fetchAll() as $r) {
        $existing[(int)$r['student_id']] = $r;
    }
}

$status_labels = ['present' => 'Yupo', 'absent' => 'Hakupo', 'late' => 'Amchelewa', 'sick' => 'Mgonjwa'];

$page_title = 'Mahudhurio';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
  <div class="card-header"><h2>&#128197; Chagua Darasa na Tarehe</h2></div>

  <?php if (!$my_classes): ?>
    <div class="empty-state"><div class="icon">&#127979;</div>Huna madarasa yanayohusika bado. Wasiliana na msimamizi.</div>
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
        <div class="form-group">
          <label>Tarehe</label>
          <input type="date" name="date" value="<?= e($date) ?>" max="<?= date('Y-m-d') ?>">
        </div>
        <button type="submit" class="btn">Fungua</button>
      </div>
    </form>
  <?php endif; ?>
</div>

<?php if ($class_id && $students): ?>
<div class="card">
  <div class="card-header">
    <h2>&#9989; Mahudhurio ya <?= e(date('d/m/Y', strtotime($date))) ?></h2>
    <span class="badge badge-muted"><?= count($existing) ?> tayari yamewekwa</span>
  </div>

  <form method="POST" action="">
    <?= csrf_field() ?>
    <input type="hidden" name="class_id" value="<?= $class_id ?>">
    <input type="hidden" name="date" value="<?= e($date) ?>">

    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>#</th><th>Mwanafunzi</th><th>Reg No</th><th>Hali</th><th>Maelezo (Remarks)</th></tr></thead>
        <tbody>
          <?php foreach ($students as $i => $st): $ex = $existing[(int)$st['id']] ?? null; ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td><strong><?= e($st['full_name']) ?></strong></td>
              <td><?= e($st['reg_no']) ?></td>
              <td>
                <select name="status[<?= (int)$st['id'] ?>]" style="min-width:130px;">
                  <?php foreach ($status_labels as $key => $label): ?>
                    <option value="<?= $key ?>" <?= ($ex ? $ex['status'] : 'present') === $key ? 'selected' : '' ?>>
                      <?= $label ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </td>
              <td>
                <input type="text" name="remarks[<?= (int)$st['id'] ?>]"
                       value="<?= $ex ? e($ex['remarks'] ?? '') : '' ?>"
                       placeholder="hiari, mf: alifika saa mbili">
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn">Hifadhi Mahudhurio</button>
      <span class="hint">Kuweka tena kwa tarehe ileile kunabadilisha (hairudii mara mbili).</span>
    </div>
  </form>
</div>

<?php elseif ($class_id): ?>
  <div class="card"><div class="empty-state"><div class="icon">&#127891;</div>Darasa hili halina wanafunzi bado.</div></div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
