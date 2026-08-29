<?php
/**
 * Jo Modas - Painel: variacoes do produto
 *
 * Uma variacao e a combinacao produto + cor + tamanho, e e nela que mora
 * o estoque. Nenhuma tela do site desconta estoque sozinha: o checkout
 * pelo WhatsApp so monta a mensagem, e a baixa e feita aqui, a mao,
 * depois que a venda se confirma.
 *
 * A combinacao cor + tamanho e unica por produto. A garantia final e a
 * chave uq_variants_combo; as conferencias abaixo existem para virar
 * mensagem em vez de erro de banco.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin();

const VARIANT_COLOR_MAX = 40;
const VARIANT_SIZE_MAX  = 20;
const VARIANT_SKU_MAX   = 60;
const VARIANT_STOCK_MAX = 999999;

$productId = input_int('product_id');

if ($productId === null || $productId < 1) {
    flash('error', 'Produto invalido.');
    redirect(base_url('admin/products.php'));
}

$stmt = db()->prepare('SELECT id, name FROM products WHERE id = ? LIMIT 1');
$stmt->execute([$productId]);
$product = $stmt->fetch();

if ($product === false) {
    flash('error', 'Produto nao encontrado.');
    redirect(base_url('admin/products.php'));
}

$redirect = base_url('admin/product_variants.php?product_id=' . $productId);

/**
 * Le e confere os campos de uma variacao vindos do POST.
 * Devolve [valores, erros].
 *
 * $variantId e o id da propria variacao ao editar, para que ela nao
 * seja acusada de conflitar consigo mesma.
 */
function read_variant_input(int $productId, ?int $variantId): array
{
    $values = [
        'color'  => post('color'),
        'size'   => post('size'),
        'sku'    => post('sku'),
        'stock'  => post('stock'),
        'active' => to_bool_int($_POST['active'] ?? 0),
    ];

    $errors = [];

    // ---------- cor ----------
    if ($values['color'] === '') {
        $errors[] = 'Informe a cor.';
    } elseif (mb_strlen($values['color']) > VARIANT_COLOR_MAX) {
        $errors[] = 'A cor deve ter no maximo ' . VARIANT_COLOR_MAX . ' caracteres.';
    }

    // ---------- tamanho ----------
    if ($values['size'] === '') {
        $errors[] = 'Informe o tamanho.';
    } elseif (mb_strlen($values['size']) > VARIANT_SIZE_MAX) {
        $errors[] = 'O tamanho deve ter no maximo ' . VARIANT_SIZE_MAX . ' caracteres.';
    }

    // ---------- estoque ----------
    // Campo vazio vale zero. Qualquer outro texto que nao seja inteiro
    // devolve null em input_int e cai no erro abaixo.
    if ($values['stock'] === '') {
        $stock = 0;
    } else {
        $stock = input_int('stock');
    }

    if ($stock === null) {
        $errors[] = 'O estoque deve ser um numero inteiro.';
        $stock = 0;
    } elseif ($stock < 0) {
        // A coluna e INT UNSIGNED: sem esta conferencia o MySQL recusaria
        // (modo estrito) ou gravaria zero calado (modo permissivo).
        $errors[] = 'O estoque nao pode ser negativo.';
    } elseif ($stock > VARIANT_STOCK_MAX) {
        $errors[] = 'O estoque deve ser no maximo ' . VARIANT_STOCK_MAX . '.';
    }

    $values['stock'] = $stock;

    // ---------- SKU ----------
    if ($values['sku'] !== '') {
        if (mb_strlen($values['sku']) > VARIANT_SKU_MAX) {
            $errors[] = 'O SKU deve ter no maximo ' . VARIANT_SKU_MAX . ' caracteres.';
        } else {
            // O SKU e unico na loja inteira, nao so dentro do produto.
            $dup = db()->prepare('SELECT id FROM product_variants WHERE sku = ? AND id <> ? LIMIT 1');
            $dup->execute([$values['sku'], $variantId ?? 0]);

            if ($dup->fetch() !== false) {
                $errors[] = 'Ja existe uma variacao com o SKU "' . $values['sku'] . '".';
            }
        }
    }

    // ---------- combinacao repetida ----------
    // A collation utf8mb4_unicode_ci ignora caixa e acento, do mesmo jeito
    // que a chave unica: "Preto"/"preto" contam como a mesma cor.
    if ($values['color'] !== '' && $values['size'] !== '') {
        $combo = db()->prepare(
            'SELECT id FROM product_variants
              WHERE product_id = ? AND color = ? AND size = ? AND id <> ? LIMIT 1'
        );
        $combo->execute([$productId, $values['color'], $values['size'], $variantId ?? 0]);

        if ($combo->fetch() !== false) {
            $errors[] = sprintf(
                'A combinacao %s / %s ja existe neste produto.',
                $values['color'],
                $values['size']
            );
        }
    }

    return [$values, $errors];
}

