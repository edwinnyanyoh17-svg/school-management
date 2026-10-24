<?php
require __DIR__ . '/config/auth.php';
requireStaffLogin();
require __DIR__ . '/config/database.php';

// Escape values before placing them in the HTML page.
function escapeHtml($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$errors = [];
$saved = $_GET['saved'] ?? '';
$searchQuery = $_GET['search'] ?? '';
$search = is_string($searchQuery) ? trim(substr($searchQuery, 0, 100)) : '';
$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT) ?: null;
$editingStudent = null;
$form = [
    'first_name' => '', 'last_name' => '', 'date_of_birth' => '',
    'gender' => '', 'class_name' => '', 'guardian_name' => '',
    'guardian_phone' => '', 'email' => '', 'address' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifySchoolCsrf($_POST['csrf'] ?? null)) {
        http_response_code(403);
        $errors[] = 'Your form expired. Refresh the page and try again.';
    }
    foreach ($form as $key => $defaultValue) {
        $postedValue = $_POST[$key] ?? $defaultValue;
        $form[$key] = is_string($postedValue) ? trim($postedValue) : '';
    }

    $fieldLimits = ['first_name'=>80,'last_name'=>80,'class_name'=>40,'guardian_name'=>160,'guardian_phone'=>30,'email'=>190,'address'=>255];
    foreach ($fieldLimits as $field => $limit) {
        if (strlen($form[$field]) > $limit) $errors[] = 'One or more fields are too long.';
    }
    if (!in_array($form['gender'], ['Female','Male','Prefer not to say'], true)) $errors[] = 'Choose a valid gender option.';

    foreach (['first_name', 'last_name', 'date_of_birth', 'gender', 'class_name', 'guardian_name', 'guardian_phone'] as $requiredField) {
        if ($form[$requiredField] === '') {
            $errors[] = 'Please fill in all required fields.';
            break;
        }
    }

    if ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address, or leave the email field blank.';
    }

    $birthDate = DateTime::createFromFormat('Y-m-d', $form['date_of_birth']);
    if (!$birthDate || $birthDate->format('Y-m-d') !== $form['date_of_birth']) {
        $errors[] = 'Enter a valid date of birth.';
    } elseif ($birthDate > new DateTime('today')) {
        $errors[] = 'Date of birth cannot be in the future.';
    }

    if (!$errors) {
        $studentValues = [
            'first_name' => $form['first_name'],
            'last_name' => $form['last_name'],
            'date_of_birth' => $form['date_of_birth'],
            'gender' => $form['gender'],
            'class_name' => $form['class_name'],
            'guardian_name' => $form['guardian_name'],
            'guardian_phone' => $form['guardian_phone'],
            'email' => $form['email'] !== '' ? $form['email'] : null,
            'address' => $form['address'] !== '' ? $form['address'] : null,
        ];

        if (($_POST['action'] ?? '') === 'update') {
            $editId = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT) ?: null;
            if (!$editId) {
                $errors[] = 'The student record could not be identified. Please choose the student again.';
            }
        }

        if (!$errors && ($_POST['action'] ?? '') === 'update') {
            $sql = 'UPDATE students SET
                first_name = :first_name, last_name = :last_name, date_of_birth = :date_of_birth,
                gender = :gender, class_name = :class_name, guardian_name = :guardian_name,
                guardian_phone = :guardian_phone, email = :email, address = :address
                WHERE id = :id';
            $studentValues['id'] = $editId;
            $successMessage = 'updated';
        } elseif (!$errors) {
            $sql = 'INSERT INTO students
                (first_name, last_name, date_of_birth, gender, class_name, guardian_name, guardian_phone, email, address)
                VALUES (:first_name, :last_name, :date_of_birth, :gender, :class_name, :guardian_name, :guardian_phone, :email, :address)';
            $successMessage = 'created';
        }

        if (!$errors) {
            try {
            $pdo->beginTransaction();
            $statement = $pdo->prepare($sql);
            $statement->execute($studentValues);
            $savedStudentId = $successMessage === 'updated' ? $editId : (int) $pdo->lastInsertId();
            $classInsert = $pdo->prepare('INSERT IGNORE INTO classes (name) VALUES (:name)');
            $classInsert->execute(['name' => $form['class_name']]);
            $classLookup = $pdo->prepare('SELECT id FROM classes WHERE name = :name');
            $classLookup->execute(['name' => $form['class_name']]);
            $classId = (int) $classLookup->fetchColumn();
            $year = $pdo->query("SELECT setting_value FROM school_settings WHERE setting_key='academic_year'")->fetchColumn();
            if (!$year) $year = date('Y') . '-' . (date('Y') + 1);
            $enrollment = $pdo->prepare('INSERT INTO enrollments (student_id, class_id, academic_year, enrolled_on, status)
                VALUES (:student_id, :class_id, :academic_year, CURRENT_DATE, \'Active\')
                ON DUPLICATE KEY UPDATE class_id=VALUES(class_id), status=\'Active\'');
            $enrollment->execute(['student_id' => $savedStudentId, 'class_id' => $classId, 'academic_year' => $year]);

            // Redirect after saving so refreshing the page does not submit the form again.
            $pdo->commit();
            header('Location: students.php?saved=' . $successMessage . ($search !== '' ? '&search=' . urlencode($search) : ''));
            exit;
            } catch (PDOException $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = 'The student record could not be saved. Check the database tables and try again.';
            }
        }
    }
}

