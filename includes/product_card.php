<?php
/**
 * Jo Modas - Cartao de produto
 *
 * Espera a variavel $card, uma linha vinda de showcase_products().
 * Usado pela home e pela listagem de categoria.
 *
 * O que o botao "Comprar" faz depende de REQUIRE_VARIANT_SELECTION:
 *
 *   true  -> leva para a pagina do produto, porque o que se vende e a
 *            combinacao cor + tamanho e essa escolha so existe la.
 *   false -> adiciona o produto ao carrinho ali mesmo, sem cor nem
 *            tamanho. Quem quiser escolher continua podendo abrir o
 *            produto pela imagem, pelo nome ou pelo preco.
 *
 * A imagem e o elemento principal do cartao; tocar nela, no nome ou no
 * preco sempre abre o produto, nos dois modos.
 */

$cardPrice    = effective_price($card);
$cardHasPromo = $cardPrice < (float) $card['price'];
$cardSoldOut  = (int) $card['total_stock'] <= 0;
$cardUrl      = base_url('produto.php?slug=' . rawurlencode($card['slug']));

// No modo simples o cartao vende direto; no estrito, encaminha.
$cardDirectBuy = !REQUIRE_VARIANT_SELECTION;
?>
<article class="card<?= ($cardSoldOut && !$cardDirectBuy) ? " is-sold-out" : "" ?>">
    <a class="card-link" href="<?= e($cardUrl) ?>">
        <div class="card-media">
            <img src="<?= e(product_image_url($card['image'])) ?>"
                 alt="<?= e($card['name']) ?>" loading="lazy" decoding="async"
                 width="600" height="800">

            <?php
            // Selos so aparecem quando o marcador correspondente esta
            // ligado no painel. Nada e fixo no codigo.
            //
            // No maximo dois por cartao: com os quatro marcadores ligados
            // a pilha de etiquetas cobria a foto, que e justamente o que
            // vende. A ordem abaixo e a prioridade.
            $cardFlags = [];

            if ($cardHasPromo) {
                $cardFlags[] = ['Promoção', 'flag flag-promo'];
            }
            if ((int) $card['is_new'] === 1) {
                $cardFlags[] = ['Novo', 'flag'];
            }
            if ((int) $card['best_seller'] === 1) {
                $cardFlags[] = ['Mais vendido', 'flag'];
            }
            if ((int) $card['featured'] === 1) {
                $cardFlags[] = ['Destaque', 'flag'];
            }

            $cardFlags = array_slice($cardFlags, 0, 2);
            ?>
            <?php if ($cardFlags !== []): ?>
                <div class="card-flags">
                    <?php foreach ($cardFlags as [$flagLabel, $flagClass]): ?>
                        <span class="<?= e($flagClass) ?>"><?= e($flagLabel) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php
            /*
             * "Esgotado" so no modo estrito. No modo simples a compra e
             * manifestacao de interesse e a peca continua vendavel, entao
             * o selo diria o contrario do que o botao faz.
             */
            ?>
            <?php if ($cardSoldOut && !$cardDirectBuy): ?>
                <span class="card-out">Esgotado</span>
            <?php endif; ?>
        </div>

        <div class="card-body">
            <h2 class="card-name"><?= e($card['name']) ?></h2>

            <p class="card-price">
                <?php if ($cardHasPromo): ?>
                    <span class="price-old"><?= e(format_price($card['price'])) ?></span>
                <?php endif; ?>
                <span class="price-now"><?= e(format_price($cardPrice)) ?></span>
            </p>
        </div>
    </a>

    <?php // Fora do <a> acima: um link nao pode conter outro link. ?>
    <?php if ($cardSoldOut && !$cardDirectBuy): ?>
        <span class="card-buy is-disabled" aria-disabled="true">Esgotado</span>
    <?php elseif ($cardDirectBuy): ?>
        <?php
        /*
         * Compra direta do cartao. Os dados vao nos data-*: o carrinho
         * so precisa disso para montar a linha, e a pagina do carrinho
         * reconfere tudo contra o banco antes de fechar o pedido.
         */
        ?>
        <button type="button" class="card-buy js-card-buy"
                data-product-id="<?= e((string) (int) $card['id']) ?>"
                data-name="<?= e($card['name']) ?>"
                data-price="<?= e(number_format($cardPrice, 2, '.', '')) ?>"
                data-image="<?= e(product_image_url($card['image'])) ?>"
                data-url="<?= e(absolute_url('produto.php?slug=' . rawurlencode($card['slug']))) ?>"
                aria-label="Comprar <?= e($card['name']) ?>">Comprar</button>
    <?php else: ?>
        <a class="card-buy" href="<?= e($cardUrl) ?>"
           aria-label="Comprar <?= e($card['name']) ?>">Comprar</a>
    <?php endif; ?>
</article>
