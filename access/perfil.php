<?php
require_once __DIR__ . '/../secure/auth.php';
require_once __DIR__ . '/../secure/config.php';
require_once __DIR__ . '/../secure/agenda.php';
startSecureSession();

$cfg = appConfig();
$host = $cfg['db_host'];
$db   = $cfg['db_name'];
$user = $cfg['db_user'];
$pass = $cfg['db_pass'];
$charset = $cfg['db_charset'];

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    ensureAgendaTables($pdo);
} catch (PDOException $e) {
    http_response_code(503);
    exit('Serviço temporariamente indisponível. Tente novamente mais tarde.');
}

$viewerUserId = currentUserId();

$publicId = trim((string) ($_GET['p'] ?? $_GET['public_id'] ?? ''));
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (preg_match('/^[a-f0-9]{32}$/', $publicId)) {
    $stmt = $pdo->prepare('SELECT * FROM profissionais WHERE public_id = :public_id LIMIT 1');
    $stmt->execute([':public_id' => $publicId]);
} elseif ($id) {
    $stmt = $pdo->prepare('SELECT * FROM profissionais WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
} elseif ($viewerUserId !== null) {
    $stmt = $pdo->prepare('SELECT * FROM profissionais WHERE user_id = :user_id ORDER BY id DESC LIMIT 1');
    $stmt->execute([':user_id' => $viewerUserId]);
} else {
    $stmt = $pdo->query('SELECT * FROM profissionais ORDER BY id DESC LIMIT 1');
}

$pro = $stmt ? $stmt->fetch() : false;
if (!$pro) {
    if ($viewerUserId !== null) {
        header('Location: ' . appPath('/secure/save_profile.php'));
        exit;
    }
    die('Profissional não encontrado.');
}

$profileOwnerId = (int) ($pro['user_id'] ?? 0);
$viewerIsOwner = $viewerUserId !== null && $profileOwnerId > 0 && (int) $viewerUserId === $profileOwnerId;
$agendaCsrf = ensureCsrfToken();

$name = trim((string) ($pro['nome'] ?? $pro['nome_completo'] ?? 'Profissional'));
$photo = trim((string) ($pro['foto_perfil'] ?? ''));
$city = trim((string) ($pro['cidade'] ?? ''));
$district = trim((string) ($pro['bairro'] ?? ''));
$locationLabel = trim($district . ', ' . $city, ', ');
$startYear = (int) ($pro['desde'] ?? 0);
$rating = (float) ($pro['nota'] ?? 0);
$online = ((int) ($pro['online'] ?? 0)) === 1;
$description = trim((string) ($pro['descricao'] ?? $pro['bio'] ?? ''));
$email = trim((string) ($pro['email'] ?? ''));
$instagram = trim((string) ($pro['instagram'] ?? ''));
$siteUrl = trim((string) ($pro['site_url'] ?? ''));
$facebook = trim((string) ($pro['facebook'] ?? ''));

$phone = preg_replace('/\D+/', '', (string) ($pro['whatsapp'] ?? $pro['telefone'] ?? ''));
$whatsappPhone = $phone;
if ($whatsappPhone !== '' && !(strlen($whatsappPhone) > 11 && str_starts_with($whatsappPhone, '55'))) {
    $whatsappPhone = '55' . $whatsappPhone;
}
$phoneDisplay = $phone !== '' ? $phone : 'Não informado';
$whatsUrl = $whatsappPhone !== '' ? 'https://wa.me/' . $whatsappPhone : '';

$especialidades = [];
if (!empty($pro['tags'])) {
    $especialidades = array_values(array_filter(array_map('trim', explode(',', (string) $pro['tags']))));
} else {
    $fromJson = json_decode((string) ($pro['serviÃ§os'] ?? '[]'), true);
    if (is_array($fromJson)) {
        $especialidades = array_values(array_filter(array_map(static fn($item) => is_string($item) ? trim($item) : '', $fromJson)));
    }
}

$fotosRaw = (string) ($pro['fotos_trabalhos'] ?? $pro['fotos_trabalho'] ?? '[]');
$fotos_trabalho = json_decode($fotosRaw, true);
if (!is_array($fotos_trabalho)) {
    $fotos_trabalho = [];
}

$marketplaceProducts = [];
$marketRaw = (string) ($pro['marketplace_products'] ?? '[]');
$marketDecoded = json_decode($marketRaw, true);
if (is_array($marketDecoded)) {
    foreach ($marketDecoded as $item) {
        if (!is_array($item)) {
            continue;
        }
        if (array_key_exists('active', $item) && !$item['active']) {
            continue;
        }
        $title = trim((string) ($item['title'] ?? ''));
        if ($title === '') {
            continue;
        }
        $images = array_values(array_filter((array) ($item['images'] ?? [])));
        $legacyImage = trim((string) ($item['image'] ?? ''));
        if (!$images && $legacyImage !== '') {
            $images = [$legacyImage];
        }
        $marketplaceProducts[] = [
            'title' => $title,
            'description' => trim((string) ($item['description'] ?? '')),
            'price' => trim((string) ($item['price'] ?? '')),
            'url' => trim((string) ($item['url'] ?? '')),
            'images' => array_slice($images, 0, 4),
            'image' => (string) ($images[0] ?? ''),
            'order' => (int) ($item['order'] ?? count($marketplaceProducts)),
        ];
    }
}

$anoAtual = (int) date('Y');
$anosExp = $startYear > 0 ? max(0, $anoAtual - $startYear) : 0;

if (empty($_SESSION['feedback_csrf']) || !is_string($_SESSION['feedback_csrf'])) {
    $_SESSION['feedback_csrf'] = bin2hex(random_bytes(32));
}
$feedbackCsrf = (string) $_SESSION['feedback_csrf'];
$publicProfileId = trim((string) ($pro['public_id'] ?? ''));
$agendaProfileKey = preg_match('/^[a-f0-9]{32}$/', $publicProfileId) === 1 ? $publicProfileId : 'id-' . (int) $pro['id'];
$canSubmitFeedback = preg_match('/^[a-f0-9]{32}$/', $publicProfileId) === 1;
$feedbackCount = 0;
$averageRating = 0.0;
$feedbacks = [];
$ownerAppointments = [];
$nextAvailableDate = null;
$nextAvailableTime = '';
$nextAvailableCount = 0;

try {
    $aggStmt = $pdo->prepare(
        "SELECT ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS total_feedbacks
         FROM feedbacks
         WHERE profissional_id = :profissional_id"
    );
    $aggStmt->execute([':profissional_id' => (int) $pro['id']]);
    $agg = $aggStmt->fetch() ?: ['avg_rating' => 0, 'total_feedbacks' => 0];
    $averageRating = (float) ($agg['avg_rating'] ?? 0);
    $feedbackCount = (int) ($agg['total_feedbacks'] ?? 0);

    $fbStmt = $pdo->prepare(
        "SELECT client_name, rating, comment, image_path, created_at
         FROM feedbacks
         WHERE profissional_id = :profissional_id
         ORDER BY id DESC
         LIMIT 10"
    );
    $fbStmt->execute([':profissional_id' => (int) $pro['id']]);
    $feedbacks = $fbStmt->fetchAll() ?: [];
} catch (Throwable $e) {
    $feedbackCount = 0;
    $averageRating = 0.0;
    $feedbacks = [];
}
usort($marketplaceProducts, static fn(array $a, array $b): int => $a['order'] <=> $b['order']);
$showAllMarketplace = isset($_GET['produtos']) && $_GET['produtos'] === '1';
$visibleMarketplaceProducts = $showAllMarketplace ? $marketplaceProducts : array_slice($marketplaceProducts, 0, 4);

if ($viewerIsOwner) {
    try {
        $agendaStmt = $pdo->prepare("SELECT cliente_nome, cliente_telefone, cliente_email, observacao, inicio, status FROM agendamentos WHERE profissional_id = :id AND status IN ('confirmado', 'pendente') AND inicio >= NOW() ORDER BY inicio ASC LIMIT 8");
        $agendaStmt->execute([':id' => (int) $pro['id']]);
        $ownerAppointments = $agendaStmt->fetchAll() ?: [];
    } catch (Throwable $e) { $ownerAppointments = []; }
}

try {
    for ($offset = 0; $offset < 14; $offset++) {
        $date = (new DateTimeImmutable('today'))->modify('+' . $offset . ' days')->format('Y-m-d');
        $availableSlots = agendaSlots($pdo, (int) $pro['id'], $date);
        if ($availableSlots !== []) {
            $nextAvailableDate = $date;
            $nextAvailableTime = (string) $availableSlots[0];
            $nextAvailableCount = count($availableSlots);
            break;
        }
    }
} catch (Throwable $e) { /* O resumo continua disponível caso a agenda esteja indisponível. */ }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Confira o perfil profissional de <?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?> no Clube dos Parceiros. Veja serviços e formas de contato.">
    <link rel="canonical" href="<?php echo htmlspecialchars(rtrim((string) (appConfig()['app_url'] ?? ''), '/') . appPath('/access/perfil.php?p=' . $publicProfileId), ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1">

    <meta property="og:site_name" content="Clube dos Parceiros">
    <meta property="og:type" content="profile">
    <meta property="og:title" content="Perfil - <?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:description" content="Confira serviços e formas de contato.">
    <meta property="og:url" content="<?php echo htmlspecialchars(rtrim((string) (appConfig()['app_url'] ?? ''), '/') . appPath('/access/perfil.php?p=' . $publicProfileId), ENT_QUOTES, 'UTF-8'); ?>">
    <meta property="og:image" content="https://clubedosparceiros.cloud/img/logo.png">
    <meta property="og:image:alt" content="Logo do Clube dos Parceiros">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Perfil - <?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="twitter:description" content="Confira serviços e formas de contato no Clube dos Parceiros.">
    <meta name="twitter:image" content="https://clubedosparceiros.cloud/img/logo.png">

    <meta name="theme-color" content="#1A3D63">
    <title>Perfil - <?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" href="/img/logomenor.png" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="../assets/theme.css?v=20260810">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; }
        .pro-card { background: white; border-radius: 1rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); border: 1px solid #e2e8f0; }
        .service-badge { background-color: #eff6ff; color: #1e40af; padding: 4px 12px; border-radius: 20px; font-size: 0.875rem; font-weight: 500; }
        .toast { position: fixed; bottom: 2rem; left: 50%; transform: translateX(-50%); background: #1e293b; color: white; padding: 0.75rem 1.5rem; border-radius: 99px; display: none; z-index: 1000; }
        .top-header {
            background: #ffffff;
            border-bottom: 1px solid #d9e2ec;
        }
        .brand-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            background: var(--flow-primary);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(30, 58, 138, .18);
            overflow: hidden;
            flex-shrink: 0;
        }
        .brand-icon img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 6px;
        }
        .brand-title {
            font-weight: 800;
            color: var(--flow-primary);
            letter-spacing: -0.01em;
            font-size: 1.05rem;
            line-height: 1.1;
            white-space: nowrap;
        }
        .profile-spotlight {
            position: relative;
            overflow: hidden;
            background:
                radial-gradient(circle at top right, rgba(59, 130, 246, 0.28), transparent 32%),
                linear-gradient(135deg, #0f172a 0%, #0b132b 55%, #111827 100%);
            border-color: rgba(30, 41, 59, 0.9);
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.22);
        }
        .profile-spotlight::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(120deg, rgba(255, 255, 255, 0.06), transparent 45%);
            pointer-events: none;
        }
        .about-copy {
            position: relative;
            color: #cbd5e1;
            font-size: 1rem;
            line-height: 1.9;
            max-width: 62ch;
        }
        .stat-panel {
            overflow: hidden;
            background: linear-gradient(145deg, #0d3263 0%, #08264c 48%, #061b37 100%);
            border-color: rgba(30, 41, 59, 0.95);
            color: #e2e8f0;
            box-shadow: 0 18px 45px rgba(15, 23, 42, 0.18);
        }
        .summary-body { background: rgba(44, 92, 146, 0.25); border-radius: 0.75rem; overflow: hidden; }
        .summary-row { display: flex; gap: 1rem; padding: 1rem 1.1rem; border-bottom: 1px solid rgba(148, 163, 184, 0.18); }
        .summary-row:last-child { border-bottom: 0; }
        .summary-icon { width: 28px; flex: 0 0 28px; color: #f8fafc; margin-top: 0.2rem; }
        .summary-label { color: #dbeafe; font-size: 0.84rem; font-weight: 500; }
        .summary-value { color: #ffffff; font-size: 1.12rem; line-height: 1.2; font-weight: 700; margin-top: 0.1rem; }
        .summary-value--blue { color: #5da6ff; font-size: 1.35rem; }
        .summary-help { color: #f1f5f9; font-size: 0.8rem; margin-top: 0.3rem; }
        .summary-link { color: #60a5fa; font-size: 0.8rem; font-weight: 600; white-space: nowrap; margin-left: auto; align-self: center; }
        .summary-link:hover { color: #bfdbfe; }
        .metric-card {
            background: rgba(148, 163, 184, 0.08);
            border: 1px solid rgba(148, 163, 184, 0.14);
            border-radius: 1rem;
            padding: 1rem;
            min-height: 116px;
            transition: transform .25s ease, border-color .25s ease, background-color .25s ease;
        }
        .metric-card:hover {
            transform: translateY(-3px);
            border-color: rgba(96, 165, 250, 0.3);
            background: rgba(59, 130, 246, 0.09);
        }
        .metric-icon {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 0.9rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(59, 130, 246, 0.14);
            color: #93c5fd;
        }
        .metric-icon > svg,
        .metric-icon > i,
        .metric-icon svg,
        .metric-icon i {
            width: 1.25rem !important;
            height: 1.25rem !important;
            display: block;
            flex: 0 0 auto;
        }
        .metric-label {
            color: #94a3b8;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .metric-value {
            color: #f8fafc;
            font-size: 1.85rem;
            line-height: 1.1;
            font-weight: 800;
            letter-spacing: -0.03em;
        }
        .metric-subtext {
            color: #cbd5e1;
            font-size: 0.92rem;
        }
        .mp-panel {
            position: relative;
            background: radial-gradient(circle at right top, #eaf3ff 0%, transparent 32%), linear-gradient(145deg, #ffffff 0%, #f7faff 100%);
            border: 1px solid #d5e3f3;
            border-radius: 1.5rem;
            box-shadow: 0 20px 46px rgba(15, 42, 78, 0.10);
            overflow: hidden;
        }
        .mp-panel-header {
            padding: 2rem 2rem 1.15rem;
        }
        .mp-panel-body {
            padding: 0.85rem 2rem 2rem;
        }
        .mp-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(205px, 1fr));
            gap: .9rem;
        }
        .mp-item {
            overflow: hidden;
            border: 1px solid #d6e2f0;
            background: #ffffff;
            border-radius: .85rem;
            padding: 0;
            box-shadow: 0 3px 12px rgba(15, 42, 78, 0.035);
            transition: transform .22s ease, box-shadow .22s ease, border-color .22s ease;
            display: flex;
            flex-direction: column;
            align-items: stretch;
        }
        .mp-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 14px 28px rgba(15, 42, 78, 0.13);
            border-color: #91b4dc;
        }
        .mp-bubble {
            box-sizing: border-box;
            width: 100%;
            height: 122px;
            border-radius: 0;
            background: #e8eef5;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 0;
            font-weight: 800;
            letter-spacing: -0.01em;
            box-shadow: none;
            transition: transform .18s ease, box-shadow .18s ease, filter .18s ease;
            text-decoration: none;
        }
        .mp-bubble-link { cursor: pointer; }
        .mp-item:hover .mp-bubble {
            filter: brightness(1.04);
        }
        .mp-bubble span {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-wrap: balance;
            line-height: 1.15;
            font-size: 0.98rem;
        }
        .mp-bubble img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 0;
            transition: transform .35s ease;
        }
        .mp-item.is-extra { display: none; }
        .mp-grid.is-expanded .mp-item.is-extra { display: flex; }
        .mp-more-wrap { margin-top: 1.75rem; padding: .35rem 0 .15rem; text-align: center; }
        .mp-item:hover .mp-bubble img {
            transform: scale(1.045);
        }
        .mp-title {
            font-size: 1rem;
            font-weight: 900;
            color: #0f172a;
            margin: .75rem .75rem 0;
            text-align: left;
            line-height: 1.12;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .mp-price {
            font-size: 0.92rem;
            color: #1556a8;
            text-align: left;
            margin: .2rem .75rem 0;
            font-weight: 800;
        }
        .mp-actions {
            margin: .65rem .75rem .75rem;
            display: flex;
            justify-content: stretch;
            gap: 0.5rem;
            flex-wrap: wrap;
            width: auto;
            box-sizing: border-box;
        }
        .mp-action-btn {
            box-sizing: border-box;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            padding: .52rem .7rem;
            border-radius: .45rem;
            font-size: .72rem;
            font-weight: 800;
            border: 1px solid rgba(226, 232, 240, 0.95);
            background: #ffffff;
            color: #0f172a;
            transition: transform .18s ease, box-shadow .18s ease, background-color .18s ease;
            flex: 1 1 auto;
            min-width: 0;
        }
        .mp-action-btn:hover {
            background: #f8fafc;
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.10);
            transform: translateY(-1px);
        }
        .mp-action-btn--wa {
            background: #0b7a54;
            border-color: #0b7a54;
            color: #ffffff;
            background: #0b2e59;
            border-color: #0b2e59;
            box-shadow: 0 5px 10px rgba(9, 46, 90, 0.16);
        }
        .mp-action-btn--wa:hover {
            background: #061f3d;
        }
        .mp-kicker { display: inline-flex; align-items: center; gap: .45rem; color: #1765b5; font-size: .72rem; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; }
        .mp-kicker::before { content: ''; width: 1.65rem; height: 2px; border-radius: 10px; background: #2f7fd3; }
        .mp-image-tag { position: absolute; left: .65rem; top: .65rem; z-index: 1; border-radius: .35rem; background: rgba(8,31,64,.88); padding: .25rem .45rem; color: #fff; font-size: .58rem; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; }
        .mp-bubble { position: relative; overflow: hidden; }
        .mp-favorite { position: absolute; right: .65rem; top: .65rem; z-index: 1; display: flex; width: 1.75rem; height: 1.75rem; align-items: center; justify-content: center; border: 0; border-radius: .45rem; background: rgba(255,255,255,.94); color: #426084; box-shadow: 0 2px 8px rgba(15,42,78,.13); font-size: 1rem; }
        .mp-description { display: -webkit-box; min-height: 2.55rem; margin: .55rem .75rem 0; overflow: hidden; color: #53657c; font-size: .7rem; line-height: 1.32; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
        .mp-accent { width: 1.65rem; height: 2px; margin: .45rem .75rem 0; border-radius: 4px; background: #7fcbb2; }
        .mp-price-label { margin: .5rem .75rem 0; color: #6b7b91; font-size: .62rem; font-weight: 600; }
        /* Vitrine de produtos */
        .mp-panel { border-radius: 1.75rem; background: radial-gradient(circle at 92% 8%, #e8f2ff 0%, transparent 28%), #fff; }
        .mp-panel-header { padding: 2.65rem 2.5rem 1.55rem; }
        .mp-panel-body { padding: 1.1rem 2.5rem 2.5rem; }
        .mp-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1.5rem; }
        .mp-item { border-radius: 1.25rem; border-color: #dce7f3; box-shadow: 0 8px 22px rgba(15,42,78,.07); }
        .mp-bubble { height: 302px; background: #f4f7fb; }
        .mp-image-tag { left: .8rem; top: .8rem; border-radius: .45rem; padding: .38rem .58rem; font-size: .68rem; }
        .mp-favorite { display: none; }
        .mp-gallery-arrow { position:absolute; top:50%; z-index:2; display:flex; width:2.7rem; height:2.7rem; align-items:center; justify-content:center; border:0; border-radius:999px; background:rgba(255,255,255,.94); color:#153d6d; box-shadow:0 3px 12px rgba(15,42,78,.15); font-size:2rem; line-height:1; transform:translateY(-50%); }
        .mp-gallery-arrow.prev { left:.7rem; }.mp-gallery-arrow.next { right:.7rem; }
        .mp-gallery-dots { position:absolute; bottom:.8rem; left:50%; z-index:2; display:flex; gap:.25rem; transform:translateX(-50%); }.mp-gallery-dots i { width:.48rem; height:.48rem; border-radius:50%; background:rgba(255,255,255,.85); }.mp-gallery-dots i:first-child { background:#1473cf; }
        .mp-title { margin: 1rem 1rem 0; font-size: 1.18rem; }.mp-accent { margin:.55rem 1rem 0; }.mp-description { min-height:3.3rem; margin:.7rem 1rem 0; font-size:.84rem; line-height:1.45; }.mp-thumbs { display:flex; gap:.42rem; margin:1rem 1rem 0; overflow:hidden; }.mp-thumb { flex:1 1 0; height:3.55rem; overflow:hidden; border:2px solid transparent; border-radius:.55rem; background:#f2f5f9; padding:.1rem; }.mp-thumb.active { border-color:#116cc3; }.mp-thumb img { width:100%; height:100%; object-fit:cover; border-radius:.35rem; }.mp-price-label { margin:.95rem 1rem 0; font-size:.75rem; }.mp-price { margin:.25rem 1rem 0; font-size:1.15rem; }.mp-actions { margin:1rem 1rem 1rem; }.mp-action-btn { min-height:3.35rem; border-radius:.6rem; font-size:.88rem; }.mp-kicker { font-size:.8rem; }
        .mp-trust-row { display:grid; grid-template-columns:repeat(4,1fr); gap:1rem; margin:1.5rem 2.5rem 2.4rem; padding:1.25rem 1.45rem; border:1px solid #edf1f6; border-radius:1.2rem; background:#fff; box-shadow:0 8px 22px rgba(15,42,78,.05); }.mp-trust { display:flex; align-items:center; gap:.7rem; padding:0 .7rem; border-right:1px solid #dbe5f0; }.mp-trust:last-child{border-right:0}.mp-trust-icon{display:flex;width:3.2rem;height:3.2rem;flex:0 0 3.2rem;align-items:center;justify-content:center;border-radius:1rem;background:#eaf3ff;color:#1264ba;font-size:1.55rem}.mp-trust b{display:block;color:#102343;font-size:.86rem;line-height:1.18}.mp-trust small{display:block;margin-top:.25rem;color:#63748c;font-size:.72rem;line-height:1.35}
        @media (max-width: 1024px) { .mp-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }.mp-bubble { height:260px; }.mp-trust-row { grid-template-columns:repeat(2,1fr); }.mp-trust:nth-child(2){border-right:0} }
        @media (max-width: 640px) { .mp-panel-header,.mp-panel-body{padding-left:1.1rem;padding-right:1.1rem}.mp-grid{display:flex;overflow-x:auto;padding-bottom:1rem}.mp-item{min-width:278px}.mp-bubble{height:270px}.mp-trust-row{grid-template-columns:1fr;margin:0 1.1rem 1.1rem}.mp-trust,.mp-trust:nth-child(2){border-right:0;border-bottom:1px solid #dbe5f0;padding:.6rem 0}.mp-trust:last-child{border-bottom:0} }
        @media (max-width: 640px) {
            .mp-panel-header { padding: 1.35rem 1.1rem 0.75rem; }
            .mp-panel-body { padding: 0.75rem 1.1rem 1.1rem; }
            .mp-grid {
                display: flex;
                gap: 0.9rem;
                overflow-x: auto;
                padding-bottom: 0.25rem;
                scroll-snap-type: x mandatory;
                -webkit-overflow-scrolling: touch;
            }
            .mp-item {
                flex: 0 0 auto;
                width: min(240px, calc(100vw - 4.2rem));
                scroll-snap-align: start;
            }
            .mp-bubble { width: 104px; height: 104px; border-radius: 1rem; }
            .mp-bubble img { border-radius: 1rem; }
        }
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
        }
        .gallery-trigger {
            position: relative;
            display: block;
            overflow: hidden;
            border-radius: 1rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 10px 25px rgba(15, 23, 42, 0.08);
            cursor: zoom-in;
        }
        .gallery-trigger img {
            width: 100%;
            aspect-ratio: 1 / 1;
            object-fit: cover;
            transition: transform .3s ease, filter .3s ease;
        }
        .gallery-trigger::after {
            content: 'Ampliar';
            position: absolute;
            right: 0.75rem;
            bottom: 0.75rem;
            background: rgba(15, 23, 42, 0.72);
            color: #fff;
            padding: 0.35rem 0.6rem;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 700;
        }
        .gallery-trigger:hover img,
        .gallery-trigger:focus-visible img {
            transform: scale(1.04);
            filter: saturate(1.05);
        }
        .feedback-zoom {
            cursor: zoom-in;
            transition: transform .25s ease, box-shadow .25s ease;
        }
        .feedback-zoom:hover {
            transform: scale(1.01);
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.12);
        }
        .lightbox {
            position: fixed;
            inset: 0;
            z-index: 100;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            background: rgba(2, 6, 23, 0.82);
            backdrop-filter: blur(10px);
        }
        .lightbox.is-open {
            display: flex;
        }
        .lightbox-dialog {
            width: min(100%, 960px);
            max-height: calc(100vh - 2rem);
            background: #0f172a;
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 1.25rem;
            overflow: hidden;
            box-shadow: 0 30px 80px rgba(2, 6, 23, 0.45);
        }
        .lightbox-frame {
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(180deg, rgba(15, 23, 42, 0.96), rgba(15, 23, 42, 0.88));
            min-height: 300px;
            max-height: calc(100vh - 8rem);
            padding: 1rem;
        }
        .lightbox-frame img {
            max-width: 100%;
            max-height: calc(100vh - 10rem);
            object-fit: contain;
            border-radius: 1rem;
        }
        @media (max-width: 767px) {
            .pro-card {
                border-radius: 1.25rem;
            }
            .metric-value {
                font-size: 1.55rem;
            }
            .gallery-grid {
                grid-template-columns: 1fr;
            }
            .lightbox {
                padding: 0.5rem;
            }
            .lightbox-dialog {
                border-radius: 1rem;
            }
            .lightbox-frame {
                min-height: 220px;
                padding: 0.75rem;
            }
        }
        .site-navbar { background: #0b2a52; transition: box-shadow .2s ease; }
        .site-navbar.is-scrolled { box-shadow: 0 8px 22px rgba(2, 19, 43, .18); }
        .nav-link { display: inline-flex; align-items: center; gap: .55rem; color: rgba(255,255,255,.88); font-size: .92rem; font-weight: 600; transition: color .18s ease; }
        .nav-link:hover, .nav-link[aria-expanded="true"] { color: #fff; }
        #site-navbar [data-nav-products] { display: inline-flex !important; position: relative; visibility: visible !important; opacity: 1 !important; }
        .nav-products { position: relative; }
        .nav-products-badge { position: absolute; left: 50%; top: -1.55rem; transform: translateX(-50%); border-radius: 999px; background: #2563eb; padding: .25rem .6rem; color: #fff; font-size: .62rem; font-weight: 900; letter-spacing: .04em; box-shadow: 0 5px 12px rgba(37,99,235,.35); }
        .site-navbar button[aria-label*="Notifica"], .site-navbar button[aria-label*="Notifica"] + span { display: none !important; }
        .category-dropdown { transform-origin: top center; transition: opacity .16s ease, transform .16s ease; }
        .category-dropdown.is-hidden { opacity: 0; transform: translateY(-6px) scale(.98); pointer-events: none; }
        .category-item { display: flex; align-items: center; gap: .7rem; border-radius: .55rem; padding: .65rem .75rem; color: #16345f; font-size: .88rem; font-weight: 600; transition: background-color .16s ease, color .16s ease; }
        .category-item:hover { background: #eff6ff; color: #0755d9; }
        /* Vitrine da loja — acabamento para telas pequenas. */
        @media (max-width: 640px) {
            .mp-panel { border-radius: 1.5rem; }
            .mp-panel-header { padding: 1.8rem 1.1rem 1rem; }
            .mp-panel-header h2 { font-size: 1.7rem; line-height: 1.15; }
            .mp-panel-header > a { width: 100%; }
            .mp-panel-body { padding: .8rem 1.1rem 1.2rem; }
            .mp-grid { display: flex !important; gap: 1rem; overflow-x: auto !important; overflow-y: hidden !important; padding: 0 .15rem .9rem !important; scroll-snap-type: x mandatory; scrollbar-width: none; } .mp-grid::-webkit-scrollbar { display: none; }
            .mp-item { flex: 0 0 calc(100% - 1.5rem); min-width: 0 !important; width: auto; border-radius: 1.2rem; scroll-snap-align: start; } .mp-grid:not(:has(.mp-item:nth-child(2))) .mp-item { flex-basis: 100%; }
            .mp-bubble { width: 100% !important; height: 230px !important; }
            .mp-bubble img { width: 100%; height: 100%; object-fit: cover; }
            .mp-title { margin: 1rem 1rem 0; font-size: 1.2rem; }
            .mp-description { margin: .65rem 1rem 0; min-height: 0; font-size: .88rem; }
            .mp-thumbs { margin: 1rem 1rem 0; }
            .mp-thumb { height: 4rem; }
            .mp-price-label { margin: 1rem 1rem 0; }
            .mp-price { margin-left: 1rem; font-size: 1.3rem; }
            .mp-actions { margin: 1rem; }
            .mp-action-btn { min-height: 3.4rem; font-size: .95rem; }
            .mp-trust-row { margin: 0 1.1rem 1.2rem; padding: .7rem 1rem; border-radius: 1rem; }
            .mp-trust { gap: .8rem; }
            .mp-trust-icon { width: 2.75rem; height: 2.75rem; flex-basis: 2.75rem; border-radius: .8rem; }
        }        .mp-trust-row { display: none !important; }
    </style>
</head>
<body class="min-h-screen text-slate-900 bg-slate-50 antialiased selection:bg-blue-200 selection:text-blue-900">

    <div id="toast" class="toast text-sm font-medium"></div>

    <nav id="site-navbar" class="site-navbar fixed top-0 z-50 w-full border-b border-white/10 text-white">
        <div class="mx-auto flex h-[76px] max-w-none items-center justify-between gap-5 px-[5.5vw]">
            <a href="<?php echo htmlspecialchars(appPath('/index.html'), ENT_QUOTES, 'UTF-8'); ?>" class="flex min-w-0 shrink-0 items-center gap-3 font-black text-white" aria-label="Ir para a página inicial"><img src="../img/logomenor.png" alt="Logo Clube dos Parceiros" class="h-11 w-auto object-contain sm:h-12"><span class="truncate text-base sm:text-lg">Clube dos Parceiros</span></a>
            <div class="hidden flex-1 items-center justify-center gap-12 xl:gap-16 lg:flex">
                <a href="<?php echo htmlspecialchars(appPath('/access/painel.php'), ENT_QUOTES, 'UTF-8'); ?>" class="nav-link"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="6"></circle><path d="m20 20-4.2-4.2"></path></svg>Encontrar profissionais</a>
                <a href="<?php echo htmlspecialchars(appPath('/access/produtos_servicos.php'), ENT_QUOTES, 'UTF-8'); ?>" class="nav-link nav-products" data-nav-products><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 9.5 5 4h14l2 5.5M4 10h16v10H4z"></path><path d="M9 20v-5h6v5"></path></svg>Produtos<span class="nav-products-badge">NOVO</span></a>
                <a href="<?php echo htmlspecialchars(appPath('/index.html') . '#como-funciona', ENT_QUOTES, 'UTF-8'); ?>" class="nav-link"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 10v6m0-9h.01"></path></svg>Como funciona</a>
                <div class="relative" id="categories-wrapper"><button id="categories-toggle" class="nav-link" type="button" aria-expanded="false" aria-controls="categories-menu"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 9.5 5 4h14l2 5.5M4 10h16v10H4z"></path><path d="M9 20v-5h6v5"></path></svg>Categorias<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"></path></svg></button><div id="categories-menu" class="category-dropdown is-hidden absolute left-1/2 top-[calc(100%+20px)] z-50 w-72 -translate-x-1/2 rounded-[10px] border border-slate-100 bg-white p-2 shadow-lg" role="menu"><a href="<?php echo htmlspecialchars(appPath('/access/painel.php?category=casa-reforma'), ENT_QUOTES, 'UTF-8'); ?>" class="category-item" role="menuitem"><span>⌂</span>Casa e Reforma</a><a href="<?php echo htmlspecialchars(appPath('/access/painel.php?category=instalacoes-manutencao'), ENT_QUOTES, 'UTF-8'); ?>" class="category-item" role="menuitem"><span>⌕</span>Instalações e Manutenção</a><a href="<?php echo htmlspecialchars(appPath('/access/painel.php?category=tecnologia'), ENT_QUOTES, 'UTF-8'); ?>" class="category-item" role="menuitem"><span>▣</span>Tecnologia</a><a href="<?php echo htmlspecialchars(appPath('/access/painel.php?category=eventos'), ENT_QUOTES, 'UTF-8'); ?>" class="category-item" role="menuitem"><span>☆</span>Eventos</a><a href="<?php echo htmlspecialchars(appPath('/access/painel.php?category=outros'), ENT_QUOTES, 'UTF-8'); ?>" class="category-item" role="menuitem"><span>•••</span>Outros</a></div></div>
            </div>
            <?php if ($viewerUserId === null): ?><div class="hidden shrink-0 items-center gap-5 lg:flex"><a href="<?php echo htmlspecialchars(appPath('/access/login.php') . '?mode=login', ENT_QUOTES, 'UTF-8'); ?>" class="text-sm font-semibold text-white/90 transition hover:text-white">Entrar</a><span class="h-7 w-px bg-white/30" aria-hidden="true"></span><a href="<?php echo htmlspecialchars(appPath('/access/login.php') . '?mode=register', ENT_QUOTES, 'UTF-8'); ?>" class="rounded-lg bg-blue-600 px-5 py-3 text-sm font-bold text-white transition duration-200 hover:-translate-y-px hover:bg-blue-500">Cadastrar como parceiro</a></div><?php else: ?><div class="relative hidden shrink-0 items-center gap-4 lg:flex"><button type="button" class="relative rounded-lg p-2 text-white/90 hover:bg-white/10" aria-label="Notificações"><svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"></path></svg></button><span class="h-7 w-px bg-white/30"></span><button id="account-toggle" type="button" class="flex items-center gap-2 rounded-lg px-1 py-1 text-sm font-semibold hover:bg-white/10" aria-expanded="false" aria-controls="account-menu"><span class="relative flex h-9 w-9 items-center justify-center rounded-full bg-blue-500 text-xs font-bold"><?php echo htmlspecialchars(strtoupper(substr(trim(currentUserName()), 0, 1)) ?: 'U', ENT_QUOTES, 'UTF-8'); ?><i class="absolute bottom-0 right-0 h-2.5 w-2.5 rounded-full border-2 border-[#0b2a52] bg-emerald-400"></i></span>Olá, <?php echo htmlspecialchars(explode(' ', trim(currentUserName()))[0] ?: 'Usuário', ENT_QUOTES, 'UTF-8'); ?>!<svg id="account-caret" class="h-4 w-4 transition-transform duration-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 9 6 6 6-6"></path></svg></button><div id="account-menu" class="category-dropdown is-hidden absolute right-0 top-[calc(100%+17px)] z-[60] w-[230px] rounded-[10px] border border-slate-100 bg-white p-2 text-slate-800 shadow-lg"><a href="<?php echo htmlspecialchars(appPath('/access/perfil.php?p=' . $publicProfileId), ENT_QUOTES, 'UTF-8'); ?>" class="category-item">◯ Meu perfil</a><a href="<?php echo htmlspecialchars(appPath('/secure/save_profile.php'), ENT_QUOTES, 'UTF-8'); ?>" class="category-item">✎ Editar perfil</a><a href="<?php echo htmlspecialchars(appPath('/access/configurar_agenda.php'), ENT_QUOTES, 'UTF-8'); ?>" class="category-item">▣ Meus agendamentos</a><div class="my-2 border-t border-slate-100"></div><a href="<?php echo htmlspecialchars(appPath('/access/logout.php'), ENT_QUOTES, 'UTF-8'); ?>" class="category-item text-red-600 hover:bg-red-50 hover:text-red-700">↪ Sair</a></div></div><?php endif; ?>
            <button id="nav-toggle" type="button" class="rounded-lg p-2 text-white transition hover:bg-white/10 lg:hidden" aria-label="Abrir menu" aria-controls="mobile-menu" aria-expanded="false"><svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"></path></svg></button>
        </div>
        <?php
        $mobileDrawer = [
            'logged_in' => $viewerUserId !== null,
            'user_name' => $viewerUserId !== null ? currentUserName() : '',
            'logo_url' => appPath('/img/logomenor.png'),
            'index_url' => appPath('/index.html'),
            'products_url' => appPath('/access/produtos_servicos.php'),
            'directory_url' => appPath('/access/painel.php'),
            'how_it_works_url' => appPath('/index.html') . '#como-funciona',
            'login_url' => appPath('/access/login.php?mode=login'),
            'signup_url' => appPath('/access/login.php?mode=register'),
            'profile_url' => appPath('/access/perfil.php'),
            'edit_url' => appPath('/secure/save_profile.php'),
            'appointments_url' => appPath('/access/configurar_agenda.php'),
            'logout_url' => appPath('/access/logout.php'),
        ];
        require __DIR__ . '/_mobile_drawer.php';
        unset($mobileDrawer);
        ?>
    </nav>

    <?php if (false): ?>
    <nav class="hidden" aria-hidden="true">
        <div class="container mx-auto px-4 sm:px-6 py-3.5 flex items-center justify-between gap-3">
            <div class="hidden md:flex shrink-0 items-center gap-2">
                <?php if ($viewerUserId !== null): ?><a href="<?php echo htmlspecialchars(appPath('/access/perfil.php?p=' . $publicProfileId), ENT_QUOTES, 'UTF-8'); ?>" class="rounded-lg border border-white bg-white px-3 py-2 text-sm font-semibold text-blue-900 shadow-sm transition hover:bg-blue-50">Meu perfil</a><?php endif; ?>
                <?php if ($viewerUserId !== null): ?><a href="<?php echo htmlspecialchars(appPath('/index.html'), ENT_QUOTES, 'UTF-8'); ?>" class="rounded-lg border border-white bg-white px-3 py-2 text-sm font-semibold text-blue-900 shadow-sm transition hover:bg-blue-50">Página inicial</a><?php endif; ?>
                <?php if ($viewerIsOwner): ?>
                    <a href="<?php echo htmlspecialchars(appPath('/secure/save_profile.php'), ENT_QUOTES, 'UTF-8'); ?>" class="rounded-lg border border-white bg-white px-3 py-2 text-sm font-semibold text-blue-900 shadow-sm transition hover:bg-blue-50">Editar perfil</a>
                <?php endif; ?>
            </div>

            <a href="<?php echo htmlspecialchars(appPath('/index.html'), ENT_QUOTES, 'UTF-8'); ?>" class="ml-auto shrink-0 flex items-center gap-3 text-white font-black text-base sm:text-xl min-w-0">
                <img src="../img/logomenor.png" alt="Logo Clube dos Parceiros" class="h-14 sm:h-16 w-auto object-contain">
                <span class="truncate text-sm sm:text-xl max-w-[170px] sm:max-w-none">Clube dos Parceiros</span>
            </a>

            <button id="nav-toggle" type="button" class="hamburger md:hidden text-white/90 hover:text-white transition" aria-label="Abrir menu" aria-controls="mobile-menu" aria-expanded="false">
                <span class="hamburger-lines" aria-hidden="true">
                    <span></span>
                    <span></span>
                    <span></span>
                </span>
            </button>
        </div>

        <div id="mobile-menu" class="md:hidden hidden px-4 sm:px-6 pb-4 pt-2 bg-blue-900 border-t border-blue-800/60">
            <div class="grid grid-cols-1 gap-2">
                <?php if ($viewerUserId !== null): ?><a href="<?php echo htmlspecialchars(appPath('/access/perfil.php?p=' . $publicProfileId), ENT_QUOTES, 'UTF-8'); ?>" class="rounded-lg border border-white bg-white px-3 py-2 text-center text-sm font-semibold text-blue-900 shadow-sm transition hover:bg-blue-50">Meu perfil</a><?php endif; ?>
                <?php if ($viewerUserId !== null): ?><a href="<?php echo htmlspecialchars(appPath('/index.html'), ENT_QUOTES, 'UTF-8'); ?>" class="rounded-lg border border-white bg-white px-3 py-2 text-center text-sm font-semibold text-blue-900 shadow-sm transition hover:bg-blue-50">Página inicial</a><?php endif; ?>
                <?php if ($viewerIsOwner): ?><a href="<?php echo htmlspecialchars(appPath('/secure/save_profile.php'), ENT_QUOTES, 'UTF-8'); ?>" class="rounded-lg border border-white bg-white px-3 py-2 text-center text-sm font-semibold text-blue-900 shadow-sm transition hover:bg-blue-50">Editar perfil</a><?php endif; ?>
            </div>
        </div>
    </nav>
    <?php endif; ?>

    <main class="max-w-6xl mx-auto px-4 pt-28 pb-8 space-y-6 mb-20">
        <section class="pro-card p-4 sm:p-6">
            <div class="flex flex-col md:flex-row gap-5 md:gap-6 items-start">
                <div class="relative self-center md:self-start">
                    <?php if ($photo !== ''): ?>
                        <img src="<?php echo htmlspecialchars($photo, ENT_QUOTES, 'UTF-8'); ?>" class="w-32 h-32 rounded-full object-cover border-4 border-white shadow-md" alt="Foto do profissional">
                    <?php else: ?>
                        <div class="flex h-32 w-32 items-center justify-center rounded-full border-4 border-white bg-slate-100 text-slate-300 shadow-md" role="img" aria-label="Profissional sem foto cadastrada">
                            <i data-lucide="user-round" class="h-14 w-14" stroke-width="1.6"></i>
                        </div>
                    <?php endif; ?>
                    <?php if ($online): ?>
                        <span class="absolute bottom-2 right-2 w-5 h-5 bg-green-500 border-4 border-white rounded-full" title="Online agora"></span>
                    <?php endif; ?>
                </div>

                <div class="flex-1 space-y-3 w-full min-w-0">
                    <div class="flex flex-wrap items-center gap-2 justify-center md:justify-start text-center md:text-left">
                        <h1 class="text-2xl md:text-3xl font-bold text-slate-900 break-words"><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></h1>
                        <span class="flex items-center gap-1 bg-blue-50 text-blue-700 text-xs font-bold px-2 py-1 rounded">
                            <i data-lucide="badge-check" class="w-4 h-4"></i> VERIFICADO
                        </span>
                    </div>

                    <div class="flex flex-col sm:flex-row sm:flex-wrap gap-2 sm:gap-4 text-slate-600 text-sm text-center md:text-left">
                        <div class="flex items-center justify-center md:justify-start gap-1 min-w-0"><i data-lucide="map-pin" class="w-4 h-4 shrink-0"></i> <span class="break-words"><?php echo htmlspecialchars($locationLabel !== '' ? $locationLabel : 'Local não informado', ENT_QUOTES, 'UTF-8'); ?></span></div>
                        <div class="flex items-center justify-center md:justify-start gap-1"><i data-lucide="calendar" class="w-4 h-4 shrink-0"></i> Desde <?php echo htmlspecialchars($startYear > 0 ? (string) $startYear : '-', ENT_QUOTES, 'UTF-8'); ?></div>
                    </div>

                    <div class="flex flex-wrap gap-2 pt-2 justify-center md:justify-start">
                        <?php foreach ($especialidades as $serviÃ§o): ?>
                            <span class="service-badge"><?php echo htmlspecialchars($serviÃ§o, ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endforeach; ?>
                    </div>

                </div>

                <div class="flex flex-col gap-3 w-full md:w-auto md:min-w-[220px]">
                    <?php if (!$viewerIsOwner): ?>
                    <button type="button" id="btn-schedule" class="bg-blue-800 text-center text-white font-semibold py-3 px-6 rounded-xl shadow-lg hover:bg-blue-900 transition flex items-center justify-center gap-2"><i data-lucide="calendar-days" class="w-5 h-5"></i> Agendar horário</button>
                    <?php endif; ?>
                    <?php if ($whatsUrl !== ''): ?>
                        <a href="<?php echo htmlspecialchars($whatsUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="bg-blue-600 text-center text-white font-semibold py-3 px-6 rounded-xl shadow-lg hover:bg-blue-700 transition">Solicitar Orçamento</a>
                    <?php else: ?>
                        <button type="button" class="bg-slate-300 text-center text-slate-500 font-semibold py-3 px-6 rounded-xl cursor-not-allowed" disabled>Solicitar Orçamento</button>
                    <?php endif; ?>
                    <button onclick="shareProfile()" class="text-slate-500 text-sm flex items-center justify-center gap-1 hover:text-blue-600"><i data-lucide="share-2" class="w-4 h-4"></i> Compartilhar Perfil</button>
                </div>
            </div>
        </section>

        <?php if (!empty($marketplaceProducts)): ?>
            <section class="mp-panel">
                <div class="mp-panel-header flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <span class="mp-kicker">Seleção do profissional</span>
                        <h2 class="text-2xl md:text-3xl font-extrabold text-slate-900">Loja do <?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></h2>
                        <p class="text-sm text-slate-600 mt-1">Produtos e serviços cadastrados pelo profissional.</p>
                    </div>
                    <?php if ($viewerIsOwner): ?>
                        <a href="<?php echo htmlspecialchars(appPath('/access/mini_marketplace.php'), ENT_QUOTES, 'UTF-8'); ?>" class="inline-flex items-center justify-center px-4 py-2 rounded-xl border border-slate-200 text-slate-700 font-semibold text-sm hover:bg-slate-50">
                            Ver/editar produtos
                        </a>
                    <?php endif; ?>
                </div>

                <div class="mp-panel-body">
                <div class="mp-grid">
                    <?php foreach ($marketplaceProducts as $productIndex => $product): ?>
                        <?php
                            $pTitle = (string) ($product['title'] ?? '');
                            $pPrice = (string) ($product['price'] ?? '');
                            $pImg = (string) ($product['image'] ?? '');
                            $pImages = array_values(array_filter((array) ($product['images'] ?? [])));
                            if (!$pImages && $pImg !== '') { $pImages = [$pImg]; }
                            $waMessage = 'Olá! Tenho interesse em ‘' . $pTitle . '’, a partir de ' . ($pPrice !== '' ? $pPrice : 'valor a combinar') . '.';
                            $waLink = $whatsUrl !== '' ? ($whatsUrl . '?text=' . rawurlencode($waMessage)) : '';
                            $priceDigits = preg_replace('/\\D+/', '', $pPrice);
                            $priceDisplay = '';
                            if (is_string($priceDigits) && $priceDigits !== '') {
                                $priceDisplay = 'R$ ' . $priceDigits . ',00';
                            } elseif (trim($pPrice) !== '') {
                                $priceDisplay = 'R$ ' . trim($pPrice);
                            }
                        ?>
                        <div class="mp-item<?php echo $productIndex >= 4 ? ' is-extra' : ''; ?>">
                                <div class="mp-bubble">
                                    <?php if (count($pImages) > 1): ?><button type="button" class="mp-gallery-arrow prev" aria-label="Imagem anterior">‹</button><button type="button" class="mp-gallery-arrow next" aria-label="Próxima imagem">›</button><?php endif; ?>
                                    <span class="mp-image-tag">Produto</span>
                                    <button type="button" class="mp-favorite" aria-label="Adicionar aos favoritos">♡</button>
                                    <?php if ($pImg !== ''): ?>
                                        <img class="mp-main-image" src="<?php echo htmlspecialchars($pImg, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($pTitle, ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php else: ?>
                                        <span><?php echo htmlspecialchars($pTitle, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endif; ?>
                                <div class="mp-gallery-dots" aria-hidden="true"><?php if (count($pImages) > 1): ?><?php foreach ($pImages as $dotImage): ?><i></i><?php endforeach; ?><?php endif; ?></div>
                                </div>
                                <div class="mp-title"><?php echo htmlspecialchars($pTitle, ENT_QUOTES, 'UTF-8'); ?></div>
                                <div class="mp-accent"></div>
                                <?php if (trim((string) ($product['description'] ?? '')) !== ''): ?>
                                    <p class="mp-description"><?php echo htmlspecialchars((string) $product['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php else: ?>
                                    <p class="mp-description">Produto ou serviço oferecido por este profissional.</p>
                                <?php endif; ?>
                                <?php if (count($pImages) > 1): ?><div class="mp-thumbs"><?php foreach ($pImages as $imageIndex => $image): ?><button type="button" class="mp-thumb <?php echo $imageIndex === 0 ? 'active' : ''; ?>" data-image="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>"><img src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>" alt="Miniatura do produto"></button><?php endforeach; ?></div><?php endif; ?>
                                <?php if ($priceDisplay !== ''): ?>
                                    <div class="mp-price-label">A partir de</div>
                                    <div class="mp-price"><?php echo htmlspecialchars($priceDisplay, ENT_QUOTES, 'UTF-8'); ?></div>
                                <?php endif; ?>
                                <div class="mp-actions">
                                    <?php if ($waLink !== ''): ?>
                                        <a class="mp-action-btn mp-action-btn--wa" href="<?php echo htmlspecialchars($waLink, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                                            Tenho interesse <span aria-hidden="true">→</span>
                                        </a>
                                    <?php endif; ?>
                                </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (count($marketplaceProducts) > 4): ?>
                    <div class="mp-more-wrap">
                        <button id="market-more" type="button" class="inline-flex items-center gap-2 rounded-xl border border-blue-200 bg-white px-5 py-2.5 text-sm font-bold text-blue-700 shadow-sm transition hover:-translate-y-px hover:border-blue-300 hover:bg-blue-50" aria-expanded="false">Ver mais produtos <span aria-hidden="true">↓</span></button>
                    </div>
                    <script>
                        (() => {
                            const button = document.getElementById('market-more');
                            const grid = document.querySelector('.mp-grid');
                            if (!button || !grid) return;
                            button.addEventListener('click', () => {
                                const expanded = grid.classList.toggle('is-expanded');
                                button.setAttribute('aria-expanded', String(expanded));
                                button.innerHTML = expanded ? 'Mostrar menos <span aria-hidden="true">↑</span>' : 'Ver mais produtos <span aria-hidden="true">↓</span>';
                            });
                        })();
                    </script>
                <?php endif; ?>
                </div>
                <div class="mp-trust-row"><div class="mp-trust"><span class="mp-trust-icon">♢</span><span><b>Profissionais verificados</b><small>Todos os profissionais são verificados.</small></span></div><div class="mp-trust"><span class="mp-trust-icon">✿</span><span><b>Qualidade garantida</b><small>Produtos e serviços com alto padrão.</small></span></div><div class="mp-trust"><span class="mp-trust-icon">♧</span><span><b>Compra segura</b><small>Seus dados protegidos e transações seguras.</small></span></div><div class="mp-trust"><span class="mp-trust-icon">◉</span><span><b>Suporte dedicado</b><small>Atendimento rápido e personalizado.</small></span></div></div>
            </section>
            <script>
            document.querySelectorAll('.mp-item').forEach((card) => {
                const main = card.querySelector('.mp-main-image');
                const thumbs = [...card.querySelectorAll('.mp-thumb')];
                const images = thumbs.map((thumb) => thumb.dataset.image);
                let current = 0;
                const setImage = (index) => { if (!main || !images.length) return; current = (index + images.length) % images.length; main.src = images[current]; thumbs.forEach((thumb, i) => thumb.classList.toggle('active', i === current)); const dots = card.querySelectorAll('.mp-gallery-dots i'); dots.forEach((dot, i) => dot.style.background = i === current ? '#1473cf' : 'rgba(255,255,255,.85)'); };
                thumbs.forEach((thumb, i) => thumb.addEventListener('click', () => setImage(i)));
                card.querySelector('.mp-gallery-arrow.prev')?.addEventListener('click', () => setImage(current - 1));
                card.querySelector('.mp-gallery-arrow.next')?.addEventListener('click', () => setImage(current + 1));
            });
            </script>
        <?php elseif ($viewerIsOwner): ?>
            <section class="mp-panel">
                <div class="mp-panel-header flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h2 class="text-2xl md:text-3xl font-extrabold text-slate-900">Sua loja</h2>
                        <p class="text-sm text-slate-600 mt-1">Adicione produtos/serviços para aparecerem no seu perfil público.</p>
                    </div>
                    <a href="<?php echo htmlspecialchars(appPath('/access/mini_marketplace.php'), ENT_QUOTES, 'UTF-8'); ?>" class="inline-flex items-center justify-center px-4 py-2 rounded-xl border border-slate-200 text-slate-700 font-semibold text-sm hover:bg-slate-50">
                        Cadastrar produtos
                    </a>
                </div>
            </section>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
            <div class="lg:col-span-3 space-y-6">

                <?php if ($viewerIsOwner): ?>
                <section id="agenda" class="pro-card p-5 sm:p-6 scroll-mt-28">
                    <div class="flex flex-col gap-3 border-b border-slate-100 pb-5 sm:flex-row sm:items-start sm:justify-between">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-700"><i data-lucide="calendar-check" class="w-5 h-5"></i></span>
                            <div><h2 class="text-xl font-bold">Próximos agendamentos</h2><p class="mt-1 text-sm text-slate-500">Acompanhe os próximos atendimentos confirmados ou pendentes.</p></div>
                        </div>
                        <a href="<?php echo htmlspecialchars(appPath('/access/configurar_agenda.php'), ENT_QUOTES, 'UTF-8'); ?>" class="inline-flex shrink-0 items-center justify-center rounded-lg border border-blue-200 px-3 py-2 text-sm font-bold text-blue-700 hover:bg-blue-50">Configurar agenda</a>
                    </div>
                    <div class="mt-5 space-y-3">
                        <?php if ($ownerAppointments): foreach ($ownerAppointments as $appointment): ?>
                            <article class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                                <div><p class="font-bold text-slate-800"><?php echo htmlspecialchars((string) $appointment['cliente_nome'], ENT_QUOTES, 'UTF-8'); ?></p><p class="mt-1 text-sm font-semibold text-blue-700"><?php echo htmlspecialchars(date('d/m/Y \à\s H:i', strtotime((string) $appointment['inicio'])), ENT_QUOTES, 'UTF-8'); ?></p><p class="mt-1 text-sm text-slate-500"><?php echo htmlspecialchars((string) $appointment['cliente_telefone'], ENT_QUOTES, 'UTF-8'); ?></p></div>
                                <span class="inline-flex w-fit rounded-full px-3 py-1 text-xs font-bold <?php echo ($appointment['status'] ?? '') === 'pendente' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'; ?>"><?php echo ($appointment['status'] ?? '') === 'pendente' ? 'Aguardando confirmação' : 'Confirmado'; ?></span>
                            </article>
                        <?php endforeach; else: ?>
                            <div class="rounded-xl border border-dashed border-slate-300 px-4 py-9 text-center"><i data-lucide="calendar-x" class="mx-auto h-7 w-7 text-slate-400"></i><p class="mt-3 font-semibold text-slate-700">Nenhum agendamento próximo</p><p class="mt-1 text-sm text-slate-500">Quando alguém agendar um horário, ele aparecerá aqui.</p></div>
                        <?php endif; ?>
                    </div>
                </section>
                <?php else: ?>
                <section id="agenda" class="pro-card p-5 sm:p-6 scroll-mt-28">
                    <div class="flex items-start gap-3 mb-5">
                        <span class="inline-flex items-center justify-center w-10 h-10 rounded-xl bg-blue-50 text-blue-700"><i data-lucide="calendar-days" class="w-5 h-5"></i></span>
                        <div><h2 class="text-xl font-bold">Agende um horário</h2><p class="text-sm text-slate-500 mt-1">Escolha uma data e um horário disponível. Atendimento médio de 1 hora.</p></div>
                    </div>
                    <div id="agenda-days" class="flex gap-2 overflow-x-auto pb-2"></div>
                    <div class="border-t border-slate-200 mt-4 pt-5">
                        <p id="agenda-date-label" class="font-semibold text-slate-800 mb-3">Selecione uma data</p>
                        <div id="agenda-slots" class="flex flex-wrap gap-2"><span class="text-sm text-slate-400">Carregando horários...</span></div>
                    </div>
                </section>
                <?php endif; ?>



                <section class="pro-card profile-spotlight p-6 md:p-8 text-slate-100">
                    <div class="relative z-10 space-y-5">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <h2 class="text-lg md:text-xl font-bold flex items-center gap-2">
                                <span class="inline-flex items-center justify-center w-11 h-11 rounded-2xl bg-white/10 border border-white/10 text-blue-300">
                                    <i data-lucide="user-round" class="w-5 h-5"></i>
                                </span>
                                Sobre o Profissional
                            </h2>
                            <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs font-semibold tracking-[0.18em] uppercase text-slate-300">
                                <span class="w-2 h-2 rounded-full bg-blue-400"></span>
                                Perfil em destaque
                            </span>
                        </div>
                        <div class="rounded-3xl border border-white/10 bg-white/5 p-5 md:p-6 backdrop-blur-sm">
                            <p class="about-copy"><?php echo nl2br(htmlspecialchars($description !== '' ? $description : 'Descrição não informada.', ENT_QUOTES, 'UTF-8')); ?></p>
                        </div>
                    </div>
                </section>

                <section class="pro-card p-4 sm:p-6">
                    <h2 class="text-lg font-bold mb-6 flex items-center gap-2"><i data-lucide="camera" class="text-blue-600"></i> Fotos de Trabalhos Realizados</h2>
                    <?php if (!empty($fotos_trabalho)): ?>
                        <?php $totalFotosTrabalho = count($fotos_trabalho); ?>
                        <div class="space-y-4">
                            <div id="work-gallery" class="gallery-grid">
                                <?php foreach ($fotos_trabalho as $idx => $foto): ?>
                                    <button
                                        type="button"
                                        class="gallery-trigger js-open-lightbox js-work-photo<?php echo $idx >= 2 ? ' hidden' : ''; ?>"
                                        data-gallery-index="<?php echo (int) $idx; ?>"
                                        data-image-src="<?php echo htmlspecialchars((string) $foto, ENT_QUOTES, 'UTF-8'); ?>"
                                        data-image-alt="Trabalho concluido"
                                    >
                                        <img src="<?php echo htmlspecialchars((string) $foto, ENT_QUOTES, 'UTF-8'); ?>" alt="Trabalho concluido">
                                    </button>
                                <?php endforeach; ?>
                            </div>
                            <?php if ($totalFotosTrabalho > 2): ?>
                                <button
                                    type="button"
                                    id="toggle-works"
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-white hover:border-blue-300 transition flex items-center justify-center gap-2"
                                    aria-expanded="false"
                                    aria-controls="work-gallery"
                                >
                                    <i data-lucide="chevron-down" class="w-4 h-4 transform transition-transform js-works-toggle-icon"></i>
                                    <span id="toggle-works-label">Mostrar mais trabalhos</span>
                                    <span id="toggle-works-count" class="text-slate-500 font-semibold">+<?php echo (int) ($totalFotosTrabalho - 2); ?></span>
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="rounded-lg border border-dashed border-slate-300 py-8 px-4 text-center text-sm text-slate-400">
                            Nenhuma foto de trabalho cadastrada ainda.
                        </div>
                    <?php endif; ?>
                </section>
            </div>

            <div class="space-y-6 lg:col-span-2">

                <section class="pro-card stat-panel p-4 sm:p-5">
                    <h2 class="text-lg font-bold text-white mb-4 flex items-center gap-3"><i data-lucide="bar-chart-3" class="w-5 h-5"></i> Resumo</h2>
                    <div class="summary-body">
                        <div class="summary-row"><i data-lucide="alarm-clock" class="summary-icon"></i><div><p class="summary-label">Próximo horário</p><p class="summary-value summary-value--blue"><?php echo htmlspecialchars($nextAvailableDate === date('Y-m-d') ? 'Hoje' : ($nextAvailableDate !== null ? date('d/m', strtotime($nextAvailableDate)) : 'Indisponível'), ENT_QUOTES, 'UTF-8'); ?><?php echo $nextAvailableTime !== '' ? ', ' . htmlspecialchars($nextAvailableTime, ENT_QUOTES, 'UTF-8') : ''; ?></p><p class="summary-help"><?php echo $nextAvailableCount > 0 ? $nextAvailableCount . ' horários disponíveis' : 'Consulte a agenda'; ?></p></div><a href="#agenda" class="summary-link">Ver agenda <i data-lucide="arrow-right" class="inline w-4 h-4"></i></a></div>
                        <div class="summary-row"><i data-lucide="star" class="summary-icon text-amber-300 fill-amber-300"></i><div><p class="summary-label">Avaliação</p><p class="summary-value"><?php echo htmlspecialchars(number_format($averageRating > 0 ? $averageRating : $rating, 1, ',', '.'), ENT_QUOTES, 'UTF-8'); ?> <span class="text-amber-300 text-base">★</span></p><p class="summary-help">Média de avaliações</p></div></div>
                        <div class="summary-row"><i data-lucide="messages-square" class="summary-icon"></i><div><p class="summary-label">Feedbacks</p><p class="summary-value"><?php echo (int) $feedbackCount; ?></p><p class="summary-help"><?php echo $feedbackCount === 1 ? '1 avaliação publicada' : (int) $feedbackCount . ' avaliações publicadas'; ?></p></div></div>
                        <div class="summary-row"><i data-lucide="clock-3" class="summary-icon"></i><div><p class="summary-label">Horário de atendimento</p><p class="summary-value">07:00 - 18:00</p></div></div>
                    </div>
                </section>

            </div>
        </div>

        <section class="pro-card p-4 sm:p-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                <h2 class="text-2xl md:text-3xl font-extrabold text-slate-900">Avaliações e Comentários</h2>
                <p class="text-xl md:text-2xl font-semibold text-slate-600">&#9733; <?php echo htmlspecialchars(number_format($averageRating, 1, ',', '.'), ENT_QUOTES, 'UTF-8'); ?> (<?php echo (int) $feedbackCount; ?>)</p>
            </div>

            <?php if ($canSubmitFeedback): ?>
            <form id="feedbackForm" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-5">
                <div>
                    <label for="fbName" class="block text-sm font-semibold text-slate-700 mb-1">Seu nome (opcional)</label>
                    <input id="fbName" name="client_name" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm" maxlength="80" placeholder="Ex: Maria">
                </div>
                <div>
                    <label for="fbRating" class="block text-sm font-semibold text-slate-700 mb-1">Nota</label>
                    <select id="fbRating" name="rating" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                        <option value="5">5 - Excelente</option>
                        <option value="4">4 - Muito bom</option>
                        <option value="3">3 - Bom</option>
                        <option value="2">2 - Regular</option>
                        <option value="1">1 - Ruim</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label for="fbComment" class="block text-sm font-semibold text-slate-700 mb-1">Comentário</label>
                    <textarea id="fbComment" name="comment" rows="3" maxlength="500" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm" placeholder="Conte como foi o atendimento (mínimo 10 caracteres)." required></textarea>
                </div>
                <div class="md:col-span-2">
                    <label for="feedbackImage" class="block text-sm font-semibold text-slate-700 mb-1">Imagem do serviço (opcional, máx. 15MB)</label>
                    <input id="feedbackImage" name="feedback_image" type="file" accept="image/png,image/jpeg,image/webp" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div class="md:col-span-2">
                    <button type="submit" class="bg-blue-600 text-white font-semibold py-2 px-4 rounded-lg hover:bg-blue-700 transition">Enviar avaliação</button>
                </div>
            </form>
            <?php else: ?>
                <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 text-amber-700 text-sm px-3 py-2">
                    Este perfil ainda não pode receber avaliações (public_id ausente).
                </div>
            <?php endif; ?>

            <div class="space-y-3">
                <?php if (!empty($feedbacks)): ?>
                    <?php foreach ($feedbacks as $fb): ?>
                        <?php
                            $fbName = trim((string) ($fb['client_name'] ?? ''));
                            $fbRating = max(1, min(5, (int) ($fb['rating'] ?? 0)));
                            $fbComment = (string) ($fb['comment'] ?? '');
                            $fbDate = trim((string) ($fb['created_at'] ?? ''));
                            $fbImage = trim((string) ($fb['image_path'] ?? ''));
                        ?>
                        <article class="border border-slate-200 rounded-xl p-3">
                            <div class="flex items-center justify-between gap-2 mb-1">
                                <p class="text-sm font-semibold text-slate-700"><?php echo htmlspecialchars($fbName !== '' ? $fbName : 'Cliente', ENT_QUOTES, 'UTF-8'); ?></p>
                                <p class="text-sm text-amber-500"><?php echo str_repeat('★', $fbRating) . str_repeat('☆', 5 - $fbRating); ?></p>
                            </div>
                            <p class="text-sm text-slate-600"><?php echo nl2br(htmlspecialchars($fbComment, ENT_QUOTES, 'UTF-8')); ?></p>
                            <?php if ($fbImage !== ''): ?>
                                <img src="<?php echo htmlspecialchars($fbImage, ENT_QUOTES, 'UTF-8'); ?>" alt="Imagem enviada no feedback" class="js-open-lightbox feedback-zoom mt-3 rounded-lg border border-slate-200 max-h-72 w-auto max-w-full" data-image-src="<?php echo htmlspecialchars($fbImage, ENT_QUOTES, 'UTF-8'); ?>" data-image-alt="Imagem enviada no feedback">
                            <?php endif; ?>
                            <?php if ($fbDate !== ''): ?>
                                <?php $fbTs = strtotime($fbDate); ?>
                                <?php if ($fbTs !== false): ?>
                                    <p class="text-xs text-slate-400 mt-2"><?php echo htmlspecialchars(date('d/m/Y H:i', $fbTs), ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php endif; ?>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="rounded-lg border border-dashed border-slate-300 py-8 px-4 text-center text-sm text-slate-400">
                        Ainda não há avaliações para este profissional.
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <div id="image-lightbox" class="lightbox" aria-hidden="true">
        <div class="lightbox-dialog" role="dialog" aria-modal="true" aria-labelledby="lightbox-title">
            <div class="flex items-center justify-between px-4 py-3 border-b border-slate-700">
                <p id="lightbox-title" class="text-sm font-semibold text-slate-200">Visualização da imagem</p>
                <button type="button" id="lightbox-close" class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-white/5 text-slate-200 hover:bg-white/10" aria-label="Fechar imagem">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>
            <div class="lightbox-frame">
                <img id="lightbox-image" src="" alt="">
            </div>
        </div>
    </div>

    <div id="schedule-modal" class="fixed inset-0 z-[70] hidden items-center justify-center bg-slate-950/60 p-4" aria-hidden="true">
        <div class="w-full max-w-lg rounded-2xl bg-white shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="schedule-title">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4"><h2 id="schedule-title" class="text-lg font-bold">Confirmar agendamento</h2><button type="button" data-close-schedule class="rounded-lg p-2 text-slate-500 hover:bg-slate-100" aria-label="Fechar"><i data-lucide="x" class="w-5 h-5"></i></button></div>
            <form id="schedule-form" class="p-5 space-y-4">
                <p id="schedule-summary" class="rounded-lg bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-800"></p>
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($agendaCsrf, ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="p" value="<?php echo htmlspecialchars($agendaProfileKey, ENT_QUOTES, 'UTF-8'); ?>"><input type="hidden" name="date" id="schedule-date"><input type="hidden" name="time" id="schedule-time">
                <div><label class="block text-sm font-semibold mb-1" for="schedule-name">Seu nome</label><input required id="schedule-name" name="name" maxlength="100" class="w-full rounded-lg border border-slate-300 px-3 py-2" placeholder="Como podemos chamar você?"></div>
                <div><label class="block text-sm font-semibold mb-1" for="schedule-phone">Telefone / WhatsApp</label><input required id="schedule-phone" name="phone" inputmode="tel" maxlength="25" class="w-full rounded-lg border border-slate-300 px-3 py-2" placeholder="(11) 99999-9999"></div>
                <div><label class="block text-sm font-semibold mb-1" for="schedule-email">E-mail <span class="font-normal text-slate-400">(opcional)</span></label><input id="schedule-email" name="email" type="email" maxlength="190" class="w-full rounded-lg border border-slate-300 px-3 py-2" placeholder="voce@email.com"></div>
                <div><label class="block text-sm font-semibold mb-1" for="schedule-note">Observação <span class="font-normal text-slate-400">(opcional)</span></label><textarea id="schedule-note" name="note" maxlength="500" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2" placeholder="Descreva brevemente o que precisa."></textarea></div>
                <p id="schedule-error" class="hidden text-sm text-red-600"></p><button id="schedule-submit" class="w-full rounded-xl bg-blue-700 px-4 py-3 font-semibold text-white hover:bg-blue-800">Confirmar horário</button>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();

        (function initScheduling() {
            const daysEl = document.getElementById('agenda-days'), slotsEl = document.getElementById('agenda-slots');
            const label = document.getElementById('agenda-date-label'), modal = document.getElementById('schedule-modal'), form = document.getElementById('schedule-form');
            if (!daysEl || !slotsEl || !modal || !form) return;
            const profile = <?php echo json_encode($agendaProfileKey, JSON_UNESCAPED_UNICODE); ?>;
            const availabilityUrl = <?php echo json_encode(appPath('/access/agenda_disponibilidade.php'), JSON_UNESCAPED_UNICODE); ?>;
            const bookingUrl = <?php echo json_encode(appPath('/access/agendar.php'), JSON_UNESCAPED_UNICODE); ?>;
            const format = new Intl.DateTimeFormat('pt-BR', { weekday: 'short', day: '2-digit', month: 'short' });
            const titleFormat = new Intl.DateTimeFormat('pt-BR', { weekday: 'long', day: '2-digit', month: 'long' });
            const dates = Array.from({length: 14}, (_, i) => { const d = new Date(); d.setHours(12, 0, 0, 0); d.setDate(d.getDate() + i); return d; });
            let selectedDate = dates[0];
            const iso = d => `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2,'0')}-${String(d.getDate()).padStart(2,'0')}`;
            const showModal = () => { modal.classList.remove('hidden'); modal.classList.add('flex'); modal.setAttribute('aria-hidden', 'false'); document.getElementById('schedule-name').focus(); };
            const closeModal = () => { modal.classList.add('hidden'); modal.classList.remove('flex'); modal.setAttribute('aria-hidden', 'true'); };
            const loadSlots = async () => {
                const selected = iso(selectedDate); label.textContent = `Horários disponíveis para ${titleFormat.format(selectedDate)}`;
                slotsEl.innerHTML = '<span class="text-sm text-slate-400">Carregando horários...</span>';
                try { const response = await fetch(`${availabilityUrl}?p=${encodeURIComponent(profile)}&date=${selected}`); const data = await response.json();
                    if (!data.ok || !data.slots.length) { slotsEl.innerHTML = '<span class="text-sm text-slate-500">Não há horários disponíveis nesta data.</span>'; return; }
                    slotsEl.innerHTML = data.slots.map(time => `<button type="button" data-time="${time}" class="rounded-lg border border-blue-500 px-4 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-50">${time}</button>`).join('');
                } catch (_) { slotsEl.innerHTML = '<span class="text-sm text-red-600">Não foi possível carregar a agenda.</span>'; }
            };
            const renderDays = () => { daysEl.innerHTML = dates.map(d => `<button type="button" data-date="${iso(d)}" class="min-w-[76px] rounded-xl border px-3 py-3 text-center text-sm font-semibold ${iso(d) === iso(selectedDate) ? 'border-blue-600 bg-blue-50 text-blue-700' : 'border-slate-200 text-slate-600 hover:border-blue-300'}">${format.format(d).replace('.', '')}</button>`).join(''); };
            renderDays(); loadSlots();
            document.getElementById('btn-schedule')?.addEventListener('click', () => document.getElementById('agenda').scrollIntoView({behavior: 'smooth', block: 'start'}));
            daysEl.addEventListener('click', e => { const button = e.target.closest('[data-date]'); if (!button) return; selectedDate = new Date(`${button.dataset.date}T12:00:00`); renderDays(); loadSlots(); });
            slotsEl.addEventListener('click', e => { const button = e.target.closest('[data-time]'); if (!button) return; document.getElementById('schedule-date').value = iso(selectedDate); document.getElementById('schedule-time').value = button.dataset.time; document.getElementById('schedule-summary').textContent = `${titleFormat.format(selectedDate)}, às ${button.dataset.time}`; document.getElementById('schedule-error').classList.add('hidden'); showModal(); });
            document.querySelectorAll('[data-close-schedule]').forEach(el => el.addEventListener('click', closeModal)); modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
            form.addEventListener('submit', async e => { e.preventDefault(); const submit = document.getElementById('schedule-submit'), error = document.getElementById('schedule-error'); submit.disabled = true; submit.textContent = 'Agendando...';
                try { const response = await fetch(bookingUrl, {method: 'POST', body: new FormData(form)}); const data = await response.json(); if (!data.ok) throw new Error(data.message); closeModal(); form.reset(); showToast(data.message); loadSlots(); }
                catch (err) { error.textContent = err.message || 'Não foi possível agendar.'; error.classList.remove('hidden'); } finally { submit.disabled = false; submit.textContent = 'Confirmar horário'; }
            });
        })();

        (function initAccountMenu() {
            const toggle = document.getElementById('account-toggle');
            const menu = document.getElementById('account-menu');
            const caret = document.getElementById('account-caret');
            if (!toggle || !menu) return;
            const appointmentsLink = menu.querySelector('a:nth-of-type(3)');
            if (appointmentsLink) appointmentsLink.setAttribute('href', '<?php echo htmlspecialchars(appPath('/access/configurar_agenda.php'), ENT_QUOTES, 'UTF-8'); ?>');
            
            const close = () => { menu.classList.add('is-hidden'); toggle.setAttribute('aria-expanded', 'false'); caret?.classList.remove('rotate-180'); };
            const open = () => { menu.classList.remove('is-hidden'); toggle.setAttribute('aria-expanded', 'true'); caret?.classList.add('rotate-180'); };
            toggle.addEventListener('click', () => menu.classList.contains('is-hidden') ? open() : close());
            document.addEventListener('click', (event) => { if (!menu.contains(event.target) && !toggle.contains(event.target)) close(); });
            menu.addEventListener('click', (event) => { if (event.target.closest('a')) close(); });
            window.addEventListener('keydown', (event) => { if (event.key === 'Escape') close(); });
        })();

        (function initMobileNav() {
            const toggle = document.getElementById('nav-toggle');
            const menu = document.getElementById('mobile-menu');
            const closeButton = document.getElementById('mobile-menu-close');
            const backdrop = menu?.querySelector('[data-mobile-menu-backdrop]');
            const categoriesToggle = document.getElementById('mobile-categories-toggle');
            const categoriesMenu = document.getElementById('mobile-categories-menu');
            if (!toggle || !menu) return;

            let closeTimer = 0;
            const closeMenu = (restoreFocus = false) => {
                window.clearTimeout(closeTimer);
                menu.classList.remove('is-open');
                menu.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('mobile-menu-open');
                toggle.setAttribute('aria-expanded', 'false');
                toggle.classList.remove('is-open');
                closeTimer = window.setTimeout(() => {
                    if (!menu.classList.contains('is-open')) menu.classList.add('hidden');
                }, 280);
                if (restoreFocus) toggle.focus({ preventScroll: true });
            };

            const openMenu = () => {
                if (window.matchMedia('(min-width: 1024px)').matches) return;
                window.clearTimeout(closeTimer);
                document.getElementById('categories-menu')?.classList.add('is-hidden');
                menu.classList.remove('hidden');
                menu.setAttribute('aria-hidden', 'false');
                document.body.classList.add('mobile-menu-open');
                toggle.setAttribute('aria-expanded', 'true');
                toggle.classList.add('is-open');
                window.requestAnimationFrame(() => menu.classList.add('is-open'));
                window.setTimeout(() => closeButton?.focus({ preventScroll: true }), 30);
            };

            toggle.addEventListener('click', () => menu.classList.contains('hidden') ? openMenu() : closeMenu());
            closeButton?.addEventListener('click', () => closeMenu(true));
            backdrop?.addEventListener('click', () => closeMenu(true));
            categoriesToggle?.addEventListener('click', () => {
                const isOpen = !categoriesMenu?.classList.contains('hidden');
                categoriesMenu?.classList.toggle('hidden', isOpen);
                categoriesToggle.setAttribute('aria-expanded', String(!isOpen));
            });

            menu.addEventListener('click', (event) => {
                if (event.target instanceof Element && event.target.closest('a')) closeMenu();
            });
            window.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && !menu.classList.contains('hidden')) closeMenu(true);
            });
            window.addEventListener('resize', () => {
                if (window.matchMedia('(min-width: 1024px)').matches) closeMenu();
            });
        })();

        (function initDesktopCategories() {
            const wrapper = document.getElementById('categories-wrapper');
            const toggle = document.getElementById('categories-toggle');
            const menu = document.getElementById('categories-menu');
            if (!wrapper || !toggle || !menu) return;
            const close = () => { menu.classList.add('is-hidden'); toggle.setAttribute('aria-expanded', 'false'); };
            const open = () => { menu.classList.remove('is-hidden'); toggle.setAttribute('aria-expanded', 'true'); };
            toggle.addEventListener('click', () => menu.classList.contains('is-hidden') ? open() : close());
            let closeTimer;
            const cancelClose = () => window.clearTimeout(closeTimer);
            const closeSoon = () => { closeTimer = window.setTimeout(close, 450); };
            wrapper.addEventListener('mouseenter', () => { cancelClose(); open(); });
            wrapper.addEventListener('mouseleave', closeSoon);
            menu.addEventListener('mouseenter', cancelClose);
            menu.addEventListener('mouseleave', closeSoon);
            document.addEventListener('click', (event) => { if (!wrapper.contains(event.target)) close(); });
            window.addEventListener('keydown', (event) => { if (event.key === 'Escape') close(); });
        })();

        (function initNavbarScroll() {
            const navbar = document.getElementById('site-navbar');
            if (!navbar) return;
            const update = () => navbar.classList.toggle('is-scrolled', window.scrollY > 6);
            update();
            window.addEventListener('scroll', update, { passive: true });
        })();

        function shareProfile() {
            if (navigator.share) {
                navigator.share({ title: 'Perfil Profissional', url: window.location.href });
            } else {
                const dummy = document.createElement('input');
                document.body.appendChild(dummy);
                dummy.value = window.location.href;
                dummy.select();
                document.execCommand('copy');
                document.body.removeChild(dummy);
                showToast('Link copiado para a area de transferencia!');
            }
        }

        function showToast(msg) {
            const t = document.getElementById('toast');
            t.innerText = msg;
            t.style.display = 'block';
            setTimeout(() => t.style.display = 'none', 3000);
        }

        const lightbox = document.getElementById('image-lightbox');
        const lightboxImage = document.getElementById('lightbox-image');

        function openLightbox(src, altText) {
            if (!lightbox || !lightboxImage || !src) return;
            lightboxImage.src = src;
            lightboxImage.alt = altText || 'Imagem ampliada';
            lightbox.classList.add('is-open');
            lightbox.setAttribute('aria-hidden', 'false');
            document.body.classList.add('overflow-hidden');
        }

        function closeLightbox() {
            if (!lightbox || !lightboxImage) return;
            lightbox.classList.remove('is-open');
            lightbox.setAttribute('aria-hidden', 'true');
            lightboxImage.src = '';
            lightboxImage.alt = '';
            document.body.classList.remove('overflow-hidden');
        }

        document.addEventListener('click', (event) => {
            const trigger = event.target.closest('.js-open-lightbox');
            if (trigger) {
                openLightbox(trigger.dataset.imageSrc, trigger.dataset.imageAlt);
                return;
            }

            if (event.target === lightbox || event.target.closest('#lightbox-close')) {
                closeLightbox();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeLightbox();
            }
        });

        const worksToggle = document.getElementById('toggle-works');
        const worksGallery = document.getElementById('work-gallery');
        if (worksToggle && worksGallery) {
            const worksItems = Array.from(worksGallery.querySelectorAll('.js-work-photo'));
            const worksLabel = document.getElementById('toggle-works-label');
            const worksCount = document.getElementById('toggle-works-count');
            const worksIcon = worksToggle.querySelector('.js-works-toggle-icon');
            const initialVisible = 2;

            const collapseWorks = () => {
                worksItems.forEach((item, idx) => {
                    if (idx >= initialVisible) item.classList.add('hidden');
                });
                worksToggle.setAttribute('aria-expanded', 'false');
                if (worksLabel) worksLabel.textContent = 'Mostrar mais trabalhos';
                if (worksCount) worksCount.classList.remove('hidden');
                if (worksIcon) worksIcon.classList.remove('rotate-180');
            };

            const expandWorks = () => {
                worksItems.forEach((item) => item.classList.remove('hidden'));
                worksToggle.setAttribute('aria-expanded', 'true');
                if (worksLabel) worksLabel.textContent = 'Mostrar menos';
                if (worksCount) worksCount.classList.add('hidden');
                if (worksIcon) worksIcon.classList.add('rotate-180');
            };

            let isExpanded = worksToggle.getAttribute('aria-expanded') === 'true';
            collapseWorks();

            worksToggle.addEventListener('click', () => {
                isExpanded = !isExpanded;
                if (isExpanded) {
                    expandWorks();
                } else {
                    collapseWorks();
                }
            });
        }

        const feedbackForm = document.getElementById('feedbackForm');
        if (feedbackForm) {
            feedbackForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                const clientName = (document.getElementById('fbName')?.value || '').trim();
                const rating = Number(document.getElementById('fbRating')?.value || 0);
                const comment = (document.getElementById('fbComment')?.value || '').trim();
                const feedbackImage = document.getElementById('feedbackImage')?.files?.[0] || null;

                if (comment.length < 10) {
                    showToast('Comentário deve ter no mínimo 10 caracteres.');
                    return;
                }
                if (feedbackImage && feedbackImage.size > 15 * 1024 * 1024) {
                    showToast('A imagem do feedback deve ter no máximo 15MB.');
                    return;
                }

                try {
                    const formData = new FormData();
                    formData.append('public_id', '<?php echo htmlspecialchars($publicProfileId, ENT_QUOTES, 'UTF-8'); ?>');
                    formData.append('rating', String(rating));
                    formData.append('comment', comment);
                    formData.append('client_name', clientName);
                    formData.append('csrf_token', '<?php echo htmlspecialchars($feedbackCsrf, ENT_QUOTES, 'UTF-8'); ?>');
                    if (feedbackImage) {
                        formData.append('feedback_image', feedbackImage);
                    }
                    const response = await fetch('<?php echo htmlspecialchars(appPath('/access/submit_feedback.php'), ENT_QUOTES, 'UTF-8'); ?>', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();
                    if (!response.ok || !data.ok) {
                        showToast(data.message || 'Não foi possível enviar a avaliação.');
                        return;
                    }
                    showToast('Avaliação enviada com sucesso!');
                    setTimeout(() => window.location.reload(), 800);
                } catch (e) {
                    showToast('Erro ao enviar avaliação.');
                }
            });
        }
    </script>
</body>
</html>
