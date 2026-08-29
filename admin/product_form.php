<?php
/**
 * Jo Modas - Painel: criar e editar produto
 *
 * Sem ?id  -> cria um produto novo
 * Com ?id  -> edita o produto informado
 *
 * Imagens e variações (cor/tamanho/estoque) não entram aqui: são a
 * próxima etapa. O estoque continua pertencendo a variação.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin();

// Limites iguais aos do schema.
const PRODUCT_NAME_MAX        = 150;
const PRODUCT_SLUG_MAX        = 180;
const PRODUCT_SKU_MAX         = 60;
const PRODUCT_DESCRIPTION_MAX = 20000;

// DECIMAL(10,2) guarda no máximo 99.999.999,99.
const PRODUCT_PRICE_MAX = 99999999.99;

$id      = input_int('id');
$isEdit  = false;
$product = null;

if ($id !== null && $id > 0) {
    $stmt = db()->prepare(
        'SELECT id, category_id, name, slug, sku, description,
                price, promo_price, active, featured, is_new, best_seller
           FROM products WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    $product = $stmt->fetch();

    if ($product === false) {
        flash('error', 'Produto não encontrado.');
        redirect(base_url('admin/products.php'));
    }

    $isEdit = true;
} else {
    $id = null;
}

// Sem categoria não ha como gravar: products.category_id e NOT NULL.
$categories = db()->query(
    'SELECT id, name, active FROM categories ORDER BY sort_order ASC, name ASC'
)->fetchAll();

if ($categories === []) {
    flash('error', 'Cadastre ao menos uma categoria antes de criar produtos.');
    redirect(base_url('admin/categories.php'));
}

// ---------------------------------------------------------
// Valores do formulario.
// Os precos ficam como texto para devolver ao usuário exatamente o que
// ele digitou quando algum outro campo falha na validacao.
// ---------------------------------------------------------

$values = [
    'category_id' => isset($product['category_id']) ? (int) $product['category_id'] : 0,
    'name'        => $product['name']        ?? '',
    'slug'        => $product['slug']        ?? '',
    'sku'         => $product['sku']         ?? '',
    'description' => $product['description'] ?? '',
    'price'       => isset($product['price']) ? number_format((float) $product['price'], 2, ',', '') : '',
    'promo_price' => isset($product['promo_price']) && $product['promo_price'] !== null
        ? number_format((float) $product['promo_price'], 2, ',', '') : '',
    'active'      => isset($product['active'])      ? (int) $product['active']      : 1,
    'featured'    => isset($product['featured'])    ? (int) $product['featured']    : 0,
    'is_new'      => isset($product['is_new'])      ? (int) $product['is_new']      : 0,
    'best_seller' => isset($product['best_seller']) ? (int) $product['best_seller'] : 0,
];

$errors = [];

if (is_post()) {
    require_csrf();

    $values['category_id'] = (int) (input_int('category_id') ?? 0);
    $values['name']        = post('name');
    $values['slug']        = post('slug');
    $values['sku']         = post('sku');
    $values['description'] = post('description');
    $values['price']       = post('price');
    $values['promo_price'] = post('promo_price');
    $values['active']      = to_bool_int($_POST['active']      ?? 0);
    $values['featured']    = to_bool_int($_POST['featured']    ?? 0);
    $values['is_new']      = to_bool_int($_POST['is_new']      ?? 0);
    $values['best_seller'] = to_bool_int($_POST['best_seller'] ?? 0);

    // ---------- categoria ----------
    if ($values['category_id'] < 1) {
        $errors[] = 'Escolha uma categoria.';
    } else {
        $check = db()->prepare('SELECT id FROM categories WHERE id = ? LIMIT 1');
        $check->execute([$values['category_id']]);

        if ($check->fetch() === false) {
            $errors[] = 'A categoria escolhida não existe mais.';
        }
    }

    // ---------- nome ----------
    if ($values['name'] === '') {
        $errors[] = 'Informe o nome do produto.';
    } elseif (mb_strlen($values['name']) > PRODUCT_NAME_MAX) {
        $errors[] = 'O nome deve ter no máximo ' . PRODUCT_NAME_MAX . ' caracteres.';
    }

    // ---------- descrição ----------
    if (mb_strlen($values['description']) > PRODUCT_DESCRIPTION_MAX) {
        $errors[] = 'A descrição deve ter no máximo ' . PRODUCT_DESCRIPTION_MAX . ' caracteres.';
    }

    // ---------- SKU ----------
    // Vazio vira NULL: a chave única aceita varios NULL, entao produtos
    // sem código não brigam entre si.
    if ($values['sku'] !== '') {
        if (mb_strlen($values['sku']) > PRODUCT_SKU_MAX) {
            $errors[] = 'O SKU deve ter no máximo ' . PRODUCT_SKU_MAX . ' caracteres.';
        } else {
            $dup = db()->prepare('SELECT id FROM products WHERE sku = ? AND id <> ? LIMIT 1');
            $dup->execute([$values['sku'], $id ?? 0]);

            if ($dup->fetch() !== false) {
                $errors[] = 'Já existe um produto com esse SKU.';
            }
        }
    }

    // ---------- preco ----------
    // parse_price aceita tanto 1.234,56 quanto 1234.56 e devolve null
    // para qualquer coisa que não seja número.
    $price = null;

    if ($values['price'] === '') {
        $errors[] = 'Informe o preco.';
    } else {
        $price = parse_price($values['price']);

        if ($price === null) {
            $errors[] = 'Preco inválido. Use apenas números, por exemplo 199,90.';
        } elseif ($price <= 0) {
            $errors[] = 'O preco deve ser maior que zero.';
        } elseif ($price > PRODUCT_PRICE_MAX) {
            $errors[] = 'O preco excede o máximo permitido (99.999.999,99).';
        }
    }

    // ---------- preco promocional ----------
    $promoPrice = null;

    if ($values['promo_price'] !== '') {
        $promoPrice = parse_price($values['promo_price']);

        if ($promoPrice === null) {
            $errors[] = 'Preco promocional inválido. Use apenas números, por exemplo 149,90.';
        } elseif ($promoPrice <= 0) {
            $errors[] = 'O preco promocional deve ser maior que zero. Deixe em branco para remover a promoção.';
        } elseif ($promoPrice > PRODUCT_PRICE_MAX) {
            $errors[] = 'O preco promocional excede o máximo permitido.';
        } elseif ($price !== null && $promoPrice >= $price) {
            // Mesma regra da constraint chk_products_promo_price. Conferida
            // aqui para virar mensagem, e não erro de banco.
            $errors[] = 'O preco promocional deve ser menor que o preco normal.';
        }
    }

    // ---------- slug ----------
    $slug = '';

    if ($errors === []) {
        $base = $values['slug'] !== '' ? $values['slug'] : $values['name'];
        $slug = slugify($base);

        if (mb_strlen($slug) > PRODUCT_SLUG_MAX) {
            $slug = rtrim(mb_substr($slug, 0, PRODUCT_SLUG_MAX), '-');
        }

        if ($slug === '') {
            $slug = 'produto';
        }

        $slug = unique_slug('products', $slug, $id);
    }

    // ---------- gravacao ----------
    if ($errors === []) {
        $sku = $values['sku'] !== '' ? $values['sku'] : null;
        $description = $values['description'] !== '' ? $values['description'] : null;

        // Os precos vao para o banco como texto com duas casas, e não como
        // float. Assim o valor gravado na coluna DECIMAL e exatamente o que
        // foi validado, sem depender da conversao de ponto flutuante.
        $priceParam = number_format($price, 2, '.', '');
        $promoParam = $promoPrice === null ? null : number_format($promoPrice, 2, '.', '');

        try {
            if ($isEdit) {
                $save = db()->prepare(
                    'UPDATE products
                        SET category_id = ?, name = ?, slug = ?, sku = ?, description = ?,
                            price = ?, promo_price = ?, active = ?,
                            featured = ?, is_new = ?, best_seller = ?
                      WHERE id = ?'
                );
                $save->execute([
                    $values['category_id'], $values['name'], $slug, $sku, $description,
                    $priceParam, $promoParam, $values['active'],
                    $values['featured'], $values['is_new'], $values['best_seller'],
                    $id,
                ]);

                $message = 'Produto atualizado.';
            } else {
                $save = db()->prepare(
                    'INSERT INTO products
                        (category_id, name, slug, sku, description,
                         price, promo_price, active, featured, is_new, best_seller)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $save->execute([
                    $values['category_id'], $values['name'], $slug, $sku, $description,
                    $priceParam, $promoParam, $values['active'],
                    $values['featured'], $values['is_new'], $values['best_seller'],
                ]);

                $message = 'Produto criado.';
            }

            if ($values['slug'] !== '' && $slug !== slugify($values['slug'])) {
                $message .= ' O endereço ficou como "' . $slug . '", porque o desejado já estava em uso.';
            }

            flash('success', $message);
            redirect(base_url('admin/products.php'));
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                $errors[] = 'Já existe um produto com esse endereço ou SKU. Tente novamente.';
            } else {
                throw $e;
            }
        }
    }
}

$pageTitle = $isEdit ? 'Editar produto' : 'Novo produto';
$activeNav = 'produtos';

require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <h1 class="page-title"><?= e($pageTitle) ?></h1>
    <div class="actions">
        <?php if ($isEdit): ?>
            <a class="btn btn-ghost"
               href="<?= e(base_url('admin/product_images.php?product_id=' . (int) $id)) ?>">Imagens</a>
            <a class="btn btn-ghost"
               href="<?= e(base_url('admin/product_variants.php?product_id=' . (int) $id)) ?>">Variações</a>
        <?php endif; ?>
        <a class="btn btn-ghost" href="<?= e(base_url('admin/products.php')) ?>">Voltar</a>
    </div>
</div>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endforeach; ?>

<form method="post" class="form-card"
      action="<?= e(base_url('admin/product_form.php' . ($isEdit ? '?id=' . (int) $id : ''))) ?>"
      novalidate>
    <?= csrf_field() ?>

    <label class="field">
        <span class="field-label">Categoria *</span>
        <select name="category_id" required>
            <option value="">Escolha uma categoria</option>
            <?php foreach ($categories as $category): ?>
                <option value="<?= e((string) $category['id']) ?>"
                    <?= $values['category_id'] === (int) $category['id'] ? 'selected' : '' ?>>
                    <?= e($category['name']) ?><?= (int) $category['active'] === 0 ? ' (inativa)' : '' ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>

    <label class="field">
        <span class="field-label">Nome *</span>
        <input type="text" name="name" value="<?= e($values['name']) ?>"
               maxlength="<?= e((string) PRODUCT_NAME_MAX) ?>" required autofocus>
    </label>

    <div class="field-row">
        <label class="field">
            <span class="field-label">Endereço (slug)</span>
            <input type="text" name="slug" value="<?= e($values['slug']) ?>"
                   maxlength="<?= e((string) PRODUCT_SLUG_MAX) ?>"
                   placeholder="gerado a partir do nome">
        </label>

        <label class="field">
            <span class="field-label">SKU</span>
            <input type="text" name="sku" value="<?= e($values['sku']) ?>"
                   maxlength="<?= e((string) PRODUCT_SKU_MAX) ?>"
                   placeholder="opcional">
            <span class="field-hint">Código do produto. Cada cor/tamanho tera o seu.</span>
        </label>
    </div>

    <label class="field">
        <span class="field-label">Descrição</span>
        <textarea name="description" rows="6"><?= e($values['description']) ?></textarea>
    </label>

    <div class="field-row">
        <label class="field">
            <span class="field-label">Preco *</span>
            <input type="text" name="price" value="<?= e($values['price']) ?>"
                   inputmode="decimal" placeholder="199,90" required>
        </label>

        <label class="field">
            <span class="field-label">Preco promocional</span>
            <input type="text" name="promo_price" value="<?= e($values['promo_price']) ?>"
                   inputmode="decimal" placeholder="deixe em branco se não houver">
            <span class="field-hint">Precisa ser menor que o preco normal.</span>
        </label>
    </div>

    <fieldset class="field-group">
        <legend class="field-label">Marcadores</legend>

        <label class="field-check">
            <input type="checkbox" name="active" value="1" <?= $values['active'] === 1 ? 'checked' : '' ?>>
            <span>Produto ativo (aparece na loja)</span>
        </label>

        <label class="field-check">
            <input type="checkbox" name="featured" value="1" <?= $values['featured'] === 1 ? 'checked' : '' ?>>
            <span>Destaque</span>
        </label>

        <label class="field-check">
            <input type="checkbox" name="is_new" value="1" <?= $values['is_new'] === 1 ? 'checked' : '' ?>>
            <span>Novidade</span>
        </label>

        <label class="field-check">
            <input type="checkbox" name="best_seller" value="1" <?= $values['best_seller'] === 1 ? 'checked' : '' ?>>
            <span>Mais vendido</span>
        </label>
    </fieldset>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">
            <?= $isEdit ? 'Salvar alteracoes' : 'Criar produto' ?>
        </button>
        <a class="btn btn-ghost" href="<?= e(base_url('admin/products.php')) ?>">Cancelar</a>
    </div>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
