-- ############################################################
-- ATENCAO: ESTE ARQUIVO APAGA TODOS OS DADOS
--
-- As linhas DROP TABLE abaixo destroem produtos, categorias,
-- imagens, variacoes, configuracoes e administradores.
--
-- Use apenas numa instalacao NOVA. Num banco que ja esta no ar,
-- aplique os arquivos migration_*.sql, que somam colunas sem
-- apagar nada.
--
-- Faca backup antes:
--   mysqldump -u USUARIO -p jo_modas > backup.sql
-- ############################################################

-- ============================================================
-- Jo Modas - Estrutura do banco de dados (MVP)
-- MySQL 8.0+ / MariaDB 10.4+
--
-- Ordem de importacao:
--   1. database/schema.sql  (este arquivo)
--   2. database/seed.sql
--
-- Convencoes adotadas:
--   - InnoDB e utf8mb4_unicode_ci em todas as tabelas;
--   - dinheiro sempre em DECIMAL(10,2), nunca FLOAT;
--   - o estoque pertence a variacao (cor + tamanho), nunca ao produto;
--   - toda tabela editavel pelo painel tem created_at e updated_at.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- Limpeza
-- O segundo bloco remove tabelas de versoes anteriores do schema,
-- para que a reimportacao funcione num banco ja existente.
-- ------------------------------------------------------------
DROP TABLE IF EXISTS `login_attempts`;
DROP TABLE IF EXISTS `product_variants`;
DROP TABLE IF EXISTS `product_images`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `admins`;
DROP TABLE IF EXISTS `settings`;

DROP TABLE IF EXISTS `product_variations`;
DROP TABLE IF EXISTS `admin_users`;
DROP TABLE IF EXISTS `colors`;
DROP TABLE IF EXISTS `sizes`;

