<?php
/**
 * student/dashboard.php
 * Dashibodi ya MWANAFUNZI — taarifa zake MWENYEWE TU:
 *   Matokeo | Mahudhurio | Comments | Picha  (tabs bila reload)
 * Rangi: ZAMBARAU (role-student)
 *
 * MUHIMU:
 *  - Account ya mwanafunzi inaundwa automatically wakati mzazi anamsajili
 *    (barua pepe = reg_no, nywira = tarehe ya kuzaliwa DDMMYYYY).
 *  - Comments ya BEHAVIOR hauonyeshwi hapa — ni kwa MZAZI TU.
 */
require_once __DIR__ . '/../includes/auth.php';
require_student();

$user = current_user();

/* ---------- Record ya mwanafunzi (kwa user_id yake) ---------- */
$stmt = $pdo->prepare(
    "SELECT s.*, c.name AS class_name, c.stream, c.level
     FROM students s
     JOIN classes c ON c.id = s.class_id
     WHERE s.user_id = ?
     LIMIT 1"
);
$stmt->execute([$user['id']]);
$student = $stmt->fetch();

if (!$student) {
    set_flash('error', 'Taarifa za mwanafunzi hazipatikani. Wasiliana na mzazi au msimamizi.');
    redirect('/auth/logout.php');
}
$student_id = (int)$student['id'];

/* ============================================================
   MATOKEO (na chujio cha term/mwaka)
   ============================================================ */
$stmt = $pdo->prepare('SELECT DISTINCT year, term FROM results WHERE student_id = ? ORDER BY year DESC, term DESC');
$stmt->execute([$student_id]);
$periods = $stmt->fetchAll();

$sel_year = (int)($_GET['year'] ?? 0);
$sel_term = (int)($_GET['term'] ?? 0);
$valid = false;
foreach ($periods as $p) {
    if ((int)$p['year'] === $sel_year && (int)$p['term'] === $sel_term) { $valid = true; break; }
}
if (!$valid && $periods) {
    $sel_year = (int)$periods[0]['year'];   // default: mpya zaidi
    $sel_term = (int)$periods[0]['term'];
}

$results = [];
if ($periods) {
    $stmt = $pdo->prepare(
        "SELECT r.marks, r.grade, r.exam_type, r.term, r.year,
                s.name AS subject, s.code
         FROM results r
         JOIN subjects s ON s.id = r.subject_id
         WHERE r.student_id = ? AND r.term = ? AND r.year = ?
         ORDER BY s.name"
    );
    $stmt->execute([$student_id, $sel_term, $sel_year]);
    $results = $stmt->fetchAll();
}

$avg = $best = $worst = null;
if ($results) {
    $marks = array_map('floatval', array_column($results, 'marks'));
    $avg   = round(array_sum($marks) / count($marks), 1);
    $best  = $results[array_search(max($marks), $marks, true)];
    $worst = $results[array_search(min($marks), $marks, true)];
}

/* ============================================================
   MAHUDHURIO
   ============================================================ */
$stmt = $pdo->prepare('SELECT status, COUNT(*) AS n FROM attendance WHERE student_id = ? GROUP BY status');
$stmt->execute([$student_id]);
$att_summary = ['present' => 0, 'absent' => 0, 'late' => 0, 'sick' => 0];
foreach ($stmt->fetchAll() as $row) {
    $att_summary[$row['status']] = (int)$row['n'];
}
$att_total = array_sum($att_summary);
$att_rate  = $att_total ? round((($att_summary['present'] + $att_summary['late'] + $att_summary['sick']) / $att_total) * 100) : 0;

$stmt = $pdo->prepare(
    "SELECT a.date, a.status, a.remarks
     FROM attendance a
     WHERE a.student_id = ?
     ORDER BY a.date DESC
     LIMIT 15"
);
$stmt->execute([$student_id]);
$att_recent = $stmt->fetchAll();

/* ============================================================
   COMMENTS — bila 'behavior' (ni kwa MZAZI TU!)
   ============================================================ */
$stmt = $pdo->prepare(
    "SELECT c.comment_type, c.comment, c.term, c.year, c.created_at,
            s.name AS subject, u.full_name AS teacher
     FROM comments c
     LEFT JOIN subjects s ON s.id = c.subject_id
     JOIN teachers t ON t.id = c.teacher_id
     JOIN users u    ON u.id = t.user_id
     WHERE c.student_id = ? AND c.comment_type != 'behavior'
     ORDER BY c.created_at DESC"
);
$stmt->execute([$student_id]);
$comments = $stmt->fetchAll();

$comment_labels = [
    'subject' => 'Comment ya Somo',
    'general' => 'Comment ya Jumla',
    'head'    => 'Comment ya Mkuu',
];

/* ============================================================
   PICHA (zake + shughuli za shule)
   ============================================================ */
$stmt = $pdo->prepare(
    "SELECT sp.photo_url, sp.caption, sp.category, sp.created_at,
            u.full_name AS uploader
     FROM student_photos sp
     JOIN teachers t ON t.id = sp.uploaded_by
     JOIN users u    ON u.id = t.user_id
     WHERE sp.student_id = ? OR sp.student_id IS NULL
     ORDER BY sp.created_at DESC
     LIMIT 24"
);
$stmt->execute([$student_id]);
$photos = $stmt->fetchAll();

