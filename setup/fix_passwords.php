<?php
/**
 * setup/fix_passwords.php   — SCRIPT YA MARA MOJA TU
 *
 * Huhifadhi upya nywira za akaunti za MFANO kwa password_hash()
 * ili kuhakikisha login inafanya kazi baada ya ku-import database.sql.
 * Nywira ya kila akaunti ya mfano: 123456
 *
 * Jinsi ya kutumia:
 *   1. Fungua http://localhost/sms-shule/setup/fix_passwords.php
 *   2. Ukiona ujumbe wa mafanikio, FUTA faili hili kwa usalama!
 */
require_once __DIR__ . '/../config/db.php';

$emails = [
    'admin@shule.ac.tz',
    'juma@shule.ac.tz',
    'ana@shule.ac.tz',
    'mzazi@gmail.com',
];

$hash  = password_hash('123456', PASSWORD_DEFAULT);
$stmt  = $pdo->prepare('UPDATE users SET password = ? WHERE email = ?');

$n = 0;
foreach ($emails as $email) {
    $stmt->execute([$hash, $email]);
    $n += $stmt->rowCount();
}

echo '<div style="font-family:sans-serif;max-width:560px;margin:60px auto;padding:24px;
      border:1px solid #4caf50;border-radius:10px;background:#e8f5e9">
      <h3 style="color:#1b5e20">&#10004; Akaunti ' . (int)$n . ' za mfano zimehifadhiwa kwa password_hash().</h3>
      <p>Nywira ya kila akaunti ya mfano ni <b>123456</b>.</p>
      <p style="color:#b71c1c"><b>MUHIMU:</b> Sasa futa faili hili
      (setup/fix_passwords.php) kwa usalama.</p></div>';
