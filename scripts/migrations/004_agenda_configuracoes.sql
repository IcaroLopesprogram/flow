CREATE TABLE IF NOT EXISTS agenda_configuracoes (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
