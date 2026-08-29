-- ============================================================
-- Jo Modas - Migracao 002
-- Acrescenta product_variants.active
--
-- Use este arquivo quando o banco JA TEM DADOS que voce quer manter.
-- Num banco novo nao rode nada disto: o schema.sql atual ja cria a
-- coluna, e importar os dois causaria erro de coluna duplicada.
--
-- RODE UMA VEZ SO. Confira antes com:
--   SHOW COLUMNS FROM `product_variants` LIKE 'active';
-- Se a consulta devolver uma linha, a migracao ja foi aplicada.
--
-- DEFAULT 1: as variacoes que ja existem continuam ativas.
-- ============================================================

SET NAMES utf8mb4;

ALTER TABLE `product_variants`
  ADD COLUMN `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 AFTER `stock`;

-- Conferencia: deve listar a coluna active.
-- SHOW COLUMNS FROM `product_variants`;
