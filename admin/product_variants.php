<?php
/**
 * Jo Modas - Painel: variações do produto
 *
 * Uma variação e a combinacao produto + cor + tamanho, e e nela que mora
 * o estoque. Nenhuma tela do site desconta estoque sozinha: o checkout
 * pelo WhatsApp só monta a mensagem, e a baixa e feita aqui, a mao,
 * depois que a venda se confirma.
 *
 * A combinacao cor + tamanho e única por produto. A garantia final e a
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
    flash('error', 'Produto inválido.');
    redirect(base_url('admin/products.php'));
}

$stmt = db()->prepare('SELECT id, name FROM products WHERE id = ? LIMIT 1');
$stmt->execute([$productId]);
$product = $stmt->fetch();

if ($product === false) {
    flash('error', 'Produto não encontrado.');
    redirect(base_url('admin/products.php'));
}

$redirect = base_url('admin/product_variants.php?product_id=' . $productId);

/**
 * Le e confere os campos de uma variação vindos do POST.
 * Devolve [valores, erros].
 *
 * $variantId e o id da própria variação ao editar, para que ela não
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
        $errors[] = 'A cor deve ter no máximo ' . VARIANT_COLOR_MAX . ' caracteres.';
    }

    // ---------- tamanho ----------
    if ($values['size'] === '') {
        $errors[] = 'Informe o tamanho.';
    } elseif (mb_strlen($values['size']) > VARIANT_SIZE_MAX) {
        $errors[] = 'O tamanho deve ter no máximo ' . VARIANT_SIZE_MAX . ' caracteres.';
    }

    // ---------- estoque ----------
    // Campo vazio vale zero. Qualquer outro texto que não seja inteiro
    // devolve null em input_int e cai no erro abaixo.
    if ($values['stock'] === '') {
        $stock = 0;
    } else {
        $stock = input_int('stock');
    }

    if ($stock === null) {
        $errors[] = 'O estoque deve ser um número inteiro.';
        $stock = 0;
    } elseif ($stock < 0) {
        // A coluna e INT UNSIGNED: sem esta conferencia o MySQL recusaria
        // (modo estrito) ou gravaria zero calado (modo permissivo).
        $errors[] = 'O estoque não pode ser negativo.';
    } elseif ($stock > VARIANT_STOCK_MAX) {
        $errors[] = 'O estoque deve ser no máximo ' . VARIANT_STOCK_MAX . '.';
    }

    $values['stock'] = $stock;

    // ---------- SKU ----------
    if ($values['sku'] !== '') {
        if (mb_strlen($values['sku']) > VARIANT_SKU_MAX) {
            $errors[] = 'O SKU deve ter no máximo ' . VARIANT_SKU_MAX . ' caracteres.';
        } else {
            // O SKU e único na loja inteira, não só dentro do produto.
            $dup = db()->prepare('SELECT id FROM product_variants WHERE sku = ? AND id <> ? LIMIT 1');
            $dup->execute([$values['sku'], $variantId ?? 0]);

            if ($dup->fetch() !== false) {
                $errors[] = 'Já existe uma variação com o SKU "' . $values['sku'] . '".';
            }
        }
    }

    // ---------- combinacao repetida ----------
    // A collation utf8mb4_unicode_ci ignora caixa e acento, do mesmo jeito
    // que a chave única: "Preto"/"preto" contam como a mesma cor.
    if ($values['color'] !== '' && $values['size'] !== '') {
        $combo = db()->prepare(
            'SELECT id FROM product_variants
              WHERE product_id = ? AND color = ? AND size = ? AND id <> ? LIMIT 1'
        );
        $combo->execute([$productId, $values['color'], $values['size'], $variantId ?? 0]);

        if ($combo->fetch() !== false) {
            $errors[] = sprintf(
                'A combinacao %s / %s já existe neste produto.',
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
                    'Variação %s / %s criada.',
                    $values['color'],
                    $values['size']
                ));
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $errors[] = 'Essa combinacao ou esse SKU já existe. Tente novamente.';
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
            flash('error', 'Variação inválida.');
            redirect($redirect);
        }

        // O product_id na condicao impede editar a variação de outro produto.
        $check = db()->prepare(
            'SELECT id FROM product_variants WHERE id = ? AND product_id = ? LIMIT 1'
        );
        $check->execute([$variantId, $productId]);

        if ($check->fetch() === false) {
            flash('error', 'Variação não encontrada neste produto.');
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

                flash('success', 'Variação atualizada.');
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $errors[] = 'Essa combinacao ou esse SKU já existe. Tente novamente.';
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
            flash('error', 'Variação inválida.');
            redirect($redirect);
        }

        $delete = db()->prepare('DELETE FROM product_variants WHERE id = ? AND product_id = ?');
        $delete->execute([$variantId, $productId]);

        flash('success', $delete->rowCount() > 0
            ? 'Variação apagada.'
            : 'Variação não encontrada neste produto.');

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

// Sugestoes para os campos de cor e tamanho: o que já foi digitado antes.
// Ajuda a manter a grafia padronizada sem tabelas de cores e tamanhos.
$colorOptions = db()->query(
    'SELECT DISTINCT color FROM product_variants ORDER BY color ASC'
)->fetchAll(PDO::FETCH_COLUMN);

$sizeOptions = db()->query(
    'SELECT DISTINCT size FROM product_variants ORDER BY size_order ASC, size ASC'
)->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Variações do produto';
$activeNav = 'produtos';

require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <h1 class="page-title">Variações: <?= e($product['name']) ?></h1>
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

    <span class="field-label">Nova variação</span>

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
        Nenhuma variação cadastrada. Sem variação o produto não tem estoque
        e não pode ser adicionado ao carrinho.
    </p>

<?php else: ?>

    <p class="hint">
        <?= e((string) count($variants)) ?> variação(oes),
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
                          onsubmit="return confirm('Apagar esta variação?');">
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
