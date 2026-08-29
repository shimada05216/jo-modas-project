-- ============================================================
-- Jo Modas - Dados iniciais
-- Importar SEMPRE depois de schema.sql
-- ============================================================

SET NAMES utf8mb4;

-- ------------------------------------------------------------
-- Usuario administrador
-- ATENCAO: o hash abaixo e um marcador temporario.
-- Abra test_connection.php no navegador, copie o comando UPDATE
-- gerado por ele e execute-o para definir a senha real.
-- ------------------------------------------------------------
INSERT INTO `admins` (`name`, `email`, `password_hash`, `active`) VALUES
('Administrador', 'admin@jomodas.com.br', 'DEFINIR_HASH', 1);

-- ------------------------------------------------------------
-- Configuracoes da loja
-- whatsapp_number: somente digitos, com codigo do pais (55) e DDD.
-- ------------------------------------------------------------
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('store_name',      'Jo Modas'),
('whatsapp_number', '5511999999999');

-- ------------------------------------------------------------
-- Categorias de exemplo
-- Alimentam o menu publico enquanto o painel nao tem cadastro.
-- ------------------------------------------------------------
INSERT INTO `categories` (`name`, `slug`, `description`, `active`, `sort_order`) VALUES
('Vestidos',    'vestidos',    'Vestidos casuais e de festa', 1, 1),
('Blusas',      'blusas',      'Blusas e camisetas',          1, 2),
('Calcas',      'calcas',      'Calcas jeans e alfaiataria',  1, 3),
('Saias',       'saias',       'Saias curtas e longas',       1, 4),
('Acessorios',  'acessorios',  'Bolsas, cintos e bijuterias', 1, 5);

-- ------------------------------------------------------------
-- Cores e tamanhos
--
-- Nao existem mais tabelas proprias: cor e tamanho sao colunas de
-- product_variants, preenchidas pelo painel a cada variacao.
-- Valores sugeridos, para manter a grafia padronizada:
--
--   cores    Preto #000000, Branco #FFFFFF, Vermelho #D32F2F,
--            Azul #1976D2, Rosa #EC407A, Verde #388E3C,
--            Bege #D7C4A3, Cinza #9E9E9E
--
--   tamanhos PP (size_order 1), P (2), M (3), G (4), GG (5), U (6)
-- ------------------------------------------------------------