$photo_labels = ['profile' => 'Profile', 'activity' => 'Shughuli', 'event' => 'Tukio', 'academic' => 'Kimasomo'];
$exam_labels  = ['test' => 'Jaribio', 'midterm' => 'Midterm', 'terminal' => 'Terminal', 'annual' => 'Mwaka'];

/* ---------- Taarifa zinazomgusa mwanafunzi ---------- */
$class_id = (int)$student['class_id'];
$sql = "SELECT title, message, created_at, c.name AS class_name
        FROM announcements a
        LEFT JOIN classes c ON c.id = a.class_id
        WHERE a.target IN ('all','students')
          AND (a.class_id IS NULL OR a.class_id = ?)
        ORDER BY a.created_at DESC
        LIMIT 6";
$stmt = $pdo->prepare($sql);
$stmt->execute([$class_id]);
$announcements = $stmt->fetchAll();

$page_title = 'Dashibodi Yangu';
require_once __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../includes/banner.php';   // utepe wa kukaribisha (picha)
?>

<!-- ============ KADI YANGU ============ -->
<div class="card" style="display:flex; gap:18px; align-items:center; flex-wrap:wrap;">
  <?php if ($student['photo']): ?>
    <img class="child-photo-ph" style="width:76px;height:76px;" src="<?= url('/' . ltrim($student['photo'], '/')) ?>" alt="<?= e($student['full_name']) ?>">
  <?php else: ?>
    <span class="child-photo-ph" style="width:76px;height:76px;"><?= e(mb_strtoupper(mb_substr($student['full_name'], 0, 1))) ?></span>
  <?php endif; ?>
  <div style="flex:1; min-width:200px;">
    <h2 style="margin-bottom:2px;"><?= e($student['full_name']) ?></h2>
    <p style="color:var(--muted); font-size:14px;">
      <?= e($student['class_name']) ?><?= $student['stream'] ? ' (' . e($student['stream']) . ')' : '' ?>
      | Reg No: <strong><?= e($student['reg_no']) ?></strong>
      | Umri: <?= (int)$student['age'] ?> miaka
      | Jinsia: <?= $student['gender'] === 'male' ? 'Me' : 'Ke' ?>
    </p>
  </div>
  <div style="text-align:center;">
    <h3 style="color:var(--primary);"><?= $att_rate ?>%</h3>
    <span class="hint">Mahudhurio Yangu</span>
  </div>
</div>

<!-- ============ TABS ============ -->
<div class="tab-list">
  <button class="tab-btn active" data-tab="tab-results">&#128221; Matokeo Yangu</button>
  <button class="tab-btn" data-tab="tab-attendance">&#128197; Mahudhurio</button>
  <button class="tab-btn" data-tab="tab-comments">&#128172; Comments</button>
  <button class="tab-btn" data-tab="tab-photos">&#128248; Picha</button>
</div>

<!-- ---------- TAB 1: MATOKEO ---------- -->
<div class="tab-panel active" id="tab-results">
  <div class="card">
    <div class="card-header">
      <h2>&#128221; Matokeo Yangu</h2>
      <?php if ($periods): ?>
        <form method="GET" action="">
          <div class="filter-bar" style="margin-bottom:0;">
            <div class="form-group">
              <label>Term</label>
              <select name="term">
                <?php foreach ($periods as $p): ?>
                  <option value="<?= (int)$p['term'] ?>" <?= (int)$p['term'] === $sel_term ? 'selected' : '' ?>>Term <?= (int)$p['term'] ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label>Mwaka</label>
              <select name="year">
                <?php foreach ($periods as $p): ?>
                  <option value="<?= (int)$p['year'] ?>" <?= (int)$p['year'] === $sel_year ? 'selected' : '' ?>><?= (int)$p['year'] ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <button type="submit" class="btn btn-sm">Onyesha</button>
          </div>
        </form>
      <?php endif; ?>
    </div>

    <?php if (!$results): ?>
      <div class="empty-state"><div class="icon">&#128221;</div>Hakuna matokeo yaliyowekwa bado. Mwalimu atakayeweka yataonekana hapa.</div>
    <?php else: ?>
      <div class="stats-grid">
        <div class="stat-card stat-purple">
          <div class="stat-icon">&#127919;</div>
          <div><h3><?= e((string)$avg) ?></h3><span>Wastuli Wangu</span></div>
        </div>
        <div class="stat-card stat-green">
          <div class="stat-icon">&#127942;</div>
          <div><h3 style="font-size:16px;"><?= e($best['subject']) ?> (<?= e((string)$best['marks']) ?>)</h3><span>Nimefanya Vizuri Sana</span></div>
        </div>
        <div class="stat-card stat-orange">
          <div class="stat-icon">&#128170;</div>
          <div><h3 style="font-size:16px;"><?= e($worst['subject']) ?> (<?= e((string)$worst['marks']) ?>)</h3><span>Nitajitahidi Zaidi</span></div>
        </div>
      </div>

      <div class="table-wrap">
        <table class="data">
          <thead><tr><th>Somo</th><th>Code</th><th>Mtihani</th><th>Alama</th><th>Grade</th></tr></thead>
          <tbody>
            <?php foreach ($results as $r): ?>
              <tr>
                <td><strong><?= e($r['subject']) ?></strong></td>
                <td><span class="badge badge-muted"><?= e($r['code']) ?></span></td>
                <td><?= e($exam_labels[$r['exam_type']] ?? $r['exam_type']) ?></td>
                <td><strong><?= e((string)$r['marks']) ?></strong> / 100</td>
                <td><span class="badge grade-<?= e($r['grade']) ?>"><?= e($r['grade']) ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="hint" style="margin-top:10px;">
        Viwango: A (80–100), B (70–79), C (60–69), D (50–59), E (40–49), F (0–39)
      </p>
    <?php endif; ?>
  </div>
