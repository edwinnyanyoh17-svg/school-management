<?php
declare(strict_types=1);
require __DIR__ . '/config/auth.php';
requireStaffLogin();
require __DIR__ . '/config/database.php';

function adminEscape($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
$settings = [];
foreach ($pdo->query('SELECT setting_key,setting_value FROM school_settings')->fetchAll() as $row) $settings[$row['setting_key']] = $row['setting_value'];
$schoolName = $settings['school_name'] ?? 'Northstar Academy';
$csrf = schoolCsrfToken();
$statuses = ['Received','Under review','Exam scheduled','Exam passed','Exam not passed','Admitted','Declined'];
$selectedId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifySchoolCsrf($_POST['csrf'] ?? null)) { http_response_code(403); exit('Your session expired. Reload the page and try again.'); }
    $action = (string) ($_POST['action'] ?? '');
    $applicationId = filter_var($_POST['application_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$applicationId) { http_response_code(422); exit('Choose a valid application.'); }
    try {
        if ($action === 'update') {
            $status = (string) ($_POST['status'] ?? '');
            $staffNote = trim((string) ($_POST['staff_note'] ?? ''));
            if (!in_array($status, $statuses, true) || strlen($staffNote) > 5000) { http_response_code(422); exit('Choose a valid status and keep staff notes under 5,000 characters.'); }
            $current = $pdo->prepare('SELECT student_id FROM admission_applications WHERE id=:id');
            $current->execute(['id' => $applicationId]);
            $currentStudentId = $current->fetchColumn();
            if ($currentStudentId === false) { http_response_code(404); exit('Application not found.'); }
            if ($status === 'Admitted' && !$currentStudentId) { http_response_code(422); exit('Use the Enroll student action after marking the exam as passed.'); }
            if ($currentStudentId && $status !== 'Admitted') { http_response_code(422); exit('This application is already enrolled as a student.'); }
            $statement = $pdo->prepare('UPDATE admission_applications SET status=:status,staff_note=:staff_note WHERE id=:id');
            $statement->execute(['status' => $status, 'staff_note' => $staffNote ?: null, 'id' => $applicationId]);
            header('Location: admissions-admin.php?id=' . $applicationId . '&saved=1'); exit;
        }
        if ($action === 'enroll') {
            $classId = filter_var($_POST['class_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $academicYear = trim((string) ($_POST['academic_year'] ?? ''));
            if (!$classId || $academicYear === '' || strlen($academicYear) > 20) { http_response_code(422); exit('Choose a class and a valid academic year.'); }
            $pdo->beginTransaction();
            $lookup = $pdo->prepare('SELECT * FROM admission_applications WHERE id=:id FOR UPDATE');
            $lookup->execute(['id' => $applicationId]);
            $application = $lookup->fetch();
            if (!$application || $application['status'] !== 'Exam passed') throw new RuntimeException('Only applications marked Exam passed can be enrolled.');
            if ($application['student_id']) throw new RuntimeException('This applicant has already been enrolled.');
            $classQuery = $pdo->prepare('SELECT id,name FROM classes WHERE id=:id');
            $classQuery->execute(['id' => $classId]);
            $class = $classQuery->fetch();
            if (!$class) throw new RuntimeException('Choose an existing class.');
            $studentInsert = $pdo->prepare('INSERT INTO students (first_name,last_name,date_of_birth,gender,class_name,guardian_name,guardian_phone,email,enrollment_status) VALUES (:first_name,:last_name,:date_of_birth,:gender,:class_name,:guardian_name,:guardian_phone,:email,"Active")');
            $studentInsert->execute([
                'first_name' => $application['student_first_name'], 'last_name' => $application['student_last_name'],
                'date_of_birth' => $application['date_of_birth'], 'gender' => $application['gender'],
                'class_name' => $class['name'], 'guardian_name' => $application['parent_name'],
                'guardian_phone' => $application['parent_phone'], 'email' => $application['parent_email'],
            ]);
            $studentId = (int) $pdo->lastInsertId();
            $enrollmentInsert = $pdo->prepare('INSERT INTO enrollments (student_id,class_id,academic_year,enrolled_on,status) VALUES (:student_id,:class_id,:academic_year,CURRENT_DATE,"Active")');
            $enrollmentInsert->execute(['student_id' => $studentId, 'class_id' => $classId, 'academic_year' => $academicYear]);
            $updateApplication = $pdo->prepare('UPDATE admission_applications SET status="Admitted",student_id=:student_id WHERE id=:id');
            $updateApplication->execute(['student_id' => $studentId, 'id' => $applicationId]);
            $pdo->commit();
            header('Location: admissions-admin.php?id=' . $applicationId . '&enrolled=1'); exit;
        }
        http_response_code(400); exit('Unsupported action.');
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $message = $exception instanceof RuntimeException ? $exception->getMessage() : 'The application could not be updated. Check that a class is available and the student is not already enrolled.';
    }
}
$applications = $pdo->query('SELECT id,reference_code,student_first_name,student_last_name,grade_applied,parent_name,parent_email,parent_phone,status,student_id,submitted_at FROM admission_applications ORDER BY submitted_at DESC LIMIT 300')->fetchAll();
$application = null;
$documents = [];
$classes = [];
if ($selectedId) {
    $detail = $pdo->prepare('SELECT * FROM admission_applications WHERE id=:id');
    $detail->execute(['id' => $selectedId]);
    $application = $detail->fetch() ?: null;
    if ($application) {
        $docQuery = $pdo->prepare('SELECT id,original_name,mime_type,size_bytes,uploaded_at FROM admission_documents WHERE application_id=:id ORDER BY id');
        $docQuery->execute(['id' => $selectedId]); $documents = $docQuery->fetchAll();
    }
    $classes = $pdo->query("SELECT id,name,grade_level FROM classes WHERE status='Active' ORDER BY name")->fetchAll();
}
$defaultYear = $settings['academic_year'] ?? date('Y') . '-' . (date('Y') + 1);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admissions | <?= adminEscape($schoolName) ?></title><link rel="stylesheet" href="assets/css/school-website.css"><style>body{background:#f5f8f5}.admin-wrap{max-width:1320px;margin:34px auto;padding:0 22px 60px}.admin-head{display:flex;justify-content:space-between;align-items:center;gap:20px;margin-bottom:20px}.admin-head h1{margin:7px 0;font:800 32px Manrope}.admin-layout{display:grid;grid-template-columns:minmax(360px,.9fr) minmax(480px,1.1fr);gap:17px}.admin-panel{padding:20px;border:1px solid #e2eae4;border-radius:13px;background:#fff}.admin-panel h2{margin:0 0 14px;font:700 18px Manrope}.admin-table{width:100%;border-collapse:collapse}.admin-table th,.admin-table td{padding:10px 7px;border-bottom:1px solid #eaf0ec;text-align:left;font-size:11px;vertical-align:top}.admin-table th{color:#74857e;text-transform:uppercase;font-size:9px;letter-spacing:.7px}.admin-table a{color:#19735d;font-weight:700}.admin-form{display:grid;gap:12px}.admin-form label{display:grid;gap:5px;font-size:12px;font-weight:700}.admin-form input,.admin-form select,.admin-form textarea{width:100%;padding:10px;border:1px solid #dce6df;border-radius:7px;font:13px 'DM Sans',sans-serif}.admin-form textarea{min-height:85px}.doc-list{display:grid;gap:9px;margin:12px 0 20px}.doc-list a{color:#19735d;font-size:13px;font-weight:700}.admin-note{padding:12px;border-radius:8px;background:#f1f6f1;color:#61756f;font-size:12px;line-height:1.6}.status-label{display:inline-block;padding:4px 7px;border-radius:14px;background:#edf5ef;color:#19735d;font-size:10px;font-weight:700}.flash{margin-bottom:15px;padding:12px;border-radius:8px;background:#e8f4ee;color:#26745b;font-size:13px}.admin-actions{display:flex;gap:9px;align-items:center;flex-wrap:wrap}.muted-small{color:#788982;font-size:11px}@media(max-width:900px){.admin-layout{grid-template-columns:1fr}.admin-table{min-width:650px}.table-scroll{overflow:auto}}@media(max-width:600px){.admin-head{align-items:flex-start;flex-direction:column}.admin-head h1{font-size:27px}}</style></head><body>
<header class="site-header"><a class="site-brand" href="school-website.php"><span class="brand-mark">N</span><span><?= adminEscape($schoolName) ?><small>STAFF ADMISSIONS</small></span></a><nav class="site-nav"><a href="index.php">Dashboard</a><a href="school-website.php">Public website</a><a href="logout.php">Sign out</a></nav></header>
<main class="admin-wrap"><div class="admin-head"><div><div class="section-label">STAFF WORKSPACE</div><h1>Admission applications</h1><p class="muted-small">Review applications, check private documents, schedule assessments, and enroll students who pass.</p></div><a class="button button-light" href="index.php">Back to dashboard</a></div>
<?php if ($message): ?><div class="flash"><?= adminEscape($message) ?></div><?php elseif (isset($_GET['enrolled'])): ?><div class="flash">Student record and enrollment created from this passed application.</div><?php elseif (isset($_GET['saved'])): ?><div class="flash">Application review saved.</div><?php endif; ?>
<div class="admin-layout"><section class="admin-panel"><h2>Recent applications</h2><?php if (!$applications): ?><p class="admin-note">No applications received yet. Share the public admissions page with families.</p><?php else: ?><div class="table-scroll"><table class="admin-table"><thead><tr><th>Applicant</th><th>Grade</th><th>Reference / status</th><th></th></tr></thead><tbody><?php foreach ($applications as $item): ?><tr><td><b><?= adminEscape($item['student_first_name'] . ' ' . $item['student_last_name']) ?></b><br><?= adminEscape($item['parent_name']) ?></td><td><?= adminEscape($item['grade_applied']) ?></td><td><?= adminEscape($item['reference_code']) ?><br><span class="status-label"><?= adminEscape($item['status']) ?></span></td><td><a href="admissions-admin.php?id=<?= (int) $item['id'] ?>">Review</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
<section class="admin-panel"><h2><?= $application ? 'Review ' . adminEscape($application['reference_code']) : 'Select an application' ?></h2><?php if (!$application): ?><p class="admin-note">Select an application to review family contact details, download documents, record the review status, or enroll a student after an assessment is passed.</p><?php else: ?><div class="admin-note"><b><?= adminEscape($application['student_first_name'] . ' ' . $application['student_last_name']) ?></b> · <?= adminEscape($application['grade_applied']) ?><br>Date of birth: <?= adminEscape($application['date_of_birth']) ?> · <?= adminEscape($application['gender']) ?><br>Parent/guardian: <?= adminEscape($application['parent_name']) ?><br>Email: <?= adminEscape($application['parent_email']) ?> · Phone: <?= adminEscape($application['parent_phone']) ?><br>Previous school: <?= adminEscape($application['previous_school'] ?: 'Not provided') ?><br>Submitted: <?= adminEscape($application['submitted_at']) ?><?php if ($application['parent_note']): ?><br><br>Family note: <?= nl2br(adminEscape($application['parent_note'])) ?><?php endif; ?><?php if ($application['staff_note']): ?><br><br>Staff note: <?= nl2br(adminEscape($application['staff_note'])) ?><?php endif; ?></div>
<h2 style="margin-top:20px">Supporting documents</h2><?php if ($documents): ?><div class="doc-list"><?php foreach ($documents as $document): ?><a href="admission-document.php?id=<?= (int) $document['id'] ?>">Download <?= adminEscape($document['original_name']) ?> <span class="muted-small">(<?= number_format((int) $document['size_bytes'] / 1024) ?> KB)</span></a><?php endforeach; ?></div><?php else: ?><p class="muted-small">No documents were attached.</p><?php endif; ?>
<form class="admin-form" method="post"><input type="hidden" name="csrf" value="<?= adminEscape($csrf) ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="application_id" value="<?= (int) $application['id'] ?>"><label>Review status<select name="status"><?php foreach ($statuses as $status): ?><option <?= $application['status'] === $status ? 'selected' : '' ?>><?= adminEscape($status) ?></option><?php endforeach; ?></select></label><label>Staff-only notes<textarea name="staff_note" maxlength="5000"><?= adminEscape($application['staff_note'] ?? '') ?></textarea></label><button class="button button-primary" type="submit">Save review status</button></form>
<?php if ($application['status'] === 'Exam passed' && !$application['student_id']): ?><form class="admin-form" method="post" style="margin-top:20px;padding-top:18px;border-top:1px solid #e2eae4"><h2>Enroll student</h2><p class="muted-small">This creates a student record and an active enrollment from the reviewed application.</p><input type="hidden" name="csrf" value="<?= adminEscape($csrf) ?>"><input type="hidden" name="action" value="enroll"><input type="hidden" name="application_id" value="<?= (int) $application['id'] ?>"><label>Class<select name="class_id" required><option value="">Choose a class</option><?php foreach ($classes as $class): ?><option value="<?= (int) $class['id'] ?>"><?= adminEscape($class['name'] . ($class['grade_level'] ? ' · ' . $class['grade_level'] : '')) ?></option><?php endforeach; ?></select></label><label>Academic year<input name="academic_year" maxlength="20" value="<?= adminEscape($defaultYear) ?>" required></label><button class="button button-primary" type="submit" <?= !$classes ? 'disabled' : '' ?>>Create student and enrollment</button><?php if (!$classes): ?><small class="muted-small">Add a class in the dashboard before enrolling.</small><?php endif; ?></form><?php elseif ($application['student_id']): ?><p class="flash">This application is linked to student record #<?= (int) $application['student_id'] ?>.</p><?php endif; ?><?php endif; ?></section></div></main></body></html>
