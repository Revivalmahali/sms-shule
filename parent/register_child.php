<?php
/**
 * parent/register_child.php
 * Usajili wa mtoto chini ya mzazi aliyeingia.
 *
 * Inafanya:
 *  - Uhakiki kamili wa server (jina, DOB, jinsia, darasa, picha)
 *  - Reg No ina-generate automatically: SMS{MWAKA}-{NAMBA}
 *  - Umri unahesabiwa automatically kutoka tarehe ya kuzaliwa
 *  - Picha: JPG/PNG tu, max 2MB, renamed: student_{id}_{timestamp}.jpg
 *  - Account ya mtoto inaundwa automatically:
 *      email  = reg_no (mf: sms2026-0003@sms-shule.local)
 *      nywira = tarehe ya kuzaliwa DDMMYYYY (mf: 12032015)
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

$errors  = [];
$success = null;   // taarifa za akaunti ya mtoto baada ya kusajiliwa
$old = ['full_name' => '', 'date_of_birth' => '', 'gender' => 'male', 'class_id' => '', 'admission_date' => date('Y-m-d')];

/* ---------- Madarasa yaliyopo kwa dropdown ---------- */
$classes = $pdo->query('SELECT id, name, stream, level FROM classes ORDER BY level, name, stream')->fetchAll();

/* ---------- Helper: ku-upload picha ya mtoto ---------- */
function handle_child_photo(array $file, int $studentId): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null; // mzazi hakuchagua picha — si lazima
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Picha imeshindikana kupakiwa. Jaribu tena.');
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('Picha ni kubwa sana. Ruhusa ni hadi 2MB.');
    }
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Aina ya faili hairuhusiwi. Tumia picha ya JPG au PNG tu.');
    }
    $relName = 'students/student_' . $studentId . '_' . time() . '.' . $allowed[$mime];
    $target  = dirname(__DIR__) . '/uploads/' . $relName;
    if (!move_uploaded_file($file['tmp_name'], $target)) {
        throw new RuntimeException('Picha imeshindikana kuhifadhiwa kwenye server.');
    }
    return 'uploads/' . $relName;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    foreach ($old as $k => $_) {
        $old[$k] = trim($_POST[$k] ?? '');
    }

    /* ---------- Uhakiki wa server ---------- */
    if (mb_strlen($old['full_name']) < 3) {
        $errors[] = 'Jina kamili la mtoto linahitaji angalau herufi 3.';
    }

    $dob = DateTime::createFromFormat('Y-m-d', $old['date_of_birth']);
    if (!$dob || $dob->format('Y-m-d') !== $old['date_of_birth']) {
        $errors[] = 'Tarehe ya kuzaliwa si sahihi.';
    } elseif ($dob > new DateTime()) {
        $errors[] = 'Tarehe ya kuzaliwa haiwezi kuwa ijayo.';
    } elseif ($dob < new DateTime('-25 years')) {
        $errors[] = 'Tarehe ya kuzaliwa haionekani kuwa ya mwanafunzi wa shule.';
    }

    if (!in_array($old['gender'], ['male', 'female'], true)) {
        $errors[] = 'Chagua jinsia sahihi (me/ke).';
    }

    $class_id = (int)$old['class_id'];
    $chk = $pdo->prepare('SELECT id FROM classes WHERE id = ? LIMIT 1');
    $chk->execute([$class_id]);
    if (!$class_id || !$chk->fetch()) {
        $errors[] = 'Chagua darasa sahihi.';
    }

    $adm = DateTime::createFromFormat('Y-m-d', $old['admission_date']);
    if (!$adm || $adm->format('Y-m-d') !== $old['admission_date']) {
        $old['admission_date'] = date('Y-m-d'); // default: leo
    }

    /* ---------- Hifadhi: users(student) + students + picha ---------- */
    if (!$errors) {
        try {
            $age = (new DateTime())->diff($dob)->y;   // umri automatic

            $pdo->beginTransaction();

            // 1. Reg No automatic — jaribu hadi mara 3 (dhidi ya duplicate)
            $regNo = null;
            for ($attempt = 0; $attempt < 3; $attempt++) {
                try {
                    $cnt = (int)$pdo->query('SELECT COUNT(*) FROM students')->fetchColumn();
                    $candidate = 'SMS' . date('Y') . '-' . str_pad((string)($cnt + 1), 4, '0', STR_PAD_LEFT);
                    $dup = $pdo->prepare('SELECT id FROM students WHERE reg_no = ?');
                    $dup->execute([$candidate]);
                    if (!$dup->fetch()) { $regNo = $candidate; break; }
                } catch (PDOException $e) { /* jaribu tena */ }
            }
            if (!$regNo) throw new RuntimeException('Imeshindikana kutengeneza namba ya usajili. Jaribu tena.');

            // 2. Account ya mtoto (email = reg_no, nywira = DOB DDMMYYYY)
            $childEmail   = strtolower($regNo) . '@sms-shule.local';
            $childPassRaw = $dob->format('dmY');
            $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role, phone, status)
                                   VALUES (?, ?, ?, 'student', NULL, 'active')");
            $stmt->execute([$old['full_name'], $childEmail, password_hash($childPassRaw, PASSWORD_DEFAULT)]);
            $childUserId = (int)$pdo->lastInsertId();

            // 3. Record ya mwanafunzi
            $stmt = $pdo->prepare(
                "INSERT INTO students (user_id, parent_id, reg_no, full_name, date_of_birth, age, gender, class_id, photo, admission_date, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, 'active')"
            );
            $stmt->execute([
                $childUserId, $parent_id, $regNo, $old['full_name'],
                $dob->format('Y-m-d'), $age,
                $old['gender'] === 'male' ? 'male' : 'female',
                $class_id, $old['admission_date'],
            ]);
            $studentId = (int)$pdo->lastInsertId();

            $pdo->commit();

            // 4. Picha (nje ya transaction — faili si DB)
            try {
                $photoPath = handle_child_photo($_FILES['photo'] ?? [], $studentId);
                if ($photoPath) {
                    $upd = $pdo->prepare('UPDATE students SET photo = ? WHERE id = ?');
                    $upd->execute([$photoPath, $studentId]);
                }
            } catch (RuntimeException $ex) {
                set_flash('error', $ex->getMessage() . ' (Mtoto amesajiliwa; pakia picha tena baadaye.)');
            }

            // Onyesha taarifa za akaunti ya mtoto kwa mzazi
            $success = [
                'name'    => $old['full_name'],
                'reg_no'  => $regNo,
                'email'   => $childEmail,
                'pass'    => $childPassRaw,
            ];
            $old = ['full_name' => '', 'date_of_birth' => '', 'gender' => 'male', 'class_id' => '', 'admission_date' => date('Y-m-d')];
        } catch (RuntimeException $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = $ex->getMessage();
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'Samahani, usajili haukufanikiwa. Jaribu tena.';
        }
    }
}

