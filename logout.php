<?php
declare(strict_types=1);
require __DIR__ . '/config/auth.php';
startSchoolSession();
sendSecurityHeaders();
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifySchoolCsrf($_POST['csrf'] ?? null)) { http_response_code(405); exit('Invalid logout request.'); }
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
header('Location: login.php'); exit;
