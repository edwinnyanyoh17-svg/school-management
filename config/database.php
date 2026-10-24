<?php
$configuredEnvironment = getenv('SCHOOL_ENV');
if ($configuredEnvironment === false || $configuredEnvironment === '') {
    $localHosts = ['localhost', '127.0.0.1', '::1'];
    $requestHost = strtolower((string) preg_replace('/:\\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
    $isLocalRequest = PHP_SAPI === 'cli'
        || (in_array((string) ($_SERVER['REMOTE_ADDR'] ?? ''), ['127.0.0.1', '::1'], true) && in_array($requestHost, $localHosts, true));
    $schoolEnvironment = $isLocalRequest ? 'development' : 'production';
} else {
    $schoolEnvironment = strtolower((string) $configuredEnvironment);
}
if (!in_array($schoolEnvironment, ['development', 'production'], true)) {
    error_log('SCHOOL_ENV must be either development or production.');
    http_response_code(500);
    exit('The school website is not fully configured. Please contact the school administrator.');
}
$isProduction = $schoolEnvironment === 'production';
$requiredProductionSettings = ['SCHOOL_DB_HOST', 'SCHOOL_DB_NAME', 'SCHOOL_DB_USER', 'SCHOOL_DB_PASSWORD', 'SCHOOL_RATE_LIMIT_KEY'];
if ($isProduction) {
    foreach ($requiredProductionSettings as $settingName) {
        if (getenv($settingName) === false || getenv($settingName) === '') {
            error_log('Production configuration is missing ' . $settingName . '.');
            http_response_code(500);
            exit('The school website is not fully configured. Please contact the school administrator.');
        }
    }
}

// Local XAMPP defaults are for development only. Production requires explicit environment settings.
$dbHost = getenv('SCHOOL_DB_HOST') ?: 'localhost';
$dbName = getenv('SCHOOL_DB_NAME') ?: 'school_management';
$dbUser = getenv('SCHOOL_DB_USER') ?: 'root';
$dbPassword = getenv('SCHOOL_DB_PASSWORD') ?: '';

try {
    $pdo = new PDO(
        "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
        $dbUser,
        $dbPassword,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $exception) {
    error_log('School database connection failed: ' . $exception->getMessage());
    http_response_code(500);
    exit($isProduction
        ? 'The school service is temporarily unavailable. Please try again later.'
        : 'Could not connect to the school database. Check config/database.php and make sure MySQL is running.');
}
