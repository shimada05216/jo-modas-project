<?php
/**
 * Jo Modas - Painel: imagens do produto
 *
 * As imagens ficam numa página própria porque só existem depois que o
 * produto tem id: product_images.product_id e uma chave estrangeira.
 *
 * Regras de seguranca aplicadas ao envio:
 *   - o nome original do arquivo e descartado por completo;
 *   - o nome gravado e aleatorio e a extensao vem do tipo detectado;
 *   - o conteudo passa por getimagesize e finfo antes de ser aceito;
 *   - a pasta de uploads não executa PHP (uploads/products/.htaccess).
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin();

const MAX_IMAGES_PER_PRODUCT = 12;

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

// ---------------------------------------------------------
// Envio maior que post_max_size
//
// Quando o corpo da requisicao passa do limite do PHP, o próprio PHP
// descarta tudo: $_POST e $_FILES chegam vazios. Sem este aviso, o
// require_csrf() abaixo acusaria "requisicao inválida", escondendo a
// causa real, que e o tamanho do envio.
// ---------------------------------------------------------

if (is_post() && $_POST === [] && $_FILES === [] && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    flash('error', sprintf(
        'O envio passou do limite do servidor (post_max_size = %s). '
        . 'Envie menos imagens de cada vez.',
        ini_get('post_max_size')
    ));

    redirect(base_url('admin/product_images.php?product_id=' . $productId));
}

if (is_post()) {
    require_csrf();

    $action   = post('action');
    $redirect = base_url('admin/product_images.php?product_id=' . $productId);

    // ---------------------------------------------------------
    // Envio de imagens
    // ---------------------------------------------------------
    if ($action === 'upload') {
        $files = normalize_files_array($_FILES['images'] ?? []);

        if ($files === []) {
            flash('error', 'Escolha ao menos uma imagem.');
            redirect($redirect);
        }

        $countStmt = db()->prepare('SELECT COUNT(*) FROM product_images WHERE product_id = ?');
        $countStmt->execute([$productId]);
        $current = (int) $countStmt->fetchColumn();

        $orderStmt = db()->prepare(
            'SELECT COALESCE(MAX(sort_order), 0) FROM product_images WHERE product_id = ?'
        );
        $orderStmt->execute([$productId]);
        $nextOrder = (int) $orderStmt->fetchColumn() + 1;

        $mainStmt = db()->prepare(
            'SELECT COUNT(*) FROM product_images WHERE product_id = ? AND is_main = 1'
        );
        $mainStmt->execute([$productId]);
        $hasMain = (int) $mainStmt->fetchColumn() > 0;

        $saved  = 0;
        $errors = [];

        foreach ($files as $file) {
            // Campo vazio no seletor multiplo: ignora sem reclamar.
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            if ($current >= MAX_IMAGES_PER_PRODUCT) {
                $errors[] = 'Limite de ' . MAX_IMAGES_PER_PRODUCT . ' imagens por produto atingido.';
                break;
            }

            $problem = validate_image_upload($file);

            if ($problem !== null) {
                $errors[] = $problem;
                continue;
            }

            // O prefixo vem do nome do produto, já vindo do banco, e passa
            // por slugify dentro de save_uploaded_image. O nome enviado
            // pelo navegador nunca e usado.
            $filename = save_uploaded_image($file, mb_substr($product['name'], 0, 40));

            if ($filename === null) {
                $errors[] = 'Não foi possível gravar uma das imagens.';
                continue;
            }

            try {
                $insert = db()->prepare(
                    'INSERT INTO product_images (product_id, filename, is_main, sort_order)
                     VALUES (?, ?, ?, ?)'
                );
                $insert->execute([
                    $productId,
                    $filename,
                    $hasMain ? 0 : 1,
                    $nextOrder,
                ]);

                $hasMain = true; // a primeira imagem do produto já vira a principal
                $nextOrder++;
                $current++;
                $saved++;
            } catch (PDOException $e) {
                // O registro não entrou: o arquivo já gravado viraria lixo
                // na pasta de uploads, entao sai junto.
                delete_product_image_file($filename);
                $errors[] = 'Não foi possível registrar uma das imagens.';
            }
        }

        if ($saved > 0) {
            flash('success', $saved === 1 ? 'Imagem enviada.' : $saved . ' imagens enviadas.');
        } elseif ($errors === []) {
            // Todos os campos vieram vazios: nada gravado e nada a reclamar,
            // mas o usuário precisa entender por que a página não mudou.
            $errors[] = 'Escolha ao menos uma imagem.';
        }

        foreach (array_unique($errors) as $error) {
            flash('error', $error);
        }

        redirect($redirect);
    }

    // ---------------------------------------------------------
    // Definir a imagem principal
    //
    // A ordem importa: product_images tem uma chave única sobre a coluna
    // gerada main_marker, entao marcar a nova antes de desmarcar a antiga
    // seria recusado pelo banco.
    // ---------------------------------------------------------
    if ($action === 'set_main') {
        $imageId = input_int('image_id');

        if ($imageId === null || $imageId < 1) {
            flash('error', 'Imagem inválida.');
            redirect($redirect);
        }

        // O product_id na condicao impede mexer na imagem de outro produto.
        $check = db()->prepare('SELECT id FROM product_images WHERE id = ? AND product_id = ? LIMIT 1');
        $check->execute([$imageId, $productId]);

        if ($check->fetch() === false) {
            flash('error', 'Imagem não encontrada neste produto.');
            redirect($redirect);
        }

        db()->beginTransaction();

        try {
            $clear = db()->prepare(
                'UPDATE product_images SET is_main = 0 WHERE product_id = ? AND is_main = 1'
            );
            $clear->execute([$productId]);

            $set = db()->prepare(
                'UPDATE product_images SET is_main = 1 WHERE id = ? AND product_id = ?'
            );
            $set->execute([$imageId, $productId]);

            db()->commit();
            flash('success', 'Imagem principal atualizada.');
        } catch (Throwable $e) {
            db()->rollBack();
            throw $e;
        }

        redirect($redirect);
    }

    // ---------------------------------------------------------
    // Apagar imagem
    // ---------------------------------------------------------
    if ($action === 'delete') {
        $imageId = input_int('image_id');

        if ($imageId === null || $imageId < 1) {
            flash('error', 'Imagem inválida.');
            redirect($redirect);
        }

        $find = db()->prepare(
            'SELECT id, filename, is_main FROM product_images
              WHERE id = ? AND product_id = ? LIMIT 1'
        );
        $find->execute([$imageId, $productId]);
        $image = $find->fetch();

        if ($image === false) {
            flash('error', 'Imagem não encontrada neste produto.');
            redirect($redirect);
        }

        db()->beginTransaction();

        try {
            $delete = db()->prepare('DELETE FROM product_images WHERE id = ? AND product_id = ?');
            $delete->execute([$imageId, $productId]);

            // Se a principal saiu, promove a próxima da fila para que o
            // produto não fique sem imagem de capa.
            if ((int) $image['is_main'] === 1) {
                $next = db()->prepare(
                    'SELECT id FROM product_images WHERE product_id = ?
                      ORDER BY sort_order ASC, id ASC LIMIT 1'
                );
                $next->execute([$productId]);
                $nextId = $next->fetchColumn();

                if ($nextId !== false) {
                    $promote = db()->prepare('UPDATE product_images SET is_main = 1 WHERE id = ?');
                    $promote->execute([(int) $nextId]);
                }
            }

            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            throw $e;
        }

        // Só depois do commit: se a transacao voltasse atras, o registro
        // continuaria apontando para um arquivo que já não existiria.
        delete_product_image_file($image['filename']);

        flash('success', 'Imagem apagada.');
        redirect($redirect);
    }

    flash('error', 'Acao desconhecida.');
    redirect($redirect);
}

// ---------------------------------------------------------
// Galeria
// ---------------------------------------------------------

$imagesStmt = db()->prepare(
    'SELECT id, filename, is_main, sort_order FROM product_images
      WHERE product_id = ? ORDER BY sort_order ASC, id ASC'
);
$imagesStmt->execute([$productId]);
$images = $imagesStmt->fetchAll();

// O .htaccess da pasta de uploads e o que impede a execucao de código ali.
// Alguns programas de FTP não enviam arquivos ocultos, entao vale conferir.
$uploadGuard = is_file(UPLOAD_PATH . '/.htaccess');

$pageTitle = 'Imagens do produto';
$activeNav = 'produtos';

require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <h1 class="page-title">Imagens: <?= e($product['name']) ?></h1>
    <a class="btn btn-ghost" href="<?= e(base_url('admin/products.php')) ?>">Voltar</a>
</div>

<?php if (!$uploadGuard): ?>
    <div class="alert alert-error">
        O arquivo <code>uploads/products/.htaccess</code> não foi encontrado.
        E ele que impede a execucao de código na pasta de imagens.
        Envie-o para o servidor antes de continuar (muitos programas de FTP
        escondem arquivos que comecam com ponto).
    </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" class="form-card"
      action="<?= e(base_url('admin/product_images.php?product_id=' . $productId)) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="upload">

    <label class="field">
        <span class="field-label">Enviar imagens</span>
        <input type="file" name="images[]" multiple
               accept="image/jpeg,image/png,image/webp" required>
        <span class="field-hint">
            JPG, PNG ou WEBP, até <?= e(format_bytes(effective_upload_limit())) ?> cada.
            Maximo de <?= e((string) MAX_IMAGES_PER_PRODUCT) ?> imagens por produto
            (<?= e((string) count($images)) ?> já enviadas).
        </span>
    </label>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Enviar</button>
    </div>
</form>

<?php if ($images === []): ?>

    <p class="empty">Nenhuma imagem enviada ainda.</p>

<?php else: ?>

    <div class="gallery">
        <?php foreach ($images as $image): ?>
            <figure class="gallery-item <?= (int) $image['is_main'] === 1 ? 'is-main' : '' ?>">
                <img src="<?= e(product_image_url($image['filename'])) ?>"
                     alt="Imagem de <?= e($product['name']) ?>" loading="lazy">

                <figcaption>
                    <?php if ((int) $image['is_main'] === 1): ?>
                        <span class="badge badge-on">Principal</span>
                    <?php else: ?>
                        <form method="post"
                              action="<?= e(base_url('admin/product_images.php?product_id=' . $productId)) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="set_main">
                            <input type="hidden" name="image_id" value="<?= e((string) $image['id']) ?>">
                            <button type="submit" class="btn btn-sm btn-ghost">Tornar principal</button>
                        </form>
                    <?php endif; ?>

                    <form method="post"
                          action="<?= e(base_url('admin/product_images.php?product_id=' . $productId)) ?>"
                          onsubmit="return confirm('Apagar esta imagem?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="image_id" value="<?= e((string) $image['id']) ?>">
                        <button type="submit" class="btn btn-sm btn-danger">Apagar</button>
                    </form>
                </figcaption>
            </figure>
        <?php endforeach; ?>
    </div>

    <p class="hint">
        A imagem principal e a que aparece na vitrine da loja.
        A ordem das demais segue a ordem de envio.
    </p>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
