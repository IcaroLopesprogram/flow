<?php
require_once __DIR__ . '/../secure/auth.php';
require_once __DIR__ . '/../secure/config.php';

startSecureSession();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function adminAuthResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    adminAuthResponse(['ok' => false, 'message' => 'Método não permitido.'], 405);
}

if (!verifyCsrfTokenOrFail($_POST['csrf_token'] ?? null)) {
    adminAuthResponse(['ok' => false, 'message' => 'Sessão expirada. Recarregue a página e tente novamente.'], 403);
}

$limit = rateLimitConsume('admin_login', 8, 300, (string) ($_POST['email'] ?? ''));
if (!$limit['allowed']) {
    adminAuthResponse(['ok' => false, 'message' => 'Muitas tentativas. Aguarde alguns minutos.'], 429);
}

$email = strtolower(trim((string) ($_POST['email'] ?? '')));
$password = (string) ($_POST['password'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    adminAuthResponse(['ok' => false, 'message' => 'E-mail ou senha inválidos.'], 401);
}

$cfg = appConfig();
try {
    $pdo = new PDO(
        "mysql:host={$cfg['db_host']};dbname={$cfg['db_name']};charset={$cfg['db_charset']}",
        $cfg['db_user'],
        $cfg['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
    $stmt = $pdo->prepare('SELECT id, nome, email, senha_hash FROM usuarios WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    adminAuthResponse(['ok' => false, 'message' => 'Não foi possível autenticar no momento.'], 503);
}

if (!is_array($user) || !password_verify($password, (string) $user['senha_hash']) || !isAdminEmail($email)) {
    adminAuthResponse(['ok' => false, 'message' => 'E-mail ou senha inválidos.'], 401);
}

loginUser((int) $user['id'], (string) $user['nome'], (string) $user['email']);
adminAuthResponse(['ok' => true, 'csrf_token' => ensureCsrfToken()]);
