<?php

require_once __DIR__ . '/../secure/auth.php';
require_once __DIR__ . '/../secure/config.php';

startSecureSession();

header('Content-Type: application/json; charset=utf-8');

function videosJsonResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function videosStorageDir(): string
{
    $dir = __DIR__ . '/../storage';
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        videosJsonResponse(['ok' => false, 'message' => 'Não foi possível preparar o armazenamento.'], 500);
    }
    return $dir;
}

function videosDataPath(): string
{
    return videosStorageDir() . '/videos.json';
}

function videosReadAll(): array
{
    $path = videosDataPath();
    if (!is_file($path)) {
        return [];
    }

    $raw = file_get_contents($path);
    $decoded = json_decode(is_string($raw) ? $raw : '', true);
    return is_array($decoded) ? array_values(array_filter($decoded, 'is_array')) : [];
}

function videosWriteAll(array $videos): void
{
    $path = videosDataPath();
    $json = json_encode(array_values($videos), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false || file_put_contents($path, $json, LOCK_EX) === false) {
        videosJsonResponse(['ok' => false, 'message' => 'Não foi possível salvar os vídeos.'], 500);
    }
}

function videosCleanText(?string $value, int $maxLen): string
{
    $text = preg_replace('/[\x00-\x1F\x7F]/u', '', (string) $value);
    $text = trim(is_string($text) ? $text : '');
    return function_exists('mb_substr') ? mb_substr($text, 0, $maxLen, 'UTF-8') : substr($text, 0, $maxLen);
}

function videosNormalizeYoutube(?string $input): ?string
{
    $raw = trim((string) $input);
    if ($raw === '' || preg_match('/^\s*javascript:/i', $raw)) {
        return null;
    }

    $parts = parse_url($raw);
    if (!is_array($parts) || empty($parts['host'])) {
        return null;
    }

    $host = strtolower(preg_replace('/^www\./', '', (string) $parts['host']));
    $path = (string) ($parts['path'] ?? '');
    $query = [];
    parse_str((string) ($parts['query'] ?? ''), $query);
    $videoId = null;

    if ($host === 'youtu.be') {
        $videoId = trim($path, '/');
    } elseif ($host === 'youtube.com' || $host === 'm.youtube.com') {
        if (str_starts_with($path, '/watch')) {
            $videoId = isset($query['v']) ? (string) $query['v'] : null;
        } elseif (str_starts_with($path, '/embed/')) {
            $chunks = array_values(array_filter(explode('/', $path)));
            $videoId = $chunks[1] ?? null;
        }
    } elseif ($host === 'youtube-nocookie.com' && str_starts_with($path, '/embed/')) {
        $chunks = array_values(array_filter(explode('/', $path)));
        $videoId = $chunks[1] ?? null;
    }

    if (!is_string($videoId) || !preg_match('/^[a-zA-Z0-9_-]{6,}$/', $videoId)) {
        return null;
    }

    return 'https://www.youtube-nocookie.com/embed/' . $videoId;
}

function videosNormalizeMp4(?string $input): ?string
{
    $raw = trim((string) $input);
    if ($raw === '' || preg_match('/^\s*javascript:/i', $raw)) {
        return null;
    }

    if (str_starts_with($raw, '/') || str_starts_with($raw, './') || str_starts_with($raw, '../')) {
        return preg_match('/\.mp4(\?.*)?$/i', $raw) ? $raw : null;
    }

    $parts = parse_url($raw);
    if (!is_array($parts) || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)) {
        return null;
    }

    return preg_match('/\.mp4$/i', (string) ($parts['path'] ?? '')) ? $raw : null;
}

function videosNormalizeLink(?string $input): ?array
{
    $raw = trim((string) $input);
    if ($raw === '') {
        return null;
    }

    if (preg_match('/youtube|youtu\.be/i', $raw)) {
        $src = videosNormalizeYoutube($raw);
        return $src ? ['kind' => 'youtube', 'src' => $src] : null;
    }

    $src = videosNormalizeMp4($raw);
    return $src ? ['kind' => 'mp4', 'src' => $src] : null;
}