-- ------------------------------------------------------------
-- admins
-- Usuarios do painel administrativo.
-- A senha e gravada como hash de password_hash(), nunca em texto puro.
-- ------------------------------------------------------------
CREATE TABLE `admins` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(100) NOT NULL,
  -- 190 caracteres: limite seguro para indice utf8mb4 em InnoDB antigo.
  `email`         VARCHAR(190) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `active`        TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  `created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_admins_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- categories
-- Alimenta o menu publico. O indice ix_categories_menu cobre
-- exatamente a consulta do menu:
--   WHERE active = 1 ORDER BY sort_order, name
-- ------------------------------------------------------------
CREATE TABLE `categories` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(100) NOT NULL,
  `slug`        VARCHAR(120) NOT NULL,
  `description` TEXT NULL,
  `active`      TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  `sort_order`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_slug` (`slug`),
  -- A collation _ci faz esta chave tratar Vestidos e vestidos como iguais.
  UNIQUE KEY `uq_categories_name` (`name`),
  KEY `ix_categories_menu` (`active`, `sort_order`, `name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- products
-- price e promo_price em DECIMAL(10,2): ate 99.999.999,99.
-- promo_price NULL significa produto sem promocao.
-- ------------------------------------------------------------
CREATE TABLE `products` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `category_id`       INT UNSIGNED NOT NULL,
  `name`              VARCHAR(150) NOT NULL,
  `slug`              VARCHAR(180) NOT NULL,
  -- Codigo do produto como um todo. O SKU de cada combinacao cor/tamanho
  -- fica em product_variants.sku; este aqui e o codigo "pai".
  `sku`               VARCHAR(60) NULL DEFAULT NULL,
  `short_description` VARCHAR(255) NULL,
  `description`       TEXT NULL,
  `price`             DECIMAL(10,2) NOT NULL,
  `promo_price`       DECIMAL(10,2) NULL DEFAULT NULL,
  `active`            TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  -- Marcadores usados pelas vitrines da home.
  `featured`          TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `is_new`            TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `best_seller`       TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_products_slug` (`slug`),
  -- SKU unico na loja. Varios NULL sao permitidos, entao produtos sem
  -- codigo nao colidem entre si.
  UNIQUE KEY `uq_products_sku` (`sku`),
  -- Vitrine da categoria: WHERE category_id = ? AND active = 1
  KEY `ix_products_category` (`category_id`, `active`),
  -- Home / novidades: WHERE active = 1 ORDER BY created_at DESC
  KEY `ix_products_active_recent` (`active`, `created_at`),
  -- Vitrines por marcador: WHERE active = 1 AND featured = 1
  KEY `ix_products_flags` (`active`, `featured`, `is_new`, `best_seller`),
  -- RESTRICT obriga o painel a mover os produtos antes de apagar a
  -- categoria, em vez de deixar produtos orfaos fora do menu.
  CONSTRAINT `fk_products_category`
    FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_products_price`
    CHECK (`price` >= 0),
  -- A promocao so vale se for menor que o preco cheio.
  CONSTRAINT `chk_products_promo_price`
    CHECK (`promo_price` IS NULL OR (`promo_price` >= 0 AND `promo_price` < `price`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- product_images
-- Varias imagens por produto, uma delas marcada como principal.
--
-- main_marker e uma coluna gerada: vale product_id quando is_main = 1
-- e NULL nos demais casos. Como um indice UNIQUE aceita varios NULL
-- mas nao valores repetidos, o proprio banco garante no maximo UMA
-- imagem principal por produto.
-- ------------------------------------------------------------
CREATE TABLE `product_images` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id`  INT UNSIGNED NOT NULL,
  `filename`    VARCHAR(255) NOT NULL,
  `is_main`     TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `sort_order`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `main_marker` INT UNSIGNED GENERATED ALWAYS AS (IF(`is_main` = 1, `product_id`, NULL)) STORED,
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_images_main` (`main_marker`),
  -- Evita gravar duas vezes o mesmo arquivo no mesmo produto.
  UNIQUE KEY `uq_product_images_file` (`product_id`, `filename`),
  KEY `ix_product_images_gallery` (`product_id`, `sort_order`, `id`),
  -- Apagar o produto apaga os registros das imagens. Remover os
  -- arquivos do disco continua sendo responsabilidade do PHP.
  --
  -- ON UPDATE RESTRICT, e nao CASCADE, por causa da coluna gerada acima:
  -- o MariaDB recusa uma coluna gerada que dependa de um campo com acao
  -- em cascata (erro 1901). Como products.id e AUTO_INCREMENT e nunca
  -- muda, o CASCADE na atualizacao nao fazia diferenca nenhuma.
  CONSTRAINT `fk_product_images_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
    ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- product_variants
-- A unidade vendavel: produto + cor + tamanho.
-- O estoque e o SKU vivem aqui, e nao em products.
--
-- stock e INT UNSIGNED, entao o banco recusa estoque negativo.
-- size_order define a ordem de exibicao (PP, P, M, G, GG), que a
-- ordem alfabetica do nome do tamanho nao produz corretamente.
-- ------------------------------------------------------------
CREATE TABLE `product_variants` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `product_id` INT UNSIGNED NOT NULL,
  `color`      VARCHAR(40) NOT NULL,
  `color_hex`  CHAR(7) NULL DEFAULT NULL,
  `size`       VARCHAR(20) NOT NULL,
  `size_order` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  `sku`        VARCHAR(60) NULL DEFAULT NULL,
  `stock`      INT UNSIGNED NOT NULL DEFAULT 0,
  -- Variacao desativada some da loja mesmo tendo estoque. Serve para
  -- tirar uma cor de linha sem apagar o historico da combinacao.
  `active`     TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  -- Uma unica linha por combinacao cor/tamanho dentro do produto.
  UNIQUE KEY `uq_variants_combo` (`product_id`, `color`, `size`),
  -- SKU unico na loja inteira. Varios NULL sao permitidos.
  UNIQUE KEY `uq_variants_sku` (`sku`),
  KEY `ix_variants_picker` (`product_id`, `size_order`, `size`),
  KEY `ix_variants_stock` (`product_id`, `stock`),
  CONSTRAINT `fk_variants_product`
    FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- settings
-- Configuracoes da loja em formato chave/valor.
-- Guarda, entre outras coisas, o numero de WhatsApp do checkout.
-- ------------------------------------------------------------
CREATE TABLE `settings` (
  `setting_key`   VARCHAR(60) NOT NULL,
  `setting_value` TEXT NULL,
  `updated_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- login_attempts
-- Registro das tentativas de login do painel. Sem isto nao ha como
-- perceber, nem travar, um ataque de forca bruta contra a senha.
-- Linhas antigas sao apagadas sozinhas a cada login bem-sucedido.
-- ------------------------------------------------------------
CREATE TABLE `login_attempts` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  -- 45 caracteres cobrem IPv6 por extenso.
  `ip`           VARCHAR(45) NOT NULL,
  `email`        VARCHAR(190) NOT NULL,
  `successful`   TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_attempts_ip` (`ip`, `attempted_at`),
  KEY `ix_attempts_email` (`email`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
