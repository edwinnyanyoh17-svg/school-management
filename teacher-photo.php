<?php
declare(strict_types=1);
require __DIR__ . '/config/auth.php';
startSchoolSession();
sendSecurityHeaders();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if (empty($_SESSION['staff_user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please sign in to upload staff photos.']);
    exit;
}
require __DIR__ . '/config/database.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 2300000) {
    http_response_code(413);
    echo json_encode(['success' => false, 'message' => 'Choose a photo no larger than 2 MB.']);
    exit;
}
if (!verifySchoolCsrf($_POST['csrf'] ?? null)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Your session expired. Refresh the dashboard and try again.']);
    exit;
}

if (($_POST['action'] ?? '') === 'delete') {
    $path = $_POST['path'] ?? '';
    if (!is_string($path) || !preg_match('#^images/teacher-[A-Za-z0-9_-]+\.(?:jpg|jpeg|png|webp)$#i', $path)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Choose a valid teacher photo.']);
        exit;
    }
    $statement = $pdo->prepare('SELECT COUNT(*) FROM teachers WHERE photo_path = :path');
    $statement->execute(['path' => $path]);
    if ((int) $statement->fetchColumn() > 0) {
        echo json_encode(['success' => true, 'deleted' => false, 'message' => 'Photo is still attached to a teacher.']);
        exit;
    }
    $photoDirectory = realpath(__DIR__ . '/images');
    $photoFile = $photoDirectory ? realpath(__DIR__ . '/' . $path) : false;
    if ($photoDirectory && $photoFile && str_starts_with($photoFile, $photoDirectory . DIRECTORY_SEPARATOR) && is_file($photoFile)) {
        if (!unlink($photoFile)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'The old photo could not be removed from the images folder.']);
            exit;
        }
    }
    echo json_encode(['success' => true, 'deleted' => true]);
    exit;
}

$file = $_FILES['photo'] ?? null;
if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Choose a photo to upload.']);
    exit;
}
$temporaryPath = (string) ($file['tmp_name'] ?? '');
$fileSize = (int) ($file['size'] ?? 0);
if ($fileSize < 1 || $fileSize > 2 * 1024 * 1024 || !is_uploaded_file($temporaryPath)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Choose a valid photo no larger than 2 MB.']);
    exit;
}
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath) ?: '';
$extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
if (!isset($extensions[$mime])) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Use a JPEG, PNG, or WebP image.']);
    exit;
}

$photoDirectory = __DIR__ . '/images';
$photoName = 'teacher-' . bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
$destination = $photoDirectory . '/' . $photoName;
if (!move_uploaded_file($temporaryPath, $destination)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'The photo could not be saved. Check that the images folder is writable.']);
    exit;
}
chmod($destination, 0644);
echo json_encode(['success' => true, 'photo_path' => 'images/' . $photoName]);
