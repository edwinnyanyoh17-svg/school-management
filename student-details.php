<?php
require __DIR__ . '/config/auth.php';
requireStaffLogin();
require __DIR__ . '/config/database.php';

function escapeHtml($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$studentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$statement = $pdo->prepare('SELECT id, student_number, photo_path, enrollment_status, first_name, last_name, date_of_birth, gender, class_name, guardian_name, guardian_phone, email, address FROM students WHERE id = :id');
$statement->execute(['id' => $studentId ?: 0]);
$student = $statement->fetch();

if (!$student) {
    http_response_code(404);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student details | Northstar</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body class="detail-page">
<header class="detail-header">
    <a class="brand" href="index.php">✳ Northstar Academy</a>
    <nav aria-label="Main navigation"><a href="index.php">Dashboard</a><a href="students.php">Students</a></nav>
</header>
<main class="detail-main">
    <?php if (!$student): ?>
        <section class="detail-card"><p class="eyebrow">STUDENT RECORD</p><h1>Student not found</h1><p class="muted">The student record may have been removed or the link may be incorrect.</p><a class="dashboard-button" href="students.php">Return to student register</a></section>
    <?php else: ?>
        <section class="detail-card">
            <a class="detail-back" href="index.php">← Back to dashboard</a>
            <p class="eyebrow">STUDENT RECORD</p>
            <?php if (!empty($student['photo_path']) && str_starts_with($student['photo_path'], 'images/') && !str_contains($student['photo_path'], '..')): ?><img class="detail-photo" src="<?= escapeHtml($student['photo_path']) ?>" alt="Student photo"><?php endif; ?>
            <h1><?= escapeHtml($student['first_name'] . ' ' . $student['last_name']) ?></h1>
            <p class="muted">Student ID: <?= escapeHtml($student['student_number'] ?: '#' . $student['id']) ?> · <?= escapeHtml($student['enrollment_status']) ?></p>
            <dl class="student-details">
                <div><dt>Class</dt><dd><?= escapeHtml($student['class_name']) ?></dd></div>
                <div><dt>Email</dt><dd><?= $student['email'] ? escapeHtml($student['email']) : 'Not provided' ?></dd></div>
                <div><dt>Date of birth</dt><dd><?= escapeHtml($student['date_of_birth']) ?></dd></div>
                <div><dt>Gender</dt><dd><?= escapeHtml($student['gender']) ?></dd></div>
                <div><dt>Parent or guardian</dt><dd><?= escapeHtml($student['guardian_name']) ?></dd></div>
                <div><dt>Guardian phone</dt><dd><?= escapeHtml($student['guardian_phone']) ?></dd></div>
                <div class="wide"><dt>Address</dt><dd><?= $student['address'] ? escapeHtml($student['address']) : 'Not provided' ?></dd></div>
            </dl>
            <a class="dashboard-button" href="students.php?edit=<?= (int) $student['id'] ?>">Edit student details</a>
        </section>
    <?php endif; ?>
</main>
</body>
</html>
