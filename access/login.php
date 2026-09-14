<?php
require_once __DIR__ . '/../secure/auth.php';
require_once __DIR__ . '/../secure/config.php';
startSecureSession();

$cfg = appConfig();
$host = $cfg['db_host'];
$db = $cfg['db_name'];
$user = $cfg['db_user'];
$pass = $cfg['db_pass'];
$charset = $cfg['db_charset'];

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

function ensureUsersTable(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS usuarios (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nome VARCHAR(100) NOT NULL,
            email VARCHAR(190) NOT NULL UNIQUE,
            telefone VARCHAR(20) NULL,
            senha_hash VARCHAR(255) NOT NULL,
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    $phoneColStmt = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'telefone'");
    $hasPhoneColumn = $phoneColStmt !== false && $phoneColStmt->fetch();
    if (!$hasPhoneColumn) {
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN telefone VARCHAR(20) NULL AFTER email");
    }
}

function ensurePasswordResetTable(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS password_resets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token_hash CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            used_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_password_resets_user (user_id),
            INDEX idx_password_resets_token (token_hash),
            CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function currentBaseUrl(): string
{
    return appBaseUrl();
}

function buildPasswordResetEmailHtml(string $toName, string $resetUrl): string
{
    $brandName = 'Clube dos Parceiros';

    $nameSafe = trim($toName) !== '' ? $toName : 'usuário';
    $nameEsc = htmlspecialchars($nameSafe, ENT_QUOTES, 'UTF-8');
    $resetUrlEsc = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');
    $brandNameEsc = htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8');

    $logoUrl = currentBaseUrl() . appPath('/img/logomenor.png');
    $logoUrlEsc = htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8');

    $preheader = 'Use o botão abaixo para redefinir sua senha (válido por 60 minutos).';
    $preheaderEsc = htmlspecialchars($preheader, ENT_QUOTES, 'UTF-8');

    $primary = '#1A3D63';
    $primaryHover = '#1A3D63';
    $bg = '#f0f4f8';
    $surface = '#ffffff';
    $text = '#0f172a';
    $muted = '#64748b';
    $border = '#d9e2ec';

    return '<!doctype html>'
        . '<html lang="pt-BR">'
        . '<head>'
        . '<meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="color-scheme" content="light dark">'
        . '<meta name="supported-color-schemes" content="light dark">'
        . '<title>Recuperação de senha</title>'
        . '</head>'
        . '<body style="margin:0;padding:0;background:' . $bg . ';color:' . $text . ';font-family:Inter,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">'
        . '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;visibility:hidden;">' . $preheaderEsc . '</div>'
        . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:' . $bg . ';padding:32px 12px;">'
        . '<tr>'
        . '<td align="center">'
        . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px;width:100%;">'
        . '<tr>'
        . '<td style="padding:0 0 14px 0;">'
        . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"><tr>'
        . '<td style="vertical-align:middle;">'
        . '<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>'
        . '<td width="44" height="44" style="width:44px;height:44px;border-radius:10px;background:' . $primary . ';box-shadow:0 2px 8px rgba(30,58,138,.18);overflow:hidden;">'
        . '<img src="' . $logoUrlEsc . '" width="44" height="44" alt="' . $brandNameEsc . '" style="display:block;border:0;outline:none;text-decoration:none;width:44px;height:44px;object-fit:contain;padding:6px;box-sizing:border-box;">'
        . '</td>'
        . '<td style="padding-left:12px;vertical-align:middle;">'
        . '<div style="font-weight:800;letter-spacing:-0.01em;color:' . $primary . ';font-size:16px;line-height:1.2;">' . $brandNameEsc . '</div>'
        . '<div style="color:' . $muted . ';font-size:13px;line-height:1.3;margin-top:2px;">Segurança da conta</div>'
        . '</td>'
        . '</tr></table>'
        . '</td>'
        . '</tr></table>'
        . '</td>'
        . '</tr>'
        . '<tr>'
        . '<td style="background:' . $surface . ';border:1px solid ' . $border . ';border-radius:16px;box-shadow:0 4px 6px rgba(15,23,42,.05);padding:22px 20px;">'
        . '<h1 style="margin:0 0 10px 0;font-size:20px;line-height:1.25;letter-spacing:-0.01em;">Redefinir senha</h1>'
        . '<p style="margin:0 0 14px 0;color:' . $muted . ';font-size:14px;line-height:1.6;">Olá ' . $nameEsc . ', recebemos um pedido para redefinir sua senha. Este link é válido por <strong>60 minutos</strong>.</p>'
        . '<div style="padding:8px 0 18px 0;">'
        . '<a href="' . $resetUrlEsc . '" style="display:inline-block;background:' . $primary . ';color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;line-height:1;padding:12px 16px;border-radius:12px;">Redefinir senha</a>'
        . '</div>'
        . '<p style="margin:0 0 6px 0;color:' . $muted . ';font-size:13px;line-height:1.6;">Se o botão não funcionar, copie e cole este link no navegador:</p>'
        . '<p style="margin:0 0 16px 0;font-size:12px;line-height:1.6;word-break:break-all;">'
        . '<a href="' . $resetUrlEsc . '" style="color:' . $primaryHover . ';text-decoration:underline;">' . $resetUrlEsc . '</a>'
        . '</p>'
        . '<div style="border-top:1px solid ' . $border . ';padding-top:14px;color:' . $muted . ';font-size:12px;line-height:1.6;">'
        . 'Se você não solicitou essa alteração, pode ignorar este e-mail com segurança.'
        . '</div>'
        . '</td>'
        . '</tr>'
        . '<tr>'
        . '<td style="padding:14px 4px 0 4px;color:' . $muted . ';font-size:12px;line-height:1.6;text-align:center;">'
        . 'Este e-mail foi enviado automaticamente. Não responda.'
        . '</td>'
        . '</tr>'
        . '</table>'
        . '</td>'
        . '</tr>'
        . '</table>'
        . '</body>'
        . '</html>';
}

function sendPasswordResetEmail(string $toEmail, string $toName, string $resetUrl): bool
{
    $cfg = appConfig();
    if (($cfg['mail_provider'] ?? '') !== 'resend') {
        return false;
    }

    $apiKey = (string) ($cfg['resend_api_key'] ?? '');
    $from = (string) ($cfg['mail_from'] ?? '');
    if ($apiKey === '' || $from === '' || !function_exists('curl_init')) {
        return false;
    }

    $nameSafe = trim($toName) !== '' ? $toName : 'usuário';
    $payload = [
        'from' => $from,
        'to' => [$toEmail],
        'subject' => 'Recuperação de senha - Clube dos Parceiros',
        'text' => "Olá {$nameSafe},\n\nRecebemos um pedido para redefinir sua senha.\nUse o link abaixo (válido por 60 minutos):\n{$resetUrl}\n\nSe você não solicitou, ignore este e-mail.\n",
        'html' => buildPasswordResetEmailHtml($toName, $resetUrl),
    ];

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT => 10,
    ]);
    curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $status >= 200 && $status < 300;
}

