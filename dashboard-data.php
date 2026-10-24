<?php
// Return staff-only dashboard data from the current database.
require __DIR__ . '/config/auth.php';
startSchoolSession();
sendSecurityHeaders();
header('Content-Type: application/json; charset=utf-8');
if (empty($_SESSION['staff_user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please sign in to view school records.']);
    exit;
}
require __DIR__ . '/config/database.php';


function tableExists(PDO $pdo, string $tableName): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
    );
    $statement->execute(['table_name' => $tableName]);
    return (int) $statement->fetchColumn() > 0;
}

function columnExists(PDO $pdo, string $tableName, string $columnName): bool
{
    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
    );
    $statement->execute(['table_name' => $tableName, 'column_name' => $columnName]);
    return (int) $statement->fetchColumn() > 0;
}

try {
    $expectedTables = ['teachers', 'classes', 'enrollments'];
    $stats = ['students' => null, 'teachers' => null, 'classes' => null, 'enrollments' => null];
    $students = [];

    if (tableExists($pdo, 'students')) {
        $stats['students'] = (int) $pdo->query('SELECT COUNT(*) FROM students')->fetchColumn();

        // These columns are optional. The current schema does not have them yet.
        $hasPhoto = columnExists($pdo, 'students', 'photo_path');
        $hasEnrollmentStatus = columnExists($pdo, 'students', 'enrollment_status');
        $hasStudentNumber = columnExists($pdo, 'students', 'student_number');

        $columns = ['id', 'first_name', 'last_name', 'class_name', 'email'];
        if ($hasPhoto) {
            $columns[] = 'photo_path';
        }
        if ($hasEnrollmentStatus) {
            $columns[] = 'enrollment_status';
        }
        if ($hasStudentNumber) {
            $columns[] = 'student_number';
        }

        $search = trim(substr($_GET['q'] ?? '', 0, 100));
        $sql = 'SELECT ' . implode(', ', $columns) . ' FROM students';
        $parameters = [];
        if ($search !== '') {
            $sql .= ' WHERE CONCAT_WS(" ", first_name, last_name) LIKE :name_search
                OR CAST(id AS CHAR) LIKE :id_search';
            $searchValue = '%' . $search . '%';
            $parameters = ['name_search' => $searchValue, 'id_search' => $searchValue];
            if ($hasStudentNumber) {
                $sql = 'SELECT ' . implode(', ', $columns) . ' FROM students
                    WHERE CONCAT_WS(" ", first_name, last_name) LIKE :name_search
                    OR CAST(id AS CHAR) LIKE :id_search OR student_number LIKE :number_search';
                $parameters['number_search'] = $searchValue;
            }
        }
        $sql .= ' ORDER BY id DESC LIMIT 100';
        $statement = $pdo->prepare($sql);
        $statement->execute($parameters);
        $students = $statement->fetchAll();

        foreach ($students as &$student) {
            // Only serve image paths that stay inside the local images folder.
            $path = $student['photo_path'] ?? '';
            if ($path !== '' && preg_match('#^images/[A-Za-z0-9._/-]+$#', $path) && !str_contains($path, '..')) {
                $student['photo_path'] = $path;
            } else {
                $student['photo_path'] = null;
            }
            $student['full_name'] = trim($student['first_name'] . ' ' . $student['last_name']);
            $student['student_number'] = $student['student_number'] ?? null;
            $student['enrollment_status'] = $student['enrollment_status'] ?? null;
        }
        unset($student);
    } else {
    }

    foreach ($expectedTables as $tableName) {
        if (tableExists($pdo, $tableName)) {
            $stats[$tableName] = (int) $pdo->query('SELECT COUNT(*) FROM `' . $tableName . '`')->fetchColumn();
        } else {
        }
    }

    $attendanceToday = null;
    if (tableExists($pdo, 'attendance')) {
        $attendanceToday = ['Present' => 0, 'Absent' => 0, 'Late' => 0, 'Excused' => 0, 'total' => 0];
        $todayRows = $pdo->query('SELECT status, COUNT(*) AS total FROM attendance WHERE attendance_date = CURRENT_DATE GROUP BY status')->fetchAll();
        foreach ($todayRows as $row) {
            $attendanceToday[$row['status']] = (int)$row['total'];
            $attendanceToday['total'] += (int)$row['total'];
        }
    }

    $todayClasses = [];
    if (tableExists($pdo, 'timetable') && tableExists($pdo, 'classes')) {
        $todayClasses = $pdo->query("SELECT c.name AS class_name, t.subject, t.start_time, t.end_time, t.room
            FROM timetable t JOIN classes c ON c.id = t.class_id
            WHERE t.day_of_week = DAYNAME(CURRENT_DATE)
            ORDER BY t.start_time LIMIT 5")->fetchAll();
    }

    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'students' => $students,
        'attendance_today' => $attendanceToday,
        'today_classes' => $todayClasses,
    ], JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Dashboard data could not be loaded. Check the database connection and students table.']);
}
