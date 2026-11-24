-- Só para quem JÁ tinha o banco antes desta versão.
-- No phpMyAdmin, selecione o banco "ademoveis" e importe este arquivo.
ALTER TABLE planos
  ADD COLUMN periodicidade_meses TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER preco,
  ADD COLUMN limite_fotos INT UNSIGNED NOT NULL DEFAULT 20 AFTER limite_imoveis,
  ADD COLUMN destaques INT UNSIGNED NOT NULL DEFAULT 0 AFTER limite_fotos,
  ADD COLUMN recursos TEXT NULL AFTER destaques,
  ADD COLUMN recomendado TINYINT(1) NOT NULL DEFAULT 0 AFTER recursos,
  ADD COLUMN ordem INT NOT NULL DEFAULT 0 AFTER recomendado;

CREATE TABLE IF NOT EXISTS cupons (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo          VARCHAR(40)   NOT NULL,
  descricao       VARCHAR(255)  NULL,
  tipo            ENUM('percentual','valor') NOT NULL DEFAULT 'percentual',
  valor           DECIMAL(10,2) NOT NULL,
  duracao         ENUM('primeira','cobrancas','sempre') NOT NULL DEFAULT 'primeira',
  duracao_cobrancas INT UNSIGNED NULL,
  plano_id        INT UNSIGNED  NULL,
  max_usos        INT UNSIGNED  NULL,
  usos            INT UNSIGNED  NOT NULL DEFAULT 0,
  valido_ate      DATE          NULL,
  ativo           TINYINT(1)    NOT NULL DEFAULT 1,
  criado_em       DATETIME      NOT NULL,
  UNIQUE KEY uk_cupons_codigo (codigo),
  CONSTRAINT fk_cupons_plano FOREIGN KEY (plano_id) REFERENCES planos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE usuarios
  ADD COLUMN mp_preapproval_novo VARCHAR(80) NULL AFTER mp_preapproval_id,
  ADD COLUMN cupom_id INT UNSIGNED NULL AFTER mp_preapproval_novo,
  ADD COLUMN cupom_cobrancas_restantes INT UNSIGNED NULL AFTER cupom_id,
  ADD CONSTRAINT fk_usuarios_cupom FOREIGN KEY (cupom_id) REFERENCES cupons(id) ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS cupom_usos (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cupom_id     INT UNSIGNED NOT NULL,
  usuario_id   INT UNSIGNED NOT NULL,
  plano_id     INT UNSIGNED NULL,
  valor_original DECIMAL(10,2) NULL,
  valor_com_desconto DECIMAL(10,2) NULL,
  criado_em    DATETIME NOT NULL,
  UNIQUE KEY uk_cupom_usuario (cupom_id, usuario_id),
  CONSTRAINT fk_usos_cupom   FOREIGN KEY (cupom_id)   REFERENCES cupons(id)   ON DELETE CASCADE,
  CONSTRAINT fk_usos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
