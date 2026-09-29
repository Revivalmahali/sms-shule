-- ============================================================
-- SMS-SHULE: School Management System (Msingi & Sekondari)
-- HATUA 1: Database kamili + Seed Data ya mfano
--
-- Jinsi ya kutumia (XAMPP):
--   1. Anzisha Apache na MySQL kwenye XAMPP Control Panel
--   2. Fungua http://localhost/phpmyadmin
--   3. Bonyeza "Import" -> chagua faili hii (database.sql) -> "Go"
--
-- TAARIFA: Kwenye XAMPP default, user ni 'root' na password ni Mtupu ('')
-- ============================================================

CREATE DATABASE IF NOT EXISTS sms_shule
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE sms_shule;

-- ============================================================
-- TABLE 1: users (Watumiaji wote wa mfumo)
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,          -- password_hash() pekee
    role ENUM('admin','teacher','parent','student') NOT NULL DEFAULT 'parent',
    phone VARCHAR(20) DEFAULT NULL,
    photo VARCHAR(255) DEFAULT NULL,         -- njia ya picha (uploads/...)
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 2: teachers (Taarifa za ziada za walimu)
-- teacher_type: aina za walimu (tazama includes/auth.php -> teacher_types())
-- ============================================================
CREATE TABLE IF NOT EXISTS teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    employee_no VARCHAR(30) NOT NULL UNIQUE,
    teacher_type ENUM('subject','class','head','assistant_head','discipline') NOT NULL DEFAULT 'subject',
    qualification VARCHAR(120) DEFAULT NULL,
    CONSTRAINT fk_teachers_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 3: parents (Taarifa za ziada za wazazi)
-- Mzazi mmoja anaweza kuwa na watoto wengi (one-to-many)
-- ============================================================
CREATE TABLE IF NOT EXISTS parents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    relationship ENUM('father','mother','guardian') NOT NULL DEFAULT 'guardian',
    occupation VARCHAR(100) DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL,
    region VARCHAR(80) DEFAULT NULL,
    CONSTRAINT fk_parents_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 4: classes (Madarasa)
-- class_teacher_id = Mwalimu wa Taaluma (teachers.id)
-- ============================================================
CREATE TABLE IF NOT EXISTS classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(60) NOT NULL,               -- mf: "Darasa la Tano", "Form One"
    level ENUM('primary','secondary') NOT NULL DEFAULT 'primary',
    stream VARCHAR(20) DEFAULT NULL,         -- mf: "A", "B", "Green"
    class_teacher_id INT DEFAULT NULL,
    UNIQUE KEY uq_class (name, stream),
    CONSTRAINT fk_classes_teacher FOREIGN KEY (class_teacher_id) REFERENCES teachers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 5: subjects (Masomo)
-- Kila somo ni kwa darasa maalum, na mwalimu wake wa somo
-- ============================================================
CREATE TABLE IF NOT EXISTS subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL,
    code VARCHAR(20) NOT NULL,
    class_id INT NOT NULL,
    teacher_id INT NOT NULL,
    UNIQUE KEY uq_subject (name, class_id),
    CONSTRAINT fk_subjects_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_subjects_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 6: students (Wanafunzi)
-- user_id NULL: mwanafunzi anaweza kuingia au kuingiwa na mzazi tu
-- parent_id: mzazi mwenyewe (parents.id)
-- age inahesabiwa automatically kwenye PHP kutoka date_of_birth
-- ============================================================
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    parent_id INT NOT NULL,
    reg_no VARCHAR(30) NOT NULL UNIQUE,      -- mf: SMS2026-0001 (inaweza generate automatically)
    full_name VARCHAR(120) NOT NULL,
    date_of_birth DATE NOT NULL,
    age INT NOT NULL DEFAULT 0,
    gender ENUM('male','female') NOT NULL,
    class_id INT NOT NULL,
    photo VARCHAR(255) DEFAULT NULL,
    admission_date DATE DEFAULT NULL,
    status ENUM('active','graduated','transferred') NOT NULL DEFAULT 'active',
    CONSTRAINT fk_students_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_students_parent FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE CASCADE,
    CONSTRAINT fk_students_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 7: results (Matokeo)
-- Grade inahesabiwa automatically kwenye PHP:
--   80-100=A, 70-79=B, 60-69=C, 50-59=D, 40-49=E, 0-39=F
-- ============================================================
CREATE TABLE IF NOT EXISTS results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    subject_id INT NOT NULL,
    exam_type ENUM('test','midterm','terminal','annual') NOT NULL DEFAULT 'test',
    marks DECIMAL(5,2) NOT NULL,
    grade CHAR(1) NOT NULL,
    term TINYINT NOT NULL,                   -- 1, 2, 3
    year SMALLINT NOT NULL,
    teacher_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_result (student_id, subject_id, exam_type, term, year),
    CONSTRAINT fk_results_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_results_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    CONSTRAINT fk_results_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 8: attendance (Mahudhurio)