$page_title = 'Sajili Mtoto';
require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($errors): ?>
  <div class="alert alert-error">
    <?php foreach ($errors as $err): ?><?= e($err) ?><br><?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if ($success): ?>
  <div class="alert alert-success">
    &#10004; <strong><?= e($success['name']) ?></strong> amesajiliwa kikamilifu!
    Reg No yake ni <strong><?= e($success['reg_no']) ?></strong>.<br><br>
    <strong>Akaunti ya mtoto (kwa ajili yeye kuingia mwenyewe):</strong><br>
    Barua pepe: <code><?= e($success['email']) ?></code> &nbsp;|&nbsp;
    Nywira: <code><?= e($success['pass']) ?></code> (tarehe ya kuzaliwa DDMMYYYY)<br><br>
    <em>Andika taarifa hizi mahali salama — nywira haionyeshwi tena.</em>
  </div>
<?php endif; ?>

<div class="card">
  <div class="card-header"><h2>&#10133; Sajili Mtoto Mpya</h2></div>

  <form method="POST" action="" enctype="multipart/form-data">
    <?= csrf_field() ?>

    <div class="form-grid">
      <div class="form-group full">
        <label>Jina Kamili la Mtoto *</label>
        <input type="text" name="full_name" value="<?= e($old['full_name']) ?>"
               placeholder="mf: Asha Hamisi" minlength="3" required>
      </div>

      <div class="form-group">
        <label>Tarehe ya Kuzaliwa *</label>
        <input type="date" name="date_of_birth" value="<?= e($old['date_of_birth']) ?>"
               max="<?= date('Y-m-d') ?>" required>
        <span class="hint">Umri utahesabiwa automatically.</span>
      </div>

      <div class="form-group">
        <label>Jinsia *</label>
        <select name="gender" required>
          <option value="male"   <?= $old['gender'] === 'male'   ? 'selected' : '' ?>>Me (Male)</option>
          <option value="female" <?= $old['gender'] === 'female' ? 'selected' : '' ?>>Ke (Female)</option>
        </select>
      </div>

      <div class="form-group">
        <label>Darasa *</label>
        <select name="class_id" required>
          <option value="">— chagua darasa —</option>
          <?php foreach ($classes as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= (string)$old['class_id'] === (string)$c['id'] ? 'selected' : '' ?>>
              <?= e($c['name']) ?><?= $c['stream'] ? ' (' . e($c['stream']) . ')' : '' ?>
              — <?= $c['level'] === 'primary' ? 'Msingi' : 'Sekondari' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label>Tarehe ya Kujiunga Shule</label>
        <input type="date" name="admission_date" value="<?= e($old['admission_date']) ?>">
      </div>

      <div class="form-group">
        <label>Picha ya Mtoto (hiari)</label>
        <input type="file" name="photo" accept=".jpg,.jpeg,.png,image/jpeg,image/png" data-preview="photo_preview">
        <span class="hint">JPG au PNG tu, hadi 2MB.</span>
      </div>

      <div class="form-group">
        <label>Onyesho la Picha</label>
        <img id="photo_preview" src="" alt="" style="display:none; width:90px; height:90px;
             border-radius:50%; object-fit:cover; border:2px solid var(--border);">
      </div>

      <div class="form-group full">
        <span class="hint">
          <strong>Namba ya usajili (Reg No)</strong> itatengenezwa automatically (mf: SMS2026-0003).
          Account ya mtoto nayo itaundwa automatically — utaonyeshwa barua pepe na nywira yake
          mara moja baada ya kusajili.
        </span>
      </div>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn">Sajili Mtoto</button>
      <a class="btn btn-secondary" href="<?= url('/parent/dashboard.php') ?>">Ghairi</a>
    </div>
  </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
