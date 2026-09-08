<?php
/**
 * Jo Modas - Painel: configuracoes da loja
 *
 * Nome da loja e número de WhatsApp usados no checkout. Sem esta tela o
 * número só poderia ser trocado direto no banco, o que não serve para
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

// Duas areas na mesma tela. O campo "form" diz qual delas foi enviada,
// para uma nao apagar os valores da outra.
if (is_post() && post('form') === 'store') {
    require_csrf();

    $values['store_name'] = post('store_name');

    // O lojista digita como quiser: +55 (42) 99987-4363 ou 42999874363.
    // normalize_whatsapp_number devolve sempre o formato internacional,
    // ou '' quando o número não serve.
    $rawNumber  = post('whatsapp_number');
    $typed      = whatsapp_digits($rawNumber);
    $normalized = normalize_whatsapp_number($rawNumber);

    // Ao recusar, o formulario devolve o que a pessoa digitou, para ela
    // ver o que corrigir em vez de encontrar o campo vazio.
    $values['whatsapp_number'] = $normalized !== '' ? $normalized : $rawNumber;

    if ($values['store_name'] === '') {
        $errors[] = 'Informe o nome da loja.';
    } elseif (mb_strlen($values['store_name']) > STORE_NAME_MAX) {
        $errors[] = 'O nome da loja deve ter no máximo ' . STORE_NAME_MAX . ' caracteres.';
    }

    // Campo em branco continua válido: desliga o checkout de proposito.
    // Já um campo preenchido que não normaliza e recusado, e não apenas
    // sinalizado: gravar assim produziria um link wa.me que não abre.
    //
    // A condicao olha o texto cru, e não os digitos: "abcdef" não tem
    // digito nenhum, e comparando por digitos passaria por campo vazio
    // e apagaria o número da loja calado.
    if ($rawNumber !== '' && $normalized === '') {
        $errors[] = 'Número de WhatsApp inválido. Use DDD + número '
            . '(42999874363) ou o formato internacional (5542999874363).';
    }

    if ($errors === []) {
        set_setting('store_name', $values['store_name']);
        set_setting('whatsapp_number', $normalized);

        $values['whatsapp_number'] = $normalized;

        if ($normalized === '') {
            flash('error', 'Configurações salvas, mas sem número o envio de pedidos fica desligado.');
        } elseif ($normalized !== $typed) {
            // Avisa quando o código do pais foi acrescentado sozinho.
            flash('success', 'Configurações salvas. O número foi gravado como '
                . $normalized . ', no formato internacional.');
        } else {
            flash('success', 'Configurações salvas.');
        }

        redirect(base_url('admin/settings.php'));
    }
}

// =========================================================
// Dados de acesso do administrador
//
// Ficam na tabela admins, e nunca em settings: settings guarda
// configuracao da loja, nao credencial.
// =========================================================

$admin        = current_admin();
$accountValues = ['name' => $admin['name'], 'email' => $admin['email']];
$accountErrors = [];

if (is_post() && post('form') === 'account') {
    require_csrf();

    $accountValues['name']  = post('name');
    $accountValues['email'] = post('email');

    // Senhas nao passam por trim: espacos podem fazer parte delas.
    $current = isset($_POST['current_password']) && is_scalar($_POST['current_password'])
        ? (string) $_POST['current_password'] : '';
    $newPass = isset($_POST['new_password']) && is_scalar($_POST['new_password'])
        ? (string) $_POST['new_password'] : '';
    $confirm = isset($_POST['confirm_password']) && is_scalar($_POST['confirm_password'])
        ? (string) $_POST['confirm_password'] : '';

    $wantsPassword = $newPass !== '' || $confirm !== '';

    if ($accountValues['name'] === '') {
        $accountErrors[] = 'Informe o nome.';
    } elseif (mb_strlen($accountValues['name']) > 100) {
        $accountErrors[] = 'O nome deve ter no máximo 100 caracteres.';
    }

    if (!filter_var($accountValues['email'], FILTER_VALIDATE_EMAIL)) {
        $accountErrors[] = 'Informe um e-mail válido.';
    } elseif (mb_strlen($accountValues['email']) > 190) {
        $accountErrors[] = 'O e-mail é longo demais.';
    } else {
        $dup = db()->prepare('SELECT id FROM admins WHERE email = ? AND id <> ? LIMIT 1');
        $dup->execute([$accountValues['email'], (int) $admin['id']]);

        if ($dup->fetch() !== false) {
            $accountErrors[] = 'Esse e-mail já pertence a outro administrador.';
        }
    }

    if ($wantsPassword) {
        if (strlen($newPass) < 10) {
            $accountErrors[] = 'A nova senha precisa ter pelo menos 10 caracteres.';
        }

        if ($newPass !== $confirm) {
            $accountErrors[] = 'A confirmação não confere com a nova senha.';
        }
    }

    // A senha atual e exigida para QUALQUER alteracao destes dados: e o
    // que impede alguem que pegou a sessao aberta de trocar o e-mail e
    // a senha e tomar a conta.
    if ($accountErrors === []) {
        $check = db()->prepare('SELECT password_hash FROM admins WHERE id = ? LIMIT 1');
        $check->execute([(int) $admin['id']]);
        $storedHash = (string) $check->fetchColumn();

        if ($current === '' || !password_verify($current, $storedHash)) {
            $accountErrors[] = 'Senha atual incorreta.';
        }
    }

    if ($accountErrors === []) {
        if ($wantsPassword) {
            $save = db()->prepare(
                'UPDATE admins SET name = ?, email = ?, password_hash = ? WHERE id = ?'
            );
            $save->execute([
                $accountValues['name'],
                $accountValues['email'],
                password_hash($newPass, PASSWORD_DEFAULT),
                (int) $admin['id'],
            ]);
        } else {
            $save = db()->prepare('UPDATE admins SET name = ?, email = ? WHERE id = ?');
            $save->execute([
                $accountValues['name'],
                $accountValues['email'],
                (int) $admin['id'],
            ]);
        }

        // A sessao continua valendo, mas com id novo: se a senha foi
        // trocada porque alguem mais a conhecia, a sessao antiga morre.
        session_regenerate_id(true);
        $_SESSION['admin_name'] = $accountValues['name'];

        flash('success', $wantsPassword
            ? 'Dados de acesso atualizados. Use a nova senha no próximo login.'
            : 'Dados de acesso atualizados.');

        redirect(base_url('admin/settings.php'));
    }
}

$pageTitle = 'Configurações';
$activeNav = 'configuracoes';

require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
    <h1 class="page-title">Configurações da loja</h1>
</div>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endforeach; ?>

<form method="post" class="form-card" action="<?= e(base_url('admin/settings.php')) ?>" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="form" value="store">

    <h2 class="form-section">Loja</h2>

    <label class="field">
        <span class="field-label">Nome da loja *</span>
        <input type="text" name="store_name" value="<?= e($values['store_name']) ?>"
               maxlength="<?= e((string) STORE_NAME_MAX) ?>" required>
        <span class="field-hint">
            Aparece no titulo das páginas, no rodape e na saudacao da mensagem
            enviada pelo WhatsApp.
        </span>
    </label>

    <label class="field">
        <span class="field-label">Número de WhatsApp</span>
        <input type="text" name="whatsapp_number" value="<?= e($values['whatsapp_number']) ?>"
               inputmode="numeric" placeholder="5542999874363">
        <span class="field-hint">
            Número brasileiro, com DDD. Pode digitar com simbolos
            (+55 42 99987-4363) ou só o DDD e o número (42999874363):
            o código do pais 55 e acrescentado sozinho e o valor e
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

<h2 class="form-section form-section-standalone">Dados de acesso</h2>

<?php foreach ($accountErrors as $error): ?>
    <div class="alert alert-error"><?= e($error) ?></div>
<?php endforeach; ?>

<form method="post" class="form-card" action="<?= e(base_url('admin/settings.php')) ?>" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="form" value="account">

    <div class="field-row">
        <label class="field">
            <span class="field-label">Nome *</span>
            <input type="text" name="name" value="<?= e($accountValues['name']) ?>"
                   maxlength="100" autocomplete="name" required>
        </label>

        <label class="field">
            <span class="field-label">E-mail (login) *</span>
            <input type="email" name="email" value="<?= e($accountValues['email']) ?>"
                   maxlength="190" autocomplete="username" required>
        </label>
    </div>

    <label class="field">
        <span class="field-label">Senha atual *</span>
        <input type="password" name="current_password" autocomplete="current-password" required>
        <span class="field-hint">
            Exigida para confirmar qualquer alteracao, inclusive so do nome.
        </span>
    </label>

    <div class="field-row">
        <label class="field">
            <span class="field-label">Nova senha</span>
            <input type="password" name="new_password" autocomplete="new-password" minlength="10">
            <span class="field-hint">Minimo de 10 caracteres. Deixe em branco para manter a atual.</span>
        </label>

        <label class="field">
            <span class="field-label">Confirmar nova senha</span>
            <input type="password" name="confirm_password" autocomplete="new-password" minlength="10">
        </label>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Salvar dados de acesso</button>
    </div>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
