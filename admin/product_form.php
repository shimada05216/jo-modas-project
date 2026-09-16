<?php
/**
 * Jo Modas - Painel: cadastro unificado de produto
 *
 * Sem ?id  -> cria um produto novo
 * Com ?id  -> edita o produto informado
 *
 * Tudo numa tela so: dados gerais, categoria, precos, imagens, variacoes
 * com estoque e marcadores. O lojista preenche e salva uma vez; nao
 * precisa mais passar por product_images.php e product_variants.php, que
 * continuam existindo como caminho alternativo.
 *
 * GRAVACAO
 * Valida tudo primeiro, inclusive os arquivos enviados. So depois abre
 * transacao, grava produto, variacoes e registros de imagem, e confirma.
 *
 * ARQUIVOS x TRANSACAO
 * O MySQL nao desfaz operacao de disco. Por isso:
 *   - arquivo novo so e gravado dentro do bloco protegido, e o nome vai
 *     para uma lista; se a transacao falhar, a lista e percorrida e os
 *     arquivos recem-criados sao apagados;
 *   - arquivo de imagem removida so sai do disco DEPOIS do commit, nunca
 *     antes, para nao sobrar registro apontando para arquivo inexistente
 *     caso a transacao volte atras.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin();

const PRODUCT_NAME_MAX        = 150;
const PRODUCT_SLUG_MAX        = 180;
const PRODUCT_SKU_MAX         = 60;
const PRODUCT_DESCRIPTION_MAX = 20000;
const PRODUCT_PRICE_MAX       = 99999999.99;

const VARIANT_COLOR_MAX = 40;
const VARIANT_SIZE_MAX  = 20;
const VARIANT_SKU_MAX   = 60;
const VARIANT_STOCK_MAX = 999999;

const MAX_IMAGES_PER_PRODUCT = 12;

$id      = input_int('id');
$isEdit  = false;
$product = null;

if ($id !== null && $id > 0) {
    $stmt = db()->prepare(
        'SELECT id, category_id, name, slug, sku, description, price, promo_price,
                active, show_without_stock, featured, is_new, best_seller
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

$categories = db()->query(
    'SELECT id, name, active FROM categories ORDER BY sort_order ASC, name ASC'
)->fetchAll();

if ($categories === [] && !$isEdit) {
    flash('error', 'Cadastre ao menos uma categoria antes de criar produtos.');
    redirect(base_url('admin/categories.php'));
}

// ---------------------------------------------------------
// Estado exibido no formulario
// ---------------------------------------------------------

$values = [
    'category_id'        => isset($product['category_id']) ? (int) $product['category_id'] : 0,
    'name'               => $product['name']        ?? '',
    'slug'               => $product['slug']        ?? '',
    'sku'                => $product['sku']         ?? '',
    'description'        => $product['description'] ?? '',
    'price'              => isset($product['price']) ? number_format((float) $product['price'], 2, ',', '') : '',
    'promo_price'        => isset($product['promo_price']) && $product['promo_price'] !== null
        ? number_format((float) $product['promo_price'], 2, ',', '') : '',
    'active'             => isset($product['active'])             ? (int) $product['active']             : 1,
    // Ao EDITAR vale exatamente o que esta gravado.
    //
    // Ao CRIAR no modo de compra simples a caixa ja nasce marcada: ali
    // variacao e opcional, e sem isso o produto recem-criado sem cor e
    // tamanho nao apareceria na loja. E so um padrao -- desmarcar
    // esconde o produto, como deve ser.
    'show_without_stock' => isset($product['show_without_stock'])
        ? (int) $product['show_without_stock']
        : (REQUIRE_VARIANT_SELECTION ? 0 : 1),
    'featured'           => isset($product['featured'])           ? (int) $product['featured']           : 0,
    'is_new'             => isset($product['is_new'])             ? (int) $product['is_new']             : 0,
    'best_seller'        => isset($product['best_seller'])        ? (int) $product['best_seller']        : 0,
];

$images = [];

if ($isEdit) {
    $imgStmt = db()->prepare(
        'SELECT id, filename, is_main, sort_order FROM product_images
          WHERE product_id = ? ORDER BY is_main DESC, sort_order ASC, id ASC'
    );
    $imgStmt->execute([$id]);
    $images = $imgStmt->fetchAll();
}

$variantRows = [];

if ($isEdit) {
    $varStmt = db()->prepare(
        'SELECT id, color, size, sku, stock, active FROM product_variants
          WHERE product_id = ? ORDER BY color ASC, size_order ASC, size ASC'
    );
    $varStmt->execute([$id]);

    foreach ($varStmt->fetchAll() as $row) {
        $variantRows[] = [
            'id'     => (string) $row['id'],
            'color'  => $row['color'],
            'size'   => $row['size'],
            'sku'    => (string) ($row['sku'] ?? ''),
            'stock'  => (string) $row['stock'],
            'active' => (int) $row['active'],
        ];
    }
}

$colorOptions = distinct_variant_colors();
$sizeOptions  = distinct_variant_sizes();

$errors      = [];
$imageNotice = false;

// =========================================================
// Gravacao
// =========================================================

if (is_post()) {
    require_csrf();

    // ---------- dados gerais ----------
    $values['category_id']        = (int) (input_int('category_id') ?? 0);
    $values['name']               = post('name');
    $values['slug']               = post('slug');
    $values['sku']                = post('sku');
    $values['description']        = post('description');
    $values['price']              = post('price');
    $values['promo_price']        = post('promo_price');
    $values['active']             = to_bool_int($_POST['active']             ?? 0);
    $values['show_without_stock'] = to_bool_int($_POST['show_without_stock'] ?? 0);
    $values['featured']           = to_bool_int($_POST['featured']           ?? 0);
    $values['is_new']             = to_bool_int($_POST['is_new']             ?? 0);
    $values['best_seller']        = to_bool_int($_POST['best_seller']        ?? 0);

    if ($values['category_id'] < 1) {
        $errors[] = 'Escolha uma categoria.';
    } else {
        $check = db()->prepare('SELECT id FROM categories WHERE id = ? LIMIT 1');
        $check->execute([$values['category_id']]);

        if ($check->fetch() === false) {
            $errors[] = 'A categoria escolhida não existe mais.';
        }
    }

    if ($values['name'] === '') {
        $errors[] = 'Informe o nome do produto.';
    } elseif (mb_strlen($values['name']) > PRODUCT_NAME_MAX) {
        $errors[] = 'O nome deve ter no máximo ' . PRODUCT_NAME_MAX . ' caracteres.';
    }

    if (mb_strlen($values['description']) > PRODUCT_DESCRIPTION_MAX) {
        $errors[] = 'A descrição deve ter no máximo ' . PRODUCT_DESCRIPTION_MAX . ' caracteres.';
    }

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

    // ---------- precos ----------
    $price = null;

    if ($values['price'] === '') {
        $errors[] = 'Informe o preço.';
    } else {
        $price = parse_price($values['price']);

        if ($price === null) {
            $errors[] = 'Preço inválido. Use apenas números, por exemplo 199,90.';
        } elseif ($price <= 0) {
            $errors[] = 'O preço deve ser maior que zero.';
        } elseif ($price > PRODUCT_PRICE_MAX) {
            $errors[] = 'O preço excede o máximo permitido.';
        }
    }

    $promoPrice = null;

    if ($values['promo_price'] !== '') {
        $promoPrice = parse_price($values['promo_price']);

        if ($promoPrice === null) {
            $errors[] = 'Preço promocional inválido.';
        } elseif ($promoPrice <= 0) {
            $errors[] = 'O preço promocional deve ser maior que zero. Deixe em branco para remover.';
        } elseif ($price !== null && $promoPrice >= $price) {
            $errors[] = 'O preço promocional deve ser menor que o preço normal.';
        }
    }

    // ---------- variacoes ----------
    $submitted      = isset($_POST['variants']) && is_array($_POST['variants']) ? $_POST['variants'] : [];
    $variantRows    = [];
    $parsedVariants = [];
    $comboSeen      = [];

    foreach ($submitted as $row) {
        if (!is_array($row)) {
            continue;
        }

        $rowColor = trim((string) ($row['color'] ?? ''));
        $rowSize  = trim((string) ($row['size']  ?? ''));
        $rowSku   = trim((string) ($row['sku']   ?? ''));
        $rowStock = trim((string) ($row['stock'] ?? ''));
        $rowId    = filter_var($row['id'] ?? '', FILTER_VALIDATE_INT);

        // Linha em branco: ou o usuario adicionou e desistiu, ou e a linha
        // que o formulario ja mostra pronta e ele nem tocou.
        //
        // O estoque conta como vazio quando e '' OU '0': a linha nasce com
        // zero preenchido, e exigir '' aqui fazia a linha intocada parecer
        // uma variacao de verdade. O cadastro entao recusava com "Toda
        // variacao precisa de cor e tamanho", o navegador limpava os
        // arquivos escolhidos, e quem tentasse de novo salvava o produto
        // sem as imagens. Variacao e opcional: linha vazia simplesmente sai.
        $rowStockEmpty = $rowStock === '' || $rowStock === '0';

        if ($rowColor === '' && $rowSize === '' && $rowSku === '' && $rowStockEmpty) {
            continue;
        }

        $variantRows[] = [
            'id'     => $rowId !== false ? (string) $rowId : '',
            'color'  => $rowColor,
            'size'   => $rowSize,
            'sku'    => $rowSku,
            'stock'  => $rowStock,
            'active' => isset($row['active']) ? 1 : 0,
        ];

        if ($rowColor === '' || $rowSize === '') {
            $errors[] = 'Toda variação precisa de cor e tamanho.';
            continue;
        }

        if (mb_strlen($rowColor) > VARIANT_COLOR_MAX || mb_strlen($rowSize) > VARIANT_SIZE_MAX) {
            $errors[] = 'Cor ou tamanho longo demais em ' . $rowColor . ' / ' . $rowSize . '.';
            continue;
        }

        $stock = $rowStock === '' ? 0 : filter_var($rowStock, FILTER_VALIDATE_INT);

        if ($stock === false) {
            $errors[] = 'O estoque de ' . $rowColor . ' / ' . $rowSize . ' deve ser um número inteiro.';
            continue;
        }

        if ($stock < 0) {
            $errors[] = 'O estoque de ' . $rowColor . ' / ' . $rowSize . ' não pode ser negativo.';
            continue;
        }

        if ($stock > VARIANT_STOCK_MAX) {
            $errors[] = 'O estoque de ' . $rowColor . ' / ' . $rowSize . ' excede o máximo.';
            continue;
        }

        // Duplicidade dentro do proprio formulario. A chave unica do banco
        // e a garantia final; aqui a mensagem fica compreensivel.
        $comboKey = mb_strtolower($rowColor . '|' . $rowSize);

        if (isset($comboSeen[$comboKey])) {
            $errors[] = 'A combinação ' . $rowColor . ' / ' . $rowSize . ' aparece mais de uma vez.';
            continue;
        }

        $comboSeen[$comboKey] = true;

        $parsedVariants[] = [
            'id'     => $rowId !== false && $rowId > 0 ? $rowId : null,
            'color'  => $rowColor,
            'size'   => $rowSize,
            'sku'    => $rowSku !== '' ? $rowSku : null,
            'stock'  => $stock,
            'active' => isset($row['active']) ? 1 : 0,
        ];
    }

    // SKU de variacao e unico na loja inteira. Duas conferencias: contra o
    // que ja esta no banco, e contra o proprio formulario. Sem a segunda,
    // duas linhas novas com o mesmo SKU passavam daqui e so quebravam no
    // INSERT, virando uma mensagem generica de conflito.
    $skuSeen = [];

    foreach ($parsedVariants as $variant) {
        if ($variant['sku'] === null) {
            continue;
        }

        $skuKey = mb_strtolower($variant['sku']);

        if (isset($skuSeen[$skuKey])) {
            $errors[] = 'O SKU "' . $variant['sku'] . '" aparece em mais de uma variação.';
            continue;
        }

        $skuSeen[$skuKey] = true;

        $dupSku = db()->prepare('SELECT id FROM product_variants WHERE sku = ? AND id <> ? LIMIT 1');
        $dupSku->execute([$variant['sku'], $variant['id'] ?? 0]);

        if ($dupSku->fetch() !== false) {
            $errors[] = 'O SKU "' . $variant['sku'] . '" já está em uso por outra variação.';
        }
    }

    // ---------- imagens ----------
    // Validadas ANTES de qualquer gravacao: nada vai para o disco enquanto
    // houver erro em qualquer parte do formulario.
    $uploads   = normalize_files_array($_FILES['images'] ?? []);
    $pending   = [];
    $deleteIds = isset($_POST['image_delete']) && is_array($_POST['image_delete'])
        ? array_values(array_filter(array_map('intval', $_POST['image_delete'])))
        : [];
    $keptCount = count($images) - count($deleteIds);

    foreach ($uploads as $file) {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }

        if ($keptCount + count($pending) >= MAX_IMAGES_PER_PRODUCT) {
            $errors[] = 'Limite de ' . MAX_IMAGES_PER_PRODUCT . ' imagens por produto.';
            break;
        }

        $problem = validate_image_upload($file);

        if ($problem !== null) {
            $errors[] = $problem;
            continue;
        }

        $pending[] = $file;
    }

    if ($errors !== [] && $pending !== []) {
        $imageNotice = true;
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

    // ---------- gravacao atomica ----------
    if ($errors === []) {
        $written    = [];   // arquivos criados nesta requisicao
        $toUnlink   = [];   // arquivos a apagar SO depois do commit
        $productId  = $id;
        $priceParam = number_format($price, 2, '.', '');
        $promoParam = $promoPrice === null ? null : number_format($promoPrice, 2, '.', '');
        $saved      = false;

        db()->beginTransaction();

        try {
            if ($isEdit) {
                $save = db()->prepare(
                    'UPDATE products
                        SET category_id = ?, name = ?, slug = ?, sku = ?, description = ?,
                            price = ?, promo_price = ?, active = ?, show_without_stock = ?,
                            featured = ?, is_new = ?, best_seller = ?
                      WHERE id = ?'
                );
                $save->execute([
                    $values['category_id'], $values['name'], $slug,
                    $values['sku'] !== '' ? $values['sku'] : null,
                    $values['description'] !== '' ? $values['description'] : null,
                    $priceParam, $promoParam, $values['active'], $values['show_without_stock'],
                    $values['featured'], $values['is_new'], $values['best_seller'],
                    $id,
                ]);
                $productId = (int) $id;
            } else {
                $save = db()->prepare(
                    'INSERT INTO products
                        (category_id, name, slug, sku, description, price, promo_price,
                         active, show_without_stock, featured, is_new, best_seller)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $save->execute([
                    $values['category_id'], $values['name'], $slug,
                    $values['sku'] !== '' ? $values['sku'] : null,
                    $values['description'] !== '' ? $values['description'] : null,
                    $priceParam, $promoParam, $values['active'], $values['show_without_stock'],
                    $values['featured'], $values['is_new'], $values['best_seller'],
                ]);
                $productId = (int) db()->lastInsertId();
            }

            // --- variacoes ---
            // Apaga primeiro as que sairam do formulario, para liberar a
            // chave unica antes das atualizacoes.
            $keepIds = [];

            foreach ($parsedVariants as $variant) {
                if ($variant['id'] !== null) {
                    $keepIds[] = $variant['id'];
                }
            }

            if ($isEdit) {
                if ($keepIds === []) {
                    $del = db()->prepare('DELETE FROM product_variants WHERE product_id = ?');
                    $del->execute([$productId]);
                } else {
                    $marks = implode(',', array_fill(0, count($keepIds), '?'));
                    $del = db()->prepare(
                        'DELETE FROM product_variants WHERE product_id = ? AND id NOT IN (' . $marks . ')'
                    );
                    $del->execute(array_merge([$productId], $keepIds));
                }
            }

            foreach ($parsedVariants as $variant) {
                $order = size_sort_order($variant['size']);

                if ($variant['id'] !== null) {
                    $upd = db()->prepare(
                        'UPDATE product_variants
                            SET color = ?, size = ?, size_order = ?, sku = ?, stock = ?, active = ?
                          WHERE id = ? AND product_id = ?'
                    );
                    $upd->execute([
                        $variant['color'], $variant['size'], $order, $variant['sku'],
                        $variant['stock'], $variant['active'], $variant['id'], $productId,
                    ]);
                } else {
                    $ins = db()->prepare(
                        'INSERT INTO product_variants
                            (product_id, color, size, size_order, sku, stock, active)
                         VALUES (?, ?, ?, ?, ?, ?, ?)'
                    );
                    $ins->execute([
                        $productId, $variant['color'], $variant['size'], $order,
                        $variant['sku'], $variant['stock'], $variant['active'],
                    ]);
                }
            }

            // --- imagens removidas ---
            // Colhe os nomes agora; o arquivo so sai do disco apos o commit.
            if ($deleteIds !== []) {
                $marks = implode(',', array_fill(0, count($deleteIds), '?'));

                $find = db()->prepare(
                    'SELECT filename FROM product_images WHERE product_id = ? AND id IN (' . $marks . ')'
                );
                $find->execute(array_merge([$productId], $deleteIds));
                $toUnlink = $find->fetchAll(PDO::FETCH_COLUMN);

                $rm = db()->prepare(
                    'DELETE FROM product_images WHERE product_id = ? AND id IN (' . $marks . ')'
                );
                $rm->execute(array_merge([$productId], $deleteIds));
            }

            // --- imagens novas ---
            $orderStmt = db()->prepare(
                'SELECT COALESCE(MAX(sort_order), 0) FROM product_images WHERE product_id = ?'
            );
            $orderStmt->execute([$productId]);
            $nextOrder = (int) $orderStmt->fetchColumn() + 1;

            foreach ($pending as $file) {
                $filename = save_uploaded_image($file, mb_substr($values['name'], 0, 40));

                if ($filename === null) {
                    throw new RuntimeException('Não foi possível gravar uma das imagens.');
                }

                $written[] = $filename;

                $insImg = db()->prepare(
                    'INSERT INTO product_images (product_id, filename, is_main, sort_order)
                     VALUES (?, ?, 0, ?)'
                );
                $insImg->execute([$productId, $filename, $nextOrder]);
                $nextOrder++;
            }

            // --- imagem principal ---
            $wantedMain = input_int('image_main');

            if ($wantedMain !== null && $wantedMain > 0 && !in_array($wantedMain, $deleteIds, true)) {
                // Limpa antes de marcar: main_marker tem chave unica, entao
                // duas principais ao mesmo tempo seriam recusadas.
                $clear = db()->prepare(
                    'UPDATE product_images SET is_main = 0 WHERE product_id = ? AND is_main = 1'
                );
                $clear->execute([$productId]);

                $setMain = db()->prepare(
                    'UPDATE product_images SET is_main = 1 WHERE id = ? AND product_id = ?'
                );
                $setMain->execute([$wantedMain, $productId]);
            }

            $mainStmt = db()->prepare(
                'SELECT COUNT(*) FROM product_images WHERE product_id = ? AND is_main = 1'
            );
            $mainStmt->execute([$productId]);

            if ((int) $mainStmt->fetchColumn() === 0) {
                // Sem principal definida, promove a primeira da fila.
                $first = db()->prepare(
                    'SELECT id FROM product_images WHERE product_id = ?
                      ORDER BY sort_order ASC, id ASC LIMIT 1'
                );
                $first->execute([$productId]);
                $firstId = $first->fetchColumn();

                if ($firstId !== false) {
                    $promote = db()->prepare('UPDATE product_images SET is_main = 1 WHERE id = ?');
                    $promote->execute([(int) $firstId]);
                }
            }

            db()->commit();
            $saved = true;
        } catch (Throwable $e) {
            db()->rollBack();

            // A transacao voltou atras, mas os arquivos ja gravados
            // continuariam no disco. Saem aqui.
            foreach ($written as $orphan) {
                delete_product_image_file($orphan);
            }

            if ($e instanceof PDOException && $e->getCode() === '23000') {
                $errors[] = 'Conflito de dados: endereço, SKU ou combinação cor/tamanho já existe.';
            } else {
                throw $e;
            }
        }

        if ($saved) {
            // Só agora, com o commit confirmado, os arquivos das imagens
            // removidas saem do disco.
            foreach ($toUnlink as $gone) {
                delete_product_image_file($gone);
            }

            flash('success', $isEdit ? 'Produto atualizado.' : 'Produto criado.');
            redirect(base_url('admin/products.php'));
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
               href="<?= e(base_url('admin/product_images.php?product_id=' . (int) $id)) ?>">Só imagens</a>
            <a class="btn btn-ghost"
               href="<?= e(base_url('admin/product_variants.php?product_id=' . (int) $id)) ?>">Só variações</a>
        <?php endif; ?>
        <a class="btn btn-ghost" href="<?= e(base_url('admin/products.php')) ?>">Voltar</a>
    </div>
</div>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endforeach; ?>

<?php if ($imageNotice): ?>
    <div class="alert alert-info">
        Por segurança o navegador não mantém os arquivos escolhidos quando o
        formulário volta com erro. Selecione as imagens novamente antes de salvar.
    </div>
<?php endif; ?>

<datalist id="color-options">
    <?php foreach ($colorOptions as $option): ?>
        <option value="<?= e($option) ?>"></option>
    <?php endforeach; ?>
</datalist>

<datalist id="size-options">
    <?php foreach ($sizeOptions as $option): ?>
        <option value="<?= e($option) ?>"></option>
    <?php endforeach; ?>
</datalist>

<form method="post" enctype="multipart/form-data" id="product-form"
      action="<?= e(base_url('admin/product_form.php' . ($isEdit ? '?id=' . (int) $id : ''))) ?>">
    <?= csrf_field() ?>

    <section class="form-card">
        <h2 class="form-section">Informações básicas</h2>

        <label class="field">
            <span class="field-label">Nome *</span>
            <input type="text" name="name" id="product-name" value="<?= e($values['name']) ?>"
                   maxlength="<?= e((string) PRODUCT_NAME_MAX) ?>" required autofocus>
        </label>

        <div class="field-row">
            <label class="field">
                <span class="field-label">Endereço (URL)</span>
                <input type="text" name="slug" id="product-slug" value="<?= e($values['slug']) ?>"
                       maxlength="<?= e((string) PRODUCT_SLUG_MAX) ?>">
                <span class="field-hint">Gerado automaticamente pelo nome do produto.</span>
            </label>

            <label class="field">
                <span class="field-label">SKU</span>
                <input type="text" name="sku" value="<?= e($values['sku']) ?>"
                       maxlength="<?= e((string) PRODUCT_SKU_MAX) ?>">
                <span class="field-hint">
                    SKU é o código interno do produto para organização e controle.
                </span>
            </label>
        </div>

        <label class="field">
            <span class="field-label">Descrição</span>
            <textarea name="description" rows="5"><?= e($values['description']) ?></textarea>
        </label>
    </section>

    <section class="form-card">
        <h2 class="form-section">Categoria</h2>

        <div class="field">
            <span class="field-label">Categoria *</span>
            <div class="inline-control">
                <select name="category_id" id="category-select" required>
                    <option value="">Escolha uma categoria</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= e((string) $category['id']) ?>"
                            <?= $values['category_id'] === (int) $category['id'] ? 'selected' : '' ?>>
                            <?= e($category['name']) ?><?= (int) $category['active'] === 0 ? ' (inativa)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="btn btn-ghost btn-plus" id="category-add"
                        aria-label="Criar nova categoria" title="Criar nova categoria">+</button>
            </div>
        </div>
    </section>

    <section class="form-card">
        <h2 class="form-section">Preços</h2>

        <div class="field-row">
            <label class="field">
                <span class="field-label">Preço *</span>
                <input type="text" name="price" value="<?= e($values['price']) ?>"
                       inputmode="decimal" placeholder="199,90" required>
            </label>

            <label class="field">
                <span class="field-label">Preço promocional</span>
                <input type="text" name="promo_price" value="<?= e($values['promo_price']) ?>"
                       inputmode="decimal" placeholder="deixe em branco se não houver">
                <span class="field-hint">Precisa ser menor que o preço normal.</span>
            </label>
        </div>
    </section>

    <section class="form-card">
        <h2 class="form-section">Imagens</h2>

        <?php if ($images !== []): ?>
            <div class="img-grid">
                <?php foreach ($images as $image): ?>
                    <figure class="img-item<?= (int) $image['is_main'] === 1 ? ' is-main' : '' ?>">
                        <img src="<?= e(product_image_url($image['filename'])) ?>"
                             alt="Imagem do produto" loading="lazy">
                        <figcaption>
                            <label class="img-opt">
                                <input type="radio" name="image_main"
                                       value="<?= e((string) $image['id']) ?>"
                                    <?= (int) $image['is_main'] === 1 ? 'checked' : '' ?>>
                                Principal
                            </label>
                            <label class="img-opt">
                                <input type="checkbox" name="image_delete[]"
                                       value="<?= e((string) $image['id']) ?>">
                                Remover
                            </label>
                        </figcaption>
                    </figure>
                <?php endforeach; ?>
            </div>
        <?php elseif ($isEdit): ?>
            <p class="field-hint">Nenhuma imagem enviada ainda.</p>
        <?php endif; ?>

        <label class="field">
            <span class="field-label">Adicionar imagens</span>
            <?php /* data-*: limites reais do servidor, para a otimizacao no navegador. */ ?>
            <input type="file" name="images[]" multiple
                   accept="image/jpeg,image/png,image/webp"
                   data-optimize-images
                   data-max-bytes="<?= e((string) effective_upload_limit()) ?>"
                   data-max-post="<?= e((string) effective_post_limit()) ?>">
            <span class="field-hint">
                JPG, PNG ou WEBP. Fotos grandes são redimensionadas e comprimidas
                automaticamente antes do envio.
                Limite final do servidor: <?= e(format_bytes(effective_upload_limit())) ?> por imagem.
                Máximo de <?= e((string) MAX_IMAGES_PER_PRODUCT) ?> por produto.
                As imagens são salvas junto com o produto.
            </span>
        </label>
    </section>

    <section class="form-card">
        <h2 class="form-section">Variações e estoque (opcional)</h2>
        <p class="field-hint">
            Deixe em branco se a peça não tem cor e tamanho para escolher:
            o produto é vendido assim mesmo. Se preencher, cada linha precisa
            de <strong>cor e tamanho</strong> juntos, e o estoque passa a
            pertencer a essa combinação.
        </p>

        <div id="variant-rows">
            <?php
            $renderRows = $variantRows !== [] ? $variantRows : [[
                'id' => '', 'color' => '', 'size' => '', 'sku' => '', 'stock' => '0', 'active' => 1,
            ]];

            foreach ($renderRows as $i => $row):
            ?>
                <div class="variant-line" data-index="<?= e((string) $i) ?>">
                    <input type="hidden" name="variants[<?= e((string) $i) ?>][id]"
                           value="<?= e($row['id']) ?>">

                    <label class="field">
                        <span class="field-label">Cor</span>
                        <input type="text" name="variants[<?= e((string) $i) ?>][color]"
                               value="<?= e($row['color']) ?>" list="color-options"
                               maxlength="<?= e((string) VARIANT_COLOR_MAX) ?>" placeholder="Preto">
                    </label>

                    <label class="field">
                        <span class="field-label">Tamanho</span>
                        <input type="text" name="variants[<?= e((string) $i) ?>][size]"
                               value="<?= e($row['size']) ?>" list="size-options"
                               maxlength="<?= e((string) VARIANT_SIZE_MAX) ?>" placeholder="M">
                    </label>

                    <label class="field">
                        <span class="field-label">SKU</span>
                        <input type="text" name="variants[<?= e((string) $i) ?>][sku]"
                               value="<?= e($row['sku']) ?>"
                               maxlength="<?= e((string) VARIANT_SKU_MAX) ?>">
                    </label>

                    <label class="field field-stock">
                        <span class="field-label">Estoque</span>
                        <input type="number" name="variants[<?= e((string) $i) ?>][stock]"
                               value="<?= e($row['stock']) ?>" min="0"
                               max="<?= e((string) VARIANT_STOCK_MAX) ?>" step="1">
                    </label>

                    <label class="field-check">
                        <input type="checkbox" name="variants[<?= e((string) $i) ?>][active]" value="1"
                            <?= (int) $row['active'] === 1 ? 'checked' : '' ?>>
                        <span>Ativa</span>
                    </label>

                    <div class="variant-line-actions">
                        <button type="button" class="btn btn-sm btn-ghost js-variant-dup">Duplicar</button>
                        <button type="button" class="btn btn-sm btn-danger js-variant-del">Remover</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <button type="button" class="btn btn-ghost" id="variant-add">+ Adicionar variação</button>
    </section>

    <section class="form-card">
        <h2 class="form-section">Exibição e marcadores</h2>

        <label class="field-check">
            <input type="checkbox" name="active" value="1" <?= $values['active'] === 1 ? 'checked' : '' ?>>
            <span>Produto ativo (aparece na loja)</span>
        </label>

        <label class="field-check">
            <input type="checkbox" name="show_without_stock" value="1"
                <?= $values['show_without_stock'] === 1 ? 'checked' : '' ?>>
            <span>Exibir produto mesmo sem estoque cadastrado</span>
        </label>
        <p class="field-hint field-hint-indent">
            Mantém o produto na vitrine mesmo sem variação com estoque.
            <?php if (REQUIRE_VARIANT_SELECTION): ?>
                Ele aparece como <strong>Indisponível no momento</strong> e não pode
                ser comprado.
            <?php else: ?>
                Como a loja está no modo de compra simples, ele continua podendo ser
                comprado. <strong>Desmarque para tirar o produto da vitrine</strong>
                enquanto não houver estoque.
            <?php endif; ?>
        </p>

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
    </section>

    <div class="form-actions form-actions-sticky">
        <button type="submit" class="btn btn-primary">
            <?= $isEdit ? 'Salvar alterações' : 'Criar produto' ?>
        </button>
        <a class="btn btn-ghost" href="<?= e(base_url('admin/products.php')) ?>">Cancelar</a>
    </div>
