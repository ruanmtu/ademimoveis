-- Só para quem JÁ tinha o banco antes desta versão (recuperação de senha).
-- No phpMyAdmin, selecione o banco e importe este arquivo.
CREATE TABLE IF NOT EXISTS senha_resets (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id   INT UNSIGNED NOT NULL,
  token_hash   CHAR(64)     NOT NULL,
  expira_em    DATETIME     NOT NULL,
  usado_em     DATETIME     NULL,
  ip           VARCHAR(45)  NULL,
  criado_em    DATETIME     NOT NULL,
  UNIQUE KEY uk_senha_resets_token (token_hash),
  KEY idx_senha_resets_usuario (usuario_id, criado_em),
  CONSTRAINT fk_senha_resets_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
