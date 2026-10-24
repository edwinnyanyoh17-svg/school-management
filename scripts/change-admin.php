<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../config/database.php';

$currentUsername = trim((string) ($argv[1] ?? ''));
$newUsername = trim((string) ($argv[2] ?? ''));
$validUsername = static fn(string $username): bool =>
    $username !== '' && strlen($username) <= 80 && preg_match('/^[A-Za-z0-9._-]+$/', $username) === 1;

if (!$validUsername($currentUsername) || !$validUsername($newUsername)) {
    fwrite(STDERR, "Usage: php scripts/change-admin.php <current-username> <new-username>\nUsernames may contain letters, digits, dot, underscore, and hyphen.\n");
    exit(1);
}

$password = trim((string) fgets(STDIN));
if (strlen($password) < 12 || strlen($password) > 72) {
    fwrite(STDERR, "Password must contain 12 to 72 characters. No account changes were made.\n");
    exit(1);
}

try {
    $pdo->beginTransaction();
    $lookup = $pdo->prepare('SELECT id FROM admin_users WHERE username = :username LIMIT 1');
    $lookup->execute(['username' => $currentUsername]);
    $adminId = $lookup->fetchColumn();
    if ($adminId === false) {
        fwrite(STDERR, "No administrator named '{$currentUsername}' was found. No account changes were made.\n");
        exit(1);
    }

    $update = $pdo->prepare('UPDATE admin_users SET username = :new_username, password_hash = :password_hash WHERE id = :id');
    $update->execute([
        'new_username' => $newUsername,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'id' => $adminId,
    ]);

    $pdo->commit();

    fwrite(STDOUT, "Administrator username and password updated. Sign in with '{$newUsername}' and the new password.\n");
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ((string) $exception->getCode() === '23000') {
        fwrite(STDERR, "That username is already in use. No account changes were made.\n");
    } else {
        fwrite(STDERR, "Could not update the administrator account. Check the database connection and schema.\n");
    }
    exit(1);
}
