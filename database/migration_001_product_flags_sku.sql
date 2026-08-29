-- ============================================================
-- Jo Modas - Migracao 001
-- Acrescenta a products: sku, featured, is_new, best_seller
--
-- Use este arquivo quando o banco JA TEM DADOS que voce quer manter.
-- Num banco novo, nao rode nada disto: o schema.sql atual ja cria as
-- colunas, e importar os dois causaria erro de coluna duplicada.
--
-- RODE UMA VEZ SO. O MySQL nao aceita ADD COLUMN IF NOT EXISTS, entao
-- executar de novo devolve "Duplicate column name", o que e inofensivo
-- mas indica que a migracao ja tinha sido aplicada.
--
-- Confira antes com:
--   SHOW COLUMNS FROM `products` LIKE 'sku';
-- Se a consulta devolver uma linha, a migracao ja foi feita.
-- ============================================================

SET NAMES utf8mb4;

ALTER TABLE `products`
  ADD COLUMN `sku`         VARCHAR(60) NULL DEFAULT NULL              AFTER `slug`,
  ADD COLUMN `featured`    TINYINT(1) UNSIGNED NOT NULL DEFAULT 0     AFTER `active`,
  ADD COLUMN `is_new`      TINYINT(1) UNSIGNED NOT NULL DEFAULT 0     AFTER `featured`,
  ADD COLUMN `best_seller` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0     AFTER `is_new`,
  ADD UNIQUE KEY `uq_products_sku` (`sku`),
  ADD KEY `ix_products_flags` (`active`, `featured`, `is_new`, `best_seller`);

-- Conferencia: deve listar sku, featured, is_new e best_seller.
-- SHOW COLUMNS FROM `products`;
