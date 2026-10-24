<?php
declare(strict_types=1);
require __DIR__ . '/config/auth.php';
startSchoolSession();
sendSecurityHeaders();
require __DIR__ . '/config/database.php';

function rejectApplication(string $message, int $status = 303): never
{
    $_SESSION['admission_error'] = $message;
    header('Location: admissions.php', true, $status);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: admissions.php'); exit; }
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 8 * 1024 * 1024) rejectApplication('The upload is too large. Please keep each file under 2 MB and submit no more than three files.', 303);
if (!verifySchoolCsrf($_POST['csrf'] ?? null)) rejectApplication('Your form session expired. Please reload the page and try again.');
if (!empty($_POST['website'])) { header('Location: school-website.php'); exit; }
if (($_POST['consent'] ?? '') !== 'yes') rejectApplication('Please confirm that you are the parent or legal guardian before submitting.');
if (isset($_SESSION['admission_last_submit']) && time() - (int) $_SESSION['admission_last_submit'] < 30) rejectApplication('Please wait a little before submitting another application.');

// Session throttling helps honest users; this shared limit also slows bots that start new sessions.
try {
    $ipHash = hash_hmac('sha256', schoolClientIp(), schoolRateLimitKey());
    $blockedQuery = $pdo->prepare('SELECT blocked_until FROM admission_rate_limits WHERE ip_hash=:ip_hash');
    $blockedQuery->execute(['ip_hash' => $ipHash]);
    $blockedUntil = $blockedQuery->fetchColumn();
    if ($blockedUntil && strtotime((string) $blockedUntil) > time()) {
        rejectApplication('Too many applications were submitted from this connection. Please try again later.');
    }
    $rateUpdate = $pdo->prepare('INSERT INTO admission_rate_limits (ip_hash,attempt_count,window_started,blocked_until) VALUES (:ip_hash,1,NOW(),NULL) ON DUPLICATE KEY UPDATE blocked_until=IF(window_started < DATE_SUB(NOW(), INTERVAL 1 HOUR),NULL,IF(attempt_count >= 3,DATE_ADD(NOW(), INTERVAL 1 HOUR),blocked_until)),attempt_count=IF(window_started < DATE_SUB(NOW(), INTERVAL 1 HOUR),1,attempt_count+1),window_started=IF(window_started < DATE_SUB(NOW(), INTERVAL 1 HOUR),NOW(),window_started)');
    $rateUpdate->execute(['ip_hash' => $ipHash]);
    $blockedQuery->execute(['ip_hash' => $ipHash]);
    $blockedUntil = $blockedQuery->fetchColumn();
    if ($blockedUntil && strtotime((string) $blockedUntil) > time()) {
        rejectApplication('Too many applications were submitted from this connection. Please try again later.');
    }
} catch (Throwable $exception) {
    error_log('Admission rate limit check failed: ' . $exception->getMessage());
    rejectApplication('Online applications are temporarily unavailable. Please try again later.');
}

$fields = [
    'first_name' => [80, true], 'last_name' => [80, true], 'gender' => [30, true],
    'grade_applied' => [80, true], 'previous_school' => [180, false],
    'parent_name' => [160, true], 'parent_email' => [190, true], 'parent_phone' => [40, true],
];
$values = [];
foreach ($fields as $field => [$limit, $required]) {
    $value = trim((string) ($_POST[$field] ?? ''));
    if ($required && $value === '') rejectApplication('Complete all required fields before submitting.');
    if (strlen($value) > $limit) rejectApplication('One or more answers are too long. Please review the form.');
    $values[$field] = $value === '' ? null : $value;
}
if (!filter_var($values['parent_email'], FILTER_VALIDATE_EMAIL)) rejectApplication('Enter a valid parent or guardian email address.');
$birthDate = trim((string) ($_POST['date_of_birth'] ?? ''));
$date = DateTime::createFromFormat('!Y-m-d', $birthDate);
if (!$date || $date->format('Y-m-d') !== $birthDate || $birthDate > date('Y-m-d')) rejectApplication('Enter a valid date of birth.');
$parentNote = trim((string) ($_POST['parent_note'] ?? ''));
if (strlen($parentNote) > 3000) rejectApplication('The additional note is too long.');

