-- Registro de eventos de segurança (tentativas de login, limites anti-abuso, trocas de senha).
-- O site cria esta tabela sozinho na primeira vez que precisar; importar este arquivo no phpMyAdmin é opcional.
CREATE TABLE IF NOT EXISTS seguranca_eventos (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  acao        VARCHAR(40)  NOT NULL,
  chave       CHAR(64)     NOT NULL,
  ip          VARCHAR(45)  NULL,
  usuario_id  INT UNSIGNED NULL,
  detalhe     VARCHAR(255) NULL,
  criado_em   DATETIME     NOT NULL,
  KEY idx_seg_chave (acao, chave, criado_em),
  KEY idx_seg_data (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
