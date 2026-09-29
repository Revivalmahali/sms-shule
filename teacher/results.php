<?php
/**
 * teacher/results.php
 * Kuweka MATOKEO: mwalimu anachagua somo lake + exam type + term + mwaka,
 * kisha anaingiza alama kwa wanafunzi wote wa darasa.
 * Grade inahesabiwa automatically (80-100=A ... 0-39=F).
 * Upsert: alama zinazowekwa tena zinabadilishwa (ON DUPLICATE KEY UPDATE).
 */
require_once __DIR__ . '/../includes/auth.php';
require_teacher();

function compute_grade(float $m): string {
    if ($m >= 80) return 'A';
    if ($m >= 70) return 'B';
    if ($m >= 60) return 'C';
    if ($m >= 50) return 'D';
    if ($m >= 40) return 'E';
    return 'F';
}

$user = current_user();
$stmt = $pdo->prepare('SELECT id FROM teachers WHERE user_id = ? LIMIT 1');
$stmt->execute([$user['id']]);
$teacher_id = (int)($stmt->fetch()['id'] ?? 0);
if (!$teacher_id) {
    set_flash('error', 'Taarifa za mwalimu hazipatikani.');
    redirect('/auth/logout.php');
}

/* ---------- Masomo ya mwalimu huyu TU ---------- */
$stmt = $pdo->prepare(
    "SELECT s.id, s.name, s.code, s.class_id, c.name AS class_name, c.stream
     FROM subjects s JOIN classes c ON c.id = s.class_id
     WHERE s.teacher_id = ?
     ORDER BY c.name, s.name"
);
$stmt->execute([$teacher_id]);
$my_subjects = $stmt->fetchAll();

/* ---------- Vichujio (somo lazima kiwe chake) ---------- */
$subject_id = (int)($_GET['subject_id'] ?? $_POST['subject_id'] ?? 0);
$subject = null;
foreach ($my_subjects as $s) {
    if ((int)$s['id'] === $subject_id) { $subject = $s; break; }   // ISOLATION
}

$exam_type = in_array($_REQUEST['exam_type'] ?? '', ['test','midterm','terminal','annual'], true)
           ? $_REQUEST['exam_type'] : 'test';
$term = max(1, min(3, (int)($_REQUEST['term'] ?? 1)));
$year = max(2000, min(2100, (int)($_REQUEST['year'] ?? date('Y'))));

/* ---------- KUHIFADHI ALAMA ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $subject) {
    verify_csrf();

    // Wanafunzi halali = wanafunzi wa darasa la somo hili
    $stmt = $pdo->prepare("SELECT id FROM students WHERE class_id = ? AND status = 'active'");
    $stmt->execute([(int)$subject['class_id']]);
    $allowed = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    $marks  = $_POST['marks'] ?? [];
    $saved  = 0;
    $bad    = 0;

    $stmt = $pdo->prepare(
        "INSERT INTO results (student_id, subject_id, exam_type, marks, grade, term, year, teacher_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE marks = VALUES(marks), grade = VALUES(grade), teacher_id = VALUES(teacher_id)"
    );

    foreach ($marks as $studentId => $value) {
        $studentId = (int)$studentId;
        $value = trim((string)$value);
        if ($value === '' || !in_array($studentId, $allowed, true)) continue;  // wazi au si mwanafunzi wa darasa

        if (!is_numeric($value) || (float)$value < 0 || (float)$value > 100) {
            $bad++;
            continue;
        }
        $m = round((float)$value, 2);
        $stmt->execute([$studentId, $subject_id, $exam_type, $m, compute_grade($m), $term, $year, $teacher_id]);
        $saved++;
    }

    if ($saved) set_flash('success', "Alama $saved zimehifadhiwa kikamilifu!" . ($bad ? " ($bad zilipuuzwa — si sahihi)" : ''));
    else        set_flash('error', 'Hakuna alama sahihi zilizohifadhiwa. Alama ziwe kati ya 0 na 100.');

    redirect("/teacher/results.php?subject_id=$subject_id&exam_type=$exam_type&term=$term&year=$year");
}

/* ---------- Wanafunzi wa darasa + alama zilizopo ---------- */
$students = [];
$existing = [];
if ($subject) {
    $stmt = $pdo->prepare(
        "SELECT id, full_name, reg_no, photo FROM students
         WHERE class_id = ? AND status = 'active'
         ORDER BY full_name"
    );
    $stmt->execute([(int)$subject['class_id']]);
    $students = $stmt->fetchAll();

    $stmt = $pdo->prepare(
        'SELECT student_id, marks, grade FROM results
         WHERE subject_id = ? AND exam_type = ? AND term = ? AND year = ?'
    );
    $stmt->execute([$subject_id, $exam_type, $term, $year]);
    foreach ($stmt->fetchAll() as $r) {
        $existing[(int)$r['student_id']] = $r;
    }
}

