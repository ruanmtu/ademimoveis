-- Só para quem JÁ importou o schema.sql antes desta versão.
-- Selecione o banco "ademoveis" no phpMyAdmin e importe este arquivo.
CREATE TABLE IF NOT EXISTS imovel_comodidades (
  imovel_id    INT UNSIGNED NOT NULL,
  comodidade   VARCHAR(40)  NOT NULL,
  PRIMARY KEY (imovel_id, comodidade),
  KEY idx_comodidade (comodidade),
  CONSTRAINT fk_comodidades_imovel FOREIGN KEY (imovel_id) REFERENCES imoveis(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
