CREATE TABLE IF NOT EXISTS agendamentos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    profissional_id INT NOT NULL,
    cliente_nome VARCHAR(100) NOT NULL,
    cliente_telefone VARCHAR(25) NOT NULL,
    cliente_email VARCHAR(190) NULL,
    observacao VARCHAR(500) NULL,
    inicio DATETIME NOT NULL,
    fim DATETIME NOT NULL,
    status ENUM('confirmado','cancelado') NOT NULL DEFAULT 'confirmado',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_agendamentos_profissional_inicio (profissional_id, inicio),
    UNIQUE KEY ux_agendamentos_profissional_inicio (profissional_id, inicio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
