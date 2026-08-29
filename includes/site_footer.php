<?php
/**
 * Jo Modas - Rodape das paginas publicas
 *
 * A faixa de servicos segue o desenho da marca, mas com texto que
 * corresponde ao que a loja realmente faz. Os selos do mockup
 * (frete gratis acima de R$199,90, 12x no cartao, 5% no Pix) ficaram
 * de fora de proposito: sao promessas comerciais, e esta versao nao
 * tem meio de pagamento nem regra de frete que as sustente.
 *
 * O numero de WhatsApp sai da tabela settings. Se estiver vazio, o
 * botao de contato nao aparece, em vez de virar um link quebrado.
 */

$footerCategories = active_categories();
$footerWhatsapp   = store_whatsapp_number();
$footerStore      = setting('store_name', 'Jo Modas');
?>
</main>

<section class="servicos" aria-label="Como a loja funciona">
    <div class="servicos-grid">
        <div class="servico">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M12 2 4 5.5v5.8c0 4.6 3.2 8.6 8 10.7 4.8-2.1 8-6.1 8-10.7V5.5L12 2Z"/>
                <path d="m9 12 2 2 4-4"/>
            </svg>
            <div>
                <b>Peças conferidas</b>
                <span>uma a uma antes do envio</span>
            </div>
        </div>

        <div class="servico">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M20.5 3.5A10 10 0 0 0 4.2 15.6L3 21l5.5-1.2A10 10 0 1 0 20.5 3.5Z"/>
                <path d="M8.8 8.4c.3-.7.6-.7.9-.7h.7c.2 0 .5 0 .7.5l.7 1.7c.1.3 0 .5-.1.7l-.4.5c-.2.2-.3.4-.1.7a6 6 0 0 0 2.7 2.4c.3.1.5.1.7-.1l.5-.6c.2-.2.4-.2.7-.1l1.6.8c.3.1.4.3.4.6v.7c0 .4-.3.9-.9 1.1-1.3.5-3.3-.2-5-1.6a10 10 0 0 1-2.9-4c-.4-1-.5-2-.2-2.6Z"/>
            </svg>
            <div>
                <b>Atendimento direto</b>
                <span>você fala com a gente</span>
            </div>
        </div>

        <div class="servico">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M3 7h11v9H3z"/>
                <path d="M14 10h4l3 3v3h-7z"/>
                <circle cx="7" cy="18" r="1.8"/>
                <circle cx="17.5" cy="18" r="1.8"/>
            </svg>
            <div>
                <b>Enviamos para todo o Brasil</b>
                <span>combine o frete no WhatsApp</span>
            </div>
        </div>

        <div class="servico">
            <svg viewBox="0 0 24 24" aria-hidden="true">
                <path d="M9 3h6l1 3h4v13a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6h4l1-3Z"/>
                <path d="M9.5 12.5 12 15l4-4.5"/>
            </svg>
            <div>
                <b>Escolha cor e tamanho</b>
                <span>estoque real por variação</span>
            </div>
        </div>
    </div>
</section>

<footer class="ftr">
    <div class="ftr-grid">
        <div class="ftr-about">
            <a class="ftr-logo" href="<?= e(base_url('index.php')) ?>">
                <img src="<?= e(asset_url('images/logo.svg')) ?>"
                     alt="<?= e($footerStore) ?>" width="300" height="78">
            </a>
            <p>
                Moda feminina escolhida peça a peça. Monte seu carrinho,
                escolha cor e tamanho, e finalize o pedido com a gente
                pelo WhatsApp.
            </p>
        </div>

        <?php if ($footerCategories !== []): ?>
            <div class="ftr-col">
                <h2 class="ftr-title">Categorias</h2>
                <ul class="ftr-list">
                    <?php foreach ($footerCategories as $item): ?>
                        <li>
                            <a href="<?= e(base_url('categoria.php?slug=' . rawurlencode($item['slug']))) ?>">
                                <?= e($item['name']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="ftr-col">
            <h2 class="ftr-title">Atendimento</h2>
            <ul class="ftr-list">
                <li><a href="<?= e(base_url('carrinho.php')) ?>">Meu carrinho</a></li>
            </ul>

            <?php if (strlen($footerWhatsapp) >= 10): ?>
                <a class="ftr-zap" href="https://wa.me/<?= e($footerWhatsapp) ?>"
                   target="_blank" rel="noopener">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M20.5 3.5A10 10 0 0 0 4.2 15.6L3 21l5.5-1.2A10 10 0 1 0 20.5 3.5ZM12 20a8 8 0 0 1-4.1-1.1l-.3-.2-3 .7.7-2.9-.2-.3A8 8 0 1 1 12 20Z"/>
                    </svg>
                    Falar no WhatsApp
                </a>
            <?php endif; ?>

            <p class="ftr-note">
                Pedidos finalizados pelo WhatsApp. Não pedimos dados de
                pagamento pelo site.
            </p>
        </div>
    </div>

    <div class="ftr-bottom">
        <?= e($footerStore) ?> &middot; <?= e(date('Y')) ?>
    </div>
</footer>

<script src="<?= e(asset_url('js/site.js')) ?>"></script>
<script src="<?= e(asset_url('js/cart.js')) ?>"></script>
</body>
</html>
