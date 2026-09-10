<?php
/**
 * Jo Modas - Inicializacao da aplicacao
 *
 * Ponto de entrada único. Toda página, publica ou do painel, comeca com:
 *
 *   require_once __DIR__ . '/../includes/bootstrap.php';
 *
 * Ordem de carga, que não deve ser alterada:
 *   1. config/config.php    valores do ambiente
 *   2. includes/errors.php  tratadores de erro, antes de qualquer outra coisa
 *   3. config/database.php  funcao db()
 *   4. includes/functions.php  helpers
 *   5. includes/auth.php    sessao, CSRF e login
 *
 * A sessao NAO e iniciada aqui de proposito: as páginas publicas não
 * precisam de cookie de sessao (o carrinho vive no localStorage).
 * Quem precisa chama start_session(), que e sob demanda.
 */

// ---------------------------------------------------------
// 1. Configuracao do ambiente
// ---------------------------------------------------------

$configFile = dirname(__DIR__) . '/config/config.php';

if (!is_file($configFile)) {
    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">'
       . '<title>Configuracao ausente - Jo Modas</title></head><body>'
       . '<h1>Configuracao ausente</h1>'
       . '<p>Copie <code>config/config.example.php</code> para '
       . '<code>config/config.php</code> e ajuste as credenciais do banco.</p>'
       . '</body></html>';
    exit(1);
}

require_once $configFile;

// ---------------------------------------------------------
// 2. Conferencia das constantes obrigatorias
//
// Protege o caso de um config.php antigo, copiado antes de o
// config.example.php ganhar novas chaves.
// ---------------------------------------------------------

$missing = [];

foreach ([
    'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_CHARSET',
    'BASE_URL', 'ROOT_PATH', 'UPLOAD_PATH', 'UPLOAD_URL',
    'MAX_UPLOAD_SIZE', 'DEBUG_MODE',
] as $constant) {
    if (!defined($constant)) {
        $missing[] = $constant;
    }
}

if ($missing !== []) {
    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">'
       . '<title>Configuracao incompleta - Jo Modas</title></head><body>'
       . '<h1>Configuracao incompleta</h1>'
       . '<p>Faltam constantes em <code>config/config.php</code>: <strong>'
       . htmlspecialchars(implode(', ', $missing), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
       . '</strong></p><p>Compare o arquivo com <code>config/config.example.php</code>.</p>'
       . '</body></html>';
    exit(1);
}

unset($missing, $constant, $configFile);

// ---------------------------------------------------------
// Chaves opcionais
//
// Ficam com padrao aqui para que um config.php antigo, escrito antes
// de elas existirem, continue funcionando sem ser editado.
// ---------------------------------------------------------

if (!defined('REQUIRE_VARIANT_SELECTION')) {
    // false = compra simples: o cliente pode comprar sem escolher cor
    //         e tamanho. As variacoes, quando existem, continuam a
    //         aparecer e podem ser escolhidas -- so nao sao exigidas.
    // true  = compra estrita: so compra quem escolher uma combinacao
    //         de cor e tamanho com estoque.
    define('REQUIRE_VARIANT_SELECTION', false);
}

// ---------------------------------------------------------
// 3. Fuso horario
// APP_TIMEZONE e opcional: config.php antigos definem o fuso
// diretamente e continuam funcionando.
// ---------------------------------------------------------

if (defined('APP_TIMEZONE')) {
    date_default_timezone_set(APP_TIMEZONE);
}

// ---------------------------------------------------------
// 4. Tratamento de erros
// Instalado cedo, para que as falhas dos passos seguintes já
// caiam na página de erro em vez de num aviso solto na tela.
// ---------------------------------------------------------

require_once __DIR__ . '/errors.php';

init_error_handling();

// ---------------------------------------------------------
// 5. Banco, helpers e autenticacao
// A conexao em si só e aberta na primeira chamada a db().
// ---------------------------------------------------------

require_once dirname(__DIR__) . '/config/database.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

// ---------------------------------------------------------
// 6. Cabecalhos padrão da resposta
//
// Content-Type repete o AddDefaultCharset do .htaccess, para o
// caso de o projeto rodar atras de um servidor que não le esse
// arquivo.
//
// X-Frame-Options impede que o painel seja carregado dentro de
// um iframe em outro site, que e a base do ataque de clickjacking
// (o admin pensa que clica numa coisa e clica em outra).
// ---------------------------------------------------------

if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
}

// ---------------------------------------------------------
// 7. HTTPS
//
// Sem TLS a senha do painel e o cookie de sessao atravessam a rede em
// texto puro, e todo o cuidado com a sessao (id novo a cada login,
// httponly, samesite) não adianta nada: basta ler o cookie do trafego.
//
// FORCE_HTTPS e opcional para não quebrar o desenvolvimento local.
// ---------------------------------------------------------

if (defined('FORCE_HTTPS') && FORCE_HTTPS) {
    $overTls = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443')
            || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');

    if (!$overTls) {
        if (!headers_sent()) {
            header('Location: https://' . ($_SERVER['HTTP_HOST'] ?? '')
                 . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
        }
        exit;
    }

    if (!headers_sent()) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}
