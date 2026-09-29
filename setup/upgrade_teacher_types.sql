-- ============================================================
-- UPGRADE: Aina mpya za walimu (endesha MARA MOJA kwenye phpMyAdmin)
--
-- Jinsi ya kutumia:
--   1. Fungua http://localhost/phpmyadmin
--   2. Chagua database "sms_shule" -> tab ya "SQL"
--   3. Copy-paste SQL hii -> "Go"
--
-- Inaongeza: Mwalimu Mkuu, Mkuu Msaidizi, Mwalimu wa Nidhamu
-- (data iliyopo ya 'subject' na 'class' haiharibiki)
-- ============================================================

USE sms_shule;

ALTER TABLE teachers
  MODIFY teacher_type ENUM('subject','class','head','assistant_head','discipline')
  NOT NULL DEFAULT 'subject';
