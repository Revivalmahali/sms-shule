<?php
/**
 * parent/child_view.php
 * Dashibodi ya mtoto MMOJA wa mzazi — tabs 4:
 *   Matokeo | Mahudhurio | Comments | Picha
 *
 * Usalama: mzazi anaona mtoto wake TU:
 *   SELECT ... FROM students WHERE id = ? AND parent_id = ?
 * Tabs zinatumia JavaScript (script.js) — hakuna reload.
 */
require_once __DIR__ . '/../includes/auth.php';
require_parent();

$user = current_user();
$stmt = $pdo->prepare('SELECT id FROM parents WHERE user_id = ? LIMIT 1');
$stmt->execute([$user['id']]);
$parent = $stmt->fetch();
if (!$parent) {
    set_flash('error', 'Taarifa za mzazi hazipatikani.');
    redirect('/parent/dashboard.php');
}
$parent_id = (int)$parent['id'];

/* ---------- Mtoto: id + parent_id (ISOLATION YA MZAZI) ---------- */
$child_id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare(
    "SELECT s.*, c.name AS class_name, c.stream, c.level
     FROM students s
     JOIN classes c ON c.id = s.class_id
     WHERE s.id = ? AND s.parent_id = ?
     LIMIT 1"
);
$stmt->execute([$child_id, $parent_id]);
$child = $stmt->fetch();

if (!$child) {
    set_flash('error', 'Mtoto huyu hakupatikana kwenye akaunti yako.');
    redirect('/parent/dashboard.php');
}

/* ============================================================
   TAB 1: MATOKEO (na chujio cha term/mwaka)
   ============================================================ */
$periods = $pdo->prepare(
    'SELECT DISTINCT year, term FROM results WHERE student_id = ? ORDER BY year DESC, term DESC'
);
$periods->execute([$child_id]);
$periods = $periods->fetchAll();

$sel_year = (int)($_GET['year'] ?? 0);
$sel_term = (int)($_GET['term'] ?? 0);
$valid    = false;
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
                s.name AS subject, s.code,
                u.full_name AS teacher
         FROM results r
         JOIN subjects s ON s.id = r.subject_id
         JOIN teachers t ON t.id = r.teacher_id
         JOIN users u    ON u.id = t.user_id
         WHERE r.student_id = ? AND r.term = ? AND r.year = ?
         ORDER BY s.name"
    );
    $stmt->execute([$child_id, $sel_term, $sel_year]);
    $results = $stmt->fetchAll();
}

// Takwimu ndogo za matokeo
$avg = $best = $worst = null;
if ($results) {
    $marks = array_map('floatval', array_column($results, 'marks'));
    $avg   = round(array_sum($marks) / count($marks), 1);
    $best  = $results[array_search(max($marks), $marks, true)];
    $worst = $results[array_search(min($marks), $marks, true)];
}

/* ============================================================
   TAB 2: MAHUDHURIO
   ============================================================ */
$stmt = $pdo->prepare(
    'SELECT status, COUNT(*) AS n FROM attendance WHERE student_id = ? GROUP BY status'
);
$stmt->execute([$child_id]);
$att_summary = ['present' => 0, 'absent' => 0, 'late' => 0, 'sick' => 0];
foreach ($stmt->fetchAll() as $row) {
    $att_summary[$row['status']] = (int)$row['n'];
}
$att_total = array_sum($att_summary);
$att_rate  = $att_total ? round((($att_summary['present'] + $att_summary['late'] + $att_summary['sick']) / $att_total) * 100) : 0;

$stmt = $pdo->prepare(
    "SELECT a.date, a.status, a.remarks, u.full_name AS marker
     FROM attendance a
     JOIN teachers t ON t.id = a.marked_by
     JOIN users u    ON u.id = t.user_id
     WHERE a.student_id = ?
     ORDER BY a.date DESC
     LIMIT 15"
);
$stmt->execute([$child_id]);
$att_recent = $stmt->fetchAll();

/* ============================================================
   TAB 3: COMMENTS (aina 4 — zote zinaonekana kwa mzazi)
   ============================================================ */
