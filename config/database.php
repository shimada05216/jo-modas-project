<?php
/**
 * Jo Modas - Conexao PDO
 *
 * Fornece a funcao db(), que devolve sempre a mesma instancia de PDO.
 * Todas as consultas do projeto devem usar prepared statements.
 */

require_once __DIR__ . '/config.php';

/**
 * Devolve a conexao PDO (criada uma unica vez por requisicao).
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_STRINGIFY_FETCHES  => false,
    ];

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        // Lanca em vez de encerrar o script: quem decide o que mostrar e o
        // tratador de includes/errors.php, conforme DEBUG_MODE. A mensagem
        // original da PDOException, que traz usuario e host, fica na excecao
        // anterior e so aparece em desenvolvimento.
        throw new RuntimeException('Nao foi possivel conectar ao banco de dados.', 0, $e);
    }

    return $pdo;
}
