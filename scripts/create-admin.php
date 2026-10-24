<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../config/database.php';
$username = trim((string)($argv[1] ?? ''));
if ($username === '' || strlen($username) > 80 || !preg_match('/^[A-Za-z0-9._-]+$/', $username)) {
    fwrite(STDERR, "Usage: php scripts/create-admin.php <username>\nUsername may contain letters, digits, dot, underscore, and hyphen.\n"); exit(1);
}
try { $count = (int)$pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn(); }
catch (PDOException $exception) { fwrite(STDERR, "The admin_users table is missing. Import the updated database/schema.sql first.\n"); exit(1); }
if ($count > 0) { fwrite(STDERR, "An administrator already exists.\n"); exit(1); }
fwrite(STDOUT, 'New password (minimum 12 characters): ');
if (PHP_OS_FAMILY !== 'Windows') system('stty -echo');
$password = trim((string)fgets(STDIN));
if (PHP_OS_FAMILY !== 'Windows') system('stty echo');
fwrite(STDOUT, "\n");
if (strlen($password) < 12 || strlen($password) > 72) { fwrite(STDERR, "Password must contain 12 to 72 bytes.\n"); exit(1); }
$hash = password_hash($password, PASSWORD_DEFAULT);
$insert = $pdo->prepare('INSERT INTO admin_users (username, password_hash) VALUES (:username, :password_hash)');
$insert->execute(['username' => $username, 'password_hash' => $hash]);
fwrite(STDOUT, "Administrator account created.\n");
