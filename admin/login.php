<?php
/**
 * Jo Modas - Login do painel administrativo
 */

require_once __DIR__ . '/../includes/bootstrap.php';

start_session();

// Quem já esta logado não precisa ver esta tela.
if (is_admin_logged_in()) {
    redirect(base_url('admin/index.php'));
}

$errors = [];
$email  = '';

if (is_post()) {
    require_csrf();

    $email = post('email');
    // A senha não passa por trim: espacos podem fazer parte dela.
    $password = isset($_POST['password']) && is_scalar($_POST['password'])
        ? (string) $_POST['password']
        : '';

    if ($email === '' || $password === '') {
        $errors[] = 'Informe o e-mail e a senha.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Informe um e-mail válido.';
    } elseif (($lockMinutes = login_lock_minutes($email)) > 0) {
        // Bloqueio por excesso de tentativas. A conferencia vem antes de
        // admin_login para que nenhuma senha seja testada enquanto durar.
        $errors[] = sprintf(
            'Muitas tentativas de login. Tente novamente em %d minuto%s.',
            $lockMinutes,
            $lockMinutes === 1 ? '' : 's'
        );
    } elseif (admin_login($email, $password)) {
        redirect(base_url('admin/index.php'));
    } else {
        // Mensagem generica: não revela se o e-mail existe.
        $errors[] = 'E-mail ou senha incorretos.';
    }
}

$flashes = take_flashes();

// Aviso vindo de logout.php.
$notices = [];

if (get('saiu') === '1') {
    $notices[] = 'Você saiu do painel.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Entrar - Jo Modas</title>
    <link rel="stylesheet" href="<?= e(asset_url('css/admin.css')) ?>">
</head>
<body class="admin admin-login-page">

<div class="login-card">
    <span class="login-logo"><img src="<?= e(asset_url('images/logo.svg')) ?>" alt="Jo Modas" width="300" height="78"></span>
    <p class="login-subtitle">Painel administrativo</p>

    <?php foreach ($notices as $notice): ?>
        <div class="alert alert-success"><?= e($notice) ?></div>
    <?php endforeach; ?>

    <?php foreach ($flashes as $flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endforeach; ?>

    <?php foreach ($errors as $error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endforeach; ?>

    <form method="post" action="<?= e(base_url('admin/login.php')) ?>" novalidate>
        <?= csrf_field() ?>

        <label class="field">
            <span class="field-label">E-mail</span>
            <input type="email" name="email" value="<?= e($email) ?>"
                   autocomplete="username" required autofocus>
        </label>

        <label class="field">
            <span class="field-label">Senha</span>
            <input type="password" name="password" autocomplete="current-password" required>
        </label>

        <button type="submit" class="btn btn-primary btn-block">Entrar</button>
    </form>
</div>

</body>
</html>
