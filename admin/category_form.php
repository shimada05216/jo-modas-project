<?php
/**
 * Jo Modas - Painel: criar e editar categoria
 *
 * Sem ?id  -> cria uma categoria nova
 * Com ?id  -> edita a categoria informada
 *
 * Toda a validacao acontece aqui, no servidor. Os atributos required e
 * maxlength do HTML servem só para avisar o usuário antes do envio: quem
 * decide o que entra no banco e este arquivo.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin();

// Limites iguais aos do schema, para a mensagem chegar antes do erro do banco.
const CATEGORY_NAME_MAX        = 100;
const CATEGORY_SLUG_MAX        = 120;
const CATEGORY_DESCRIPTION_MAX = 2000;
const CATEGORY_SORT_MAX        = 65535; // SMALLINT UNSIGNED

$id       = input_int('id');
$isEdit   = false;
$category = null;

if ($id !== null && $id > 0) {
    $stmt = db()->prepare(
        'SELECT id, name, slug, description, active, sort_order
           FROM categories WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    $category = $stmt->fetch();

    if ($category === false) {
        flash('error', 'Categoria não encontrada.');
        redirect(base_url('admin/categories.php'));
    }

    $isEdit = true;
} else {
    $id = null;
}

// ---------------------------------------------------------
// Valores exibidos no formulario.
// Numa requisicao GET vem do banco (ou vazios, na criacao).
// Depois de um POST inválido, vem do que o usuário digitou.
// ---------------------------------------------------------

$values = [
    'name'        => $category['name']        ?? '',
    'slug'        => $category['slug']        ?? '',
    'description' => $category['description'] ?? '',
    'active'      => isset($category['active']) ? (int) $category['active'] : 1,
    'sort_order'  => isset($category['sort_order']) ? (int) $category['sort_order'] : 0,
];

$errors = [];

if (is_post()) {
    require_csrf();

    $values['name']        = post('name');
    $values['slug']        = post('slug');
    $values['description'] = post('description');
    $values['active']      = to_bool_int($_POST['active'] ?? 0);

    // Campo vazio vale zero. Se veio preenchido com algo que não e inteiro,
    // input_int devolve null e a conferencia abaixo acusa o erro. Passar 0
    // como padrão aqui esconderia justamente esse caso.
    $values['sort_order'] = post('sort_order') === '' ? 0 : input_int('sort_order');

    // ---------- nome ----------
    if ($values['name'] === '') {
        $errors[] = 'Informe o nome da categoria.';
    } elseif (mb_strlen($values['name']) > CATEGORY_NAME_MAX) {
        $errors[] = 'O nome deve ter no máximo ' . CATEGORY_NAME_MAX . ' caracteres.';
    }

    // ---------- descrição ----------
    if (mb_strlen($values['description']) > CATEGORY_DESCRIPTION_MAX) {
        $errors[] = 'A descrição deve ter no máximo ' . CATEGORY_DESCRIPTION_MAX . ' caracteres.';
    }

    // ---------- ordem ----------
    if ($values['sort_order'] === null) {
        $errors[] = 'A ordem deve ser um número inteiro.';
        $values['sort_order'] = 0;
    } elseif ($values['sort_order'] < 0 || $values['sort_order'] > CATEGORY_SORT_MAX) {
        $errors[] = 'A ordem deve ficar entre 0 e ' . CATEGORY_SORT_MAX . '.';
    }

    // ---------- nome repetido ----------
    // A collation utf8mb4_unicode_ci faz esta busca ignorar maiusculas e
    // acentos, do mesmo jeito que a chave única uq_categories_name.
    if ($values['name'] !== '') {
        $dup = db()->prepare('SELECT id FROM categories WHERE name = ? AND id <> ? LIMIT 1');
        $dup->execute([$values['name'], $id ?? 0]);

        if ($dup->fetch() !== false) {
            $errors[] = 'Já existe uma categoria com esse nome.';
        }
    }

    // ---------- slug ----------
    // Slug vazio e gerado a partir do nome. unique_slug acrescenta -2, -3...
    // caso o slug já esteja em uso por outra categoria.
    $slug = '';

    if ($errors === []) {
        $base = $values['slug'] !== '' ? $values['slug'] : $values['name'];
        $slug = slugify($base);

        if (mb_strlen($slug) > CATEGORY_SLUG_MAX) {
            $slug = mb_substr($slug, 0, CATEGORY_SLUG_MAX);
            $slug = rtrim($slug, '-');
        }

        // Rede de seguranca: um nome só com simbolos pode não sobrar nada.
        if ($slug === '') {
            $slug = 'categoria';
        }

        $slug = unique_slug('categories', $slug, $id);
    }

    // ---------- gravacao ----------
    if ($errors === []) {
        try {
            if ($isEdit) {
                $save = db()->prepare(
                    'UPDATE categories
                        SET name = ?, slug = ?, description = ?, active = ?, sort_order = ?
                      WHERE id = ?'
                );
                $save->execute([
                    $values['name'],
                    $slug,
                    $values['description'] !== '' ? $values['description'] : null,
                    $values['active'],
                    $values['sort_order'],
                    $id,
                ]);

                $message = 'Categoria atualizada.';
            } else {
                $save = db()->prepare(
                    'INSERT INTO categories (name, slug, description, active, sort_order)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $save->execute([
                    $values['name'],
                    $slug,
                    $values['description'] !== '' ? $values['description'] : null,
                    $values['active'],
                    $values['sort_order'],
                ]);

                $message = 'Categoria criada.';
            }

            // Avisa quando o slug gravado não foi o pedido, para o lojista
            // não procurar depois por um endereço que não existe.
            if ($values['slug'] !== '' && $slug !== slugify($values['slug'])) {
                $message .= ' O endereço ficou como "' . $slug . '", porque o desejado já estava em uso.';
            }

            flash('success', $message);
            redirect(base_url('admin/categories.php'));
        } catch (PDOException $e) {
            // Duas pessoas gravando ao mesmo tempo podem passar pelas
            // conferencias acima e colidir na chave única do banco.
            if ($e->getCode() === '23000') {
                $errors[] = 'Já existe uma categoria com esse nome ou endereço. Tente novamente.';
            } else {
                throw $e;
            }
        }
    }
}

$pageTitle = $isEdit ? 'Editar categoria' : 'Nova categoria';
$activeNav = 'categorias';

require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <h1 class="page-title"><?= e($pageTitle) ?></h1>
    <a class="btn btn-ghost" href="<?= e(base_url('admin/categories.php')) ?>">Voltar</a>
</div>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endforeach; ?>

<form method="post" class="form-card"
      action="<?= e(base_url('admin/category_form.php' . ($isEdit ? '?id=' . (int) $id : ''))) ?>"
      novalidate>
    <?= csrf_field() ?>

    <label class="field">
        <span class="field-label">Nome *</span>
        <input type="text" name="name" value="<?= e($values['name']) ?>"
               maxlength="<?= e((string) CATEGORY_NAME_MAX) ?>" required autofocus>
    </label>

    <label class="field">
        <span class="field-label">Endereço (slug)</span>
        <input type="text" name="slug" value="<?= e($values['slug']) ?>"
               maxlength="<?= e((string) CATEGORY_SLUG_MAX) ?>"
               placeholder="deixe em branco para gerar a partir do nome">
        <span class="field-hint">
            Usado no endereço da categoria na loja. So letras, números e hifens.
        </span>
    </label>

    <label class="field">
        <span class="field-label">Descrição</span>
        <textarea name="description" rows="4"
                  maxlength="<?= e((string) CATEGORY_DESCRIPTION_MAX) ?>"><?= e($values['description']) ?></textarea>
    </label>

    <label class="field field-narrow">
        <span class="field-label">Ordem no menu</span>
        <input type="number" name="sort_order" value="<?= e((string) $values['sort_order']) ?>"
               min="0" max="<?= e((string) CATEGORY_SORT_MAX) ?>" step="1">
        <span class="field-hint">Menor número aparece primeiro.</span>
    </label>

    <label class="field-check">
        <input type="checkbox" name="active" value="1" <?= $values['active'] === 1 ? 'checked' : '' ?>>
        <span>Categoria ativa (aparece no menu da loja)</span>
    </label>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">
            <?= $isEdit ? 'Salvar alteracoes' : 'Criar categoria' ?>
        </button>
        <a class="btn btn-ghost" href="<?= e(base_url('admin/categories.php')) ?>">Cancelar</a>
    </div>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
