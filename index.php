<?php
/**
 * Jo Modas - Home
 *
 * A home e o proprio catalogo: abre e ja mostra produtos, sem banner
 * ocupando a primeira tela. A escolha de cor, tamanho e quantidade
 * continua na pagina do produto, porque e la que existe estoque por
 * variacao.
 *
 * O banner antigo nao foi apagado: ficou comentado logo abaixo, junto
 * com as vitrines por marcador (destaque / novidade / mais vendido),
 * caso o lojista queira retomar aquele formato.
 */

require_once __DIR__ . '/includes/bootstrap.php';

const HOME_PER_PAGE = 12;

$total = count_active_products();
$pages = max(1, (int) ceil($total / HOME_PER_PAGE));

$page = input_int('pagina', 1) ?? 1;
$page = max(1, min($pages, $page));

$products = $total > 0
    ? showcase_products('recent', HOME_PER_PAGE, null, ($page - 1) * HOME_PER_PAGE)
    : [];

// Faixa de categorias da home. Vem do banco: cadastrar ou desativar uma
// categoria no painel muda esta lista sozinho, sem tocar em codigo.
$categories = active_categories();

$pageTitle = 'Moda feminina';
$metaDesc  = 'Jo Modas: vestidos, blusas e acessórios femininos. '
           . 'Escolha cor e tamanho e finalize seu pedido pelo WhatsApp.';

require __DIR__ . '/includes/site_header.php';
?>

<?php
/* ============================================================
   BANNER / HERO - DESATIVADO A PEDIDO DO CLIENTE
   ------------------------------------------------------------
   Guardado aqui de proposito, e nao apagado, caso se queira
   retomar o formato com banner na primeira tela. Para reativar,
   tire este bloco de comentario e ajuste o CSS .hero.

   <section class="hero">
       <div class="hero-text">
           <span class="hero-kicker">Nova coleção</span>
           <h1 class="hero-title">Peças que <br>combinam com <em>o seu dia</em></h1>
           <p class="hero-sub">
               Seleção de moda feminina escolhida peça a peça.
               Escolha cor e tamanho e feche o pedido pelo WhatsApp.
           </p>
           <div class="hero-actions">
               <a class="btn btn-solid" href="produto.php?slug=...">Ver destaque</a>
           </div>
       </div>
       <div class="hero-media"><img src="..." alt="..."></div>
   </section>

   As vitrines por marcador tambem sairam, substituidas pela lista
   unica de produtos. Os marcadores continuam no banco e aparecem
   como selo no cartao. Para retomar as secoes separadas:

       $featured = showcase_products('featured', 8);
       $novelty  = showcase_products('new', 8);
       $best     = showcase_products('best', 8);

   e uma <section class="shelf"> para cada grupo.
   ============================================================ */
?>

<?php if ($categories !== []): ?>
    <?php
    /*
     * Faixa compacta de categorias, retomada a pedido do cliente. Uma
     * linha so, rolando de lado no celular, para os produtos continuarem
     * logo abaixo. Nao volta o banner: ele segue comentado acima.
     */
    ?>
    <nav class="cat-strip" aria-label="Navegar por categoria">
        <?php foreach ($categories as $category): ?>
            <a href="<?= e(base_url('categoria.php?slug=' . rawurlencode($category['slug']))) ?>">
                <?= e($category['name']) ?>
            </a>
        <?php endforeach; ?>
    </nav>
<?php endif; ?>

<div class="page-intro">
    <h1 class="page-heading">Nossos produtos</h1>
    <?php if ($total > 0): ?>
        <p class="page-count">
            <?= e((string) $total) ?> <?= $total === 1 ? 'peça disponível' : 'peças disponíveis' ?>
        </p>
    <?php endif; ?>
</div>

<?php if ($products === []): ?>

    <div class="empty-state">
        <h2>Vitrine em preparação</h2>
        <p>Ainda não há produtos publicados. Volte em breve.</p>
    </div>

<?php else: ?>

    <div class="grid">
        <?php foreach ($products as $card): ?>
            <?php require __DIR__ . '/includes/product_card.php'; ?>
        <?php endforeach; ?>
    </div>

    <?php if ($pages > 1): ?>
        <nav class="pager" aria-label="Páginas">
            <?php if ($page > 1): ?>
                <a class="pager-btn" href="<?= e(base_url('index.php?pagina=' . ($page - 1))) ?>">Anterior</a>
            <?php endif; ?>

            <span class="pager-info">
                Página <?= e((string) $page) ?> de <?= e((string) $pages) ?>
            </span>

            <?php if ($page < $pages): ?>
                <a class="pager-btn" href="<?= e(base_url('index.php?pagina=' . ($page + 1))) ?>">Próxima</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>

<?php endif; ?>

<?php require __DIR__ . '/includes/site_footer.php'; ?>