if (is_post()) {
    require_csrf();

    $action = post('action');

    // ---------------------------------------------------------
    // Criar
    // ---------------------------------------------------------
    if ($action === 'create') {
        [$values, $errors] = read_variant_input($productId, null);

        if ($errors === []) {
            try {
                $insert = db()->prepare(
                    'INSERT INTO product_variants
                        (product_id, color, size, size_order, sku, stock, active)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $insert->execute([
                    $productId,
                    $values['color'],
                    $values['size'],
                    size_sort_order($values['size']),
                    $values['sku'] !== '' ? $values['sku'] : null,
                    $values['stock'],
                    $values['active'],
                ]);

                flash('success', sprintf(
                    'Variacao %s / %s criada.',
                    $values['color'],
                    $values['size']
                ));
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $errors[] = 'Essa combinacao ou esse SKU ja existe. Tente novamente.';
                } else {
                    throw $e;
                }
            }
        }

        foreach ($errors as $error) {
            flash('error', $error);
        }

        redirect($redirect);
    }

    // ---------------------------------------------------------
    // Editar
    // ---------------------------------------------------------
    if ($action === 'update') {
        $variantId = input_int('variant_id');

        if ($variantId === null || $variantId < 1) {
            flash('error', 'Variacao invalida.');
            redirect($redirect);
        }

        // O product_id na condicao impede editar a variacao de outro produto.
        $check = db()->prepare(
            'SELECT id FROM product_variants WHERE id = ? AND product_id = ? LIMIT 1'
        );
        $check->execute([$variantId, $productId]);

        if ($check->fetch() === false) {
            flash('error', 'Variacao nao encontrada neste produto.');
            redirect($redirect);
        }

        [$values, $errors] = read_variant_input($productId, $variantId);

        if ($errors === []) {
            try {
                $update = db()->prepare(
                    'UPDATE product_variants
                        SET color = ?, size = ?, size_order = ?, sku = ?, stock = ?, active = ?
                      WHERE id = ? AND product_id = ?'
                );
                $update->execute([
                    $values['color'],
                    $values['size'],
                    size_sort_order($values['size']),
                    $values['sku'] !== '' ? $values['sku'] : null,
                    $values['stock'],
                    $values['active'],
                    $variantId,
                    $productId,
                ]);

                flash('success', 'Variacao atualizada.');
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $errors[] = 'Essa combinacao ou esse SKU ja existe. Tente novamente.';
                } else {
                    throw $e;
                }
            }
        }

        foreach ($errors as $error) {
            flash('error', $error);
        }

        redirect($redirect);
    }

    // ---------------------------------------------------------
    // Apagar
    // ---------------------------------------------------------
    if ($action === 'delete') {
        $variantId = input_int('variant_id');

        if ($variantId === null || $variantId < 1) {
            flash('error', 'Variacao invalida.');
            redirect($redirect);
        }

        $delete = db()->prepare('DELETE FROM product_variants WHERE id = ? AND product_id = ?');
        $delete->execute([$variantId, $productId]);

        flash('success', $delete->rowCount() > 0
            ? 'Variacao apagada.'
            : 'Variacao nao encontrada neste produto.');

        redirect($redirect);
    }

    flash('error', 'Acao desconhecida.');
    redirect($redirect);
}

// ---------------------------------------------------------
// Listagem
// ---------------------------------------------------------

$variantsStmt = db()->prepare(
    'SELECT id, color, size, sku, stock, active
       FROM product_variants
      WHERE product_id = ?
      ORDER BY color ASC, size_order ASC, size ASC'
);
$variantsStmt->execute([$productId]);
$variants = $variantsStmt->fetchAll();

$totalStock = 0;

foreach ($variants as $variant) {
    $totalStock += (int) $variant['stock'];
}

// Sugestoes para os campos de cor e tamanho: o que ja foi digitado antes.
// Ajuda a manter a grafia padronizada sem tabelas de cores e tamanhos.
$colorOptions = db()->query(
    'SELECT DISTINCT color FROM product_variants ORDER BY color ASC'
)->fetchAll(PDO::FETCH_COLUMN);

$sizeOptions = db()->query(
    'SELECT DISTINCT size FROM product_variants ORDER BY size_order ASC, size ASC'
)->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Variacoes do produto';
$activeNav = 'produtos';

