-- ============================================================
-- Jo Modas - Migracao 004
-- Acrescenta products.show_without_stock
--
-- Use este arquivo num banco que JA TEM DADOS. Num banco novo o
-- schema.sql atual ja cria a coluna.
--
-- RODE UMA VEZ SO. Confira antes com:
--   SHOW COLUMNS FROM `products` LIKE 'show_without_stock';
--
-- Nao apaga nem recria nada: so acrescenta uma coluna.
-- ============================================================

SET NAMES utf8mb4;

ALTER TABLE `products`
  ADD COLUMN `show_without_stock` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `active`;

-- ------------------------------------------------------------
-- IMPORTANTE: preserva o comportamento atual da loja.
--
-- A partir desta versao, um produto sem variacao vendavel some das
-- listagens, a menos que este marcador esteja ligado. Os produtos que
-- ja existem sao marcados com 1 para que NENHUM deles desapareca da
-- loja no momento da atualizacao.
--
-- Produtos novos nascem com 0: enquanto nao tiverem estoque, ficam
-- fora da vitrine. O lojista liga o marcador quando quiser expor um
-- produto mesmo sem estoque cadastrado.
-- ------------------------------------------------------------
UPDATE `products` SET `show_without_stock` = 1;

-- Conferencia:
-- SELECT id, name, active, show_without_stock FROM `products`;