$uploads = [];
if (isset($_FILES['documents']) && is_array($_FILES['documents']['name'] ?? null)) {
    $total = count($_FILES['documents']['name']);
    if ($total > 3) rejectApplication('Please choose no more than three documents.');
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $allowed = ['application/pdf' => '.pdf', 'image/jpeg' => '.jpg', 'image/png' => '.png'];
    for ($index = 0; $index < $total; $index++) {
        $errorCode = (int) ($_FILES['documents']['error'][$index] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode === UPLOAD_ERR_NO_FILE) continue;
        if ($errorCode !== UPLOAD_ERR_OK) rejectApplication('A document could not be uploaded. Try again with a file under 2 MB.');
        $tmpPath = (string) ($_FILES['documents']['tmp_name'][$index] ?? '');
        $fileSize = (int) ($_FILES['documents']['size'][$index] ?? 0);
        if ($fileSize < 1 || $fileSize > 2 * 1024 * 1024 || !is_uploaded_file($tmpPath)) rejectApplication('Each document must be a valid file no larger than 2 MB.');
        $mime = $finfo->file($tmpPath) ?: '';
        if (!isset($allowed[$mime])) rejectApplication('Use PDF, JPG, or PNG files only.');
        $originalName = basename(str_replace('\\', '/', (string) $_FILES['documents']['name'][$index]));
        $originalName = preg_replace('/[^A-Za-z0-9._ -]+/', '_', $originalName) ?: 'document';
        $uploads[] = ['tmp' => $tmpPath, 'name' => substr($originalName, 0, 190), 'mime' => $mime, 'size' => $fileSize, 'extension' => $allowed[$mime]];
    }
}

$storageDir = __DIR__ . '/private/admissions';
if (!is_dir($storageDir) && !mkdir($storageDir, 0700, true) && !is_dir($storageDir)) rejectApplication('The school could not prepare secure document storage. Please contact the school office.', 303);
$storedFiles = [];
try {
    $pdo->beginTransaction();
    $reference = 'NSA-' . strtoupper(bin2hex(random_bytes(16)));
    $insert = $pdo->prepare('INSERT INTO admission_applications (reference_code,student_first_name,student_last_name,date_of_birth,gender,grade_applied,previous_school,parent_name,parent_email,parent_phone,parent_note) VALUES (:reference_code,:first_name,:last_name,:date_of_birth,:gender,:grade_applied,:previous_school,:parent_name,:parent_email,:parent_phone,:parent_note)');
    $insert->execute([
        'reference_code' => $reference, 'first_name' => $values['first_name'], 'last_name' => $values['last_name'],
        'date_of_birth' => $birthDate, 'gender' => $values['gender'], 'grade_applied' => $values['grade_applied'],
        'previous_school' => $values['previous_school'], 'parent_name' => $values['parent_name'],
        'parent_email' => $values['parent_email'], 'parent_phone' => $values['parent_phone'], 'parent_note' => $parentNote ?: null,
    ]);
    $applicationId = (int) $pdo->lastInsertId();
    $documentInsert = $pdo->prepare('INSERT INTO admission_documents (application_id,original_name,storage_name,mime_type,size_bytes) VALUES (:application_id,:original_name,:storage_name,:mime_type,:size_bytes)');
    foreach ($uploads as $upload) {
        $storageName = bin2hex(random_bytes(32)) . $upload['extension'];
        $destination = $storageDir . '/' . $storageName;
        if (!move_uploaded_file($upload['tmp'], $destination)) throw new RuntimeException('Could not store an uploaded document.');
        chmod($destination, 0600);
        $storedFiles[] = $destination;
        $documentInsert->execute(['application_id' => $applicationId, 'original_name' => $upload['name'], 'storage_name' => $storageName, 'mime_type' => $upload['mime'], 'size_bytes' => $upload['size']]);
    }
    $pdo->commit();
    $_SESSION['admission_last_submit'] = time();
    header('Location: admission-confirmation.php?ref=' . rawurlencode($reference), true, 303);
    exit;
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    foreach ($storedFiles as $storedFile) if (is_file($storedFile)) unlink($storedFile);
    error_log('Admission submission failed: ' . $exception->getMessage());
    rejectApplication('The application could not be saved. Please try again or contact the school office.');
}