require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <h1 class="page-title">Variacoes: <?= e($product['name']) ?></h1>
    <div class="actions">
        <a class="btn btn-ghost"
           href="<?= e(base_url('admin/product_images.php?product_id=' . $productId)) ?>">Imagens</a>
        <a class="btn btn-ghost" href="<?= e(base_url('admin/products.php')) ?>">Voltar</a>
    </div>
</div>

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

<form method="post" class="form-card" action="<?= e($redirect) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">

    <span class="field-label">Nova variacao</span>

    <div class="variant-fields">
        <label class="field">
            <span class="field-label">Cor *</span>
            <input type="text" name="color" list="color-options"
                   maxlength="<?= e((string) VARIANT_COLOR_MAX) ?>" placeholder="Preto" required>
        </label>

        <label class="field">
            <span class="field-label">Tamanho *</span>
            <input type="text" name="size" list="size-options"
                   maxlength="<?= e((string) VARIANT_SIZE_MAX) ?>" placeholder="M" required>
        </label>

        <label class="field">
            <span class="field-label">SKU</span>
            <input type="text" name="sku"
                   maxlength="<?= e((string) VARIANT_SKU_MAX) ?>" placeholder="opcional">
        </label>

        <label class="field">
            <span class="field-label">Estoque</span>
            <input type="number" name="stock" value="0" min="0"
                   max="<?= e((string) VARIANT_STOCK_MAX) ?>" step="1">
        </label>

        <label class="field-check">
            <input type="checkbox" name="active" value="1" checked>
            <span>Ativa</span>
        </label>

        <button type="submit" class="btn btn-primary">Adicionar</button>
    </div>

    <span class="field-hint">
        A ordem de exibicao dos tamanhos (PP, P, M, G, GG) e calculada
        automaticamente a partir do nome.
    </span>
</form>

<?php if ($variants === []): ?>

    <p class="empty">
        Nenhuma variacao cadastrada. Sem variacao o produto nao tem estoque
        e nao pode ser adicionado ao carrinho.
    </p>

<?php else: ?>

    <p class="hint">
        <?= e((string) count($variants)) ?> variacao(oes),
        <?= e((string) $totalStock) ?> peca(s) em estoque no total.
        O estoque nunca baixa sozinho: ajuste aqui depois de confirmar a venda.
    </p>

    <div class="variants">
        <?php foreach ($variants as $variant): ?>
            <div class="variant-row<?= (int) $variant['stock'] === 0 ? ' is-empty' : '' ?><?= (int) $variant['active'] === 0 ? ' is-off' : '' ?>">
                <form method="post" action="<?= e($redirect) ?>" class="variant-fields">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="variant_id" value="<?= e((string) $variant['id']) ?>">

                    <label class="field">
                        <span class="field-label">Cor</span>
                        <input type="text" name="color" value="<?= e($variant['color']) ?>"
                               list="color-options" maxlength="<?= e((string) VARIANT_COLOR_MAX) ?>" required>
                    </label>

                    <label class="field">
                        <span class="field-label">Tamanho</span>
                        <input type="text" name="size" value="<?= e($variant['size']) ?>"
                               list="size-options" maxlength="<?= e((string) VARIANT_SIZE_MAX) ?>" required>
                    </label>

                    <label class="field">
                        <span class="field-label">SKU</span>
                        <input type="text" name="sku" value="<?= e((string) ($variant['sku'] ?? '')) ?>"
                               maxlength="<?= e((string) VARIANT_SKU_MAX) ?>">
                    </label>

                    <label class="field">
                        <span class="field-label">Estoque</span>
                        <input type="number" name="stock" value="<?= e((string) $variant['stock']) ?>"
                               min="0" max="<?= e((string) VARIANT_STOCK_MAX) ?>" step="1">
                    </label>

                    <label class="field-check">
                        <input type="checkbox" name="active" value="1"
                            <?= (int) $variant['active'] === 1 ? 'checked' : '' ?>>
                        <span>Ativa</span>
                    </label>

                    <button type="submit" class="btn btn-sm btn-ghost">Salvar</button>
                </form>

                <div class="variant-side">
                    <?php if ((int) $variant['stock'] === 0): ?>
                        <span class="badge badge-off">Sem estoque</span>
                    <?php endif; ?>

                    <form method="post" action="<?= e($redirect) ?>"
                          onsubmit="return confirm('Apagar esta variacao?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="variant_id" value="<?= e((string) $variant['id']) ?>">
                        <button type="submit" class="btn btn-sm btn-danger">Apagar</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
