<?php
require_once __DIR__ . '/../secure/config.php';
require_once __DIR__ . '/../secure/agenda.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $cfg = appConfig();
    $pdo = new PDO("mysql:host={$cfg['db_host']};dbname={$cfg['db_name']};charset={$cfg['db_charset']}", $cfg['db_user'], $cfg['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    ensureAgendaTables($pdo);
    $publicId = trim((string) ($_GET['p'] ?? ''));
    $date = trim((string) ($_GET['date'] ?? ''));
    $stmt = str_starts_with($publicId, 'id-')
        ? $pdo->prepare('SELECT id FROM profissionais WHERE id = :id LIMIT 1')
        : $pdo->prepare('SELECT id FROM profissionais WHERE public_id = :p LIMIT 1');
    $stmt->execute(str_starts_with($publicId, 'id-') ? [':id' => (int) substr($publicId, 3)] : [':p' => $publicId]);
    $id = (int) $stmt->fetchColumn();
    if (!$id) throw new RuntimeException('Profissional não encontrado.');
    echo json_encode(['ok' => true, 'slots' => agendaSlots($pdo, $id, $date)], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Não foi possível consultar a agenda.'], JSON_UNESCAPED_UNICODE);
}
