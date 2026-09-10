<?php
/**
 * Jo Modas - Dados atualizados do carrinho (JSON)
 *
 * O carrinho fica no localStorage, que e do navegador e pode estar velho
 * ou ter sido editado a mao. Antes de fechar o pedido, a pagina do
 * carrinho manda os variant_id para ca e recebe de volta o que o banco
 * diz agora: nome, preco, estoque e se a variacao continua a venda.
 *
 * E o que garante a regra de que variacao sem estoque nao e comprada:
 * de nada adiantaria conferir apenas no momento de adicionar, porque o
 * estoque pode zerar enquanto o carrinho espera.
 *
 * Somente leitura, e devolve apenas dados que ja sao publicos.
 *
 * Entrada:  POST com corpo JSON {"ids": [1, 2, 3]}
 * Saida:    {"items": [...]}
 */

require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$body = json_decode((string) file_get_contents('php://input'), true);

/**
 * So inteiros positivos, sem repetir, e no maximo 100 por chamada.
 */
function cart_clean_ids(mixed $raw): array
{
    if (!is_array($raw)) {
        return [];
    }

    $out = [];

    foreach ($raw as $value) {
        if (!is_scalar($value)) {
            continue;
        }

        $id = filter_var($value, FILTER_VALIDATE_INT);

        if ($id !== false && $id > 0 && !in_array($id, $out, true)) {
            $out[] = $id;
        }

        if (count($out) >= 100) {
            break;
        }
    }

    return $out;
}

$ids        = cart_clean_ids($body['ids']        ?? null);
$productIds = cart_clean_ids($body['productIds'] ?? null);

if ($ids === [] && $productIds === []) {
    echo json_encode(['items' => []]);
    exit;
}

$items = [];

// =========================================================
// Linhas de VARIACAO (cor + tamanho)
// =========================================================

if ($ids !== []) {
    $placeholders = implode(', ', array_fill(0, count($ids), '?'));

    // So variacao ativa, de produto ativo, em categoria ativa.
    //
    // Sem estes tres filtros bastava pedir os ids 1, 2, 3... para receber
    // nome, preco e estoque exato de todo o catalogo, inclusive de produtos
    // ainda nao publicados. O que nao passa aqui simplesmente nao volta, e
    // o carrinho ja trata id ausente removendo o item.
    $stmt = db()->prepare(
        'SELECT v.id, v.color, v.size, v.stock,
                p.id AS product_id, p.name, p.slug, p.price, p.promo_price,
                (SELECT pi.filename FROM product_images pi
                  WHERE pi.product_id = p.id
                  ORDER BY pi.is_main DESC, pi.sort_order ASC, pi.id ASC
                  LIMIT 1) AS image
           FROM product_variants v
           JOIN products p ON p.id = v.product_id
           JOIN categories c ON c.id = p.category_id
          WHERE v.id IN (' . $placeholders . ')
            AND v.active = 1
            AND p.active = 1
            AND c.active = 1'
    );
    try {
        $stmt->execute($ids);
        $rows = $stmt->fetchAll();
    } catch (Throwable $e) {
        // O tratador global desenha HTML; aqui quem consome e o JavaScript
        // do carrinho, que espera JSON.
        error_log("carrinho_dados: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["items" => [], "error" => "indisponivel"]);
        exit;
    }

    foreach ($rows as $row) {
        $price = effective_price($row);

        // Chegou ate aqui, entao esta publicado. Falta so ter estoque.
        $available = (int) $row['stock'] > 0;

        $items[] = [
            'variantId' => (int) $row['id'],
            'productId' => (int) $row['product_id'],
            'name'      => $row['name'],
            'color'     => $row['color'],
            'size'      => $row['size'],
            'price'     => round($price, 2),
            'stock'     => (int) $row['stock'],
            'available' => $available,
            'image'     => product_image_url($row['image']),
            // Absoluta: vai para a mensagem do WhatsApp, aberta fora do site.
            'url'       => absolute_url('produto.php?slug=' . rawurlencode($row['slug'])),
        ];
    }
}

// =========================================================
// Linhas de PRODUTO (compra sem escolher cor e tamanho)
//
// Mesmos filtros da consulta acima: produto ativo, em categoria ativa.
// Sem eles, pedir os ids 1, 2, 3... devolveria o catalogo inteiro,
// inclusive o que ainda nao foi publicado.
//
// stock vai null de proposito: nao ha estoque reservado numa compra sem
// variacao, e mandar zero faria o carrinho tratar como esgotado.
// =========================================================

if ($productIds !== []) {
    $marks = implode(', ', array_fill(0, count($productIds), '?'));

    $stmt = db()->prepare(
        'SELECT p.id, p.name, p.slug, p.price, p.promo_price,
                (SELECT pi.filename FROM product_images pi
                  WHERE pi.product_id = p.id
                  ORDER BY pi.is_main DESC, pi.sort_order ASC, pi.id ASC
                  LIMIT 1) AS image
           FROM products p
           JOIN categories c ON c.id = p.category_id
          WHERE p.id IN (' . $marks . ')
            AND p.active = 1
            AND c.active = 1'
    );

    try {
        $stmt->execute($productIds);
        $productRows = $stmt->fetchAll();
    } catch (Throwable $e) {
        error_log('carrinho_dados (produtos): ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['items' => [], 'error' => 'indisponivel']);
        exit;
    }

    foreach ($productRows as $row) {
        $items[] = [
            'variantId' => null,
            'productId' => (int) $row['id'],
            'name'      => $row['name'],
            'color'     => '',
            'size'      => '',
            'price'     => round(effective_price($row), 2),
            'stock'     => null,
            'available' => true,
            'image'     => product_image_url($row['image']),
            'url'       => absolute_url('produto.php?slug=' . rawurlencode($row['slug'])),
        ];
    }
}

echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);
