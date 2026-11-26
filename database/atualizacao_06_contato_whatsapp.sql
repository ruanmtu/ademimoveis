-- Escolha do anunciante: mostrar o WhatsApp no anúncio (1) ou receber contato só pelo chat (0).
-- O site cria esta coluna sozinho na primeira vez que precisar; importar este arquivo no phpMyAdmin é opcional.
ALTER TABLE imoveis ADD COLUMN contato_whatsapp TINYINT(1) NOT NULL DEFAULT 1 AFTER destaque;