function registerAttemptFailed(): void
{
    $now = time();
    $windowStart = $_SESSION['login_window_start'] ?? $now;
    $count = (int) ($_SESSION['login_fail_count'] ?? 0);

    if (($now - (int) $windowStart) > 300) {
        $_SESSION['login_window_start'] = $now;
        $_SESSION['login_fail_count'] = 1;
        return;
    }

    $_SESSION['login_fail_count'] = $count + 1;
}

function resetAttemptFailures(): void
{
    unset($_SESSION['login_window_start'], $_SESSION['login_fail_count']);
}

function isLoginTemporarilyBlocked(): bool
{
    $windowStart = (int) ($_SESSION['login_window_start'] ?? 0);
    $count = (int) ($_SESSION['login_fail_count'] ?? 0);
    if ($windowStart <= 0 || $count < 6) {
        return false;
    }
    return (time() - $windowStart) <= 300;
}

function registerForgotAttempt(): void
{
    $now = time();
    $windowStart = $_SESSION['forgot_window_start'] ?? $now;
    $count = (int) ($_SESSION['forgot_count'] ?? 0);

    if (($now - (int) $windowStart) > 900) {
        $_SESSION['forgot_window_start'] = $now;
        $_SESSION['forgot_count'] = 1;
        return;
    }

    $_SESSION['forgot_count'] = $count + 1;
}

function isForgotTemporarilyBlocked(): bool
{
    $windowStart = (int) ($_SESSION['forgot_window_start'] ?? 0);
    $count = (int) ($_SESSION['forgot_count'] ?? 0);
    if ($windowStart <= 0 || $count < 5) {
        return false;
    }
    return (time() - $windowStart) <= 900;
}

$requestedMode = strtolower(trim((string) ($_GET['mode'] ?? $_POST['mode'] ?? 'login')));
$allowedModes = ['login', 'register', 'forgot', 'reset'];
$mode = in_array($requestedMode, $allowedModes, true) ? $requestedMode : 'login';
$resetToken = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$rawNextPath = $_GET['next'] ?? $_POST['next'] ?? null;
// O campo "next" também é enviado pelo próprio formulário. Guarde se ele
// veio originalmente de um link/fluxo externo para não tratar o destino
// padrão de cadastro como um redirecionamento obrigatório no login comum.
$hasExplicitNextPath = isset($_GET['next'])
    ? is_string($_GET['next']) && trim($_GET['next']) !== ''
    : (($_POST['has_explicit_next'] ?? '') === '1');
$defaultNextPath = $mode === 'register'
    ? appPath('/secure/save_profile.php')
    : appPath('/access/perfil.php');
$nextPath = normalizeNextPath($rawNextPath, $defaultNextPath);

if (isAuthenticated() && $mode !== 'forgot' && $mode !== 'reset') {
    header('Location: ' . $nextPath);
    exit;
}

$error = '';
$success = '';
$debugResetLink = '';
$formName = '';
$formEmail = '';
$formPhone = '';

