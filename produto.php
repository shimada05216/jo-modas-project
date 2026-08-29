<?php
/**
 * Jo Modas - Pagina publica do produto
 *
 * Endereco: produto.php?slug=vestido-longo
 *
 * O carrinho vive no localStorage do navegador, entao esta pagina nao
 * abre sessao nem grava nada no servidor. Ela apenas entrega os dados
 * das variacoes para o JavaScript montar os seletores de cor e tamanho.
 *
 * Estoque nao e reservado nem descontado em nenhum momento aqui.
 */

require_once __DIR__ . '/includes/bootstrap.php';

$slug = get('slug');

$product = false;

if ($slug !== '') {
    $stmt = db()->prepare(
        'SELECT p.id, p.name, p.slug, p.sku, p.description, p.short_description,
                p.price, p.promo_price,
                c.name AS category_name, c.slug AS category_slug
           FROM products p
           JOIN categories c ON c.id = p.category_id
          WHERE p.slug = ? AND p.active = 1 AND c.active = 1
          LIMIT 1'
    );
    $stmt->execute([$slug]);
    $product = $stmt->fetch();
}

if ($product === false) {
    http_response_code(404);

    $pageTitle = 'Produto nao encontrado';
    require __DIR__ . '/includes/site_header.php';

    echo '<div class="empty-state">'
       . '<h2>Produto nao encontrado</h2>'
       . '<p>O produto que voce procura nao existe ou saiu do catalogo.</p>'
       . '<a class="btn btn-solid" href="' . e(base_url('index.php')) . '">Voltar para a loja</a>'
       . '</div>';

    require __DIR__ . '/includes/site_footer.php';
    exit;
}

$productId = (int) $product['id'];

// ---------------------------------------------------------
// Imagens: a principal vem primeiro
// ---------------------------------------------------------

$imagesStmt = db()->prepare(
    'SELECT filename FROM product_images
      WHERE product_id = ?
      ORDER BY is_main DESC, sort_order ASC, id ASC'
);
$imagesStmt->execute([$productId]);
$images = $imagesStmt->fetchAll(PDO::FETCH_COLUMN);

// ---------------------------------------------------------
// Variacoes ativas
// As inativas nem chegam ao navegador. As sem estoque chegam, porque
// o seletor precisa mostrar o tamanho como esgotado.
// ---------------------------------------------------------

$variantsStmt = db()->prepare(
    'SELECT id, color, size, stock FROM product_variants
      WHERE product_id = ? AND active = 1
      ORDER BY color ASC, size_order ASC, size ASC'
);
$variantsStmt->execute([$productId]);
$variants = $variantsStmt->fetchAll();

// Cores na ordem em que aparecem, sem repetir.
$colors = [];

foreach ($variants as $variant) {
    if (!in_array($variant['color'], $colors, true)) {
        $colors[] = $variant['color'];
    }
}

$price       = effective_price($product);
$hasDiscount = $price < (float) $product['price'];

// Dados entregues ao JavaScript. O preco vai junto so para montar a
// mensagem do WhatsApp; a pagina do carrinho confere tudo de novo
// contra o banco antes de fechar o pedido.
$payload = [
    'id'       => $productId,
    'name'     => $product['name'],
    'slug'     => $product['slug'],
    'price'    => round($price, 2),
    'image'    => $images !== [] ? product_image_url($images[0]) : product_image_url(null),
    'url'      => base_url('produto.php?slug=' . rawurlencode($product['slug'])),
    'variants' => array_map(static function (array $variant): array {
        return [
            'id'    => (int) $variant['id'],
            'color' => $variant['color'],
            'size'  => $variant['size'],
            'stock' => (int) $variant['stock'],
        ];
    }, $variants),
];

$pageTitle  = $product['name'];
$activeSlug = $product['category_slug'];
$metaDesc   = $product['short_description'] !== null && $product['short_description'] !== ''
    ? $product['short_description']
    : $product['name'] . ' na Jo Modas.';

