<?php
/**
 * Jo Modas - Painel: visao geral
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin();

$counts = [
    'categories'      => (int) db()->query('SELECT COUNT(*) FROM categories')->fetchColumn(),
    'products'        => (int) db()->query('SELECT COUNT(*) FROM products')->fetchColumn(),
    'products_active' => (int) db()->query('SELECT COUNT(*) FROM products WHERE active = 1')->fetchColumn(),
    'out_of_stock'    => (int) db()->query('SELECT COUNT(*) FROM product_variants WHERE stock = 0')->fetchColumn(),
];

$pageTitle = 'Painel';
$activeNav = 'dashboard';

require __DIR__ . '/includes/header.php';
?>

<h1 class="page-title">Visao geral</h1>

<div class="cards">
    <div class="card">
        <div>
            <span class="card-label">Categorias</span>
            <span class="card-value"><?= e((string) $counts['categories']) ?></span>
        </div>
        <span class="card-icon tom-2" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M3 12.5V4h8.5L21 13.5 13.5 21 3 12.5Z"/><circle cx="7.5" cy="7.5" r="1.4"/></svg>
        </span>
    </div>

    <div class="card">
        <div>
            <span class="card-label">Produtos cadastrados</span>
            <span class="card-value"><?= e((string) $counts['products']) ?></span>
        </div>
        <span class="card-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M21 8 12 3 3 8v8l9 5 9-5V8Z"/><path d="m3 8 9 5 9-5"/></svg>
        </span>
    </div>

    <div class="card">
        <div>
            <span class="card-label">Produtos ativos</span>
            <span class="card-value"><?= e((string) $counts['products_active']) ?></span>
        </div>
        <span class="card-icon tom-3" aria-hidden="true">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="m8.5 12 2.4 2.4L15.5 10"/></svg>
        </span>
    </div>

    <div class="card">
        <div>
            <span class="card-label">Variacoes sem estoque</span>
            <span class="card-value"><?= e((string) $counts['out_of_stock']) ?></span>
        </div>
        <span class="card-icon tom-4" aria-hidden="true">
            <svg viewBox="0 0 24 24"><path d="M12 3.5 2.5 20h19L12 3.5Z"/><path d="M12 10v4M12 17v.5"/></svg>
        </span>
    </div>
</div>

<p class="hint">
    <a href="<?= e(base_url('admin/categories.php')) ?>">Gerenciar categorias</a> ou
    <a href="<?= e(base_url('admin/products.php')) ?>">gerenciar produtos</a>.
    Imagens e variacoes de cor/tamanho entram na proxima etapa.
</p>

<?php require __DIR__ . '/includes/footer.php'; ?>
