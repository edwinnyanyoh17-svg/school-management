<?php
declare(strict_types=1);

// PHP's built-in server ignores .htaccess, so deny direct web access to internals here.
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; script-src 'self' 'unsafe-inline'");
$requestPath = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$requestPath = str_replace('\\', '/', $requestPath);
$parts = array_values(array_filter(explode('/', $requestPath), static fn($part) => $part !== ''));
$firstPart = strtolower($parts[0] ?? '');
$basename = strtolower(basename($requestPath));
$blockedFiles = ['.htaccess', 'readme.md', 'dashboard-view.html', 'router.php', 'schema.sql', 'upgrade_existing.sql'];
$hasHiddenPath = count(array_filter($parts, static fn($part) => str_starts_with($part, '.'))) > 0;

if (in_array($firstPart, ['config', 'database', 'scripts', 'private'], true)
    || in_array($basename, $blockedFiles, true)
    || str_starts_with($basename, '.env')
    || $hasHiddenPath) {
    http_response_code(404);
    exit('Not found.');
}

$root = realpath(__DIR__);
$target = realpath(__DIR__ . DIRECTORY_SEPARATOR . ltrim($requestPath, '/'));
if ($target !== false && $root !== false && !str_starts_with($target, $root . DIRECTORY_SEPARATOR) && $target !== $root) {
    http_response_code(404);
    exit('Not found.');
}

// The built-in server does not read .htaccess, so serve the public school site at `/` here too.
if ($requestPath === '/') {
    require __DIR__ . '/school-website.php';
    exit;
}

return false;
