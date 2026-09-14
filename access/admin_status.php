<?php
require_once __DIR__ . '/../secure/auth.php';

startSecureSession();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

echo json_encode([
    'ok' => true,
    'authenticated' => isAuthenticated() && currentUserIsAdmin(),
    'csrf_token' => ensureCsrfToken(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