function videosSaveUpload(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        videosJsonResponse(['ok' => false, 'message' => 'Não foi possível enviar o vídeo.'], 400);
    }

    if ((int) ($file['size'] ?? 0) > 100 * 1024 * 1024) {
        videosJsonResponse(['ok' => false, 'message' => 'O vídeo deve ter no máximo 100MB.'], 400);
    }

    $tmpPath = (string) ($file['tmp_name'] ?? '');
    if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
        videosJsonResponse(['ok' => false, 'message' => 'Arquivo de vídeo inválido.'], 400);
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($tmpPath);
    if (!in_array($mime, ['video/mp4', 'application/mp4', 'video/x-m4v'], true)) {
        videosJsonResponse(['ok' => false, 'message' => 'Envie um vídeo em MP4.'], 400);
    }

    $uploadDir = __DIR__ . '/../uploads/videos';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        videosJsonResponse(['ok' => false, 'message' => 'Não foi possível preparar a pasta de vídeos.'], 500);
    }

    $filename = 'video_' . bin2hex(random_bytes(12)) . '.mp4';
    $target = $uploadDir . '/' . $filename;
    if (!move_uploaded_file($tmpPath, $target)) {
        videosJsonResponse(['ok' => false, 'message' => 'Não foi possível salvar o vídeo enviado.'], 500);
    }

    return appPath('/uploads/videos/' . $filename);
}

function videosRemoveUpload(?string $src): void
{
    $src = (string) $src;
    $prefix = appPath('/uploads/videos/');
    if ($src === '' || !str_starts_with($src, $prefix)) {
        return;
    }

    $basename = basename($src);
    if ($basename === '' || $basename !== str_replace(['/', '\\'], '', $basename)) {
        return;
    }

    $path = __DIR__ . '/../uploads/videos/' . $basename;
    if (is_file($path)) {
        @unlink($path);
    }
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'GET') {
    videosJsonResponse(['ok' => true, 'videos' => videosReadAll()]);
}

if ($method !== 'POST') {
    videosJsonResponse(['ok' => false, 'message' => 'Método não permitido.'], 405);
}

if (!isAuthenticated() || !currentUserIsAdmin()) {
    videosJsonResponse(['ok' => false, 'message' => 'Acesso não autorizado.'], 401);
}

if (!verifyCsrfTokenOrFail($_POST['csrf_token'] ?? null)) {
    videosJsonResponse(['ok' => false, 'message' => 'Sessão expirada. Recarregue a página e tente novamente.'], 403);
}

$writeLimit = rateLimitConsume('video_write', 30, 300, (string) currentUserId());
if (!$writeLimit['allowed']) {
    videosJsonResponse(['ok' => false, 'message' => 'Muitas alterações em pouco tempo.'], 429);
}

$action = (string) ($_POST['action'] ?? 'upsert');
$videos = videosReadAll();

if ($action === 'delete') {
    $id = videosCleanText($_POST['id'] ?? '', 120);
    $remaining = [];
    $deleted = false;

    foreach ($videos as $video) {
        if ((string) ($video['id'] ?? '') === $id) {
            videosRemoveUpload($video['src'] ?? null);
            $deleted = true;
            continue;
        }
        $remaining[] = $video;
    }

    videosWriteAll($remaining);
    videosJsonResponse(['ok' => true, 'deleted' => $deleted, 'videos' => $remaining]);
}

$id = videosCleanText($_POST['id'] ?? '', 120);
$title = videosCleanText($_POST['title'] ?? '', 80);
$description = videosCleanText($_POST['description'] ?? '', 200);
$link = videosCleanText($_POST['link'] ?? '', 500);

if ($title === '') {
    videosJsonResponse(['ok' => false, 'message' => 'Digite o nome do vídeo.'], 400);
}

$uploadedSrc = null;
if (isset($_FILES['video_file']) && is_array($_FILES['video_file']) && ($_FILES['video_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $uploadedSrc = videosSaveUpload($_FILES['video_file']);
}

$normalized = $uploadedSrc ? ['kind' => 'mp4', 'src' => $uploadedSrc] : videosNormalizeLink($link);
if (!$normalized) {
    videosJsonResponse(['ok' => false, 'message' => 'Envie um MP4 ou informe um link de vídeo válido.'], 400);
}

$now = date(DATE_ATOM);
$newItem = [
    'id' => $id !== '' ? $id : bin2hex(random_bytes(16)),
    'title' => $title,
    'description' => $description,
    'kind' => $normalized['kind'],
    'src' => $normalized['src'],
    'updatedAt' => $now,
];

$updated = false;
foreach ($videos as $index => $video) {
    if ((string) ($video['id'] ?? '') === $newItem['id']) {
        if ($uploadedSrc) {
            videosRemoveUpload($video['src'] ?? null);
        }
        $videos[$index] = $newItem;
        $updated = true;
        break;
    }
}

if (!$updated) {
    array_unshift($videos, $newItem);
}

videosWriteAll($videos);
videosJsonResponse(['ok' => true, 'video' => $newItem, 'videos' => $videos]);
