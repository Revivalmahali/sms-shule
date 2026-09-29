<?php
/**
 * admin/students.php
 * Kuona wanafunzi WOTE wa shule + utafutaji (jina/reg_no) na chujio la darasa.
 * (Usajili wa wanafunzi unafanywa na WAZAZI — admin anasimamia/kuona tu)
 */
require_once __DIR__ . '/../includes/auth.php';
require_admin();

/* ---------- Vichujio vya utafutaji ---------- */
$q        = trim($_GET['q'] ?? '');
$class_id = (int)($_GET['class_id'] ?? 0);

$sql = "SELECT s.*, c.name AS class_name, c.stream,
               u.full_name AS parent_name, u.phone AS parent_phone, p.relationship
        FROM students s
        JOIN classes c ON c.id = s.class_id
        JOIN parents p ON p.id = s.parent_id
        JOIN users u   ON u.id = p.user_id
        WHERE 1 = 1";
$params = [];

if ($q !== '') {
    $sql .= ' AND (s.full_name LIKE ? OR s.reg_no LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($class_id) {
    $sql .= ' AND s.class_id = ?';
    $params[] = $class_id;
}
$sql .= ' ORDER BY c.name, s.full_name';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

/* ---------- Madarasa kwa chujio ---------- */
$classes = $pdo->query('SELECT id, name, stream FROM classes ORDER BY name, stream')->fetchAll();

/* ---------- Mahudhurio ya leo (kwa muhtasari) ---------- */
$today = date('Y-m-d');
$today_stmt = $pdo->prepare("SELECT status, COUNT(*) AS n FROM attendance WHERE date = ? GROUP BY status");
$today_stmt->execute([$today]);
$today_stats = [];
foreach ($today_stmt->fetchAll() as $row) {
    $today_stats[$row['status']] = (int)$row['n'];
}

$page_title = 'Wanafunzi';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="stats-grid">
  <div class="stat-card stat-green">
    <div class="stat-icon">&#9989;</div>
    <div><h3><?= $today_stats['present'] ?? 0 ?></h3><span>Waliohudhuria Leo</span></div>
  </div>
  <div class="stat-card stat-red">
    <div class="stat-icon">&#10060;</div>
    <div><h3><?= $today_stats['absent'] ?? 0 ?></h3><span>Waliokosekana Leo</span></div>
  </div>
  <div class="stat-card stat-orange">
    <div class="stat-icon">&#9203;</div>
    <div><h3><?= $today_stats['late'] ?? 0 ?></h3><span>Waliochelewa Leo</span></div>
  </div>
  <div class="stat-card stat-blue">
    <div class="stat-icon">&#127973;</div>
    <div><h3><?= $today_stats['sick'] ?? 0 ?></h3><span>Waliougua Leo</span></div>
  </div>
</div>

<div class="card">
  <div class="card-header"><h2>&#128269; Tafuta Wanafunzi</h2></div>

  <form method="GET" action="">
    <div class="filter-bar">
      <div class="form-group">
        <label>Jina au Reg No</label>
        <input type="text" name="q" value="<?= e($q) ?>" placeholder="mf: Asha au SMS2026-0001">
      </div>
      <div class="form-group">
        <label>Darasa</label>
        <select name="class_id">
          <option value="">— madarasa yote —</option>
          <?php foreach ($classes as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= $class_id === (int)$c['id'] ? 'selected' : '' ?>>
              <?= e($c['name']) ?><?= $c['stream'] ? ' (' . e($c['stream']) . ')' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn">Tafuta</button>
      <?php if ($q !== '' || $class_id): ?>
        <a class="btn btn-secondary" href="<?= url('/admin/students.php') ?>">Futa Vichujio</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<div class="card">
  <div class="card-header"><h2>&#127891; Wanafunzi (<?= count($students) ?>)</h2></div>

  <div class="table-wrap">
    <table class="data">
      <thead>
        <tr><th>Picha</th><th>Jina</th><th>Reg No</th><th>Darasa</th><th>Jinsia</th><th>Umri</th><th>Mzazi</th><th>Simu ya Mzazi</th><th>Hali</th></tr>
      </thead>
      <tbody>
        <?php if (!$students): ?>
          <tr><td colspan="9"><div class="empty-state"><div class="icon">&#127891;</div>Hakuna mwanafunzi aliyepatikana.</div></td></tr>
        <?php else: foreach ($students as $s): ?>
          <tr>
            <td>
              <?php if ($s['photo']): ?>
                <img class="avatar" src="<?= url('/' . ltrim($s['photo'], '/')) ?>" alt="<?= e($s['full_name']) ?>">
              <?php else: ?>
                <span class="avatar avatar-initials"><?= e(mb_strtoupper(mb_substr($s['full_name'], 0, 1))) ?></span>
              <?php endif; ?>
            </td>
            <td><strong><?= e($s['full_name']) ?></strong></td>
            <td><?= e($s['reg_no']) ?></td>
            <td><?= e($s['class_name']) ?><?= $s['stream'] ? ' (' . e($s['stream']) . ')' : '' ?></td>
            <td><?= $s['gender'] === 'male' ? 'Me' : 'Ke' ?></td>
            <td><?= (int)$s['age'] ?></td>
            <td><?= e($s['parent_name']) ?> <span class="badge badge-muted"><?= e(ucfirst($s['relationship'])) ?></span></td>
            <td><?= e($s['parent_phone']) ?></td>
            <td>
              <?php if ($s['status'] === 'active'): ?>
                <span class="badge att-present">Hai</span>
              <?php elseif ($s['status'] === 'graduated'): ?>
                <span class="badge badge-info">Amemaliza</span>
              <?php else: ?>
                <span class="badge badge-muted">Amehamia</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
