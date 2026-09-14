<?php

/** Recursos compartilhados do agendamento público. */
function ensureAgendaTables(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS agendamentos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            profissional_id INT NOT NULL,
            cliente_nome VARCHAR(100) NOT NULL,
            cliente_telefone VARCHAR(25) NOT NULL,
            cliente_email VARCHAR(190) NULL,
            observacao VARCHAR(500) NULL,
            inicio DATETIME NOT NULL,
            fim DATETIME NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'confirmado',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_agendamentos_profissional_inicio (profissional_id, inicio),
            UNIQUE KEY ux_agendamentos_profissional_inicio (profissional_id, inicio),
            INDEX idx_agendamentos_inicio (inicio)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $index = $pdo->query("SHOW INDEX FROM agendamentos WHERE Key_name = 'ux_agendamentos_profissional_inicio'");
    if ($index !== false && !$index->fetch()) {
        $pdo->exec('ALTER TABLE agendamentos ADD UNIQUE KEY ux_agendamentos_profissional_inicio (profissional_id, inicio)');
    }
    $statusColumn = $pdo->query("SHOW COLUMNS FROM agendamentos LIKE 'status'");
    $statusInfo = $statusColumn !== false ? $statusColumn->fetch(PDO::FETCH_ASSOC) : false;
    if (is_array($statusInfo) && str_starts_with(strtolower((string) ($statusInfo['Type'] ?? '')), 'enum(')) {
        $pdo->exec("ALTER TABLE agendamentos MODIFY status VARCHAR(20) NOT NULL DEFAULT 'confirmado'");
    }
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS agenda_configuracoes (
            profissional_id INT NOT NULL PRIMARY KEY,
            agenda_ativa TINYINT(1) NOT NULL DEFAULT 1,
            duracao_minutos SMALLINT NOT NULL DEFAULT 60,
            intervalo_minutos SMALLINT NOT NULL DEFAULT 15,
            antecedencia_minutos SMALLINT NOT NULL DEFAULT 120,
            confirmacao_manual TINYINT(1) NOT NULL DEFAULT 0,
            limite_diario SMALLINT NOT NULL DEFAULT 0,
            horarios_json TEXT NOT NULL,
            atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            CONSTRAINT fk_agenda_config_profissional FOREIGN KEY (profissional_id) REFERENCES profissionais(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function agendaDefaultHours(): array
{
    return [
        '1' => ['active' => true, 'start' => '07:00', 'end' => '18:00'],
        '2' => ['active' => true, 'start' => '07:00', 'end' => '18:00'],
        '3' => ['active' => true, 'start' => '07:00', 'end' => '18:00'],
        '4' => ['active' => true, 'start' => '07:00', 'end' => '18:00'],
        '5' => ['active' => true, 'start' => '07:00', 'end' => '18:00'],
        '6' => ['active' => true, 'start' => '08:00', 'end' => '12:00'],
        '7' => ['active' => false, 'start' => '07:00', 'end' => '18:00'],
    ];
}

function agendaGetConfig(PDO $pdo, int $professionalId): array
{
    $stmt = $pdo->prepare('SELECT * FROM agenda_configuracoes WHERE profissional_id = :id LIMIT 1');
    $stmt->execute([':id' => $professionalId]);
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$config) {
        $hours = agendaDefaultHours();
        $insert = $pdo->prepare('INSERT INTO agenda_configuracoes (profissional_id, horarios_json) VALUES (:id, :hours)');
        $insert->execute([':id' => $professionalId, ':hours' => json_encode($hours, JSON_UNESCAPED_UNICODE)]);
        $config = ['agenda_ativa' => 1, 'duracao_minutos' => 60, 'intervalo_minutos' => 15, 'antecedencia_minutos' => 120, 'confirmacao_manual' => 0, 'limite_diario' => 0, 'horarios_json' => json_encode($hours)];
    }
    $hours = json_decode((string) ($config['horarios_json'] ?? ''), true);
    $config['hours'] = is_array($hours) ? array_replace(agendaDefaultHours(), $hours) : agendaDefaultHours();
    return $config;
}

function agendaSlots(PDO $pdo, int $professionalId, string $date): array
{
    $day = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$day || ($errors !== false && ($errors['warning_count'] || $errors['error_count']))) {
        return [];
    }
    $today = new DateTimeImmutable('today');
    if ($day < $today || $day > $today->modify('+60 days')) return [];

    $config = agendaGetConfig($pdo, $professionalId);
    if ((int) ($config['agenda_ativa'] ?? 0) !== 1) return [];
    $weekday = (int) $day->format('N');
    $dayConfig = $config['hours'][(string) $weekday] ?? null;
    if (!is_array($dayConfig) || empty($dayConfig['active'])) return [];
    $start = (string) ($dayConfig['start'] ?? '07:00');
    $end = (string) ($dayConfig['end'] ?? '18:00');
    $duration = max(15, min(480, (int) ($config['duracao_minutos'] ?? 60)));
    $interval = max(0, min(120, (int) ($config['intervalo_minutos'] ?? 0)));
    $leadMinutes = max(0, min(10080, (int) ($config['antecedencia_minutos'] ?? 0)));
    $from = $day->format('Y-m-d') . ' 00:00:00';
    $until = $day->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
    $stmt = $pdo->prepare("SELECT inicio, fim FROM agendamentos WHERE profissional_id = :id AND status IN ('confirmado', 'pendente') AND inicio >= :from AND inicio < :until");
    $stmt->execute([':id' => $professionalId, ':from' => $from, ':until' => $until]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $dailyLimit = max(0, (int) ($config['limite_diario'] ?? 0));
    if ($dailyLimit > 0 && count($bookings) >= $dailyLimit) return [];

    $slots = [];
    $cursor = new DateTimeImmutable($day->format('Y-m-d') . ' ' . $start);
    $limit = new DateTimeImmutable($day->format('Y-m-d') . ' ' . $end);
    $now = new DateTimeImmutable();
    while ($cursor < $limit) {
        $time = $cursor->format('H:i');
        $slotEnd = $cursor->modify('+' . $duration . ' minutes');
        $overlaps = false;
        foreach ($bookings as $booking) {
            $bookingStart = new DateTimeImmutable((string) $booking['inicio']);
            $bookingEnd = new DateTimeImmutable((string) $booking['fim']);
            if ($cursor < $bookingEnd && $slotEnd > $bookingStart) { $overlaps = true; break; }
        }
        if ($slotEnd <= $limit && $cursor >= $now->modify('+' . $leadMinutes . ' minutes') && !$overlaps) $slots[] = $time;
        $cursor = $cursor->modify('+' . max(15, $duration + $interval) . ' minutes');
    }
    return $slots;
}
