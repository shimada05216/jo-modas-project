<?php
/**
 * Jo Modas - Cartao de produto
 *
 * Espera a variavel $card, uma linha vinda de showcase_products().
 * Usado pela home e pela listagem de categoria.
 *
 * O cartao leva para a pagina do produto: a escolha de cor e tamanho
 * acontece la, porque e a variacao que tem estoque.
 */

$cardPrice    = effective_price($card);
$cardHasPromo = $cardPrice < (float) $card['price'];
$cardSoldOut  = (int) $card['total_stock'] <= 0;
$cardUrl      = base_url('produto.php?slug=' . rawurlencode($card['slug']));
?>
<article class="card<?= $cardSoldOut ? ' is-sold-out' : '' ?>">
    <a class="card-link" href="<?= e($cardUrl) ?>">
        <div class="card-media">
            <img src="<?= e(product_image_url($card['image'])) ?>"
                 alt="<?= e($card['name']) ?>" loading="lazy">

            <div class="card-flags">
                <?php if ($cardHasPromo): ?>
                    <span class="flag flag-promo">Promoção</span>
                <?php endif; ?>
                <?php if ((int) $card['is_new'] === 1): ?>
                    <span class="flag">Novidade</span>
                <?php endif; ?>
                <?php if ((int) $card['best_seller'] === 1): ?>
                    <span class="flag">Mais vendido</span>
                <?php endif; ?>
            </div>

            <?php if ($cardSoldOut): ?>
                <span class="card-out">Esgotado</span>
            <?php endif; ?>
        </div>

        <div class="card-body">
            <span class="card-cat"><?= e($card['category_name']) ?></span>
            <h3 class="card-name"><?= e($card['name']) ?></h3>

            <p class="card-price">
                <?php if ($cardHasPromo): ?>
                    <span class="price-old"><?= e(format_price($card['price'])) ?></span>
                <?php endif; ?>
                <span class="price-now"><?= e(format_price($cardPrice)) ?></span>
            </p>
        </div>
    </a>
</article>