-- ============================================================
CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    date DATE NOT NULL,
    status ENUM('present','absent','late','sick') NOT NULL DEFAULT 'present',
    marked_by INT NOT NULL,                  -- teachers.id aliyeweka mahudhurio
    remarks VARCHAR(255) DEFAULT NULL,
    UNIQUE KEY uq_attendance (student_id, date),
    CONSTRAINT fk_attendance_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_attendance_teacher FOREIGN KEY (marked_by) REFERENCES teachers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 9: comments (Maoni ya walimu)
-- comment_type:
--   'subject'  -> mwalimu wa somo (inonekana kwa mzazi + mwanafunzi)
--   'general'  -> mwalimu wa taaluma (inonekana kwa mzazi + mwanafunzi)
--   'behavior' -> mwalimu yeyote (kwa MZAZI TU)
--   'head'     -> mkuu wa shule/admin (inonekana kwa mzazi + mwanafunzi)
-- ============================================================
CREATE TABLE IF NOT EXISTS comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    teacher_id INT NOT NULL,
    subject_id INT DEFAULT NULL,             -- NULL kwa general/behavior/head
    comment_type ENUM('subject','general','behavior','head') NOT NULL DEFAULT 'subject',
    comment TEXT NOT NULL,
    term TINYINT NOT NULL,
    year SMALLINT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_comments_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_comments_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    CONSTRAINT fk_comments_subject FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 10: student_photos (Picha za wanafunzi na shughuli)
-- Upload validation ifanyike PHP: jpg/png TU, max 2MB
-- ============================================================
CREATE TABLE IF NOT EXISTS student_photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT DEFAULT NULL,             -- NULL = picha ya shughuli za darasa/shule
    uploaded_by INT NOT NULL,                -- teachers.id
    photo_url VARCHAR(255) NOT NULL,
    caption VARCHAR(255) DEFAULT NULL,
    category ENUM('profile','activity','event','academic') NOT NULL DEFAULT 'activity',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_photos_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_photos_teacher FOREIGN KEY (uploaded_by) REFERENCES teachers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- TABLE 11: announcements (Taarifa/Matangazo)
-- target: nani anapaswa kuona | class_id NULL = shule nzima
-- ============================================================
CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    message TEXT NOT NULL,
    target ENUM('all','parents','teachers','students') NOT NULL DEFAULT 'all',
    class_id INT DEFAULT NULL,
    created_by INT NOT NULL,                 -- users.id (admin)
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ann_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_ann_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- ============================================================
--  SEED DATA (DATA YA MFANO) — passwords zote ni: 123456
-- ============================================================
-- ============================================================

-- ----------
-- 1. ADMIN mmoja
-- Login: admin@shule.ac.tz / 123456
-- ----------
INSERT INTO users (full_name, email, password, role, phone, photo, status) VALUES
('Mkuu wa Shule', 'admin@shule.ac.tz', '$2y$10$0ilmAfFDMTDFTQfvTDWkFe1N7LvURzufrNUkmHj8xYRLNDpzKfDFa', 'admin', '+255700000001', NULL, 'active');

-- ----------
-- 2. WATUMIAJI WA WALIMU (2)
-- Mwalimu 1: juma@shule.ac.tz / 123456  (Mwalimu wa Taaluma - class)
-- Mwalimu 2: ana@shule.ac.tz / 123456   (Mwalimu wa Somo - subject)
-- ----------
INSERT INTO users (full_name, email, password, role, phone, photo, status) VALUES
('Juma Mwalimu', 'juma@shule.ac.tz', '$2y$10$0ilmAfFDMTDFTQfvTDWkFe1N7LvURzufrNUkmHj8xYRLNDpzKfDFa', 'teacher', '+255700000002', NULL, 'active'),
('Ana Mwalimu', 'ana@shule.ac.tz', '$2y$10$0ilmAfFDMTDFTQfvTDWkFe1N7LvURzufrNUkmHj8xYRLNDpzKfDFa', 'teacher', '+255700000003', NULL, 'active');

INSERT INTO teachers (user_id, employee_no, teacher_type, qualification) VALUES
(2, 'EMP-001', 'class', 'Diploma ya Ualimu (Msingi)'),
(3, 'EMP-002', 'subject', 'Shahada ya Sayansi na Ualimu');

-- ----------
-- 3. MZAZI MMOJA
-- Login: mzazi@gmail.com / 123456
-- ----------
INSERT INTO users (full_name, email, password, role, phone, photo, status) VALUES
('Hamisi Mzazi', 'mzazi@gmail.com', '$2y$10$0ilmAfFDMTDFTQfvTDWkFe1N7LvURzufrNUkmHj8xYRLNDpzKfDFa', 'parent', '+255700000004', NULL, 'active');

INSERT INTO parents (user_id, relationship, occupation, address, region) VALUES
(4, 'father', 'Mfanyabiashara', 'Mtaa wa Uhuru, S/L 145', 'Dar es Salaam');

-- ----------
-- 4. MADARASA MAWILI
-- Darasa la Tano (A) -> Class Teacher: Juma (teachers.id = 1)
-- Darasa la Sita (A) -> Class Teacher: Juma (teachers.id = 1)
-- ----------
INSERT INTO classes (name, level, stream, class_teacher_id) VALUES
('Darasa la Tano', 'primary', 'A', 1),
('Darasa la Sita', 'primary', 'A', 1);

