<?php
/**
 * Jo Modas - Painel: configuracoes da loja
 *
 * Nome da loja e numero de WhatsApp usados no checkout. Sem esta tela o
 * numero so poderia ser trocado direto no banco, o que nao serve para
 * quem opera a loja.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin();

const STORE_NAME_MAX = 60;

$values = [
    'store_name'      => (string) setting('store_name', 'Jo Modas'),
    'whatsapp_number' => (string) setting('whatsapp_number', ''),
];

$errors = [];

if (is_post()) {
    require_csrf();

    $values['store_name'] = post('store_name');

    // O lojista digita como quiser: +55 (42) 99987-4363 ou 42999874363.
    // normalize_whatsapp_number devolve sempre o formato internacional,
    // ou '' quando o numero nao serve.
    $rawNumber  = post('whatsapp_number');
    $typed      = whatsapp_digits($rawNumber);
    $normalized = normalize_whatsapp_number($rawNumber);

    // Ao recusar, o formulario devolve o que a pessoa digitou, para ela
    // ver o que corrigir em vez de encontrar o campo vazio.
    $values['whatsapp_number'] = $normalized !== '' ? $normalized : $rawNumber;

    if ($values['store_name'] === '') {
        $errors[] = 'Informe o nome da loja.';
    } elseif (mb_strlen($values['store_name']) > STORE_NAME_MAX) {
        $errors[] = 'O nome da loja deve ter no maximo ' . STORE_NAME_MAX . ' caracteres.';
    }

    // Campo em branco continua valido: desliga o checkout de proposito.
    // Ja um campo preenchido que nao normaliza e recusado, e nao apenas
    // sinalizado: gravar assim produziria um link wa.me que nao abre.
    //
    // A condicao olha o texto cru, e nao os digitos: "abcdef" nao tem
    // digito nenhum, e comparando por digitos passaria por campo vazio
    // e apagaria o numero da loja calado.
    if ($rawNumber !== '' && $normalized === '') {
        $errors[] = 'Numero de WhatsApp invalido. Use DDD + numero '
            . '(42999874363) ou o formato internacional (5542999874363).';
    }

    if ($errors === []) {
        set_setting('store_name', $values['store_name']);
        set_setting('whatsapp_number', $normalized);

        $values['whatsapp_number'] = $normalized;

        if ($normalized === '') {
            flash('error', 'Configuracoes salvas, mas sem numero o envio de pedidos fica desligado.');
        } elseif ($normalized !== $typed) {
            // Avisa quando o codigo do pais foi acrescentado sozinho.
            flash('success', 'Configuracoes salvas. O numero foi gravado como '
                . $normalized . ', no formato internacional.');
        } else {
            flash('success', 'Configuracoes salvas.');
        }

        redirect(base_url('admin/settings.php'));
    }
}

$pageTitle = 'Configuracoes';
$activeNav = 'configuracoes';

require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <h1 class="page-title">Configuracoes da loja</h1>
</div>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endforeach; ?>

<form method="post" class="form-card" action="<?= e(base_url('admin/settings.php')) ?>" novalidate>
    <?= csrf_field() ?>

    <label class="field">
        <span class="field-label">Nome da loja *</span>
        <input type="text" name="store_name" value="<?= e($values['store_name']) ?>"
               maxlength="<?= e((string) STORE_NAME_MAX) ?>" required>
        <span class="field-hint">
            Aparece no titulo das paginas, no rodape e na saudacao da mensagem
            enviada pelo WhatsApp.
        </span>
    </label>

    <label class="field">
        <span class="field-label">Numero de WhatsApp</span>
        <input type="text" name="whatsapp_number" value="<?= e($values['whatsapp_number']) ?>"
               inputmode="numeric" placeholder="5542999874363">
        <span class="field-hint">
            Numero brasileiro, com DDD. Pode digitar com simbolos
            (+55 42 99987-4363) ou so o DDD e o numero (42999874363):
            o codigo do pais 55 e acrescentado sozinho e o valor e
            guardado no formato internacional.
            Deixe em branco para desligar o envio de pedidos.
        </span>
    </label>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Salvar</button>
    </div>
</form>

<?php if ($values['whatsapp_number'] !== ''): ?>
    <p class="hint">
        Link gerado:
        <a href="https://wa.me/<?= e($values['whatsapp_number']) ?>" target="_blank" rel="noopener">
            https://wa.me/<?= e($values['whatsapp_number']) ?>
        </a>
        &mdash; abra para confirmar que leva a conta certa.
    </p>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