</form>

<div class="modal" id="category-modal" hidden>
    <div class="modal-box" role="dialog" aria-modal="true" aria-labelledby="category-modal-title">
        <h2 class="form-section" id="category-modal-title">Nova categoria</h2>

        <div class="alert alert-error" id="category-modal-error" hidden></div>

        <label class="field">
            <span class="field-label">Nome da categoria *</span>
            <input type="text" id="category-modal-name" maxlength="100">
        </label>

        <div class="form-actions">
            <button type="button" class="btn btn-primary" id="category-modal-save">Criar</button>
            <button type="button" class="btn btn-ghost" id="category-modal-cancel">Cancelar</button>
        </div>
    </div>
</div>

<script>
    window.JOMODAS_ADMIN = {
        categoryEndpoint: <?= json_encode(base_url('admin/ajax_category.php'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        csrf: <?= json_encode(csrf_token(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
        slugAuto: <?= $isEdit ? 'false' : 'true' ?>,

        // Mesmos limites que o servidor aplica. Aqui servem so para
        // avisar antes do envio; quem decide continua sendo o PHP.
        maxUpload: <?= (int) effective_upload_limit() ?>,
        maxPost: <?= (int) effective_post_limit() ?>,
        maxImages: <?= (int) MAX_IMAGES_PER_PRODUCT ?>
    };
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