-- ----------
-- 5. MASOMO MANNE
-- Juma (id=1): Kiswahili + Maendeleo (Tano); Ana (id=2): Hisabati (Tano) + Sayansi (Sita)
-- ----------
INSERT INTO subjects (name, code, class_id, teacher_id) VALUES
('Kiswahili', 'KIS', 1, 1),
('Maendeleo ya Jamii', 'MAJ', 1, 1),
('Hisabati', 'HIS', 1, 2),
('Sayansi', 'SAY', 2, 2);

-- ----------
-- 6. WANAFUNZI WAWILI (chini ya mzazi mmoja)
-- Umri umehesabiwa kulingana na tarehe ya kuzaliwa
-- ----------
INSERT INTO students (user_id, parent_id, reg_no, full_name, date_of_birth, age, gender, class_id, photo, admission_date, status) VALUES
(NULL, 1, 'SMS2026-0001', 'Asha Hamisi', '2015-03-12', 11, 'female', 1, NULL, '2024-01-15', 'active'),
(NULL, 1, 'SMS2026-0002', 'Baraka Hamisi', '2014-07-25', 12, 'male', 2, NULL, '2023-01-10', 'active');

-- ----------
-- 7. MATOKEO YA MFANO (term 1, mwaka 2026)
-- Grades zinazuilwa kwa kiwango: 80-100=A, 70-79=B, 60-69=C, 50-59=D, 40-49=E, 0-39=F
-- ----------
INSERT INTO results (student_id, subject_id, exam_type, marks, grade, term, year, teacher_id) VALUES
-- Asha (id=1) - Darasa la Tano
(1, 1, 'midterm', 85.00, 'A', 1, 2026, 1),   -- Kiswahili
(1, 2, 'midterm', 68.00, 'C', 1, 2026, 1),   -- Maendeleo
(1, 3, 'midterm', 74.50, 'B', 1, 2026, 2),   -- Hisabati
-- Baraka (id=2) - Darasa la Sita
(2, 4, 'midterm', 55.00, 'D', 1, 2026, 2);   -- Sayansi

-- ----------
-- 8. MAHUDHURIO YA MFANO (tarehe za hivi karibuni)
-- ----------
INSERT INTO attendance (student_id, date, status, marked_by, remarks) VALUES
(1, '2026-02-02', 'present', 1, NULL),
(1, '2026-02-03', 'late',    1, 'Alifika saa mbili asubuhi'),
(1, '2026-02-04', 'present', 1, NULL),
(2, '2026-02-02', 'absent',  1, 'Aliugua - homa'),
(2, '2026-02-03', 'sick',    1, 'Yuko kliniki'),
(2, '2026-02-04', 'present', 1, NULL);

-- ----------
-- 9. COMMENTS ZA MFANO (aina nne)
-- ----------
INSERT INTO comments (student_id, teacher_id, subject_id, comment_type, comment, term, year) VALUES
(1, 1, 1, 'subject', 'Asha anafanya vizuri sana kwenye Kiswahili. Aendelee kusoma hadithi nyumbani.', 1, 2026),
(1, 1, NULL, 'general', 'Asha ni mwanafunzi mchangamfu na mwenye nidhamu nzuri darasani.', 1, 2026),
(1, 2, NULL, 'behavior', 'Tafadhali msemeshe Asha kuhusu kukamilisha kazi za nyumbani kwa wakati.', 1, 2026),
(1, 1, NULL, 'head', 'Hongera Asha! Endelea na bidii ileile hadi mwisho wa mwaka.', 1, 2026),
(2, 1, NULL, 'general', 'Baraka anajitahidi lakini anahitaji kuongeza juhudi kwenye Hisabati.', 1, 2026);

-- ----------
-- 10. TAARIFA/MATANGAZO YA MFANO
-- ----------
INSERT INTO announcements (title, message, target, class_id, created_by) VALUES
('Mkutano wa Wazazi', 'Mkutano wa wazazi na walimu utafanyika Jumamosi ijayo saa kumi asubuhi ukumbi mkuu. Karibuni wote!', 'all', NULL, 1),
('Majaribio ya Midterm', 'Majaribio ya midterm yataanza Alhamisi ijayo. Wanafunzi wajipange vizuri.', 'parents', 1, 1);

-- ============================================================
-- MWISHO WA SEED DATA
-- ============================================================
-- FUATILIA HATUA 2: config/db.php, includes/auth.php, auth/login.php,
--                  auth/register.php, auth/logout.php
--
-- NYWIRA (Passwords) ZA MFANO — zote ni: 123456
--   Admin   : admin@shule.ac.tz
--   Mwalimu : juma@shule.ac.tz  (mwalimu wa taaluma)
--             ana@shule.ac.tz   (mwalimu wa somo)
--   Mzazi   : mzazi@gmail.com
--
-- MUHIMU: Baada ya ku-import, fungua MARA MOJA:
--   http://localhost/sms-shule/setup/fix_passwords.php
-- (Script hii inahifadhi nywira za mfano kwa password_hash() kisha UIFUTE)
-- ============================================================
