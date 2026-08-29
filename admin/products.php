<?php
/**
 * Jo Modas - Painel: lista de produtos
 *
 * Trata tambem ativar/desativar e apagar, por POST com token CSRF,
 * seguindo o mesmo padrao POST/Redirect/GET das categorias.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin();

if (is_post()) {
    require_csrf();

    $action = post('action');
    $id     = input_int('id');

    if ($id === null || $id < 1) {
        flash('error', 'Produto invalido.');
        redirect(base_url('admin/products.php'));
    }

    $stmt = db()->prepare('SELECT id, name, active FROM products WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $product = $stmt->fetch();

    if ($product === false) {
        flash('error', 'Produto nao encontrado.');
        redirect(base_url('admin/products.php'));
    }

    if ($action === 'toggle') {
        $newState = (int) $product['active'] === 1 ? 0 : 1;

        $update = db()->prepare('UPDATE products SET active = ? WHERE id = ?');
        $update->execute([$newState, $id]);

        flash('success', $newState === 1
            ? 'Produto ativado e visivel na loja.'
            : 'Produto desativado e fora da loja.');

        redirect(base_url('admin/products.php'));
    }

    if ($action === 'delete') {
        // O ON DELETE CASCADE limpa os registros de imagens e variacoes,
        // mas nao toca nos arquivos em disco. Por isso a lista de nomes e
        // colhida antes: depois do DELETE ela nao existe mais.
        $imageStmt = db()->prepare('SELECT filename FROM product_images WHERE product_id = ?');
        $imageStmt->execute([$id]);
        $filenames = $imageStmt->fetchAll(PDO::FETCH_COLUMN);

        $delete = db()->prepare('DELETE FROM products WHERE id = ?');
        $delete->execute([$id]);

        foreach ($filenames as $filename) {
            delete_product_image_file($filename);
        }

        flash('success', 'Produto apagado.');
        redirect(base_url('admin/products.php'));
    }

    flash('error', 'Acao desconhecida.');
    redirect(base_url('admin/products.php'));
}

// ---------------------------------------------------------
// Listagem
// JOIN simples: category_id e NOT NULL, entao todo produto tem categoria.
// ---------------------------------------------------------

$products = db()->query(
    'SELECT p.id, p.name, p.slug, p.sku, p.price, p.promo_price, p.active,
            p.featured, p.is_new, p.best_seller,
            c.name AS category_name,
            (SELECT COUNT(*) FROM product_images pi WHERE pi.product_id = p.id) AS image_count,
            (SELECT COUNT(*) FROM product_variants pv WHERE pv.product_id = p.id) AS variant_count,
            (SELECT COALESCE(SUM(pv.stock), 0) FROM product_variants pv
              WHERE pv.product_id = p.id AND pv.active = 1) AS total_stock
       FROM products p
       JOIN categories c ON c.id = p.category_id
      ORDER BY p.created_at DESC, p.id DESC'
)->fetchAll();

$categoryCount = (int) db()->query('SELECT COUNT(*) FROM categories')->fetchColumn();

$pageTitle = 'Produtos';
$activeNav = 'produtos';

require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <h1 class="page-title">Produtos</h1>
    <?php if ($categoryCount > 0): ?>
        <a class="btn btn-primary" href="<?= e(base_url('admin/product_form.php')) ?>">Novo produto</a>
    <?php endif; ?>
</div>

<?php if ($categoryCount === 0): ?>

    <p class="empty">
        Antes de cadastrar produtos e preciso ter ao menos uma categoria.
        <br><a href="<?= e(base_url('admin/category_form.php')) ?>">Criar a primeira categoria</a>.
    </p>

<?php elseif ($products === []): ?>

    <p class="empty">Nenhum produto cadastrado ainda.</p>

<?php else: ?>

    <div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>Produto</th>
                <th>Categoria</th>
                <th>SKU</th>
                <th>Preco</th>
                <th class="col-num">Estoque</th>
                <th>Marcadores</th>
                <th>Situacao</th>
                <th class="col-actions">Acoes</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($products as $product): ?>
            <tr>
                <td><strong><?= e($product['name']) ?></strong></td>
                <td><?= e($product['category_name']) ?></td>
                <td class="mono"><?= $product['sku'] !== null && $product['sku'] !== ''
                        ? e($product['sku']) : '&mdash;' ?></td>
                <td>
                    <?php if ($product['promo_price'] !== null): ?>
                        <span class="price-old"><?= e(format_price($product['price'])) ?></span>
                        <span class="price-now"><?= e(format_price($product['promo_price'])) ?></span>
                    <?php else: ?>
                        <?= e(format_price($product['price'])) ?>
                    <?php endif; ?>
                </td>
                <td class="col-num">
                    <?php if ((int) $product['variant_count'] === 0): ?>
                        <span class="badge badge-off">Sem variacao</span>
                    <?php else: ?>
                        <?= e((string) $product['total_stock']) ?>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ((int) $product['featured'] === 1): ?>
                        <span class="pill">Destaque</span>
                    <?php endif; ?>
                    <?php if ((int) $product['is_new'] === 1): ?>
                        <span class="pill">Novidade</span>
                    <?php endif; ?>
                    <?php if ((int) $product['best_seller'] === 1): ?>
                        <span class="pill">Mais vendido</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ((int) $product['active'] === 1): ?>
                        <span class="badge badge-on">Ativo</span>
                    <?php else: ?>
                        <span class="badge badge-off">Inativo</span>
                    <?php endif; ?>
                </td>
                <td class="col-actions">
                    <div class="actions">
                        <a class="btn btn-sm btn-ghost"
                           href="<?= e(base_url('admin/product_form.php?id=' . (int) $product['id'])) ?>">Editar</a>

                        <a class="btn btn-sm btn-ghost"
                           href="<?= e(base_url('admin/product_images.php?product_id=' . (int) $product['id'])) ?>">
                            Imagens (<?= e((string) $product['image_count']) ?>)</a>

                        <a class="btn btn-sm btn-ghost"
                           href="<?= e(base_url('admin/product_variants.php?product_id=' . (int) $product['id'])) ?>">
                            Variacoes (<?= e((string) $product['variant_count']) ?>)</a>

                        <?php if ((int) $product['active'] === 1): ?>
                            <a class="btn btn-sm btn-ghost" target="_blank" rel="noopener"
                               href="<?= e(base_url('produto.php?slug=' . rawurlencode($product['slug']))) ?>">Ver na loja</a>
                        <?php endif; ?>

                        <form method="post" action="<?= e(base_url('admin/products.php')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= e((string) $product['id']) ?>">
                            <button type="submit" class="btn btn-sm btn-ghost">
                                <?= (int) $product['active'] === 1 ? 'Desativar' : 'Ativar' ?>
                            </button>
                        </form>

                        <form method="post" action="<?= e(base_url('admin/products.php')) ?>"
                              onsubmit="return confirm('Apagar este produto? Imagens e variacoes ligadas a ele tambem serao apagadas.');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= e((string) $product['id']) ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Apagar</button>
                        </form>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
