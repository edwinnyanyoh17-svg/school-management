<?php
declare(strict_types=1);
require __DIR__ . '/config/auth.php';
require __DIR__ . '/config/database.php';
startSchoolSession();
sendSecurityHeaders();
if (!empty($_SESSION['staff_user_id'])) { header('Location: index.php'); exit; }
$next = $_GET['next'] ?? 'index.php';
if (!preg_match('/^(index\.php|students\.php|student-details\.php\?id=[1-9][0-9]*|admissions-admin\.php(?:\?id=[1-9][0-9]*)?|admission-document\.php\?id=[1-9][0-9]*)$/', $next)) $next = 'index.php';
$error = '';
$clientIpHash = hash_hmac('sha256', schoolClientIp(), schoolRateLimitKey());
if (empty($_SESSION['login_attempts'])) $_SESSION['login_attempts'] = [];
$_SESSION['login_attempts'] = array_values(array_filter($_SESSION['login_attempts'], static fn($time) => $time > time() - 600));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $lockQuery = $pdo->prepare('SELECT locked_until FROM auth_login_attempts WHERE ip_hash = :ip_hash');
        $lockQuery->execute(['ip_hash' => $clientIpHash]);
        $lockedUntil = $lockQuery->fetchColumn();
    } catch (PDOException $exception) { $lockedUntil = false; }
    if ($lockedUntil && strtotime((string)$lockedUntil) > time()) $error = 'Too many attempts. Wait 15 minutes, then try again.';
    elseif (count($_SESSION['login_attempts']) >= 8) $error = 'Too many attempts. Wait 10 minutes, then try again.';
    elseif (!verifySchoolCsrf($_POST['csrf'] ?? null)) $error = 'Your login form expired. Refresh and try again.';
    else {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        try {
            $query = $pdo->prepare('SELECT id, username, password_hash FROM admin_users WHERE username = :username LIMIT 1');
            $query->execute(['username' => substr($username, 0, 80)]);
            $user = $query->fetch();
        } catch (PDOException $exception) { $user = false; }
        if (strlen($password) <= 72 && $user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['staff_user_id'] = (int)$user['id'];
            $_SESSION['staff_username'] = $user['username'];
            $_SESSION['school_csrf'] = bin2hex(random_bytes(32));
            $_SESSION['login_attempts'] = [];
            $reset = $pdo->prepare('DELETE FROM auth_login_attempts WHERE ip_hash = :ip_hash');
            $reset->execute(['ip_hash' => $clientIpHash]);
            header('Location: ' . $next); exit;
        }
        $_SESSION['login_attempts'][] = time();
        try {
            $failed = $pdo->prepare('INSERT INTO auth_login_attempts (ip_hash, attempts, window_started, locked_until) VALUES (:ip_hash, 1, NOW(), NULL) ON DUPLICATE KEY UPDATE attempts = IF(window_started < DATE_SUB(NOW(), INTERVAL 15 MINUTE), 1, attempts + 1), locked_until = IF(attempts >= 7, DATE_ADD(NOW(), INTERVAL 15 MINUTE), locked_until), window_started = IF(window_started < DATE_SUB(NOW(), INTERVAL 15 MINUTE), NOW(), window_started)');
            $failed->execute(['ip_hash' => $clientIpHash]);
        } catch (PDOException $exception) { /* The per-session limiter remains active if throttling storage is unavailable. */ }
        $error = 'The username or password is incorrect.';
    }
}
$csrf = schoolCsrfToken();
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Staff sign in | Northstar Academy</title><link rel="stylesheet" href="assets/css/style.css"><style>body{min-height:100vh;display:grid;place-items:center;background:#f4f7f4;font-family:Arial,sans-serif}.login-card{width:min(420px,calc(100% - 32px));padding:32px;background:#fff;border:1px solid #e4ece7;border-radius:16px;box-shadow:0 18px 50px #18392c12}.login-card h1{margin:16px 0 8px}.login-card p{color:#71817f;line-height:1.55}.login-card label{display:block;margin:16px 0 6px;font-weight:700}.login-card input{box-sizing:border-box;width:100%;padding:12px;border:1px solid #d5e0da;border-radius:8px}.login-card button{width:100%;margin-top:20px;padding:13px;border:0;border-radius:8px;background:#1e725e;color:#fff;font-weight:700;cursor:pointer}.error{padding:11px;background:#fff0ed;color:#8c3025;border-radius:7px}.brand{color:#1e725e;font-weight:800;text-decoration:none}</style></head>
<body><main class="login-card"><a class="brand" href="parent-portal.php">✳ Northstar Academy</a><h1>Staff sign in</h1><p>Sign in to manage student and school records.</p><?php if ($error): ?><p class="error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?><form method="post" action="login.php?next=<?= rawurlencode($next) ?>"><input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"><label for="username">Username</label><input id="username" name="username" maxlength="80" autocomplete="username" required autofocus><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required><button type="submit">Sign in</button></form></main></body></html>
