<?php
/**
 * Jo Modas - Sessao, protecao CSRF e autenticacao do painel
 *
 * Este arquivo cuida de tres coisas:
 *   1. iniciar a sessao com cookies seguros;
 *   2. gerar e conferir o token CSRF dos formularios do admin;
 *   3. autenticar o administrador com password_hash / password_verify.
 */

require_once __DIR__ . '/functions.php';

// =========================================================
// Sessao
// =========================================================

/**
 * Inicia a sessao uma única vez, com cookie httponly e SameSite=Lax.
 */
function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
          || (($_SERVER['SERVER_PORT'] ?? '') === '443')
          || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');

    // Aceita apenas ids gerados pelo próprio PHP. Sem isto, um id inventado
    // na URL ou num cookie forjado seria adotado como sessao válida, que e a
    // base do ataque de fixacao de sessao.
    ini_set('session.use_strict_mode', '1');

    // O id de sessao nunca viaja na URL, só no cookie.
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_name('jomodas_session');
    session_start();
}

// =========================================================
// CSRF
// =========================================================

/**
 * Devolve o token CSRF da sessao, criando-o na primeira chamada.
 */
function csrf_token(): string
{
    start_session();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Campo oculto pronto para colar dentro de um <form>.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Compara o token recebido com o da sessao (comparacao em tempo constante).
 */
function csrf_valid($token): bool
{
    start_session();

    $stored = $_SESSION['csrf_token'] ?? '';

    return $stored !== '' && is_string($token) && hash_equals($stored, $token);
}

/**
 * Interrompe a requisicao quando o token do POST não confere.
 * Deve ser a primeira linha de todo tratamento de POST do admin.
 */
function require_csrf(): void
{
    if (!csrf_valid($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        die('Requisicao inválida ou sessao expirada. Volte, atualize a página e envie o formulario novamente.');
    }
}

// =========================================================
// Mensagens rapidas (flash)
// =========================================================

/**
 * Guarda uma mensagem para ser exibida na próxima página.
 * $type: success | error | info
 */
function flash(string $type, string $message): void
{
    start_session();
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/**
 * Devolve e limpa as mensagens acumuladas.
 */
function take_flashes(): array
{
    start_session();

    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $messages;
}

// =========================================================
// Autenticacao
// =========================================================

/**
 * Hash de reserva, usado só quando não existe nenhum admin cadastrado.
 * E o hash publico de "password", nunca aceito como credencial: o
 * resultado da comparacao e sempre descartado.
 *
 * ATENCAO: não use este valor como referencia de tempo no caso normal.
 * Ele foi gerado com custo 10, e o custo padrão do PHP mudou para 12 na
 * versao 8.4. Comparar contra um hash de custo 10 enquanto os hashes
 * reais tem custo 12 faz a resposta de "e-mail inexistente" voltar em
 * cerca de um quarto do tempo, o que denuncia quais e-mails tem conta.
 * Medido: 60 ms contra 208 ms. Por isso timing_reference_hash() abaixo
 * prefere sempre um hash real do banco.
 */
const DUMMY_PASSWORD_HASH = '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy';

/**
 * Devolve um hash para gastar tempo quando o e-mail não existe.
 *
 * Usa o hash de um administrador real, entao o custo e sempre o mesmo do
 * caminho "senha errada", sem depender de nenhuma constante escrita a
 * mao. Se o custo padrão do PHP mudar de novo, isto continua certo.
 */
function timing_reference_hash(): string
{
    $hash = db()->query(
        'SELECT password_hash FROM admins WHERE active = 1 ORDER BY id ASC LIMIT 1'
    )->fetchColumn();

    return $hash !== false ? (string) $hash : DUMMY_PASSWORD_HASH;
}

// =========================================================
// Controle de tentativas de login
//
// Sem isto o painel aceita adivinhacoes de senha sem limite: com o
// e-mail do administrador conhecido, uma lista de senhas comuns roda
// até acertar, e nada fica registrado.
//
// Sao dois limites, de proposito:
//   - por IP, baixo, que barra o caso comum de um script só;
//   - por e-mail, bem mais alto, para pegar ataque distribuido sem
//     entregar ao atacante uma forma facil de trancar o dono da loja
//     de fora (bastaria errar a senha dele algumas vezes).
// =========================================================

const LOGIN_MAX_PER_IP    = 5;
const LOGIN_MAX_PER_EMAIL = 20;
const LOGIN_WINDOW_MIN    = 15;
const LOGIN_KEEP_HOURS    = 24;

/**
 * IP de quem esta tentando entrar.
 * Le somente REMOTE_ADDR: cabecalhos como X-Forwarded-For são enviados
 * pelo próprio cliente e serviriam para escapar do limite.
 */
function login_client_ip(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

    return $ip !== '' ? substr($ip, 0, 45) : 'desconhecido';
}

/**
 * Minutos que faltam para liberar, ou 0 se não ha bloqueio.
 */
function login_lock_minutes(string $email): int
{
    // O tempo restante e calculado dentro do SQL, e não no PHP. Comparar
    // NOW() do banco com time() do PHP daria errado sempre que os dois
    // estivessem em fusos diferentes, que e o caso aqui: o PHP roda em
    // America/Sao_Paulo e o MariaDB no fuso do sistema.
    $stmt = db()->prepare(
        'SELECT
            SUM(ip = ?)    AS by_ip,
            SUM(email = ?) AS by_email,
            TIMESTAMPDIFF(
                SECOND,
                NOW(),
                MAX(attempted_at) + INTERVAL ' . LOGIN_WINDOW_MIN . ' MINUTE
            ) AS wait_seconds
           FROM login_attempts
          WHERE successful = 0
            AND attempted_at > (NOW() - INTERVAL ' . LOGIN_WINDOW_MIN . ' MINUTE)'
    );
    $stmt->execute([login_client_ip(), $email]);
    $row = $stmt->fetch();

    if ($row === false || $row['wait_seconds'] === null) {
        return 0;
    }

    $blocked = (int) $row['by_ip'] >= LOGIN_MAX_PER_IP
            || (int) $row['by_email'] >= LOGIN_MAX_PER_EMAIL;

    if (!$blocked || (int) $row['wait_seconds'] <= 0) {
        return 0;
    }

    return max(1, (int) ceil((int) $row['wait_seconds'] / 60));
}

/**
 * Registra a tentativa. Todas entram, inclusive as bem-sucedidas,
 * para que o histórico sirva de trilha de auditoria.
 */
function login_record_attempt(string $email, bool $successful): void
{
    $stmt = db()->prepare(
        'INSERT INTO login_attempts (ip, email, successful) VALUES (?, ?, ?)'
    );
    $stmt->execute([login_client_ip(), substr($email, 0, 190), $successful ? 1 : 0]);
}

/**
 * Depois de um login certo, zera o contador de quem acertou e limpa
 * o histórico velho. Roda aqui porque e o momento raro e barato.
 */
function login_clear_attempts(string $email): void
{
    $clear = db()->prepare(
        'DELETE FROM login_attempts WHERE successful = 0 AND (ip = ? OR email = ?)'
    );
    $clear->execute([login_client_ip(), $email]);

    db()->exec(
        'DELETE FROM login_attempts
          WHERE attempted_at < (NOW() - INTERVAL ' . LOGIN_KEEP_HOURS . ' HOUR)'
    );
}

/**
 * Confere e-mail e senha. Em caso de sucesso registra o admin na sessao.
 */
function admin_login(string $email, string $password): bool
{
    start_session();

    $stmt = db()->prepare(
        'SELECT id, name, password_hash FROM admins WHERE email = ? AND active = 1 LIMIT 1'
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user === false) {
        // Sem esta comparacao a resposta para "e-mail inexistente" voltaria
        // muito antes da resposta para "senha errada", e o tempo de resposta
        // revelaria quais e-mails tem conta. O retorno e ignorado de proposito.
        password_verify($password, timing_reference_hash());

        login_record_attempt($email, false);

        return false;
    }

    if (!password_verify($password, (string) $user['password_hash'])) {
        login_record_attempt($email, false);

        return false;
    }

    // Se o custo padrão do PHP mudou desde que a senha foi criada, regrava
    // o hash agora, que e o único momento em que a senha esta disponível.
    if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
        $update = db()->prepare('UPDATE admins SET password_hash = ? WHERE id = ?');
        $update->execute([password_hash($password, PASSWORD_DEFAULT), (int) $user['id']]);
    }

    // Novo id de sessao para evitar fixacao de sessao.
    session_regenerate_id(true);

    $_SESSION['admin_id']      = (int) $user['id'];
    $_SESSION['admin_name']    = $user['name'];
    $_SESSION['last_activity'] = time();

    // O token antigo deixa de valer junto com a identidade antiga.
    unset($_SESSION['csrf_token']);

    login_record_attempt($email, true);
    login_clear_attempts($email);

    return true;
}

/**
 * Encerra a sessao do administrador e apaga o cookie.
 */
function admin_logout(): void
{
    start_session();

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }

    session_destroy();
}

/**
 * Dados do administrador logado, ou null.
 * A cada requisicao consulta o banco uma única vez, o que garante que
 * uma conta desativada perca o acesso na hora.
 */
function current_admin(): ?array
{
    static $admin  = null;
    static $loaded = false;

    if ($loaded) {
        return $admin;
    }

    $loaded = true;
    start_session();

    $id = $_SESSION['admin_id'] ?? null;

    if ($id === null) {
        return null;
    }

    // Expiracao por inatividade. A sessao não e destruida aqui de proposito:
    // apagar só as chaves do admin deixa o flash da próxima página funcionar,
    // e o session_regenerate_id troca o id sem reaproveitar o antigo.
    $timeout = defined('ADMIN_SESSION_TIMEOUT') ? (int) ADMIN_SESSION_TIMEOUT : 7200;
    $last    = (int) ($_SESSION['last_activity'] ?? 0);

    if ($timeout > 0 && $last > 0 && (time() - $last) > $timeout) {
        unset($_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['last_activity'], $_SESSION['csrf_token']);
        session_regenerate_id(true);
        $_SESSION['session_expired'] = true;

        return null;
    }

    $_SESSION['last_activity'] = time();

    $stmt = db()->prepare('SELECT id, name, email FROM admins WHERE id = ? AND active = 1 LIMIT 1');
    $stmt->execute([(int) $id]);
    $row = $stmt->fetch();

    $admin = $row === false ? null : $row;

    return $admin;
}

function is_admin_logged_in(): bool
{
    return current_admin() !== null;
}

/**
 * Bloqueia a página para quem não esta logado.
 */
function require_admin(): void
{
    if (is_admin_logged_in()) {
        return;
    }

    if (!empty($_SESSION['session_expired'])) {
        unset($_SESSION['session_expired']);
        flash('error', 'Sua sessao expirou por inatividade. Entre novamente.');
    } else {
        flash('error', 'Faca login para acessar o painel.');
    }

    redirect(base_url('admin/login.php'));
}
