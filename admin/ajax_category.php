<?php
/**
 * Jo Modas - Criacao rapida de categoria (JSON)
 *
 * Usado pelo botao "+" ao lado do seletor de categoria no cadastro de
 * produto, para o lojista nao precisar sair do formulario e perder o
 * que ja preencheu.
 *
 * Mesmas protecoes do resto do painel: exige administrador logado,
 * aceita apenas POST, confere o token CSRF e usa prepared statements.
 *
 * Entrada:  POST name=<texto>, csrf_token=<token>
 * Saida:    {"ok":true,"id":12,"name":"Bolsas"}
 *           {"ok":false,"error":"mensagem"}
 */

require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

/**
 * Responde e encerra.
 */
function category_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

// Sessao de administrador. Sem redirecionar: quem consome aqui e o
// JavaScript, que precisa de JSON e nao de uma pagina de login.
if (!is_admin_logged_in()) {
    category_json(['ok' => false, 'error' => 'Sessão expirada. Entre novamente no painel.'], 401);
}

if (!is_post()) {
    category_json(['ok' => false, 'error' => 'Método não permitido.'], 405);
}

if (!csrf_valid($_POST['csrf_token'] ?? null)) {
    category_json(['ok' => false, 'error' => 'Requisição inválida. Atualize a página.'], 400);
}

$name = post('name');

if ($name === '') {
    category_json(['ok' => false, 'error' => 'Informe o nome da categoria.'], 422);
}

if (mb_strlen($name) > 100) {
    category_json(['ok' => false, 'error' => 'O nome deve ter no máximo 100 caracteres.'], 422);
}

// A collation utf8mb4_unicode_ci ignora caixa e acento, igual a chave
// unica uq_categories_name: "Bolsas" e "bolsas" contam como a mesma.
$dup = db()->prepare('SELECT id FROM categories WHERE name = ? LIMIT 1');
$dup->execute([$name]);

if ($dup->fetch() !== false) {
    category_json(['ok' => false, 'error' => 'Já existe uma categoria com esse nome.'], 409);
}

$slug = unique_slug('categories', slugify($name));

// A ordem coloca a nova categoria no fim do menu.
$orderStmt = db()->query('SELECT COALESCE(MAX(sort_order), 0) FROM categories');
$sortOrder = (int) $orderStmt->fetchColumn() + 1;

try {
    $insert = db()->prepare(
        'INSERT INTO categories (name, slug, active, sort_order) VALUES (?, ?, 1, ?)'
    );
    $insert->execute([$name, $slug, $sortOrder]);
} catch (PDOException $e) {
    // Duas pessoas gravando ao mesmo tempo podem passar pela conferencia
    // acima e colidir na chave unica.
    if ($e->getCode() === '23000') {
        category_json(['ok' => false, 'error' => 'Já existe uma categoria com esse nome.'], 409);
    }

    error_log('ajax_category: ' . $e->getMessage());
    category_json(['ok' => false, 'error' => 'Não foi possível criar a categoria.'], 500);
}

category_json([
    'ok'   => true,
    'id'   => (int) db()->lastInsertId(),
    'name' => $name,
]);
