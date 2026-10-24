<?php
declare(strict_types=1);
require __DIR__ . '/config/auth.php';
sendSecurityHeaders();
require __DIR__ . '/config/database.php';
function confirmationEscape($value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
$reference = strtoupper(trim((string) ($_GET['ref'] ?? '')));
$statement = $pdo->prepare('SELECT reference_code,student_first_name,student_last_name,grade_applied,submitted_at FROM admission_applications WHERE reference_code=:reference LIMIT 1');
$statement->execute(['reference' => $reference]);
$application = $statement->fetch();
if (!$application) { http_response_code(404); }
$settings = [];
foreach ($pdo->query('SELECT setting_key,setting_value FROM school_settings')->fetchAll() as $row) $settings[$row['setting_key']] = $row['setting_value'];
$schoolName = $settings['school_name'] ?? 'Northstar Academy';
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Application received | <?= confirmationEscape($schoolName) ?></title><link rel="stylesheet" href="assets/css/school-website.css"><style>.confirmation{max-width:690px;margin:12vh auto;padding:35px 22px;text-align:center}.confirmation-card{padding:34px;border:1px solid #e2eae4;border-radius:16px;background:#fff}.confirmation-mark{display:grid;place-items:center;width:58px;height:58px;margin:0 auto 20px;border-radius:50%;background:#e5f3e9;color:#19735d;font-size:28px}.confirmation h1{font:800 34px Manrope,sans-serif}.confirmation p{color:#61756f;line-height:1.7}.reference{display:inline-block;margin:12px;padding:12px 17px;border-radius:8px;background:#f1f6f1;color:#18352f;font:800 20px Manrope;letter-spacing:1px}</style></head><body><main class="confirmation"><div class="confirmation-card"><?php if ($application): ?><div class="confirmation-mark">✓</div><div class="section-label">APPLICATION RECEIVED</div><h1>Thank you, <?= confirmationEscape($application['student_first_name']) ?>’s family.</h1><p>Your application for <?= confirmationEscape($application['grade_applied']) ?> has been sent to <?= confirmationEscape($schoolName) ?>. The school will contact the parent or guardian using the details provided.</p><p>Save this reference number for your records:</p><div class="reference"><?= confirmationEscape($application['reference_code']) ?></div><p>Submitting an application does not guarantee admission. The school will confirm any assessment or further steps.</p><?php else: ?><h1>Application reference not found</h1><p>Please check the reference link or submit a new application.</p><?php endif; ?><p><a class="button button-primary" href="school-website.php">Return to the school website</a></p></div></main></body></html>
