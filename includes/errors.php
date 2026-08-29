<?php
/**
 * Jo Modas - Tratamento de erros
 *
 * Este arquivo nao depende de nenhum outro do projeto, porque precisa
 * funcionar mesmo quando a falha acontece durante a inicializacao.
 * Por isso usa htmlspecialchars() diretamente, e nao o helper e().
 *
 * Comportamento conforme DEBUG_MODE:
 *
 *   DEBUG_MODE = true   (desenvolvimento)
 *     - avisos e notices viram ErrorException, ou seja, a execucao para
 *       no primeiro problema em vez de seguir com dados errados;
 *     - a tela mostra tipo, mensagem, arquivo, linha e pilha de chamadas.
 *
 *   DEBUG_MODE = false  (producao)
 *     - avisos e notices vao para o log e a pagina continua;
 *     - a tela mostra apenas um aviso generico, sem detalhes internos.
 */

/**
 * Instala os tratadores. Deve ser chamado uma unica vez, pelo bootstrap.
 */
function init_error_handling(): void
{
    $debug = defined('DEBUG_MODE') && DEBUG_MODE;

    error_reporting(E_ALL);
    ini_set('log_errors', '1');

    // A exibicao fica por conta dos tratadores abaixo, que escapam a saida.
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');

    set_error_handler('handle_php_error');
    set_exception_handler('handle_uncaught_exception');
    register_shutdown_function('handle_fatal_shutdown');
}

/**
 * Recebe avisos, notices e deprecations do PHP.
 *
 * Em desenvolvimento converte tudo em excecao (falha rapido).
 * Em producao registra no log e deixa a pagina continuar.
 */
function handle_php_error(int $severity, string $message, string $file = '', int $line = 0): bool
{
    // Erro suprimido com @: error_reporting() fica zerado para este nivel.
    // Devolver false entrega o caso ao tratamento padrao do PHP, que o ignora.
    if (!(error_reporting() & $severity)) {
        return false;
    }

    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }

    error_log(sprintf('[Jo Modas] PHP %d: %s em %s:%d', $severity, $message, $file, $line));

    return true;
}

/**
 * Ultimo recurso para excecoes que ninguem capturou.
 */
function handle_uncaught_exception(Throwable $e): void
{
    error_log(sprintf(
        '[Jo Modas] %s: %s em %s:%d',
        get_class($e),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    ));

    $detail = '';

    // Percorre tambem as excecoes anteriores: a causa real de uma falha de
    // conexao, por exemplo, fica na PDOException encadeada.
    for ($current = $e; $current !== null; $current = $current->getPrevious()) {
        $detail .= sprintf(
            "%s: %s\n  em %s, linha %d\n\n",
            get_class($current),
            $current->getMessage(),
            $current->getFile(),
            $current->getLine()
        );
    }

    $detail .= "Pilha de chamadas:\n" . $e->getTraceAsString();

    render_error_page($detail);
}

/**
 * Captura erros fatais, que nao passam por handle_php_error().
 */
function handle_fatal_shutdown(): void
{
    $error = error_get_last();

    if ($error === null) {
        return;
    }

    $fatal = E_ERROR | E_PARSE | E_CORE_ERROR | E_CORE_WARNING
           | E_COMPILE_ERROR | E_COMPILE_WARNING | E_USER_ERROR;

    if (!($error['type'] & $fatal)) {
        return;
    }

    error_log(sprintf(
        '[Jo Modas] Erro fatal: %s em %s:%d',
        $error['message'],
        $error['file'],
        $error['line']
    ));

    render_error_page(sprintf(
        "Erro fatal: %s\n  em %s, linha %d",
        $error['message'],
        $error['file'],
        $error['line']
    ));
}

/**
 * Desenha a pagina de erro.
 *
 * $detail so chega ao navegador quando DEBUG_MODE e true; em producao a
 * variavel e descartada, para nao vazar caminhos, consultas ou credenciais.
 */
function render_error_page(string $detail): void
{
    // exit() nao impede os shutdown functions de rodarem em seguida, entao
    // sem esta trava um erro fatal poderia desenhar uma segunda pagina de
    // erro emendada na primeira.
    static $alreadyRendered = false;

    if ($alreadyRendered) {
        return;
    }

    $alreadyRendered = true;

    $debug = defined('DEBUG_MODE') && DEBUG_MODE;

    // Descarta saida ja bufferizada, para o erro nao aparecer no meio
    // de uma pagina pela metade.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
    }

    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>Erro - Jo Modas</title><style>'
       . 'body{font-family:"Segoe UI",Arial,sans-serif;max-width:820px;margin:40px auto;'
       . 'padding:0 20px;color:#23262b;line-height:1.5}'
       . 'h1{color:#b02a5b;font-size:22px}'
       . 'pre{background:#1f2430;color:#e6e6e6;padding:16px;border-radius:8px;'
       . 'overflow-x:auto;font-size:13px;white-space:pre-wrap;word-break:break-word}'
       . '.hint{color:#6b7280;font-size:14px}'
       . '</style></head><body>';

    if ($debug) {
        echo '<h1>Erro na aplicacao</h1>'
           . '<pre>' . htmlspecialchars($detail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>'
           . '<p class="hint">Estes detalhes aparecem porque DEBUG_MODE esta ligado '
           . 'em config/config.php. Deixe-o como false em producao.</p>';
    } else {
        echo '<h1>Ocorreu um erro</h1>'
           . '<p>Nao foi possivel completar a operacao. Tente novamente em instantes.</p>'
           . '<p class="hint">O detalhe tecnico foi gravado no log do servidor.</p>';
    }

    echo '</body></html>';

    exit(1);
}