// Load the selected student into the form when the user clicks Edit.
if ($editId && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $statement = $pdo->prepare('SELECT * FROM students WHERE id = :id');
    $statement->execute(['id' => $editId]);
    $editingStudent = $statement->fetch();

    if ($editingStudent) {
        foreach ($form as $key => $defaultValue) {
            $form[$key] = $editingStudent[$key] ?? $defaultValue;
        }
    } else {
        $editId = null;
        $errors[] = 'That student record could not be found.';
    }
}

// Search by student name, class, guardian name, or phone number.
$sql = 'SELECT id, first_name, last_name, class_name, guardian_name, guardian_phone, created_at FROM students';
$parameters = [];
if ($search !== '') {
    $sql .= ' WHERE first_name LIKE :search_first OR last_name LIKE :search_last
        OR class_name LIKE :search_class OR guardian_name LIKE :search_guardian OR guardian_phone LIKE :search_phone';
    $searchValue = '%' . $search . '%';
    $parameters = [
        'search_first' => $searchValue,
        'search_last' => $searchValue,
        'search_class' => $searchValue,
        'search_guardian' => $searchValue,
        'search_phone' => $searchValue,
    ];
}
$sql .= ' ORDER BY id DESC LIMIT 100';
$statement = $pdo->prepare($sql);
$statement->execute($parameters);
$students = $statement->fetchAll();
$classNames = $pdo->query('SELECT name FROM classes ORDER BY name')->fetchAll(PDO::FETCH_COLUMN);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student registration | Northstar</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header>
    <a class="brand" href="index.php">✳ Northstar Academy</a>
    <nav aria-label="Main navigation">
        <a href="index.php">Dashboard</a>
        <a href="students.php">Students</a>
    </nav>
