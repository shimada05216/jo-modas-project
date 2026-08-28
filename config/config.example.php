<?php
/**
 * Jo Modas - Configuracao do ambiente
 *
 * Copie este arquivo para config/config.php e ajuste os valores.
 * O arquivo config.php NAO deve ser versionado.
 */

// ---------------------------------------------------------
// Banco de dados
// ---------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'jo_modas');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ---------------------------------------------------------
// URLs e caminhos
// BASE_URL: endereco publico da loja, SEM barra no final.
//   Local  : http://localhost/jo-modas
//   Hostinger: https://www.seudominio.com.br
// ---------------------------------------------------------
define('BASE_URL', 'http://localhost/jo-modas');
define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_PATH', ROOT_PATH . '/uploads/products');
define('UPLOAD_URL', BASE_URL . '/uploads/products');

// ---------------------------------------------------------
// Upload de imagens
// ---------------------------------------------------------
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5 MB por imagem

// ---------------------------------------------------------
// Ambiente
// Em producao use false: os erros deixam de aparecer na tela.
// ---------------------------------------------------------
define('DEBUG_MODE', true);

// ---------------------------------------------------------
// Fuso horario
// ---------------------------------------------------------
date_default_timezone_set('America/Sao_Paulo');

if (DEBUG_MODE) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
}
