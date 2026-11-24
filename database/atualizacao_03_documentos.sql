-- Só para quem JÁ tinha o banco antes desta versão (CPF/CNPJ e verificação de CRECI).
-- No phpMyAdmin, selecione o banco e importe este arquivo.
ALTER TABLE usuarios
  ADD COLUMN documento_tipo ENUM('cpf','cnpj') NULL AFTER whatsapp,
  ADD COLUMN documento VARCHAR(14) NULL AFTER documento_tipo,
  ADD COLUMN creci_uf CHAR(2) NULL AFTER creci,
  ADD COLUMN creci_status ENUM('nao_informado','pendente','verificado','recusado') NOT NULL DEFAULT 'nao_informado' AFTER creci_uf,
  ADD COLUMN creci_verificado_em DATETIME NULL AFTER creci_status,
  ADD UNIQUE KEY uk_usuarios_documento (documento),
  ADD KEY idx_usuarios_creci (creci_status);

-- Quem já tinha CRECI cadastrado fica aguardando verificação do admin
UPDATE usuarios SET creci_status = 'pendente' WHERE creci IS NOT NULL AND creci <> '';
