-- =========================================================
--  Ademimóveis - Estrutura do banco de dados (MySQL / MariaDB)
--  Importe este arquivo pelo phpMyAdmin (aba "Importar").
-- =========================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS ademoveis CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ademoveis;

-- ---------------------------------------------------------
-- Planos de assinatura dos anunciantes (valor editável no painel admin)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS planos (
  id                   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nome                 VARCHAR(80)   NOT NULL,
  descricao            VARCHAR(255)  NULL,
  preco                DECIMAL(10,2) NOT NULL DEFAULT 0,      -- valor cobrado a cada período
  periodicidade_meses  TINYINT UNSIGNED NOT NULL DEFAULT 1,   -- 1 mensal, 3 trimestral, 6 semestral, 12 anual
  limite_imoveis       INT UNSIGNED  NOT NULL DEFAULT 0,      -- 0 = ilimitado
  limite_fotos         INT UNSIGNED  NOT NULL DEFAULT 20,
  destaques            INT UNSIGNED  NOT NULL DEFAULT 0,      -- imóveis que o anunciante pode destacar
  recursos             TEXT          NULL,                    -- benefícios extras, um por linha
  recomendado          TINYINT(1)    NOT NULL DEFAULT 0,
  ordem                INT           NOT NULL DEFAULT 0,
  ativo                TINYINT(1)    NOT NULL DEFAULT 1,
  criado_em            DATETIME      NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Cupons de desconto na mensalidade
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS cupons (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo          VARCHAR(40)   NOT NULL,
  descricao       VARCHAR(255)  NULL,
  tipo            ENUM('percentual','valor') NOT NULL DEFAULT 'percentual',
  valor           DECIMAL(10,2) NOT NULL,
  duracao         ENUM('primeira','cobrancas','sempre') NOT NULL DEFAULT 'primeira',
  duracao_cobrancas INT UNSIGNED NULL,          -- usado quando duracao = 'cobrancas'
  plano_id        INT UNSIGNED  NULL,           -- NULL = vale para todos os planos
  max_usos        INT UNSIGNED  NULL,           -- NULL = sem limite
  usos            INT UNSIGNED  NOT NULL DEFAULT 0,
  valido_ate      DATE          NULL,
  ativo           TINYINT(1)    NOT NULL DEFAULT 1,
  criado_em       DATETIME      NOT NULL,
  UNIQUE KEY uk_cupons_codigo (codigo),
  CONSTRAINT fk_cupons_plano FOREIGN KEY (plano_id) REFERENCES planos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Usuários (um login só: cliente, anunciante ou admin)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tipo                ENUM('cliente','anunciante','admin') NOT NULL DEFAULT 'cliente',
  nome                VARCHAR(120) NOT NULL,
  email               VARCHAR(190) NOT NULL,
  senha_hash          VARCHAR(255) NOT NULL,
  telefone            VARCHAR(30)  NULL,
  whatsapp            VARCHAR(30)  NULL,
  documento_tipo      ENUM('cpf','cnpj') NULL,
  documento           VARCHAR(14)  NULL,     -- só dígitos/letras, sem pontuação
  creci               VARCHAR(30)  NULL,     -- ex.: 12345-F
  creci_uf            CHAR(2)      NULL,
  creci_status        ENUM('nao_informado','pendente','verificado','recusado') NOT NULL DEFAULT 'nao_informado',
  creci_verificado_em DATETIME     NULL,
  cidade              VARCHAR(120) NULL,
  estado              CHAR(2)      NULL,
  bio                 TEXT         NULL,
  foto                VARCHAR(255) NULL,
  status              ENUM('ativo','bloqueado') NOT NULL DEFAULT 'ativo',
  -- assinatura (somente anunciantes)
  plano_id            INT UNSIGNED NULL,
  assinatura_status   ENUM('inativa','pendente','ativa','cancelada') NOT NULL DEFAULT 'inativa',
  assinatura_ate      DATE         NULL,     -- NULL com status 'ativa' = liberado sem prazo (ativação manual)
  mp_preapproval_id   VARCHAR(80)  NULL,
  mp_preapproval_novo VARCHAR(80)  NULL,     -- troca de plano aguardando confirmação
  cupom_id            INT UNSIGNED NULL,     -- cupom em uso na assinatura atual
  cupom_cobrancas_restantes INT UNSIGNED NULL,
  ultimo_login        DATETIME     NULL,
  criado_em           DATETIME     NOT NULL,
  UNIQUE KEY uk_usuarios_email (email),
  UNIQUE KEY uk_usuarios_documento (documento),
  KEY idx_usuarios_creci (creci_status),
  KEY idx_usuarios_tipo (tipo),
  KEY idx_usuarios_mp (mp_preapproval_id),
  CONSTRAINT fk_usuarios_plano FOREIGN KEY (plano_id) REFERENCES planos(id) ON DELETE SET NULL,
  CONSTRAINT fk_usuarios_cupom FOREIGN KEY (cupom_id) REFERENCES cupons(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Imóveis
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS imoveis (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  anunciante_id   INT UNSIGNED NOT NULL,
  titulo          VARCHAR(150) NOT NULL,
  descricao       TEXT         NULL,
  finalidade      ENUM('venda','aluguel','temporada') NOT NULL,
  tipo            ENUM('casa','apartamento','terreno','comercial','rural','outro') NOT NULL,
  preco           DECIMAL(12,2) NOT NULL DEFAULT 0,
  condominio      DECIMAL(10,2) NULL,
  iptu            DECIMAL(10,2) NULL,
  area            DECIMAL(10,2) NULL,
  quartos         TINYINT UNSIGNED NOT NULL DEFAULT 0,
  banheiros       TINYINT UNSIGNED NOT NULL DEFAULT 0,
  vagas           TINYINT UNSIGNED NOT NULL DEFAULT 0,
  cep             VARCHAR(10)  NULL,
  endereco        VARCHAR(200) NULL,
  bairro          VARCHAR(120) NULL,
  cidade          VARCHAR(120) NOT NULL,
  estado          CHAR(2)      NOT NULL,
  status          ENUM('ativo','pausado','removido') NOT NULL DEFAULT 'ativo',
  destaque        TINYINT(1)   NOT NULL DEFAULT 0,
  visualizacoes   INT UNSIGNED NOT NULL DEFAULT 0,
  criado_em       DATETIME     NOT NULL,
  atualizado_em   DATETIME     NOT NULL,
  KEY idx_imoveis_busca (status, finalidade, tipo, cidade),
  KEY idx_imoveis_preco (preco),
  KEY idx_imoveis_anunciante (anunciante_id),
  CONSTRAINT fk_imoveis_anunciante FOREIGN KEY (anunciante_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS imovel_fotos (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  imovel_id   INT UNSIGNED NOT NULL,
  arquivo     VARCHAR(255) NOT NULL,
  ordem       INT          NOT NULL DEFAULT 0,
  criado_em   DATETIME     NOT NULL,
  KEY idx_fotos_imovel (imovel_id, ordem),
  CONSTRAINT fk_fotos_imovel FOREIGN KEY (imovel_id) REFERENCES imoveis(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Comodidades do imóvel (piscina, aceita pets, varanda...)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS imovel_comodidades (
  imovel_id    INT UNSIGNED NOT NULL,
  comodidade   VARCHAR(40)  NOT NULL,
  PRIMARY KEY (imovel_id, comodidade),
  KEY idx_comodidade (comodidade),
  CONSTRAINT fk_comodidades_imovel FOREIGN KEY (imovel_id) REFERENCES imoveis(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Chat (interessado <-> anunciante)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS conversas (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  interessado_id  INT UNSIGNED NOT NULL,
  anunciante_id   INT UNSIGNED NOT NULL,
  imovel_id       INT UNSIGNED NULL,
  criado_em       DATETIME NOT NULL,
  atualizado_em   DATETIME NOT NULL,
  KEY idx_conversas_interessado (interessado_id, atualizado_em),
  KEY idx_conversas_anunciante (anunciante_id, atualizado_em),
  CONSTRAINT fk_conversas_interessado FOREIGN KEY (interessado_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_conversas_anunciante  FOREIGN KEY (anunciante_id)  REFERENCES usuarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_conversas_imovel      FOREIGN KEY (imovel_id)      REFERENCES imoveis(id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mensagens (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  conversa_id   INT UNSIGNED NOT NULL,
  remetente_id  INT UNSIGNED NOT NULL,
  texto         TEXT NOT NULL,
  lida          TINYINT(1) NOT NULL DEFAULT 0,
  criado_em     DATETIME NOT NULL,
  KEY idx_mensagens_conversa (conversa_id, id),
  KEY idx_mensagens_lida (lida),
  CONSTRAINT fk_mensagens_conversa  FOREIGN KEY (conversa_id)  REFERENCES conversas(id) ON DELETE CASCADE,
  CONSTRAINT fk_mensagens_remetente FOREIGN KEY (remetente_id) REFERENCES usuarios(id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Avaliações do anunciante (1 por cliente por anunciante)
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS avaliacoes (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  anunciante_id   INT UNSIGNED NOT NULL,
  autor_id        INT UNSIGNED NOT NULL,
  nota            TINYINT UNSIGNED NOT NULL,
  comentario      TEXT NULL,
  status          ENUM('visivel','oculta') NOT NULL DEFAULT 'visivel',
  criado_em       DATETIME NOT NULL,
  UNIQUE KEY uk_avaliacao (anunciante_id, autor_id),
  CONSTRAINT fk_avaliacoes_anunciante FOREIGN KEY (anunciante_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_avaliacoes_autor      FOREIGN KEY (autor_id)      REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Pagamentos / eventos do Mercado Pago
-- ---------------------------------------------------------
CREATE TABLE IF NOT EXISTS pagamentos (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  usuario_id    INT UNSIGNED NULL,
  mp_id         VARCHAR(80)  NULL,
  tipo          VARCHAR(60)  NOT NULL,
  status        VARCHAR(40)  NULL,
  valor         DECIMAL(10,2) NULL,
  payload       MEDIUMTEXT   NULL,
  criado_em     DATETIME     NOT NULL,
  KEY idx_pagamentos_usuario (usuario_id),
  CONSTRAINT fk_pagamentos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------
-- Uso de cupons (cada anunciante usa um cupom uma vez)
-- ---------------------------------------------------------
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

-- ---------------------------------------------------------
-- Recuperação de senha (link de uso único, guardado só como hash)
-- ---------------------------------------------------------
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

-- Eventos de segurança (login, limites anti-abuso, troca de senha)
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

SET FOREIGN_KEY_CHECKS = 1;

-- Planos de exemplo (altere nomes, valores e limites em Admin → Planos)
INSERT INTO planos (nome, descricao, preco, periodicidade_meses, limite_imoveis, limite_fotos, destaques, recursos, recomendado, ordem, ativo, criado_em) VALUES
('Básico', 'Para quem está começando', 49.90, 1, 10, 15, 0, 'Chat com interessados\nPerfil com avaliações', 0, 1, 1, NOW()),
('Profissional', 'Para corretores ativos', 89.90, 1, 50, 25, 3, 'Chat com interessados\nPerfil com avaliações\nSelo de imóvel em destaque', 1, 2, 1, NOW()),
('Premium', 'Para imobiliárias', 149.90, 1, 0, 30, 10, 'Chat com interessados\nPerfil com avaliações\nSelo de imóvel em destaque\nImóveis ilimitados', 0, 3, 1, NOW());
