<?php
/**
 * auth/register.php
 * Usajili wa MZAZI TU (public registration) — split-screen na picha kando.
 * Walimu/wanafunzi wanaingizwa na admin au mzazi (hatua zijazo).
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/images.php';

if (is_logged_in()) {
    redirect(dashboard_for($_SESSION['user']['role']));
}

$errors = [];
// Thamani za awali (ili form isipoteze data ukiwa na makosa)
$old = [
    'full_name'    => '',
    'email'        => '',
    'phone'        => '',
    'relationship' => 'father',
    'occupation'   => '',
    'address'      => '',
    'region'       => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    foreach ($old as $key => $_) {
        $old[$key] = trim($_POST[$key] ?? '');
    }
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    /* ---------- Uhakiki wa upande wa server (server-side validation) ---------- */
    if (mb_strlen($old['full_name']) < 3) {
        $errors[] = 'Jina kamili linahitaji angalau herufi 3.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Barua pepe si sahihi.';
    }
    if ($old['phone'] === '' || strlen($old['phone']) < 9) {
        $errors[] = 'Namba ya simu si sahihi (mf: +255700123456).';
    }
    if (!in_array($old['relationship'], ['father', 'mother', 'guardian'], true)) {
        $errors[] = 'Chagua uhusiano sahihi (baba, mama au mlezi).';
    }
    if (strlen($password) < 6) {
        $errors[] = 'Nywira inahitaji angalau herufi 6.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Nywira hazifanani. Hakikisha zote mbili ni sawa.';
    }

    // Hakikisha barua pepe haijatumika kabisa
    if (!$errors) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$old['email']]);
        if ($stmt->fetch()) {
            $errors[] = 'Barua pepe hii imetumika. Tumia nyingine au ingia kwenye akaunti yako.';
        }
    }

    /* ---------- Hifadhi kwenye transaction (users + parents pamoja) ---------- */
    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role, phone, status)
                                   VALUES (?, ?, ?, 'parent', ?, 'active')");
            $stmt->execute([
                $old['full_name'],
                $old['email'],
                password_hash($password, PASSWORD_DEFAULT),  // Usalama wa nywira
                $old['phone'],
            ]);
            $userId = (int)$pdo->lastInsertId();

            $stmt = $pdo->prepare('INSERT INTO parents (user_id, relationship, occupation, address, region)
                                   VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([
                $userId,
                $old['relationship'],
                $old['occupation'],
                $old['address'],
                $old['region'],
            ]);

            $pdo->commit();

            set_flash('success', 'Usajili umefanikiwa! Tafadhali ingia kwa barua pepe na nywira yako.');
            redirect('/auth/login.php');
        } catch (PDOException $ex) {
            $pdo->rollBack();
            $errors[] = 'Samahani, usajili haukufanikiwa. Tafadhali jaribu tena.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="sw">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Usajili wa Mzazi — SMS Shule</title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI', Tahoma, sans-serif; }
  body { color:#1e293b; }

  /* ---------- SPLIT SCREEN (picha kando) ---------- */
  .auth-split { display:grid; grid-template-columns:1fr 1fr; min-height:100vh; }
  .auth-side-img { background-size:cover; background-position:center; background-color:#26324b; position:relative; }
  .auth-side-img .side-quote { position:absolute; bottom:36px; left:30px; right:30px; color:#fff;
      background:rgba(10,25,55,.55); border-radius:12px; padding:14px 18px; font-size:14.5px;
      text-shadow:0 1px 2px rgba(0,0,0,.4); }
  .auth-side-form { display:flex; align-items:center; justify-content:center; padding:30px 22px; }
  @media (max-width: 760px) {
    .auth-split { grid-template-columns:1fr; }
    .auth-side-img { min-height:180px; order:-1; }   /* picha juu kwenye simu */
    .auth-side-img .side-quote { display:none; }
  }

  /* ---------- CARD YA FORM ---------- */
  .card { background:#fff; width:100%; max-width:480px; border-radius:14px; padding:32px 30px;
          box-shadow:0 18px 45px rgba(0,0,0,.25); }
  .logo { width:64px; height:64px; border-radius:50%; background:#e65100; color:#fff; font-weight:700;
          display:flex; align-items:center; justify-content:center; margin:0 auto 14px; font-size:17px; }
  h1 { font-size:21px; text-align:center; color:#222; margin-bottom:4px; }
  .sub { text-align:center; color:#888; font-size:13px; margin-bottom:20px; }
  label { display:block; font-size:13px; color:#444; margin:11px 0 5px; font-weight:600; }
  input, select { width:100%; padding:11px 12px; border:1px solid #d5d9e0; border-radius:8px; font-size:14px;
                  background:#fff; }
  input:focus, select:focus { outline:none; border-color:#e65100; box-shadow:0 0 0 3px rgba(230,81,0,.15); }
  .row { display:flex; gap:12px; }
  .row > div { flex:1; }
  button { width:100%; margin-top:20px; padding:12px; background:#e65100; color:#fff; border:0;
           border-radius:8px; font-size:15px; font-weight:600; cursor:pointer; }
  button:hover { background:#bf360c; }
  .alert { padding:10px 12px; border-radius:8px; font-size:13px; margin-top:12px; }
  .alert-error   { background:#fdecea; color:#b71c1c; }
  .alert-success { background:#e8f5e9; color:#1b5e20; }
  .foot { text-align:center; margin-top:16px; font-size:13px; color:#666; }
  .foot a { color:#e65100; font-weight:600; text-decoration:none; }
  .back { text-align:center; margin-top:10px; font-size:12.5px; }
  .back a { color:#888; text-decoration:none; }
  .back a:hover { color:#e65100; }
</style>
</head>
<body>
<div class="auth-split">

  <!-- ---------- PICHA YA KUSHOTO ---------- -->
  <div class="auth-side-img" style="background-image:url('<?= e(img_url('register_side')) ?>');">
    <div class="side-quote">
      <strong>Jiunge nasi!</strong> Zaidi ya wazazi elfu kadhaa wanafuatilia elimu ya
      watoto wao kila siku kupitia SMS Shule.
    </div>
  </div>

  <!-- ---------- FORM YA KULIA ---------- -->
  <div class="auth-side-form">
    <div class="card">
      <div class="logo">SMS</div>
      <h1>Usajili wa Mzazi</h1>
      <p class="sub">Jaza taarifa zako ili kufuatilia shughuli za mtoto wako</p>

      <?php foreach ($errors as $err): ?>
        <div class="alert alert-error"><?= e($err) ?></div>
      <?php endforeach; ?>
      <?= render_flashes() ?>

      <form method="POST" action="">
        <?= csrf_field() ?>

        <label for="full_name">Jina Kamili *</label>
        <input type="text" id="full_name" name="full_name" value="<?= e($old['full_name']) ?>"
               placeholder="mf: Hamisi Juma" minlength="3" required>

        <div class="row">
          <div>
            <label for="email">Barua Pepe *</label>
            <input type="email" id="email" name="email" value="<?= e($old['email']) ?>"
                   placeholder="mf: mzazi@gmail.com" required>
          </div>
          <div>
            <label for="phone">Namba ya Simu *</label>
            <input type="tel" id="phone" name="phone" value="<?= e($old['phone']) ?>"
                   placeholder="mf: +255700123456" required>
          </div>
        </div>

        <label for="relationship">Uhusiano na Mtoto *</label>
        <select id="relationship" name="relationship" required>
          <option value="father"   <?= $old['relationship'] === 'father'   ? 'selected' : '' ?>>Baba</option>
          <option value="mother"   <?= $old['relationship'] === 'mother'   ? 'selected' : '' ?>>Mama</option>
          <option value="guardian" <?= $old['relationship'] === 'guardian' ? 'selected' : '' ?>>Mlezi</option>
        </select>

        <div class="row">
          <div>
            <label for="occupation">Kazi</label>
            <input type="text" id="occupation" name="occupation" value="<?= e($old['occupation']) ?>"
                   placeholder="mf: Mfanyabiashara">
          </div>
          <div>
            <label for="region">Mkoa</label>
            <input type="text" id="region" name="region" value="<?= e($old['region']) ?>"
                   placeholder="mf: Dar es Salaam">
          </div>
        </div>

        <label for="address">Anuani</label>
        <input type="text" id="address" name="address" value="<?= e($old['address']) ?>"
               placeholder="mf: Mtaa wa Uhuru, S/L 145">

        <div class="row">
          <div>
            <label for="password">Nywira *</label>
            <input type="password" id="password" name="password" minlength="6" required>
          </div>
          <div>
            <label for="confirm_password">Thibitisha Nywira *</label>
            <input type="password" id="confirm_password" name="confirm_password" minlength="6" required>
          </div>
        </div>

        <button type="submit">Sajili</button>
      </form>

      <p class="foot">Tayari una akaunti? <a href="login.php">Ingia hapa</a></p>
      <p class="back"><a href="../index.php">&larr; Rudi nyumbani</a></p>
    </div>
  </div>

</div>
</body>
</html>
