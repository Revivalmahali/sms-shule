<?php
/**
 * config/db.php
 * Muunganisho wa Database (PDO) + Session + Helper functions
 * Jumlisha kwenye kila ukurasa: require_once __DIR__ . '/../config/db.php';
 */

/* ---------- Mipangilio ya Database (badilisha kulingana na mazingira yako) ---------- */
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'sms_shule');
define('DB_USER', 'root');
define('DB_PASS', '');          // XAMPP default: password tupu
define('DB_CHARSET', 'utf8mb4');

/* ---------- Session (inanza mara moja tu) ---------- */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ---------- BASE_URL (inagundulika kiotomatiki, mf: /sms-shule) ---------- */
if (!defined('BASE_URL')) {
    $projectRoot = str_replace('\\', '/', dirname(__DIR__));
    $docRoot     = isset($_SERVER['DOCUMENT_ROOT'])
                 ? rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/')
                 : '';
    define('BASE_URL', ($docRoot !== '' && stripos($projectRoot, $docRoot) === 0)
        ? rtrim(substr($projectRoot, strlen($docRoot)), '/')
        : '');
}

/* ---------- Muunganisho wa PDO ---------- */
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // Makosa yakapeleke exception
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // Fetch kama associative array
            PDO::ATTR_EMULATE_PREPARES   => false,                   // Prepared statements za kweli (usalama)
        ]
    );
} catch (PDOException $ex) {
    // Ujumbe wa kirafiki kwa Kiswahili (matakwa ya kiufundi yamefichwa)
    if ((int)$ex->getCode() === 1049) {
        die('<div style="font-family:sans-serif;max-width:520px;margin:60px auto;padding:24px;border:1px solid #f44336;border-radius:10px">
              <h3>Database "sms_shule" haipatikani.</h3>
              <p>Tafadhali import faili ya <b>database.sql</b> kwenye phpMyAdmin kwanza
              (<a href="http://localhost/phpmyadmin">localhost/phpmyadmin</a>).</p></div>');
    }
    die('<div style="font-family:sans-serif;max-width:520px;margin:60px auto;padding:24px;border:1px solid #f44336;border-radius:10px">
          <h3>Imeshindikana kuunganisha na database.</h3>
          <p>Hakikisha <b>MySQL</b> inaendelea kukimbia kwenye XAMPP Control Panel.</p></div>');
}

/* ============================================================
   HELPERS — kazi ndogo zinazotumiwa kote kwenye mfumo
   ============================================================ */

/**
 * e(): fiche output kwa usalama (uzuia XSS)
 * Njia fupi ya htmlspecialchars — tumia kwa KILA data inayoonyeshwa
 */
function e(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/** url(): tengeneza URL kamili ndani ya mradi, mf: url('/auth/login.php') */
function url(string $path): string {
    return BASE_URL . $path;
}

/** redirect(): elekeza mtumiaji kwenye ukurasa mwingine na simama */
function redirect(string $path): void {
    header('Location: ' . url($path));
    exit;
}

/* ---------- CSRF Token (usalama wa forms) ---------- */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** csrf_field(): hidden input ya CSRF kwa kila form */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

/** verify_csrf(): hakiki token ya form iliyotumwa (kila POST inapashapass hapa) */
function verify_csrf(): void {
    if (!isset($_POST['csrf_token']) || !hash_equals(csrf_token(), (string)$_POST['csrf_token'])) {
        http_response_code(403);
        die('Hitilafu ya usalama (CSRF token si sahihi). Rudi nyuma, pakia upya ukurasa na jaribu tena.');
    }
}

/* ---------- Flash Messages (ujumbe wa muda unaonekana mara moja) ---------- */
function set_flash(string $type, string $message): void {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** Rejesha HTML ya flash messages (success = kijani, error = nyekundu) kisha zifute */
function render_flashes(): string {
    $html = '';
    foreach (($_SESSION['flash'] ?? []) as $flash) {
        $class = $flash['type'] === 'success' ? 'alert-success'
               : ($flash['type'] === 'error'   ? 'alert-error'   : 'alert-info');
        $html .= '<div class="alert ' . $class . '">' . e($flash['message']) . '</div>';
    }
    unset($_SESSION['flash']);
    return $html;
}
