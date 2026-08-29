<?php
/**
 * Jo Modas - Carrinho e fechamento pelo WhatsApp
 *
 * A pagina chega vazia: o conteudo e montado pelo JavaScript a partir do
 * localStorage e conferido contra o banco em carrinho_dados.php.
 *
 * Nada de estoque e alterado aqui. O botao apenas abre o WhatsApp com a
 * mensagem do pedido montada; a baixa do estoque continua sendo feita a
 * mao no painel, depois que a venda se confirma.
 */

require_once __DIR__ . '/includes/bootstrap.php';

// Numero e nome vem do painel (Configuracoes), nunca do codigo.
// store_whatsapp_number() devolve somente digitos, ja conferidos, ou ''
// quando nao ha numero utilizavel cadastrado.
$whatsapp  = store_whatsapp_number();
$storeName = (string) setting('store_name', 'Jo Modas');

// A saudacao e montada aqui, e nao no JavaScript, porque leva acento e o
// PHP ja e servido como UTF-8. Assim o "Ola" sai correto na mensagem.
$greeting = 'Olá, gostaria de fazer este pedido na ' . $storeName . ':';

$pageTitle = 'Carrinho';

require __DIR__ . '/includes/site_header.php';
?>

<h1 class="page-title">Seu carrinho</h1>

<?php if ($whatsapp === '' || strlen($whatsapp) < 10): ?>
    <div class="notice notice-warn">
        <strong>Envio de pedidos indisponivel.</strong>
        A loja ainda nao cadastrou um numero de WhatsApp para atendimento.
        Voce pode montar o carrinho, mas o pedido ainda nao pode ser enviado.
    </div>
<?php endif; ?>

<div id="cart-root"
     data-whatsapp="<?= e($whatsapp) ?>"
     data-greeting="<?= e($greeting) ?>"
     data-endpoint="<?= e(base_url('carrinho_dados.php')) ?>">
    <p class="cart-loading">Carregando o carrinho...</p>
</div>

<?php require __DIR__ . '/includes/site_footer.php'; ?>