</header>
<main>
    <div class="intro">
        <div class="muted">NORTHSTAR ACADEMY / STUDENTS</div>
        <h1>Student records</h1>
        <p>Register students, find records, and keep guardian details up to date.</p>
    </div>

    <?php if ($saved === 'created'): ?>
        <div class="notice success" role="status">Student registered successfully.</div>
    <?php elseif ($saved === 'updated'): ?>
        <div class="notice success" role="status">Student details updated successfully.</div>
    <?php endif; ?>
    <?php if ($errors): ?>
        <div class="notice error" role="alert">
            <strong>Please check the form:</strong>
            <ul><?php foreach ($errors as $error): ?><li><?= escapeHtml($error) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <div class="layout">
        <section class="card">
            <h2><?= $editId ? 'Edit student' : 'New student' ?></h2>
            <form method="post" action="students.php<?= $search !== '' ? '?search=' . urlencode($search) : '' ?>">
                <input type="hidden" name="csrf" value="<?= escapeHtml(schoolCsrfToken()) ?>">
                <input type="hidden" name="action" value="<?= $editId ? 'update' : 'create' ?>">
                <?php if ($editId): ?><input type="hidden" name="student_id" value="<?= (int) $editId ?>"><?php endif; ?>
                <div class="two-columns">
                    <div class="field"><label for="first_name">First name *</label><input id="first_name" name="first_name" maxlength="80" value="<?= escapeHtml($form['first_name']) ?>" required></div>
                    <div class="field"><label for="last_name">Last name *</label><input id="last_name" name="last_name" maxlength="80" value="<?= escapeHtml($form['last_name']) ?>" required></div>
                </div>
                <div class="two-columns">
                    <div class="field"><label for="date_of_birth">Date of birth *</label><input id="date_of_birth" name="date_of_birth" type="date" value="<?= escapeHtml($form['date_of_birth']) ?>" required></div>
                    <div class="field"><label for="gender">Gender *</label><select id="gender" name="gender" required><option value="">Select</option><?php foreach (['Female', 'Male', 'Prefer not to say'] as $gender): ?><option value="<?= escapeHtml($gender) ?>" <?= $form['gender'] === $gender ? 'selected' : '' ?>><?= escapeHtml($gender) ?></option><?php endforeach; ?></select></div>
                </div>
                <div class="field"><label for="class_name">Class *</label><input id="class_name" name="class_name" list="schoolClasses" maxlength="40" placeholder="Choose a class or enter one" value="<?= escapeHtml($form['class_name']) ?>" required><datalist id="schoolClasses"><?php foreach ($classNames as $className): ?><option value="<?= escapeHtml($className) ?>"><?php endforeach; ?></datalist></div>
                <div class="field"><label for="guardian_name">Parent or guardian name *</label><input id="guardian_name" name="guardian_name" maxlength="160" value="<?= escapeHtml($form['guardian_name']) ?>" required></div>
                <div class="field"><label for="guardian_phone">Guardian phone *</label><input id="guardian_phone" name="guardian_phone" type="tel" maxlength="30" value="<?= escapeHtml($form['guardian_phone']) ?>" required></div>
                <div class="field"><label for="email">Student or guardian email</label><input id="email" name="email" type="email" maxlength="190" value="<?= escapeHtml($form['email']) ?>"></div>
                <div class="field"><label for="address">Home address</label><textarea id="address" name="address" maxlength="255" rows="2"><?= escapeHtml($form['address']) ?></textarea></div>
                <button class="button" type="submit"><?= $editId ? 'Save changes' : 'Register student' ?></button>
                <?php if ($editId): ?><a class="button secondary" href="students.php<?= $search !== '' ? '?search=' . urlencode($search) : '' ?>">Cancel</a><?php endif; ?>
            </form>
        </section>

        <section class="card">
            <div class="list-heading"><h2>Registered students <span class="count"><?= count($students) ?></span></h2>
                <form class="student-search" method="get" action="students.php">
                    <label class="visually-hidden" for="search">Search student records</label>
                    <input id="search" name="search" type="search" value="<?= escapeHtml($search) ?>" placeholder="Search name, class, guardian...">
                    <button class="button" type="submit">Search</button>
                    <?php if ($search !== ''): ?><a class="clear-search" href="students.php">Clear</a><?php endif; ?>
                </form>
            </div>
            <?php if (!$students): ?>
                <p class="muted">No students have been registered yet.</p>
            <?php else: ?>
                <div class="table-scroll"><table>
                    <thead><tr><th>Student</th><th>Class</th><th>Guardian</th><th>Phone</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td><?= escapeHtml($student['first_name'] . ' ' . $student['last_name']) ?><br><span class="muted">#<?= (int) $student['id'] ?></span></td>
                            <td><?= escapeHtml($student['class_name']) ?></td>
                            <td><?= escapeHtml($student['guardian_name']) ?></td>
                            <td><?= escapeHtml($student['guardian_phone']) ?></td>
                            <td><a class="edit-link" href="students.php?edit=<?= (int) $student['id'] ?><?= $search !== '' ? '&amp;search=' . urlencode($search) : '' ?>">Edit</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
                <p class="muted"><?= $search !== '' ? 'Search results are limited to the 100 newest matching records.' : 'Showing the 100 most recently registered students.' ?></p>
            <?php endif; ?>
        </section>
    </div>
</main>
</body>
</html>
