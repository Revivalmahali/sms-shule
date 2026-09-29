<?php
/**
 * auth/login.php
 * Ukurasa wa kuingia (login) — split-screen na picha kando.
 * (Ukurasa huu ni standalone — hautumii header.php ya dashibodi)
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/images.php';

// Kama ameshalogin, mpeleke moja kwa moja kwenye dashibodi yake
if (is_logged_in()) {
    redirect(dashboard_for($_SESSION['user']['role']));
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Tafadhali jaza barua pepe na nywira.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Barua pepe si sahihi.';
    } else {
        // Prepared statement — hakuna concatenation ya SQL
        $stmt = $pdo->prepare('SELECT id, full_name, email, password, role, photo, status
                               FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            $error = 'Barua pepe au nywira si sahihi.';
        } elseif ($user['status'] !== 'active') {
            $error = 'Akaunti yako imezimwa. Wasiliana na msimamizi wa shule.';
        } else {
            // Zuia session fixation
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id'        => (int)$user['id'],
                'full_name' => $user['full_name'],
                'email'     => $user['email'],
                'role'      => $user['role'],
                'photo'     => $user['photo'],
            ];
            // Role-based redirect: kila role kwenda dashibodi yake
            redirect(dashboard_for($user['role']));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="sw">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kuingia — SMS Shule</title>
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
  .card { background:#fff; width:100%; max-width:420px; border-radius:14px; padding:34px 30px;
          box-shadow:0 18px 45px rgba(0,0,0,.25); }
  .logo { width:64px; height:64px; border-radius:50%; background:#1e3c72; color:#fff; font-weight:700;
          display:flex; align-items:center; justify-content:center; margin:0 auto 14px; font-size:17px; }
  h1 { font-size:21px; text-align:center; color:#222; margin-bottom:4px; }
  .sub { text-align:center; color:#888; font-size:13px; margin-bottom:20px; }
  label { display:block; font-size:13px; color:#444; margin:12px 0 5px; font-weight:600; }
  input { width:100%; padding:11px 12px; border:1px solid #d5d9e0; border-radius:8px; font-size:14px; }
  input:focus { outline:none; border-color:#2a5298; box-shadow:0 0 0 3px rgba(42,82,152,.15); }
  button { width:100%; margin-top:20px; padding:12px; background:#2a5298; color:#fff; border:0;
           border-radius:8px; font-size:15px; font-weight:600; cursor:pointer; }
  button:hover { background:#1e3c72; }
  .alert { padding:10px 12px; border-radius:8px; font-size:13px; margin-top:14px; }
  .alert-error   { background:#fdecea; color:#b71c1c; }
  .alert-success { background:#e8f5e9; color:#1b5e20; }
  .alert-info    { background:#e3f2fd; color:#0d47a1; }
  .foot { text-align:center; margin-top:18px; font-size:13px; color:#666; }
  .foot a { color:#2a5298; font-weight:600; text-decoration:none; }
  .hint { margin-top:18px; background:#f5f7fb; border:1px dashed #ccd4e0; border-radius:8px;
          padding:10px 12px; font-size:12px; color:#555; line-height:1.7; }
  .back { text-align:center; margin-top:12px; font-size:12.5px; }
  .back a { color:#888; text-decoration:none; }
  .back a:hover { color:#2a5298; }
</style>
</head>
<body>
<div class="auth-split">

  <!-- ---------- PICHA YA KUSHOTO ---------- -->
  <div class="auth-side-img" style="background-image:url('<?= e(img_url('login_side')) ?>');">
    <div class="side-quote">
      <strong>SMS Shule</strong> — matokeo, mahudhurio na comments za mwalimu,
      zote zinafika kwa mzazi siku hiyo hiyo.
    </div>
  </div>

  <!-- ---------- FORM YA KULIA ---------- -->
  <div class="auth-side-form">
    <div class="card">
      <div class="logo">SMS</div>
      <h1>SMS Shule</h1>
      <p class="sub">Mfumo wa Usimamizi wa Shule</p>

      <?= render_flashes() ?>
      <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

      <form method="POST" action="">
        <?= csrf_field() ?>

        <label for="email">Barua Pepe</label>
        <input type="email" id="email" name="email" value="<?= e($email) ?>"
               placeholder="mf: mzazi@gmail.com" required autofocus>

        <label for="password">Nywira</label>
        <input type="password" id="password" name="password" placeholder="••••••" required>

        <button type="submit">Ingia</button>
      </form>

      <p class="foot">Huna akaunti? <a href="register.php">Sajili kama mzazi</a></p>
      <p class="back"><a href="../index.php">&larr; Rudi nyumbani</a></p>

      <div class="hint">
        <strong>Akaunti za mfano</strong> (nywira: 123456)<br>
        Admin: admin@shule.ac.tz<br>
        Mwalimu: juma@shule.ac.tz<br>
        Mzazi: mzazi@gmail.com
      </div>
    </div>
  </div>

</div>
</body>
</html>