$stmt = $pdo->prepare(
    "SELECT c.comment_type, c.comment, c.term, c.year, c.created_at,
            s.name AS subject, u.full_name AS teacher
     FROM comments c
     LEFT JOIN subjects s ON s.id = c.subject_id
     JOIN teachers t ON t.id = c.teacher_id
     JOIN users u    ON u.id = t.user_id
     WHERE c.student_id = ?
     ORDER BY c.created_at DESC"
);
$stmt->execute([$child_id]);
$comments = $stmt->fetchAll();

$comment_labels = [
    'subject'  => 'Comment ya Somo',
    'general'  => 'Comment ya Jumla',
    'behavior' => 'Comment ya Tabia',
    'head'     => 'Comment ya Mkuu',
];

/* ============================================================
   TAB 4: PICHA (za mtoto + shughuli za shule)
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
$stmt->execute([$child_id]);
$photos = $stmt->fetchAll();

$photo_labels = [
    'profile'  => 'Profile',
    'activity' => 'Shughuli',
    'event'    => 'Tukio',
    'academic' => 'Kimasomo',
];

$exam_labels = ['test' => 'Jaribio', 'midterm' => 'Midterm', 'terminal' => 'Terminal', 'annual' => 'Mwaka'];

$page_title = 'Mtoto: ' . $child['full_name'];
require_once __DIR__ . '/../includes/header.php';
?>

<p><a href="<?= url('/parent/dashboard.php') ?>">&larr; Rudi kwenye watoto wangu</a></p>

<!-- ============ KADI YA MTOTO ============ -->
<div class="card" style="display:flex; gap:18px; align-items:center; flex-wrap:wrap;">
  <?php if ($child['photo']): ?>
    <img class="child-photo-ph" style="width:76px;height:76px;" src="<?= url('/' . ltrim($child['photo'], '/')) ?>" alt="<?= e($child['full_name']) ?>">
  <?php else: ?>
    <span class="child-photo-ph" style="width:76px;height:76px;"><?= e(mb_strtoupper(mb_substr($child['full_name'], 0, 1))) ?></span>
  <?php endif; ?>
  <div style="flex:1; min-width:200px;">
    <h2 style="margin-bottom:2px;"><?= e($child['full_name']) ?></h2>
    <p style="color:var(--muted); font-size:14px;">
      <?= e($child['class_name']) ?><?= $child['stream'] ? ' (' . e($child['stream']) . ')' : '' ?>
      | Reg No: <strong><?= e($child['reg_no']) ?></strong>
      | Umri: <?= (int)$child['age'] ?> miaka
      | Jinsia: <?= $child['gender'] === 'male' ? 'Me' : 'Ke' ?>
    </p>
  </div>
  <div style="text-align:center;">
    <h3 style="color:var(--primary);"><?= $att_rate ?>%</h3>
    <span class="hint">Mahudhurio</span>
  </div>
</div>

<!-- ============ TABS ============ -->
<div class="tab-list">
  <button class="tab-btn active" data-tab="tab-results">&#128221; Matokeo</button>
  <button class="tab-btn" data-tab="tab-attendance">&#128197; Mahudhurio</button>
  <button class="tab-btn" data-tab="tab-comments">&#128172; Comments</button>
  <button class="tab-btn" data-tab="tab-photos">&#128248; Picha</button>
</div>

<!-- ---------- TAB 1: MATOKEO ---------- -->
<div class="tab-panel active" id="tab-results">
  <div class="card">
    <div class="card-header">
      <h2>&#128221; Matokeo</h2>
      <?php if ($periods): ?>
        <form method="GET" action="">
          <input type="hidden" name="id" value="<?= $child_id ?>">
          <div class="filter-bar" style="margin-bottom:0;">
            <div class="form-group">
              <label>Rudia</label>
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
      <div class="empty-state"><div class="icon">&#128221;</div>Hakuna matokeo yaliyowekwa kwa mtoto huyu bado.</div>
    <?php else: ?>
      <div class="stats-grid">
        <div class="stat-card stat-blue">
          <div class="stat-icon">&#127919;</div>
          <div><h3><?= e((string)$avg) ?></h3><span>Wastuli wa Alama</span></div>
        </div>
        <div class="stat-card stat-green">
          <div class="stat-icon">&#127942;</div>
          <div>
            <h3 style="font-size:16px;"><?= e($best['subject']) ?> (<?= e((string)$best['marks']) ?>)</h3>
            <span>Somo Bora zaidi</span>
          </div>
        </div>
        <div class="stat-card stat-orange">
          <div class="stat-icon">&#128218;</div>
          <div>
            <h3 style="font-size:16px;"><?= e($worst['subject']) ?> (<?= e((string)$worst['marks']) ?>)</h3>
            <span>Inahitaji Kuzidi Juhudi</span>
          </div>
        </div>
      </div>

      <div class="table-wrap">
        <table class="data">
          <thead>
            <tr><th>Somo</th><th>Code</th><th>Aina ya Mtihani</th><th>Alama</th><th>Grade</th><th>Mwalimu</th></tr>
          </thead>
          <tbody>
            <?php foreach ($results as $r): ?>
              <tr>
                <td><strong><?= e($r['subject']) ?></strong></td>
                <td><span class="badge badge-muted"><?= e($r['code']) ?></span></td>
                <td><?= e($exam_labels[$r['exam_type']] ?? $r['exam_type']) ?></td>
                <td><strong><?= e((string)$r['marks']) ?></strong> / 100</td>
                <td><span class="badge grade-<?= e($r['grade']) ?>"><?= e($r['grade']) ?></span></td>
                <td><?= e($r['teacher']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="hint" style="margin-top:10px;">
        Viwango vya grade: A (80–100), B (70–79), C (60–69), D (50–59), E (40–49), F (0–39)
      </p>
    <?php endif; ?>
  </div>
</div>

<!-- ---------- TAB 2: MAHUDHURIO ---------- -->
<div class="tab-panel" id="tab-attendance">
  <div class="card">
    <div class="card-header"><h2>&#128197; Muhtasari wa Mahudhurio</h2></div>

    <?php if (!$att_total): ?>
      <div class="empty-state"><div class="icon">&#128197;</div>Hakuna mahudhurio yaliyowekwa bado.</div>
    <?php else: ?>
      <div class="stats-grid">
        <div class="stat-card stat-green">
          <div class="stat-icon">&#9989;</div>
          <div><h3><?= $att_summary['present'] ?></h3><span>Walikuwepo</span></div>
        </div>
        <div class="stat-card stat-red">
          <div class="stat-icon">&#10060;</div>
          <div><h3><?= $att_summary['absent'] ?></h3><span>Walikosekana</span></div>
        </div>
        <div class="stat-card stat-orange">
          <div class="stat-icon">&#9203;</div>
          <div><h3><?= $att_summary['late'] ?></h3><span>Walichelewa</span></div>
        </div>
        <div class="stat-card stat-blue">
          <div class="stat-icon">&#127973;</div>
          <div><h3><?= $att_summary['sick'] ?></h3><span>Waliougua</span></div>
        </div>
      </div>

      <div class="table-wrap">
        <table class="data">
          <thead><tr><th>Tarehe</th><th>Hali</th><th>Maelezo</th><th>Aliyeweka</th></tr></thead>
          <tbody>
            <?php foreach ($att_recent as $a): ?>
              <tr>
                <td><?= e(date('d/m/Y', strtotime($a['date']))) ?></td>
                <td><span class="badge att-<?= e($a['status']) ?>">
                  <?= ['present'=>'Yupo','absent'=>'Hakupo','late'=>'Amchelewa','sick'=>'Mgonjwa'][$a['status']] ?? e($a['status']) ?>
                </span></td>
                <td><?= e($a['remarks'] ?? '—') ?></td>
                <td><?= e($a['marker']) ?></td>
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
    <div class="card-header"><h2>&#128248; Picha za Mtoto na Shughuli za Shule</h2></div>

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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
