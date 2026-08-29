<?php
/**
 * Jo Modas - Painel: lista de categorias
 *
 * Tambem trata as acoes de ativar/desativar e apagar, que chegam por POST
 * com token CSRF. Depois de cada acao a pagina redireciona (padrao POST/
 * Redirect/GET), para que atualizar o navegador nao repita a operacao.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin();

if (is_post()) {
    require_csrf();

    $action = post('action');
    $id     = input_int('id');

    if ($id === null || $id < 1) {
        flash('error', 'Categoria invalida.');
        redirect(base_url('admin/categories.php'));
    }

    $stmt = db()->prepare('SELECT id, name, active FROM categories WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $category = $stmt->fetch();

    if ($category === false) {
        flash('error', 'Categoria nao encontrada.');
        redirect(base_url('admin/categories.php'));
    }

    // ---------------------------------------------------------
    // Ativar / desativar
    // ---------------------------------------------------------
    if ($action === 'toggle') {
        $newState = (int) $category['active'] === 1 ? 0 : 1;

        $update = db()->prepare('UPDATE categories SET active = ? WHERE id = ?');
        $update->execute([$newState, $id]);

        flash('success', $newState === 1
            ? 'Categoria ativada e visivel no menu.'
            : 'Categoria desativada e fora do menu.');

        redirect(base_url('admin/categories.php'));
    }

    // ---------------------------------------------------------
    // Apagar, somente quando nao ha produtos ligados
    //
    // A chave estrangeira usa ON DELETE RESTRICT, entao o banco ja
    // barraria a exclusao. A contagem abaixo existe para transformar
    // esse erro tecnico numa mensagem que o lojista entende.
    // ---------------------------------------------------------
    if ($action === 'delete') {
        $count = db()->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?');
        $count->execute([$id]);
        $productCount = (int) $count->fetchColumn();

        if ($productCount > 0) {
            flash('error', sprintf(
                'Nao e possivel apagar "%s": ha %d produto(s) nesta categoria. '
                . 'Mova os produtos para outra categoria antes de apagar.',
                $category['name'],
                $productCount
            ));

            redirect(base_url('admin/categories.php'));
        }

        try {
            $delete = db()->prepare('DELETE FROM categories WHERE id = ?');
            $delete->execute([$id]);

            flash('success', 'Categoria apagada.');
        } catch (PDOException $e) {
            // Rede de seguranca: se um produto foi criado nesta categoria
            // entre a contagem acima e o DELETE, a FK barra a operacao.
            flash('error', 'Nao foi possivel apagar: a categoria passou a ter produtos.');
        }

        redirect(base_url('admin/categories.php'));
    }

    flash('error', 'Acao desconhecida.');
    redirect(base_url('admin/categories.php'));
}

// ---------------------------------------------------------
// Listagem
// O LEFT JOIN traz quantos produtos cada categoria tem, o que decide
// se o botao de apagar aparece habilitado ou nao.
// ---------------------------------------------------------

$categories = db()->query(
    'SELECT c.id, c.name, c.slug, c.active, c.sort_order,
            COUNT(p.id) AS product_count
       FROM categories c
       LEFT JOIN products p ON p.category_id = c.id
      GROUP BY c.id, c.name, c.slug, c.active, c.sort_order
      ORDER BY c.sort_order ASC, c.name ASC'
)->fetchAll();

$pageTitle = 'Categorias';
$activeNav = 'categorias';

require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <h1 class="page-title">Categorias</h1>
    <a class="btn btn-primary" href="<?= e(base_url('admin/category_form.php')) ?>">Nova categoria</a>
</div>

<?php if ($categories === []): ?>

    <p class="empty">Nenhuma categoria cadastrada ainda.
       Comece criando a primeira: ela aparece no menu da loja.</p>

<?php else: ?>

    <div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th class="col-order">Ordem</th>
                <th>Nome</th>
                <th>Slug</th>
                <th class="col-num">Produtos</th>
                <th>Situacao</th>
                <th class="col-actions">Acoes</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($categories as $category): ?>
            <?php $hasProducts = (int) $category['product_count'] > 0; ?>
            <tr>
                <td class="col-order"><?= e((string) $category['sort_order']) ?></td>
                <td><strong><?= e($category['name']) ?></strong></td>
                <td class="mono"><?= e($category['slug']) ?></td>
                <td class="col-num"><?= e((string) $category['product_count']) ?></td>
                <td>
                    <?php if ((int) $category['active'] === 1): ?>
                        <span class="badge badge-on">Ativa</span>
                    <?php else: ?>
                        <span class="badge badge-off">Inativa</span>
                    <?php endif; ?>
                </td>
                <td class="col-actions">
                    <div class="actions">
                        <a class="btn btn-sm btn-ghost"
                           href="<?= e(base_url('admin/category_form.php?id=' . (int) $category['id'])) ?>">Editar</a>

                        <form method="post" action="<?= e(base_url('admin/categories.php')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= e((string) $category['id']) ?>">
                            <button type="submit" class="btn btn-sm btn-ghost">
                                <?= (int) $category['active'] === 1 ? 'Desativar' : 'Ativar' ?>
                            </button>
                        </form>

                        <?php if ($hasProducts): ?>
                            <button type="button" class="btn btn-sm btn-danger" disabled
                                    title="Ha produtos nesta categoria">Apagar</button>
                        <?php else: ?>
                            <form method="post" action="<?= e(base_url('admin/categories.php')) ?>"
                                  onsubmit="return confirm('Apagar esta categoria? A acao nao pode ser desfeita.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= e((string) $category['id']) ?>">
                                <button type="submit" class="btn btn-sm btn-danger">Apagar</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <p class="hint">
        A ordem controla a posicao no menu da loja: menor numero aparece primeiro.
        Categorias inativas somem do menu, mas continuam guardadas aqui.
    </p>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
