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

    // O que o lojista digita costuma vir como +55 (11) 99999-9999.
    // Guardamos so os digitos, que e o formato aceito pelo link wa.me.
    $rawNumber = post('whatsapp_number');
    $digits    = preg_replace('/\D+/', '', $rawNumber);

    $values['whatsapp_number'] = $digits;

    if ($values['store_name'] === '') {
        $errors[] = 'Informe o nome da loja.';
    } elseif (mb_strlen($values['store_name']) > STORE_NAME_MAX) {
        $errors[] = 'O nome da loja deve ter no maximo ' . STORE_NAME_MAX . ' caracteres.';
    }

    // Numero em branco e permitido: desliga o checkout de proposito.
    if ($digits !== '' && (strlen($digits) < 10 || strlen($digits) > 15)) {
        $errors[] = 'O numero deve ter entre 10 e 15 digitos, contando o codigo do pais.';
    }

    if ($errors === []) {
        set_setting('store_name', $values['store_name']);
        set_setting('whatsapp_number', $values['whatsapp_number']);

        if ($digits === '') {
            flash('error', 'Configuracoes salvas, mas sem numero o envio de pedidos fica desligado.');
        } elseif (strpos($digits, '55') !== 0) {
            flash('success', 'Configuracoes salvas. Confira o numero: ele nao comeca com 55, '
                . 'o codigo do Brasil.');
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
               inputmode="numeric" placeholder="5511999999999">
        <span class="field-hint">
            Com codigo do pais e DDD. Pode digitar com simbolos
            (+55 11 99999-9999) que so os digitos sao guardados.
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
