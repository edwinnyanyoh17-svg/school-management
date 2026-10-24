-- Run once in phpMyAdmin on an existing installation.
USE school_management;

SET @teacher_photo_sql = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'teachers' AND COLUMN_NAME = 'photo_path'),
    'SELECT 1',
    'ALTER TABLE teachers ADD COLUMN photo_path VARCHAR(255) DEFAULT NULL'
);
PREPARE teacher_photo_statement FROM @teacher_photo_sql;
EXECUTE teacher_photo_statement;
DEALLOCATE PREPARE teacher_photo_statement;

SET @class_status_sql = IF(
    EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'classes' AND COLUMN_NAME = 'status'),
    'SELECT 1',
    'ALTER TABLE classes ADD COLUMN status ENUM(''Active'',''Inactive'') NOT NULL DEFAULT ''Active'''
);
PREPARE class_status_statement FROM @class_status_sql;
EXECUTE class_status_statement;
DEALLOCATE PREPARE class_status_statement;

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
    reference_code VARCHAR(24) NOT NULL UNIQUE,
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

SET @reference_column_length = (
    SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admission_applications' AND COLUMN_NAME = 'reference_code'
);
SET @reference_column_sql = IF(
    @reference_column_length < 48,
    'ALTER TABLE admission_applications MODIFY reference_code VARCHAR(48) NOT NULL',
    'SELECT 1'
);
PREPARE reference_column_statement FROM @reference_column_sql;
EXECUTE reference_column_statement;
DEALLOCATE PREPARE reference_column_statement;

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
