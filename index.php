<?php
/**
 * Jo Modas - Home
 *
 * Hero + vitrines de destaques, novidades e mais vendidos. As tres secoes
 * saem dos marcadores do painel (featured, is_new, best_seller) e cada uma
 * so aparece se tiver produto, para a home nunca mostrar espaco vazio.
 */

require_once __DIR__ . '/includes/bootstrap.php';

$featured = showcase_products('featured', 8);
$novelty  = showcase_products('new', 8);
$best     = showcase_products('best', 8);

// Se o lojista ainda nao marcou nada, a home mostra os mais recentes,
// em vez de ficar sem produto nenhum.
$fallback = ($featured === [] && $novelty === [] && $best === [])
    ? showcase_products('recent', 8)
    : [];

// A imagem do hero vem do primeiro produto que tiver foto, na ordem de
// prioridade das vitrines. Sem foto, o hero fica so com a tipografia.
$heroImage   = null;
$heroProduct = null;

foreach ([$featured, $novelty, $best, $fallback] as $group) {
    foreach ($group as $candidate) {
        if ($candidate['image'] !== null && $candidate['image'] !== '') {
            $heroImage   = product_image_url($candidate['image']);
            $heroProduct = $candidate;
            break 2;
        }
    }
}

$categories = active_categories();

$pageTitle = 'Moda feminina';
$metaDesc  = 'Jo Modas: vestidos, blusas e acessórios femininos. '
           . 'Escolha cor e tamanho e finalize seu pedido pelo WhatsApp.';

require __DIR__ . '/includes/site_header.php';
?>

<section class="hero<?= $heroImage === null ? ' hero-plain' : '' ?>">
    <div class="hero-text">
        <span class="hero-kicker">Nova coleção</span>
        <h1 class="hero-title">Peças que<br>combinam com<em>o seu dia</em></h1>
        <p class="hero-sub">
            Seleção de moda feminina escolhida peça a peça.
            Escolha cor e tamanho e feche o pedido pelo WhatsApp.
        </p>

        <div class="hero-actions">
            <?php if ($heroProduct !== null): ?>
                <a class="btn btn-solid"
                   href="<?= e(base_url('produto.php?slug=' . rawurlencode($heroProduct['slug']))) ?>">
                    Ver destaque
                </a>
            <?php endif; ?>

            <?php if ($categories !== []): ?>
                <a class="btn btn-line"
                   href="<?= e(base_url('categoria.php?slug=' . rawurlencode($categories[0]['slug']))) ?>">
                    <?= e($categories[0]['name']) ?>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($heroImage !== null): ?>
        <div class="hero-media">
            <img src="<?= e($heroImage) ?>" alt="<?= e($heroProduct['name']) ?>">
        </div>
    <?php endif; ?>
</section>

<?php if ($categories !== []): ?>
    <nav class="cat-strip" aria-label="Navegar por categoria">
        <?php foreach ($categories as $category): ?>
            <a href="<?= e(base_url('categoria.php?slug=' . rawurlencode($category['slug']))) ?>">
                <?php
                // O mockup usa uma foto dentro de cada bolha, mas categorias
                // nao tem campo de imagem no banco. Ate existir, a inicial
                // faz as vezes do monograma, sem inventar dado nenhum.
                ?>
                <span class="cat-bolha" aria-hidden="true"><?= e(mb_substr($category['name'], 0, 1)) ?></span>
                <?= e($category['name']) ?>
            </a>
        <?php endforeach; ?>
    </nav>
<?php endif; ?>

<?php
/**
 * Desenha uma vitrine. Sem produtos, nao desenha nada.
 */
$section = static function (string $title, string $subtitle, array $items): void {
    if ($items === []) {
        return;
    }
    ?>
    <section class="shelf">
        <header class="shelf-head">
            <h2 class="shelf-title"><?= e($title) ?></h2>
            <p class="shelf-sub"><?= e($subtitle) ?></p>
        </header>

        <div class="grid">
            <?php foreach ($items as $card): ?>
                <?php require __DIR__ . '/includes/product_card.php'; ?>
            <?php endforeach; ?>
        </div>
    </section>
    <?php
};

$section('Destaques', 'Escolhas da Jo para esta semana', $featured);
$section('Novidades', 'Chegou agora na loja', $novelty);
$section('Mais vendidos', 'O que as clientes mais levam', $best);
$section('Nossos produtos', 'Adicionados recentemente', $fallback);
?>

<?php if ($featured === [] && $novelty === [] && $best === [] && $fallback === []): ?>
    <div class="empty-state">
        <h2>Vitrine em preparação</h2>
        <p>Ainda não há produtos publicados. Volte em breve.</p>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/site_footer.php'; ?>
