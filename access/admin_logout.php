<?php
require_once __DIR__ . '/../secure/auth.php';

startSecureSession();
header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit;
}
if (!verifyCsrfTokenOrFail($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit;
}

logoutUser();
echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