try {
    if (!empty($cfg['app_auto_migrate'])) {
        $pdo = new PDO("mysql:host=$host;charset=$charset", $user, $pass, $options);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET $charset COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$db`");
        ensureUsersTable($pdo);
        ensurePasswordResetTable($pdo);
    } else {
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=$charset", $user, $pass, $options);
    }
} catch (PDOException $e) {
    $error = 'Não foi possível inicializar o login no banco de dados.';
}

function defaultLoginDestination(PDO $pdo, int $userId): string
{
    try {
        $profileStmt = $pdo->prepare('SELECT 1 FROM profissionais WHERE user_id = :user_id LIMIT 1');
        $profileStmt->execute([':user_id' => $userId]);
        if ($profileStmt->fetchColumn()) {
            return appPath('/access/perfil.php');
        }
    } catch (PDOException $e) {
        // Na primeira configuração, continue para o cadastro do perfil.
    }

    return appPath('/secure/save_profile.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    if (!verifyCsrfTokenOrFail($_POST['csrf_token'] ?? null)) {
        $error = 'Sessão expirada. Atualize a página e tente novamente.';
    } else {
        $postLimiter = rateLimitConsume('login_post', 30, 300);
        if (!$postLimiter['allowed']) {
            $error = 'Muitas tentativas em pouco tempo. Aguarde e tente novamente.';
        }
    }

    if ($error === '') {
        $postedMode = strtolower(trim((string) ($_POST['mode'] ?? 'login')));
        $mode = in_array($postedMode, $allowedModes, true) ? $postedMode : 'login';
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['senha'] ?? '');
        $formEmail = $email;

        if ($mode === 'register') {
            $registerLimiter = rateLimitConsume('register_post', 8, 3600, $email);
            if (!$registerLimiter['allowed']) {
                $error = 'Muitas tentativas de cadastro. Tente novamente mais tarde.';
            }
            $name = trim((string) ($_POST['nome'] ?? ''));
            $phone = null;
            $formName = $name;
            $passwordMeetsRules = strlen($password) >= 8
                && preg_match('/[a-zA-Z]/', $password)
                && preg_match('/\d/', $password)
                && preg_match('/[!@#$%]/', $password);
            if ($error === '' && ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$passwordMeetsRules)) {
                $error = 'Preencha nome e e-mail válidos. A senha precisa ter 8 caracteres, letras, números e um caractere especial (!@#$%).';
            } elseif ($error === '') {
                try {
                    $stmt = $pdo->prepare('INSERT INTO usuarios (nome, email, telefone, senha_hash) VALUES (:nome, :email, :telefone, :senha_hash)');
                    $stmt->execute([
                        ':nome' => $name,
                        ':email' => $email,
                        ':telefone' => $phone,
                        ':senha_hash' => password_hash($password, PASSWORD_DEFAULT),
                    ]);
                    loginUser((int) $pdo->lastInsertId(), $name, $email);
                    // Uma conta recém-criada ainda não possui perfil profissional.
                    header('Location: ' . appPath('/secure/save_profile.php'));
                    exit;
                } catch (PDOException $e) {
                    $error = 'Não foi possível criar a conta. Esse e-mail pode já estar em uso.';
                }
            }
        } elseif ($mode === 'forgot') {
            $forgotLimiter = rateLimitConsume('forgot_post', 6, 900, $email);
            if (!$forgotLimiter['allowed']) {
                $error = 'Muitas solicitações de recuperação. Aguarde alguns minutos e tente novamente.';
            } elseif (isForgotTemporarilyBlocked()) {
                $error = 'Muitas solicitações de recuperação. Aguarde alguns minutos e tente novamente.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Informe um e-mail válido.';
            } else {
                registerForgotAttempt();
                $lookupStmt = $pdo->prepare('SELECT id, nome, email FROM usuarios WHERE email = :email LIMIT 1');
                $lookupStmt->execute([':email' => $email]);
                $userRow = $lookupStmt->fetch();

                if ($userRow) {
                    $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = :user_id AND used_at IS NULL')
                        ->execute([':user_id' => (int) $userRow['id']]);

                    $tokenPlain = bin2hex(random_bytes(32));
                    $tokenHash = hash('sha256', $tokenPlain);
                    $insertResetStmt = $pdo->prepare(
                        'INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (:user_id, :token_hash, DATE_ADD(NOW(), INTERVAL 60 MINUTE))'
                    );
                    $insertResetStmt->execute([
                        ':user_id' => (int) $userRow['id'],
                        ':token_hash' => $tokenHash,
                    ]);

                    $resetUrl = currentBaseUrl() . appPath('/access/login.php?mode=reset&token=' . urlencode($tokenPlain));
                    $sent = sendPasswordResetEmail((string) $userRow['email'], (string) $userRow['nome'], $resetUrl);
                    if (isLocalEnvironment()) {
                        $debugResetLink = $resetUrl;
                    }
                }
                $success = 'Se o e-mail existir, enviamos um link para redefinir a senha.';
            }
        } elseif ($mode === 'reset') {
            $resetToken = trim((string) ($_POST['token'] ?? ''));
            $newPassword = (string) ($_POST['nova_senha'] ?? '');
            $confirmPassword = (string) ($_POST['confirmar_senha'] ?? '');
            if (!preg_match('/^[a-f0-9]{64}$/', $resetToken)) {
                $error = 'Token de recuperação inválido.';
            } elseif (strlen($newPassword) < 8) {
                $error = 'A nova senha deve ter pelo menos 8 caracteres.';
            } elseif (!hash_equals($newPassword, $confirmPassword)) {
                $error = 'As senhas não conferem.';
            } else {
                $tokenHash = hash('sha256', $resetToken);
                $tokenStmt = $pdo->prepare(
                    'SELECT id, user_id
                     FROM password_resets
                     WHERE token_hash = :token_hash
                       AND used_at IS NULL
                       AND expires_at >= NOW()
                     ORDER BY id DESC
                     LIMIT 1'
                );
                $tokenStmt->execute([':token_hash' => $tokenHash]);
                $tokenRow = $tokenStmt->fetch();

                if (!$tokenRow) {
                    $error = 'Link de recuperação inválido ou expirado.';
                } else {
                    $updateUserStmt = $pdo->prepare('UPDATE usuarios SET senha_hash = :senha_hash WHERE id = :id LIMIT 1');
                    $updateUserStmt->execute([
                        ':senha_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                        ':id' => (int) $tokenRow['user_id'],
                    ]);

                    $consumeStmt = $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = :id');
                    $consumeStmt->execute([':id' => (int) $tokenRow['id']]);

                    $success = 'Senha redefinida com sucesso. Faca login com a nova senha.';
                    $mode = 'login';
                    $resetToken = '';
                }
            }
        } else {
            $loginLimiter = rateLimitConsume('login_password', 12, 300, $email);
            if (!$loginLimiter['allowed']) {
                $error = 'Muitas tentativas de login. Aguarde alguns minutos e tente novamente.';
            } elseif (isLoginTemporarilyBlocked()) {
                $error = 'Muitas tentativas de login. Aguarde alguns minutos e tente novamente.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
                $error = 'Informe e-mail e senha válidos.';
            } else {
                $stmt = $pdo->prepare('SELECT id, nome, senha_hash FROM usuarios WHERE email = :email LIMIT 1');
                $stmt->execute([':email' => $email]);
                $userRow = $stmt->fetch();

                if (!$userRow || !password_verify($password, (string) $userRow['senha_hash'])) {
                    registerAttemptFailed();
                    $error = 'E-mail ou senha inválidos.';
                } else {
                    resetAttemptFailures();
                    $userId = (int) $userRow['id'];
                    loginUser($userId, (string) $userRow['nome'], $email);
                    $profileDestination = defaultLoginDestination($pdo, $userId);
                    // Contas que já possuem perfil sempre retornam ao próprio perfil após entrar.
                    $destination = $profileDestination === appPath('/access/perfil.php')
                        ? $profileDestination
                        : ($hasExplicitNextPath ? $nextPath : $profileDestination);
                    header('Location: ' . $destination);
                    exit;
                }
            }
        }
    }
}

if ($mode === 'reset' && $error === '' && $success === '') {
    if (!preg_match('/^[a-f0-9]{64}$/', $resetToken)) {
        $error = 'Link de recuperação inválido.';
    } else {
        $tokenHash = hash('sha256', $resetToken);
        $tokenStmt = $pdo->prepare(
            'SELECT id
             FROM password_resets
             WHERE token_hash = :token_hash
               AND used_at IS NULL
               AND expires_at >= NOW()
             ORDER BY id DESC
             LIMIT 1'
        );
        $tokenStmt->execute([':token_hash' => $tokenHash]);
        if (!$tokenStmt->fetch()) {
            $error = 'Link de recuperação inválido ou expirado.';
        }
    }
}

$csrf = ensureCsrfToken();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Acesse sua conta no Clube dos Parceiros para criar ou editar seu perfil profissional.">
    <link rel="canonical" href="https://clubedosparceiros.cloud/access/login.php">
    <meta name="robots" content="noindex,nofollow">

    <meta property="og:site_name" content="Clube dos Parceiros">
    <meta property="og:type" content="website">
    <meta property="og:title" content="Entrar - Clube dos Parceiros">
    <meta property="og:description" content="Acesse sua conta para gerenciar seu perfil.">
    <meta property="og:url" content="https://clubedosparceiros.cloud/access/login.php">
    <meta property="og:image" content="https://clubedosparceiros.cloud/img/logo.png">
    <meta property="og:image:alt" content="Logo do Clube dos Parceiros">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Entrar - Clube dos Parceiros">
    <meta name="twitter:description" content="Acesse sua conta para gerenciar seu perfil.">
    <meta name="twitter:image" content="https://clubedosparceiros.cloud/img/logo.png">

    <meta name="theme-color" content="#1A3D63">
    <title>Entrar - Clube dos Parceiros</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" href="/img/logomenor.png" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Archivo+Black&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/theme.css?v=20260513">
    <style>
        :root { --auth-navy: var(--flow-primary); --auth-page: #f6f9fb; }
        body { font-family: 'Inter', sans-serif; background: var(--auth-page); }
        body > nav { display: none; }
        .auth-layout { min-height: 100vh; display: grid; grid-template-columns: minmax(420px, 52.3%) 1fr; }
        .auth-aside { background: var(--auth-navy); color: #fff; padding: 50px clamp(42px, 5.5vw, 78px) 66px; display: flex; flex-direction: column; }
        .auth-brand { display: inline-flex; align-items: center; gap: 13px; color: #fff; font-size: 1.35rem; font-weight: 800; text-decoration: none; }
        .auth-brand img { width: 48px; height: 48px; object-fit: contain; filter: brightness(0) invert(1); }
        .auth-aside-copy { margin: auto 0; max-width: 390px; }
        .auth-aside-copy h2 { font-size: clamp(1.7rem, 2.2vw, 2.25rem); line-height: 1.22; font-weight: 800; letter-spacing: -0.025em; }
        .auth-aside-copy p { margin-top: 1.55rem; font-size: 1.05rem; line-height: 1.48; color: rgba(255,255,255,.96); }
        .auth-aside-footer { font-size: .88rem; font-weight: 600; color: rgba(255,255,255,.94); }
        .auth-content { display: flex; align-items: center; justify-content: center; padding: 42px 24px; background: var(--auth-page); }
        .auth-mobile-brand { display: none; }
        .auth-card { width: 100%; max-width: 350px; border: 0; border-radius: 0; box-shadow: none; background: transparent; padding: 0; }
        .auth-card h1 { font-size: 1.25rem; letter-spacing: -0.02em; }
        .auth-tabs { display: grid; grid-template-columns: 1fr 1fr; gap: 2px; padding: 3px; background: #e9f0f5; border-radius: 10px; margin: 1.35rem 0 1rem; }
        .auth-tab { text-align: center; border-radius: 8px; padding: .48rem .6rem; font-size: .8rem; font-weight: 600; color: #64748b; text-decoration: none; }
        .auth-tab.is-active { background: #fff; color: #1e293b; box-shadow: 0 1px 3px rgba(15, 23, 42, .14); }
        .auth-field { background: #fff; border-color: #d8e2e9; border-radius: 9px; padding: .64rem .85rem; box-shadow: 0 1px 2px rgba(15,23,42,.04); }
        .password-field { position: relative; }
        .password-field .auth-field { padding-right: 3rem; }
        .password-toggle { position: absolute; right: .55rem; top: 50%; transform: translateY(-50%); width: 2.25rem; height: 2.25rem; display: inline-flex; align-items: center; justify-content: center; border: 0; border-radius: 7px; color: #64748b; background: transparent; cursor: pointer; }
        .password-toggle:hover, .password-toggle:focus-visible { background: var(--flow-primary-soft); color: var(--flow-primary); outline: none; }
        .password-toggle svg { width: 1.2rem; height: 1.2rem; fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
        .password-strength { margin-top: .65rem; }
        .password-strength-bar { height: 4px; overflow: hidden; border-radius: 999px; background: #e2e8f0; }
        .password-strength-bar span { display: block; width: 0; height: 100%; border-radius: inherit; background: #ef4444; transition: width .2s ease, background .2s ease; }
        .password-rules { margin-top: .65rem; border: 1px solid #e2e8f0; border-radius: 8px; padding: .55rem .65rem; font-size: .72rem; line-height: 1.55; color: #64748b; }
        .password-rules li::before { content: '✓'; display: inline-flex; width: 1rem; color: #94a3b8; font-weight: 800; }
        .password-rules li.is-valid { color: #2563eb; }
        .password-rules li.is-valid::before { color: #2563eb; }
        .register-notice { margin-top: .9rem; text-align: center; font-size: .7rem; line-height: 1.45; color: #94a3b8; }
        .register-notice svg { display: inline-block; width: 1rem; height: 1rem; margin-right: .35rem; vertical-align: -3px; color: #64748b; }
        .register-notice a { color: #2563eb; font-weight: 600; text-decoration: none; }
        .register-notice a:hover { text-decoration: underline; }
        .auth-btn { background: var(--flow-primary) !important; border-color: var(--flow-primary) !important; border-radius: 9px; box-shadow: 0 1px 2px rgba(15,23,42,.15); padding: .68rem 1rem; }
        .auth-btn:hover { background: var(--flow-primary-hover) !important; }
        .auth-card {
            animation: fadeUp .28s ease-out both;
        }
        .auth-field {
            transition: border-color .18s ease, box-shadow .18s ease;
        }
        .auth-field:focus {
            border-color: #93c5fd;
            box-shadow: 0 0 0 3px rgba(147, 197, 253, .25);
            outline: none;
        }
        .auth-btn {
            transition: transform .18s ease, background .18s ease;
        }
        .auth-btn:hover { transform: translateY(-1px); }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @media (prefers-reduced-motion: reduce) {
            .auth-card { animation: none; }
            .auth-field,
            .auth-btn { transition: none; }
        }

        /* Cores e tipografia padronizadas via ../assets/theme.css */
        .page-enter {
            animation: pageFadeIn .32s ease both;
        }
        @keyframes pageFadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @media (prefers-reduced-motion: reduce) {
            .page-enter { animation: none; }
        }

        .loading-overlay {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background: rgba(15, 23, 42, 0.55);
            backdrop-filter: blur(6px);
        }
        .loading-overlay.is-visible {
            display: flex;
        }
        .loading-card {
            width: 100%;
            max-width: 420px;
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(226, 232, 240, 0.9);
            border-radius: 1.25rem;
            box-shadow: 0 22px 60px rgba(15, 23, 42, 0.25);
            padding: 1.25rem 1.1rem;
            display: flex;
            align-items: center;
            gap: 0.9rem;
            transform: translateY(8px);
            opacity: 0;
            transition: transform .18s ease, opacity .18s ease;
        }
        .loading-overlay.is-visible .loading-card {
            transform: translateY(0);
            opacity: 1;
        }
        .loading-spinner {
            width: 2.25rem;
            height: 2.25rem;
            border-radius: 999px;
            border: 3px solid rgba(26, 61, 99, 0.22);
            border-top-color: var(--flow-primary);
            animation: spin .8s linear infinite;
            flex: 0 0 auto;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        @media (prefers-reduced-motion: reduce) {
            .loading-card { transition: none; transform: none; opacity: 1; }
            .loading-spinner { animation: none; }
        }

        .auth-btn.is-loading {
            cursor: wait;
            opacity: 0.92;
        }
        .auth-btn .btn-inline {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
        }
        .auth-btn .btn-mini-spinner {
            width: 1.05rem;
            height: 1.05rem;
            border-radius: 999px;
            border: 2px solid rgba(255, 255, 255, 0.35);
            border-top-color: #ffffff;
            animation: spin .8s linear infinite;
            display: none;
        }
        .auth-btn.is-loading .btn-mini-spinner {
            display: inline-block;
        }
        @media (max-width: 850px) {
            .auth-layout { display: block; }
            .auth-aside { display: none; }
            .auth-content { min-height: 100vh; align-items: flex-start; padding-top: 42px; }
            .auth-content-inner { width: 100%; max-width: 350px; }
            .auth-mobile-brand { display: inline-flex; align-items: center; gap: .55rem; margin-bottom: 3rem; color: var(--auth-navy); font-weight: 800; text-decoration: none; }
            .auth-mobile-brand img { width: 32px; height: 32px; object-fit: contain; }
        }
    </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 page-enter">
    <nav class="fixed top-0 w-full z-50 bg-blue-900/95 text-white backdrop-blur-md border-b border-blue-800/60">
        <div class="container mx-auto px-4 sm:px-6 py-3.5 flex items-center justify-between gap-3">
            <a href="<?php echo htmlspecialchars(appPath('/index.html'), ENT_QUOTES, 'UTF-8'); ?>" class="shrink-0 flex items-center gap-3 text-white font-black text-base sm:text-xl min-w-0">
                <img src="../img/logomenor.png" alt="Logo Clube dos Parceiros" class="h-14 sm:h-16 w-auto object-contain">
                <span class="truncate text-sm sm:text-xl max-w-[170px] sm:max-w-none">Clube dos Parceiros</span>
            </a>

            <div class="hidden md:flex flex-1 items-center justify-center gap-6 lg:gap-8 text-sm font-medium text-white/85">
                <a href="<?php echo htmlspecialchars(appPath('/index.html#como-funciona'), ENT_QUOTES, 'UTF-8'); ?>" class="hover:text-white transition-colors">Como funciona</a>
                <a href="<?php echo htmlspecialchars(appPath('/index.html#vantagens'), ENT_QUOTES, 'UTF-8'); ?>" class="hover:text-white transition-colors">Vantagens</a>
                <a href="<?php echo htmlspecialchars(appPath('/index.html#videos'), ENT_QUOTES, 'UTF-8'); ?>" class="hover:text-white transition-colors">Vídeos</a>
                <a href="<?php echo htmlspecialchars(appPath('/access/painel.php'), ENT_QUOTES, 'UTF-8'); ?>" class="hover:text-white transition-colors">Ver profissionais</a>
            </div>

            <div class="hidden md:flex shrink-0 flex-wrap items-center justify-end gap-2 sm:gap-3">
                <a href="<?php echo htmlspecialchars(appPath('/access/login.php?mode=register&next=' . rawurlencode($nextPath)), ENT_QUOTES, 'UTF-8'); ?>" class="flow-btn flow-btn-light px-3 py-1.5 sm:px-4 sm:py-2 rounded-lg font-semibold transition-all duration-300 transform hover:-translate-y-1 shadow-md flex items-center justify-center gap-2 border-2 text-xs sm:text-sm whitespace-nowrap">
                    Cadastrar
                </a>
                <a href="<?php echo htmlspecialchars(appPath('/access/login.php?mode=login'), ENT_QUOTES, 'UTF-8'); ?>" class="flow-btn flow-btn-light px-3 py-1.5 sm:px-4 sm:py-2 rounded-lg font-semibold transition-all duration-300 transform hover:-translate-y-1 shadow-md flex items-center justify-center gap-2 border-2 text-xs sm:text-sm whitespace-nowrap">
                    Entrar
                </a>
            </div>

            <button id="nav-toggle" type="button" class="hamburger md:hidden text-white/90 hover:text-white transition" aria-label="Abrir menu" aria-controls="mobile-menu" aria-expanded="false">
                <span class="hamburger-lines" aria-hidden="true">
                    <span></span>
                    <span></span>
                    <span></span>
                </span>
            </button>
        </div>

        <div id="mobile-menu" class="md:hidden hidden px-4 sm:px-6 pb-4 pt-2 bg-blue-900 border-t border-blue-800/60">
            <div class="flex flex-col gap-1 text-sm font-semibold">
                <a href="<?php echo htmlspecialchars(appPath('/index.html#como-funciona'), ENT_QUOTES, 'UTF-8'); ?>" class="px-3 py-2 rounded-lg hover:bg-white/10 transition">Como funciona</a>
                <a href="<?php echo htmlspecialchars(appPath('/index.html#vantagens'), ENT_QUOTES, 'UTF-8'); ?>" class="px-3 py-2 rounded-lg hover:bg-white/10 transition">Vantagens</a>
                <a href="<?php echo htmlspecialchars(appPath('/index.html#videos'), ENT_QUOTES, 'UTF-8'); ?>" class="px-3 py-2 rounded-lg hover:bg-white/10 transition">Vídeos</a>
                <a href="<?php echo htmlspecialchars(appPath('/access/painel.php'), ENT_QUOTES, 'UTF-8'); ?>" class="px-3 py-2 rounded-lg hover:bg-white/10 transition">Ver profissionais</a>
            </div>
            <div class="mt-3 grid grid-cols-2 gap-2">
                <a href="<?php echo htmlspecialchars(appPath('/access/login.php?mode=register&next=' . rawurlencode($nextPath)), ENT_QUOTES, 'UTF-8'); ?>" class="flow-btn flow-btn-light px-3 py-2 rounded-lg font-bold border-2 transition text-center">
                    Cadastrar
                </a>
                <a href="<?php echo htmlspecialchars(appPath('/access/login.php?mode=login'), ENT_QUOTES, 'UTF-8'); ?>" class="flow-btn flow-btn-light px-3 py-2 rounded-lg font-bold border-2 transition text-center">
                    Entrar
                </a>
            </div>
        </div>
    </nav>

    <div class="auth-layout">
        <aside class="auth-aside">
            <a class="auth-brand" href="<?php echo htmlspecialchars(appPath('/index.html'), ENT_QUOTES, 'UTF-8'); ?>">
                <img src="../img/logomenor.png" alt="">
                <span>Clube dos Parceiros</span>
            </a>
            <div class="auth-aside-copy">
                <h2>Precisou de um serviço?<br>Encontre quem resolve.</h2>
                <p>Encontre profissionais qualificados para pequenos reparos, instalações e manutenções. Compare, encontre especialistas perto de você e contrate sem complicação.</p>
            </div>
            <p class="auth-aside-footer">Dados protegidos e separados por conta.</p>
        </aside>
        <section class="auth-content">
            <div class="auth-content-inner">
            <a class="auth-mobile-brand" href="<?php echo htmlspecialchars(appPath('/index.html'), ENT_QUOTES, 'UTF-8'); ?>">
                <img src="../img/logomenor.png" alt="">
                <span>Clube dos Parceiros</span>
            </a>
        <main class="auth-card">
        <?php
            $title = 'Acesse sua conta';
            $subtitle = 'Entre ou crie sua conta para começar.';
            if ($mode === 'register') {
                $title = 'Criar conta';
                $subtitle = 'Crie sua conta para cadastrar seu perfil profissional.';
            } elseif ($mode === 'forgot') {
                $title = 'Recuperar senha';
                $subtitle = 'Informe seu e-mail para receber o link de redefinicao.';
            } elseif ($mode === 'reset') {
                $title = 'Redefinir senha';
                $subtitle = 'Digite sua nova senha para concluir a recuperação.';
            }
        ?>
        <h1 class="text-2xl font-extrabold text-slate-900 mb-1"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="text-sm text-slate-500 mb-6"><?php echo htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8'); ?></p>

        <?php if ($mode === 'login' || $mode === 'register'): ?>
            <div class="auth-tabs" aria-label="Acesso à conta">
                <a class="auth-tab <?php echo $mode === 'login' ? 'is-active' : ''; ?>" href="<?php echo htmlspecialchars(appPath('/access/login.php?mode=login&next=' . rawurlencode($nextPath)), ENT_QUOTES, 'UTF-8'); ?>">Entrar</a>
                <a class="auth-tab <?php echo $mode === 'register' ? 'is-active' : ''; ?>" href="<?php echo htmlspecialchars(appPath('/access/login.php?mode=register&next=' . rawurlencode($nextPath)), ENT_QUOTES, 'UTF-8'); ?>">Criar conta</a>
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 text-red-700 text-sm px-3 py-2"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($success !== ''): ?>
            <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-700 text-sm px-3 py-2"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        <?php if ($debugResetLink !== ''): ?>
            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 text-amber-800 text-sm px-3 py-2">
                Ambiente local detectado. Link de recuperação:
                <a class="underline break-all" href="<?php echo htmlspecialchars($debugResetLink, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($debugResetLink, ENT_QUOTES, 'UTF-8'); ?></a>
            </div>
        <?php endif; ?>

        <form method="post" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="mode" value="<?php echo htmlspecialchars($mode, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="next" value="<?php echo htmlspecialchars($nextPath, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="has_explicit_next" value="<?php echo $hasExplicitNextPath ? '1' : '0'; ?>">

            <?php if ($mode === 'register'): ?>
                <div>
                    <label for="nome" class="block text-sm font-semibold text-slate-700 mb-1">Nome</label>
                    <input id="nome" name="nome" class="auth-field w-full rounded-xl border border-slate-300 px-4 py-3" value="<?php echo htmlspecialchars($formName, ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
            <?php endif; ?>

            <?php if ($mode !== 'reset'): ?>
                <div>
                    <label for="email" class="block text-sm font-semibold text-slate-700 mb-1">E-mail</label>
                    <input id="email" name="email" type="email" class="auth-field w-full rounded-xl border border-slate-300 px-4 py-3" value="<?php echo htmlspecialchars($formEmail, ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>
            <?php endif; ?>

            <?php if ($mode === 'login' || $mode === 'register'): ?>
                <div>
                    <label for="senha" class="block text-sm font-semibold text-slate-700 mb-1">Senha</label>
                    <div class="password-field">
                        <input id="senha" name="senha" type="password" class="auth-field w-full rounded-xl border border-slate-300 px-4 py-3" minlength="8" required>
                        <button class="password-toggle" type="button" aria-label="Mostrar senha" aria-pressed="false" data-password-toggle="senha">
                            <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"></path><circle cx="12" cy="12" r="2.5"></circle></svg>
                        </button>
                    </div>
                    <?php if ($mode === 'register'): ?>
                        <div class="password-strength" aria-live="polite">
                            <div class="flex items-center justify-between text-[11px] font-semibold text-slate-500"><span>Força da senha</span><span id="passwordStrengthLabel">Muito fraca</span></div>
                            <div class="password-strength-bar mt-1"><span id="passwordStrengthBar"></span></div>
                            <ul class="password-rules" id="passwordRules">
                                <li data-rule="length">Pelo menos 8 caracteres</li>
                                <li data-rule="lettersNumbers">Inclua letras e números</li>
                                <li data-rule="special">Use caracteres especiais (!@#$%)</li>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($mode === 'reset'): ?>
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($resetToken, ENT_QUOTES, 'UTF-8'); ?>">
                <div>
                    <label for="nova_senha" class="block text-sm font-semibold text-slate-700 mb-1">Nova senha</label>
                    <div class="password-field">
                        <input id="nova_senha" name="nova_senha" type="password" class="auth-field w-full rounded-xl border border-slate-300 px-4 py-3" minlength="8" required>
                        <button class="password-toggle" type="button" aria-label="Mostrar senha" aria-pressed="false" data-password-toggle="nova_senha">
                            <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"></path><circle cx="12" cy="12" r="2.5"></circle></svg>
                        </button>
                    </div>
                </div>
                <div>
                    <label for="confirmar_senha" class="block text-sm font-semibold text-slate-700 mb-1">Confirmar nova senha</label>
                    <div class="password-field">
                        <input id="confirmar_senha" name="confirmar_senha" type="password" class="auth-field w-full rounded-xl border border-slate-300 px-4 py-3" minlength="8" required>
                        <button class="password-toggle" type="button" aria-label="Mostrar senha" aria-pressed="false" data-password-toggle="confirmar_senha">
                            <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"></path><circle cx="12" cy="12" r="2.5"></circle></svg>
                        </button>
                    </div>
                </div>
            <?php endif; ?>

            <?php
                $submitLabel = 'Entrar';
                if ($mode === 'register') {
                    $submitLabel = 'Criar conta';
                } elseif ($mode === 'forgot') {
                    $submitLabel = 'Enviar link de recuperação';
                } elseif ($mode === 'reset') {
                    $submitLabel = 'Salvar nova senha';
                }
            ?>

            <button type="submit" class="auth-btn flow-btn w-full rounded-xl border-2 font-bold py-3">
                <span class="btn-inline">
                    <span class="btn-mini-spinner" aria-hidden="true"></span>
                    <span class="btn-label"><?php echo htmlspecialchars($submitLabel, ENT_QUOTES, 'UTF-8'); ?></span>
                </span>
            </button>
            <?php if ($mode === 'register'): ?>
                <p class="register-notice"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3 5 6v5c0 4.5 3 8.4 7 10 4-1.6 7-5.5 7-10V6l-7-3Z"></path><path d="m9 12 2 2 4-4"></path></svg>Seus dados estão protegidos e não são compartilhados.<br>Ao criar sua conta, você concorda com nossos <a href="<?php echo htmlspecialchars(appPath('/access/termos_responsabilidade.php'), ENT_QUOTES, 'UTF-8'); ?>">Termos de Uso</a> e a <a href="<?php echo htmlspecialchars(appPath('/access/politica_privacidade.php'), ENT_QUOTES, 'UTF-8'); ?>">Política de Privacidade</a>.</p>
            <?php endif; ?>
        </form>

        <div class="mt-5 text-sm text-slate-600">
            <?php if ($mode === 'register'): ?>
                Já tem conta? <a href="<?php echo htmlspecialchars(appPath('/access/login.php?mode=login&next=' . rawurlencode($nextPath)), ENT_QUOTES, 'UTF-8'); ?>" class="text-blue-700 font-semibold hover:underline">Entrar</a>
            <?php elseif ($mode === 'login'): ?>
                Não tem conta? <a href="<?php echo htmlspecialchars(appPath('/access/login.php?mode=register&next=' . rawurlencode($nextPath)), ENT_QUOTES, 'UTF-8'); ?>" class="text-blue-700 font-semibold hover:underline">Criar conta</a>
                <span class="mx-2 text-slate-300">|</span>
                <a href="<?php echo htmlspecialchars(appPath('/access/login.php?mode=forgot'), ENT_QUOTES, 'UTF-8'); ?>" class="text-blue-700 font-semibold hover:underline">Esqueci minha senha</a>
            <?php elseif ($mode === 'forgot'): ?>
                Lembrou a senha? <a href="<?php echo htmlspecialchars(appPath('/access/login.php?mode=login&next=' . rawurlencode($nextPath)), ENT_QUOTES, 'UTF-8'); ?>" class="text-blue-700 font-semibold hover:underline">Entrar</a>
            <?php else: ?>
                <a href="<?php echo htmlspecialchars(appPath('/access/login.php?mode=login&next=' . rawurlencode($nextPath)), ENT_QUOTES, 'UTF-8'); ?>" class="text-blue-700 font-semibold hover:underline">Voltar para entrar</a>
            <?php endif; ?>
        </div>
        </main>
            </div>
        </section>
    </div>

    <div id="loadingOverlay" class="loading-overlay" aria-hidden="true">
        <div class="loading-card" role="status" aria-live="polite">
            <div class="loading-spinner" aria-hidden="true"></div>
            <div class="text-left">
                <p class="font-extrabold text-slate-900 leading-tight">Carregando…</p>
                <p class="text-sm text-slate-600 mt-0.5">Aguarde só um instante.</p>
            </div>
        </div>
    </div>

    <script>
        document.querySelectorAll('[data-password-toggle]').forEach((button) => {
            button.addEventListener('click', () => {
                const input = document.getElementById(button.dataset.passwordToggle);
                if (!input) return;
                const visible = input.type === 'text';
                input.type = visible ? 'password' : 'text';
                button.setAttribute('aria-pressed', String(!visible));
                button.setAttribute('aria-label', visible ? 'Mostrar senha' : 'Ocultar senha');
            });
        });

        (function initPasswordStrength() {
            const input = document.getElementById('senha');
            const bar = document.getElementById('passwordStrengthBar');
            const label = document.getElementById('passwordStrengthLabel');
            const rules = document.getElementById('passwordRules');
            if (!input || !bar || !label || !rules) return;

            const update = () => {
                const value = input.value;
                const state = {
                    length: value.length >= 8,
                    lettersNumbers: /[a-zA-Z]/.test(value) && /\d/.test(value),
                    special: /[!@#$%]/.test(value)
                };
                Object.entries(state).forEach(([rule, valid]) => {
                    const item = rules.querySelector(`[data-rule="${rule}"]`);
                    if (item) item.classList.toggle('is-valid', valid);
                });
                const score = Object.values(state).filter(Boolean).length;
                const levels = [
                    ['Muito fraca', '0%', '#ef4444'],
                    ['Fraca', '34%', '#ef4444'],
                    ['Média', '67%', '#f59e0b'],
                    ['Forte', '100%', '#2563eb']
                ];
                const [text, width, color] = levels[score];
                label.textContent = text;
                bar.style.width = width;
                bar.style.background = color;
            };
            input.addEventListener('input', update);
            update();
        })();

        (function initMobileNav() {
            const toggle = document.getElementById('nav-toggle');
            const menu = document.getElementById('mobile-menu');
            if (!toggle || !menu) return;

            const closeMenu = () => {
                menu.classList.add('hidden');
                toggle.setAttribute('aria-expanded', 'false');
                toggle.classList.remove('is-open');
            };

            toggle.addEventListener('click', () => {
                const isOpen = !menu.classList.contains('hidden');
                if (isOpen) closeMenu();
                else {
                    menu.classList.remove('hidden');
                    toggle.setAttribute('aria-expanded', 'true');
                    toggle.classList.add('is-open');
                }
            });

            menu.addEventListener('click', (event) => {
                const target = event.target;
                if (target && target.closest && target.closest('a')) closeMenu();
            });

            window.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') closeMenu();
            });

            window.addEventListener('resize', () => {
                if (window.matchMedia('(min-width: 768px)').matches) closeMenu();
            });
        })();

        (function initAuthLoading() {
            const form = document.querySelector('form[method="post"]');
            const overlay = document.getElementById('loadingOverlay');
            if (!form) return;

            const btn = form.querySelector('button[type="submit"]');
            const label = btn ? btn.querySelector('.btn-label') : null;
            let locked = false;

            form.addEventListener('submit', () => {
                if (locked) return;
                locked = true;

                if (btn) {
                    btn.classList.add('is-loading');
                    btn.setAttribute('disabled', 'disabled');
                    btn.setAttribute('aria-disabled', 'true');
                    if (label) label.textContent = 'Carregando…';
                }
                if (overlay) {
                    overlay.classList.add('is-visible');
                    overlay.setAttribute('aria-hidden', 'false');
                }
            });
        })();
    </script>
</body>
</html>
