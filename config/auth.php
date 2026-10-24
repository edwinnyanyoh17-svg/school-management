<?php
declare(strict_types=1);

function startSchoolSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    $https = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || getenv('SCHOOL_FORCE_HTTPS') === '1';
    session_name('northstar_staff');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Strict']);
    session_start();
}

function schoolClientIp(): string
{
    $remoteAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    if (!filter_var($remoteAddress, FILTER_VALIDATE_IP)) return 'unknown';
    $trustedProxies = array_filter(array_map('trim', explode(',', (string) (getenv('SCHOOL_TRUSTED_PROXY_IPS') ?: ''))));
    if (in_array($remoteAddress, $trustedProxies, true)) {
        // Only trust X-Real-IP when the direct connection came from an explicitly trusted proxy.
        $forwardedAddress = (string) ($_SERVER['HTTP_X_REAL_IP'] ?? '');
        if (filter_var($forwardedAddress, FILTER_VALIDATE_IP)) return $forwardedAddress;
    }
    return $remoteAddress;
}

function schoolRateLimitKey(): string
{
    return (string) (getenv('SCHOOL_RATE_LIMIT_KEY') ?: getenv('SCHOOL_DB_PASSWORD') ?: 'development-only-rate-limit-key');
}

function sendSecurityHeaders(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; script-src 'self' 'unsafe-inline'");
    header('X-Permitted-Cross-Domain-Policies: none');
    if ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || getenv('SCHOOL_FORCE_HTTPS') === '1') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
}

function requireStaffLogin(): void
{
    startSchoolSession();
    sendSecurityHeaders();
    if (empty($_SESSION['staff_user_id'])) {
        $requestPath = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '');
        $returnPaths = ['students.php', 'student-details.php', 'admissions-admin.php', 'admission-document.php'];
        $returnTo = in_array($requestPath, $returnPaths, true) ? $requestPath : 'index.php';
        if (in_array($requestPath, ['student-details.php', 'admissions-admin.php', 'admission-document.php'], true)) {
            $studentId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($studentId) $returnTo .= '?id=' . $studentId;
        }
        header('Location: login.php?next=' . rawurlencode($returnTo));
        exit;
    }
}

function schoolCsrfToken(): string
{
    startSchoolSession();
    if (empty($_SESSION['school_csrf'])) $_SESSION['school_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['school_csrf'];
}

function verifySchoolCsrf(?string $token): bool
{
    return isset($_SESSION['school_csrf']) && is_string($token) && hash_equals($_SESSION['school_csrf'], $token);
}
