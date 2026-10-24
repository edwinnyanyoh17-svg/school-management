CREATE DATABASE IF NOT EXISTS school_management
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE school_management;

CREATE TABLE IF NOT EXISTS admin_users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(80) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS auth_login_attempts (
    ip_hash CHAR(64) PRIMARY KEY,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    window_started DATETIME NOT NULL,
    locked_until DATETIME DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS students (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(80) NOT NULL,
    last_name VARCHAR(80) NOT NULL,
    date_of_birth DATE NOT NULL,
    gender VARCHAR(20) NOT NULL,
    class_name VARCHAR(40) NOT NULL,
    guardian_name VARCHAR(160) NOT NULL,
    guardian_phone VARCHAR(30) NOT NULL,
    email VARCHAR(190) DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL,
    student_number VARCHAR(40) DEFAULT NULL UNIQUE,
    photo_path VARCHAR(255) DEFAULT NULL,
    enrollment_status ENUM('Active', 'Pending', 'Withdrawn', 'Graduated') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_students_name (last_name, first_name),
    INDEX idx_students_class (class_name)
);

CREATE TABLE IF NOT EXISTS teachers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_number VARCHAR(40) DEFAULT NULL UNIQUE,
    first_name VARCHAR(80) NOT NULL,
    last_name VARCHAR(80) NOT NULL,
    email VARCHAR(190) DEFAULT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    subject VARCHAR(100) DEFAULT NULL,
    photo_path VARCHAR(255) DEFAULT NULL,
    status ENUM('Active', 'On leave', 'Inactive') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS classes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE,
    grade_level VARCHAR(40) DEFAULT NULL,
    room VARCHAR(40) DEFAULT NULL,
    teacher_id INT UNSIGNED DEFAULT NULL,
    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_classes_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS enrollments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    class_id INT UNSIGNED NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    enrolled_on DATE NOT NULL,
    status ENUM('Active', 'Pending', 'Completed', 'Withdrawn') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_enrollments_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_enrollments_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE RESTRICT,
    UNIQUE KEY uq_student_year (student_id, academic_year),
    INDEX idx_enrollments_class (class_id)
);

CREATE TABLE IF NOT EXISTS attendance (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    class_id INT UNSIGNED DEFAULT NULL,
    attendance_date DATE NOT NULL,
    status ENUM('Present', 'Absent', 'Late', 'Excused') NOT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_attendance_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    CONSTRAINT fk_attendance_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL,
    UNIQUE KEY uq_attendance_student_day (student_id, attendance_date),
    INDEX idx_attendance_date (attendance_date)
);

CREATE TABLE IF NOT EXISTS payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id INT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'USD',
    paid_on DATE NOT NULL,
    method VARCHAR(40) DEFAULT NULL,
    status ENUM('Paid', 'Pending', 'Waived', 'Refunded') NOT NULL DEFAULT 'Paid',
    reference VARCHAR(100) DEFAULT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_payments_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    INDEX idx_payments_date (paid_on),
    INDEX idx_payments_status (status)
);

CREATE TABLE IF NOT EXISTS timetable (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_id INT UNSIGNED NOT NULL,
    teacher_id INT UNSIGNED DEFAULT NULL,
    subject VARCHAR(100) NOT NULL,
    day_of_week ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    room VARCHAR(40) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_timetable_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    CONSTRAINT fk_timetable_teacher FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL,
    INDEX idx_timetable_day_time (day_of_week, start_time)
);

CREATE TABLE IF NOT EXISTS school_settings (
    setting_key VARCHAR(80) PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS announcements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    category ENUM('News','Event','Notice') NOT NULL DEFAULT 'News',
    summary VARCHAR(400) NOT NULL,
    body TEXT NOT NULL,
    published_on DATE NOT NULL,
    event_date DATE DEFAULT NULL,
    status ENUM('Draft','Published') NOT NULL DEFAULT 'Draft',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_announcements_public (status, published_on)
);

CREATE TABLE IF NOT EXISTS learning_updates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_id INT UNSIGNED NOT NULL,
    subject VARCHAR(100) NOT NULL,
    topic VARCHAR(180) NOT NULL,
    summary TEXT NOT NULL,
    published_on DATE NOT NULL,
    status ENUM('Draft','Published') NOT NULL DEFAULT 'Draft',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_learning_updates_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    INDEX idx_learning_public (status, published_on),
    INDEX idx_learning_class (class_id)
);

CREATE TABLE IF NOT EXISTS school_achievements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    summary VARCHAR(1000) NOT NULL,
    achieved_on DATE DEFAULT NULL,
    status ENUM('Draft','Published') NOT NULL DEFAULT 'Draft',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_achievements_public (status, achieved_on)
);

CREATE TABLE IF NOT EXISTS admission_applications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference_code VARCHAR(48) NOT NULL UNIQUE,
    student_first_name VARCHAR(80) NOT NULL,
    student_last_name VARCHAR(80) NOT NULL,
    date_of_birth DATE NOT NULL,
    gender VARCHAR(30) NOT NULL,
    grade_applied VARCHAR(80) NOT NULL,
    previous_school VARCHAR(180) DEFAULT NULL,
    parent_name VARCHAR(160) NOT NULL,
    parent_email VARCHAR(190) NOT NULL,
    parent_phone VARCHAR(40) NOT NULL,
    parent_note TEXT DEFAULT NULL,
    status ENUM('Received','Under review','Exam scheduled','Exam passed','Exam not passed','Admitted','Declined') NOT NULL DEFAULT 'Received',
    staff_note TEXT DEFAULT NULL,
    student_id INT UNSIGNED DEFAULT NULL UNIQUE,
    submitted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_admission_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE SET NULL,
    INDEX idx_admission_status (status, submitted_at)
);

CREATE TABLE IF NOT EXISTS admission_documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    application_id INT UNSIGNED NOT NULL,
    original_name VARCHAR(190) NOT NULL,
    storage_name CHAR(64) NOT NULL UNIQUE,
    mime_type VARCHAR(80) NOT NULL,
    size_bytes INT UNSIGNED NOT NULL,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_admission_document_application FOREIGN KEY (application_id) REFERENCES admission_applications(id) ON DELETE CASCADE,
    INDEX idx_admission_documents_application (application_id)
);

CREATE TABLE IF NOT EXISTS admission_rate_limits (
    ip_hash CHAR(64) PRIMARY KEY,
    attempt_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    window_started DATETIME NOT NULL,
    blocked_until DATETIME DEFAULT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Build class records from existing, real student class assignments.
INSERT IGNORE INTO classes (name)
SELECT DISTINCT TRIM(class_name)
FROM students
WHERE class_name IS NOT NULL AND TRIM(class_name) <> '';
