<?php
require_once __DIR__ . '/../secure/auth.php';
require_once __DIR__ . '/../secure/config.php';
require_once __DIR__ . '/../secure/agenda.php';
startSecureSession();
header('Content-Type: application/json; charset=utf-8');

function agendaReply(bool $ok, string $message, int $status = 200): void { http_response_code($status); echo json_encode(['ok' => $ok, 'message' => $message], JSON_UNESCAPED_UNICODE); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrfTokenOrFail($_POST['csrf'] ?? null)) agendaReply(false, 'Solicitação inválida. Atualize a página e tente novamente.', 403);
try {
    $name = trim((string) ($_POST['name'] ?? ''));
    $phone = preg_replace('/\D+/', '', (string) ($_POST['phone'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $note = trim((string) ($_POST['note'] ?? ''));
    $date = trim((string) ($_POST['date'] ?? ''));
    $time = trim((string) ($_POST['time'] ?? ''));
    $publicId = trim((string) ($_POST['p'] ?? ''));
    if (mb_strlen($name) < 2 || strlen($phone) < 10 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $time) || !(preg_match('/^[a-f0-9]{32}$/', $publicId) || preg_match('/^id-\d+$/', $publicId))) agendaReply(false, 'Preencha nome, telefone, data e horário corretamente.', 422);
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) agendaReply(false, 'Informe um e-mail válido ou deixe o campo vazio.', 422);
    $cfg = appConfig();
    $pdo = new PDO("mysql:host={$cfg['db_host']};dbname={$cfg['db_name']};charset={$cfg['db_charset']}", $cfg['db_user'], $cfg['db_pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
    ensureAgendaTables($pdo);
    $stmt = str_starts_with($publicId, 'id-') ? $pdo->prepare('SELECT id FROM profissionais WHERE id = :id LIMIT 1') : $pdo->prepare('SELECT id FROM profissionais WHERE public_id = :p LIMIT 1');
    $stmt->execute(str_starts_with($publicId, 'id-') ? [':id' => (int) substr($publicId, 3)] : [':p' => $publicId]); $professionalId = (int) $stmt->fetchColumn();
    if (!$professionalId || !in_array($time, agendaSlots($pdo, $professionalId, $date), true)) agendaReply(false, 'Esse horário acabou de ficar indisponível. Escolha outro.', 409);
    $agendaConfig = agendaGetConfig($pdo, $professionalId);
    $bookingStatus = !empty($agendaConfig['confirmacao_manual']) ? 'pendente' : 'confirmado';
    $start = $date . ' ' . $time . ':00';
    $end = (new DateTimeImmutable($start))->modify('+1 hour')->format('Y-m-d H:i:s');
    $insert = $pdo->prepare('INSERT INTO agendamentos (profissional_id, cliente_nome, cliente_telefone, cliente_email, observacao, inicio, fim, status) VALUES (:professional_id, :name, :phone, :email, :note, :start, :end, :status)');
    $insert->execute([':professional_id' => $professionalId, ':name' => mb_substr($name, 0, 100), ':phone' => substr($phone, 0, 25), ':email' => $email !== '' ? mb_substr($email, 0, 190) : null, ':note' => $note !== '' ? mb_substr($note, 0, 500) : null, ':start' => $start, ':end' => $end, ':status' => $bookingStatus]);
    agendaReply(true, $bookingStatus === 'pendente' ? 'Solicitação enviada! O profissional confirmará o horário em breve.' : 'Horário agendado com sucesso! O profissional receberá seus dados.');
} catch (PDOException $e) {
    if ((string) $e->getCode() === '23000') agendaReply(false, 'Esse horário acabou de ficar indisponível. Escolha outro.', 409);
    agendaReply(false, 'Não foi possível concluir o agendamento. Tente novamente.', 500);
} catch (Throwable $e) { agendaReply(false, 'Não foi possível concluir o agendamento. Tente novamente.', 500); }
