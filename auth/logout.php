<?php
/**
 * auth/logout.php
 * Kutoka (logout): futa session zote na rudi kwenye ukurasa wa kuingia
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// 1. Futa data yote ya session
$_SESSION = [];

// 2. Futa cookie ya session (uzuia matumizi tena ya cookie ya zamani)
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// 3. Haribu session kabisa
session_destroy();

// 4. Rudi kwenye ukurasa wa kuingia
redirect('/auth/login.php');
