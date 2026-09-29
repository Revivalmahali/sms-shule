<?php
/**
 * includes/auth.php
 * Uhakiki wa session na Role-Based Access Control (RBAC)
 * Jumlisha: require_once __DIR__ . '/../includes/auth.php';
 * (Faili hii inajenga juu ya config/db.php kiotomatiki)
 */
require_once __DIR__ . '/../config/db.php';

/** Njia ya dashibodi kwa kila role */
function dashboard_for(string $role): string {
    $map = [
        'admin'   => '/admin/dashboard.php',
        'teacher' => '/teacher/dashboard.php',
        'parent'  => '/parent/dashboard.php',
        'student' => '/student/dashboard.php',
    ];
    return $map[$role] ?? '/auth/login.php';
}

/** Je, mtumiaji ameingia? */
function is_logged_in(): bool {
    return isset($_SESSION['user']) && !empty($_SESSION['user']['id']);
}

/** Taarifa za mtumiaji aliyeingia (au NULL) */
function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

/** Zuia ukurasa kwa waliologin TU — asiyeingia anapelekwa login */
function require_login(): void {
    if (!is_logged_in()) {
        redirect('/auth/login.php');
    }
}

/**
 * Zuia ukurasa kwa roles maalum.
 * Mfano: require_role('admin');  au  require_role('teacher', 'admin');
 * Role isiyo sahihi inapelekwa kwenye dashibodi yake mwenyewe.
 */
function require_role(string ...$roles): void {
    require_login();
    $user = current_user();
    if (!in_array($user['role'], $roles, true)) {
        redirect(dashboard_for($user['role']));
    }
}

/* ---------- Njia za mkato ---------- */
function require_admin(): void   { require_role('admin'); }
function require_teacher(): void { require_role('teacher'); }
function require_parent(): void  { require_role('parent'); }
function require_student(): void { require_role('student'); }

/* ============================================================
   AINA ZA WALIMU (teacher_type)
   Key ni zile zilizo kwenye database ENUM; label ni jina la Kiswahili.
   Kuongeza aina mpya: ongeza hapa + ALTER TABLE kwenye database.
   ============================================================ */
function teacher_types(): array {
    return [
        'subject'        => 'Mwalimu wa Somo',
        'class'          => 'Mwalimu wa Taaluma',
        'head'           => 'Mwalimu Mkuu',
        'assistant_head' => 'Mwalimu Mkuu Msaidizi',
        'discipline'     => 'Mwalimu wa Nidhamu',
    ];
}

/** Jina la Kiswahili la aina ya mwalimu */
function teacher_type_label(string $type): string {
    return teacher_types()[$type] ?? $type;
}