</div>

<!-- ---------- TAB 2: MAHUDHURIO ---------- -->
<div class="tab-panel" id="tab-attendance">
  <div class="card">
    <div class="card-header"><h2>&#128197; Mahudhurio Yangu</h2></div>

    <?php if (!$att_total): ?>
      <div class="empty-state"><div class="icon">&#128197;</div>Hakuna mahudhurio yaliyowekwa bado.</div>
    <?php else: ?>
      <div class="stats-grid">
        <div class="stat-card stat-green">
          <div class="stat-icon">&#9989;</div>
          <div><h3><?= $att_summary['present'] ?></h3><span>Nilikuwepo</span></div>
        </div>
        <div class="stat-card stat-red">
          <div class="stat-icon">&#10060;</div>
          <div><h3><?= $att_summary['absent'] ?></h3><span>Nilikosekana</span></div>
        </div>
        <div class="stat-card stat-orange">
          <div class="stat-icon">&#9203;</div>
          <div><h3><?= $att_summary['late'] ?></h3><span>Nilichelewa</span></div>
        </div>
        <div class="stat-card stat-blue">
          <div class="stat-icon">&#127973;</div>
          <div><h3><?= $att_summary['sick'] ?></h3><span>Niliousha</span></div>
        </div>
      </div>

      <div class="table-wrap">
        <table class="data">
          <thead><tr><th>Tarehe</th><th>Hali</th><th>Maelezo</th></tr></thead>
          <tbody>
            <?php foreach ($att_recent as $a): ?>
              <tr>
                <td><?= e(date('d/m/Y', strtotime($a['date']))) ?></td>
                <td><span class="badge att-<?= e($a['status']) ?>">
                  <?= ['present'=>'Nilikuwepo','absent'=>'Nilikosekana','late'=>'Nilichelewa','sick'=>'Niliousha'][$a['status']] ?? e($a['status']) ?>
                </span></td>
                <td><?= e($a['remarks'] ?? '—') ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ---------- TAB 3: COMMENTS ---------- -->
<div class="tab-panel" id="tab-comments">
  <div class="card">
    <div class="card-header"><h2>&#128172; Comments za Walimu (<?= count($comments) ?>)</h2></div>

    <?php if (!$comments): ?>
      <div class="empty-state"><div class="icon">&#128172;</div>Hakuna comments bado.</div>
    <?php else: foreach ($comments as $c): ?>
      <div class="comment-item">
        <div class="comment-meta">
          <span class="badge badge-info"><?= e($comment_labels[$c['comment_type']] ?? $c['comment_type']) ?></span>
          <?php if ($c['subject']): ?><strong><?= e($c['subject']) ?></strong><?php endif; ?>
          | <strong><?= e($c['teacher']) ?></strong>
          | Term <?= (int)$c['term'] ?>, <?= (int)$c['year'] ?>
          | <?= e(date('d/m/Y', strtotime($c['created_at']))) ?>
        </div>
        <div class="comment-text"><?= e($c['comment']) ?></div>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<!-- ---------- TAB 4: PICHA ---------- -->
<div class="tab-panel" id="tab-photos">
  <div class="card">
    <div class="card-header"><h2>&#128248; Picha Zangu na Shughuli za Shule</h2></div>

    <?php if (!$photos): ?>
      <div class="empty-state"><div class="icon">&#128248;</div>Hakuna picha zilizopakiwa bado.</div>
    <?php else: ?>
      <div class="photo-grid">
        <?php foreach ($photos as $p): ?>
          <figure>
            <img src="<?= url('/' . ltrim($p['photo_url'], '/')) ?>" alt="<?= e($p['caption'] ?: 'Picha') ?>">
            <figcaption>
              <span class="badge badge-muted"><?= e($photo_labels[$p['category']] ?? $p['category']) ?></span><br>
              <?= e($p['caption'] ?: '—') ?><br>
              <small><?= e($p['uploader']) ?> · <?= e(date('d/m/Y', strtotime($p['created_at']))) ?></small>
            </figcaption>
          </figure>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ============ TAARIFA ZA SHULE ============ -->
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
