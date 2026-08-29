-- ============================================================
-- Jo Modas - Migracao 003
-- Cria login_attempts, usada para travar ataques de forca bruta
-- contra o painel.
--
-- Use este arquivo quando o banco JA TEM DADOS que voce quer manter.
-- Num banco novo o schema.sql atual ja cria a tabela.
--
-- RODE UMA VEZ SO. Confira antes com:
--   SHOW TABLES LIKE 'login_attempts';
-- ============================================================

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS `login_attempts` (
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
