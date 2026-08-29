<?php
/**
 * Jo Modas - Listagem de uma categoria
 *
 * Endereco: categoria.php?slug=vestidos&pagina=2
 *
 * Mostra os produtos ativos da categoria, do mais novo para o mais antigo,
 * em paginas de 12. So categorias ativas sao acessiveis.
 */

require_once __DIR__ . '/includes/bootstrap.php';

const PER_PAGE = 12;

$slug     = get('slug');
$category = false;

if ($slug !== '') {
    $stmt = db()->prepare(
        'SELECT id, name, slug, description FROM categories
          WHERE slug = ? AND active = 1 LIMIT 1'
    );
    $stmt->execute([$slug]);
    $category = $stmt->fetch();
}

if ($category === false) {
    http_response_code(404);

    $pageTitle = 'Categoria nao encontrada';
    require __DIR__ . '/includes/site_header.php';
    ?>
    <div class="empty-state">
        <h2>Categoria nao encontrada</h2>
        <p>Essa categoria nao existe ou saiu do ar.</p>
        <a class="btn btn-solid" href="<?= e(base_url('index.php')) ?>">Voltar para a loja</a>
    </div>
    <?php
    require __DIR__ . '/includes/site_footer.php';
    exit;
}

$categoryId = (int) $category['id'];
$total      = count_category_products($categoryId);
$pages      = max(1, (int) ceil($total / PER_PAGE));

$page = input_int('pagina', 1) ?? 1;
$page = max(1, min($pages, $page));

$products = $total > 0
    ? showcase_products('category', PER_PAGE, $categoryId, ($page - 1) * PER_PAGE)
    : [];

$pageTitle  = $category['name'];
$activeSlug = $category['slug'];
$metaDesc   = $category['description'] !== null && $category['description'] !== ''
    ? $category['description']
    : 'Produtos da categoria ' . $category['name'] . ' na Jo Modas.';

require __DIR__ . '/includes/site_header.php';
?>

<nav class="crumb" aria-label="Voce esta em">
    <a href="<?= e(base_url('index.php')) ?>">Inicio</a>
    <span class="crumb-sep">/</span>
    <span aria-current="page"><?= e($category['name']) ?></span>
</nav>

<header class="cat-head">
    <h1 class="cat-title"><?= e($category['name']) ?></h1>

    <?php if ($category['description'] !== null && $category['description'] !== ''): ?>
        <p class="cat-desc"><?= e($category['description']) ?></p>
    <?php endif; ?>

    <p class="cat-count">
        <?= e((string) $total) ?> <?= $total === 1 ? 'produto' : 'produtos' ?>
    </p>
</header>

<?php if ($products === []): ?>

    <div class="empty-state">
        <h2>Nada por aqui ainda</h2>
        <p>Esta categoria ainda nao tem produtos publicados.</p>
        <a class="btn btn-solid" href="<?= e(base_url('index.php')) ?>">Ver a loja</a>
    </div>

<?php else: ?>

    <div class="grid">
        <?php foreach ($products as $card): ?>
            <?php require __DIR__ . '/includes/product_card.php'; ?>
        <?php endforeach; ?>
    </div>

    <?php if ($pages > 1): ?>
        <nav class="pager" aria-label="Paginas">
            <?php if ($page > 1): ?>
                <a class="pager-btn" href="<?= e(base_url('categoria.php?slug='
                    . rawurlencode($category['slug']) . '&pagina=' . ($page - 1))) ?>">Anterior</a>
            <?php endif; ?>

            <span class="pager-info">
                Pagina <?= e((string) $page) ?> de <?= e((string) $pages) ?>
            </span>

            <?php if ($page < $pages): ?>
                <a class="pager-btn" href="<?= e(base_url('categoria.php?slug='
                    . rawurlencode($category['slug']) . '&pagina=' . ($page + 1))) ?>">Proxima</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>

<?php endif; ?>

<?php require __DIR__ . '/includes/site_footer.php'; ?>
