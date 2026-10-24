<?php
declare(strict_types=1);
require __DIR__ . '/config/auth.php';
requireStaffLogin();
require __DIR__ . '/config/database.php';
$documentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$documentId) { http_response_code(404); exit('Document not found.'); }
$query = $pdo->prepare('SELECT original_name,storage_name,mime_type,size_bytes FROM admission_documents WHERE id=:id');
$query->execute(['id' => $documentId]);
$document = $query->fetch();
if (!$document || !in_array($document['mime_type'], ['application/pdf','image/jpeg','image/png'], true) || !preg_match('/^[a-f0-9]{64}\.(pdf|jpg|png)$/', $document['storage_name'])) { http_response_code(404); exit('Document not found.'); }
$storageRoot = realpath(__DIR__ . '/private/admissions');
$path = realpath(__DIR__ . '/private/admissions/' . $document['storage_name']);
if (!$storageRoot || !$path || !str_starts_with($path, $storageRoot . DIRECTORY_SEPARATOR) || !is_file($path)) { http_response_code(404); exit('Document not found.'); }
$safeName = preg_replace('/[^A-Za-z0-9._ -]+/', '_', basename((string) $document['original_name'])) ?: 'admission-document';
header('Content-Type: ' . $document['mime_type']);
header('Content-Length: ' . (string) filesize($path));
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $safeName) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