require __DIR__ . '/includes/site_header.php';
?>

<nav class="crumb" aria-label="Voce esta em">
    <a href="<?= e(base_url('index.php')) ?>">Inicio</a>
    <span class="crumb-sep">/</span>
    <a href="<?= e(base_url('categoria.php?slug=' . rawurlencode($product['category_slug']))) ?>">
        <?= e($product['category_name']) ?>
    </a>
    <span class="crumb-sep">/</span>
    <span aria-current="page"><?= e($product['name']) ?></span>
</nav>

<div class="product">

    <div class="product-gallery">
        <?php if ($images === []): ?>
            <img class="gallery-main" id="gallery-main"
                 src="<?= e(product_image_url(null)) ?>" alt="<?= e($product['name']) ?>">
        <?php else: ?>
            <img class="gallery-main" id="gallery-main"
                 src="<?= e(product_image_url($images[0])) ?>" alt="<?= e($product['name']) ?>">

            <?php if (count($images) > 1): ?>
                <div class="gallery-thumbs">
                    <?php foreach ($images as $index => $filename): ?>
                        <button type="button"
                                class="thumb<?= $index === 0 ? ' is-active' : '' ?>"
                                data-full="<?= e(product_image_url($filename)) ?>">
                            <img src="<?= e(product_image_url($filename)) ?>"
                                 alt="<?= e($product['name']) ?> - imagem <?= e((string) ($index + 1)) ?>"
                                 loading="lazy">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <div class="product-info">
        <h1 class="product-name"><?= e($product['name']) ?></h1>

        <?php if ($product['sku'] !== null && $product['sku'] !== ''): ?>
            <p class="product-sku">Cod. <?= e($product['sku']) ?></p>
        <?php endif; ?>

        <p class="product-price">
            <?php if ($hasDiscount): ?>
                <span class="price-old"><?= e(format_price($product['price'])) ?></span>
            <?php endif; ?>
            <span class="price-now"><?= e(format_price($price)) ?></span>
        </p>

        <?php if ($product['short_description'] !== null && $product['short_description'] !== ''): ?>
            <p class="product-short"><?= e($product['short_description']) ?></p>
        <?php endif; ?>

        <?php if ($variants === []): ?>

            <p class="unavailable">Este produto esta sem opcoes disponiveis no momento.</p>

        <?php else: ?>

            <form class="buy-form" id="buy-form" novalidate>

                <div class="option-block">
                    <span class="option-label">Cor</span>
                    <div class="option-list" id="color-list">
                        <?php foreach ($colors as $color): ?>
                            <button type="button" class="option" data-color="<?= e($color) ?>">
                                <?= e($color) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="option-block">
                    <span class="option-label">Tamanho</span>
                    <div class="option-list" id="size-list">
                        <span class="option-empty">Escolha uma cor primeiro.</span>
                    </div>
                </div>

                <p class="stock-line" id="stock-line" aria-live="polite">
                    Escolha cor e tamanho para ver a disponibilidade.
                </p>

                <div class="qty-block">
                    <label class="option-label" for="qty">Quantidade</label>
                    <input type="number" id="qty" value="1" min="1" step="1" disabled>
                </div>

                <button type="submit" class="btn-buy" id="add-to-cart" disabled>
                    Adicionar ao carrinho
                </button>

                <p class="added-msg" id="added-msg" hidden>
                    Produto adicionado.
                    <a href="<?= e(base_url('carrinho.php')) ?>">Ver carrinho</a>
                </p>
            </form>

        <?php endif; ?>

        <?php if ($product['description'] !== null && $product['description'] !== ''): ?>
            <div class="product-description">
                <h2>Descricao</h2>
                <p><?= nl2br(e($product['description'])) ?></p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script type="application/json" id="jm-product-data">
<?= json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>
</script>

<?php require __DIR__ . '/includes/site_footer.php'; ?>
