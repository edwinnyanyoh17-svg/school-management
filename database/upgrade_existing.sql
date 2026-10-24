-- Run once on an existing school_management database.
USE school_management;

ALTER TABLE students
    ADD COLUMN student_number VARCHAR(40) DEFAULT NULL UNIQUE,
    ADD COLUMN photo_path VARCHAR(255) DEFAULT NULL,
    ADD COLUMN enrollment_status ENUM('Active', 'Pending', 'Withdrawn', 'Graduated') NOT NULL DEFAULT 'Active';

-- Then import the updated database/schema.sql in phpMyAdmin to create all
-- missing tables. CREATE TABLE IF NOT EXISTS keeps the existing students.
