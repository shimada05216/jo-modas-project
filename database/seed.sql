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
INSERT INTO `admin_users` (`name`, `email`, `password_hash`, `active`) VALUES
('Administrador', 'admin@jomodas.com.br', 'DEFINIR_HASH', 1);

-- ------------------------------------------------------------
-- Configuracoes da loja
-- ------------------------------------------------------------
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('store_name',      'Jo Modas'),
('whatsapp_number', '5511999999999');

-- ------------------------------------------------------------
-- Cores
-- ------------------------------------------------------------
INSERT INTO `colors` (`name`, `hex`) VALUES
('Preto',    '#000000'),
('Branco',   '#FFFFFF'),
('Vermelho', '#D32F2F'),
('Azul',     '#1976D2'),
('Rosa',     '#EC407A'),
('Verde',    '#388E3C'),
('Bege',     '#D7C4A3'),
('Cinza',    '#9E9E9E');

-- ------------------------------------------------------------
-- Tamanhos
-- ------------------------------------------------------------
INSERT INTO `sizes` (`name`, `sort_order`) VALUES
('PP', 1),
('P',  2),
('M',  3),
('G',  4),
('GG', 5),
('U',  6);

-- ------------------------------------------------------------
-- Categorias de exemplo
-- ------------------------------------------------------------
INSERT INTO `categories` (`name`, `slug`, `description`, `active`, `sort_order`) VALUES
('Vestidos',    'vestidos',    'Vestidos casuais e de festa', 1, 1),
('Blusas',      'blusas',      'Blusas e camisetas',          1, 2),
('Calcas',      'calcas',      'Calcas jeans e alfaiataria',  1, 3),
('Saias',       'saias',       'Saias curtas e longas',       1, 4),
('Acessorios',  'acessorios',  'Bolsas, cintos e bijuterias', 1, 5);