$exam_labels = ['test' => 'Jaribio', 'midterm' => 'Midterm', 'terminal' => 'Terminal', 'annual' => 'Mwaka'];

$page_title = 'Matokeo';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="card">
  <div class="card-header"><h2>&#128221; Chagua Somo na Mtihani</h2></div>

  <?php if (!$my_subjects): ?>
    <div class="empty-state"><div class="icon">&#128218;</div>Hujapewa masomo bado. Wasiliana na msimamizi.</div>
  <?php else: ?>
    <form method="GET" action="">
      <div class="filter-bar">
        <div class="form-group" style="max-width:320px;">
          <label>Somo Changu (darasa) *</label>
          <select name="subject_id" required>
            <?php foreach ($my_subjects as $s): ?>
              <option value="<?= (int)$s['id'] ?>" <?= $subject_id === (int)$s['id'] ? 'selected' : '' ?>>
                <?= e($s['name']) ?> — <?= e($s['class_name']) ?><?= $s['stream'] ? ' (' . e($s['stream']) . ')' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Aina ya Mtihani</label>
          <select name="exam_type">
            <?php foreach ($exam_labels as $key => $label): ?>
              <option value="<?= $key ?>" <?= $exam_type === $key ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group" style="max-width:110px;">
          <label>Term</label>
          <select name="term">
            <?php for ($i = 1; $i <= 3; $i++): ?>
              <option value="<?= $i ?>" <?= $term === $i ? 'selected' : '' ?>>Term <?= $i ?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="form-group" style="max-width:130px;">
          <label>Mwaka</label>
          <input type="number" name="year" value="<?= $year ?>" min="2000" max="2100" required>
        </div>
        <button type="submit" class="btn">Fungua</button>
      </div>
    </form>
  <?php endif; ?>
</div>

<?php if ($subject && $students): ?>
<div class="card">
  <div class="card-header">
    <h2>&#9997; Alama: <?= e($subject['name']) ?> — <?= e($subject['class_name']) ?>
        (<?= e($exam_labels[$exam_type]) ?>, Term <?= $term ?>, <?= $year ?>)</h2>
    <span class="badge badge-muted"><?= count($existing) ?> tayari zipo</span>
  </div>

  <form method="POST" action="">
    <?= csrf_field() ?>
    <input type="hidden" name="subject_id" value="<?= $subject_id ?>">
    <input type="hidden" name="exam_type"  value="<?= e($exam_type) ?>">
    <input type="hidden" name="term"       value="<?= $term ?>">
    <input type="hidden" name="year"       value="<?= $year ?>">

    <div class="table-wrap">
      <table class="data">
        <thead><tr><th>#</th><th>Mwanafunzi</th><th>Reg No</th><th>Alama (0–100)</th><th>Grade</th></tr></thead>
        <tbody>
          <?php foreach ($students as $i => $st): $ex = $existing[(int)$st['id']] ?? null; ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td><strong><?= e($st['full_name']) ?></strong></td>
              <td><?= e($st['reg_no']) ?></td>
              <td style="max-width:140px;">
                <input type="number" name="marks[<?= (int)$st['id'] ?>]"
                       value="<?= $ex ? e((string)$ex['marks']) : '' ?>"
                       min="0" max="100" step="0.5" inputmode="decimal"
                       placeholder="mf: 75" data-grade-output="grade_<?= (int)$st['id'] ?>">
              </td>
              <td>
                <span class="badge <?= $ex ? 'grade-' . e($ex['grade']) : 'badge-muted' ?>" id="grade_<?= (int)$st['id'] ?>">
                  <?= $ex ? e($ex['grade']) : '—' ?>
                </span>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn">Hifadhi Alama Zote</button>
      <span class="hint">Acha wazi usibadilishe. Viwango: A 80+, B 70+, C 60+, D 50+, E 40+, F chini ya 40.</span>
    </div>
  </form>
</div>

<script>
/* Grade inajionesha live ukiandika alama */
document.querySelectorAll('input[data-grade-output]').forEach(function (inp) {
  inp.addEventListener('input', function () {
    var out = document.getElementById(inp.dataset.gradeOutput);
    if (!out) return;
    var v = parseFloat(inp.value);
    var g = null;
    if (!isNaN(v) && v >= 0 && v <= 100) {
      g = v >= 80 ? 'A' : v >= 70 ? 'B' : v >= 60 ? 'C' : v >= 50 ? 'D' : v >= 40 ? 'E' : 'F';
    }
    if (g) { out.textContent = g; out.className = 'badge grade-' + g; }
    else   { out.textContent = '—'; out.className = 'badge badge-muted'; }
  });
});
</script>

<?php elseif ($subject): ?>
  <div class="card"><div class="empty-state"><div class="icon">&#127891;</div>Darasa hili halina wanafunzi aliyesajiliwa bado.</div></div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
