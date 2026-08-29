<?php
/**
 * Jo Modas - Topo das paginas do painel
 *
 * Antes de incluir este arquivo, defina:
 *   $pageTitle  titulo da pagina
 *   $activeNav  item de menu em destaque (dashboard, categorias, ...)
 *
 * A barra lateral lista somente o que existe de verdade no sistema.
 * Os mockups mostram tambem Pedidos, Clientes, Cupons, Banners,
 * Usuarios, Relatorios e Logs: nada disso foi construido, e um menu
 * que leva a lugar nenhum atrapalha mais do que ajuda.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

require_admin();

$admin     = current_admin();
$pageTitle = $pageTitle ?? 'Painel';
$activeNav = $activeNav ?? '';
$flashes   = take_flashes();

/**
 * Um item da barra lateral, com o icone ja desenhado.
 */
$navItem = static function (string $key, string $label, string $href, string $icon) use ($activeNav): void {
    $icons = [
        'painel' => '<path d="M3 12 12 4l9 8"/><path d="M5.5 10.4V20h13v-9.6"/>',
        'tag'    => '<path d="M3 12.5V4h8.5L21 13.5 13.5 21 3 12.5Z"/><circle cx="7.5" cy="7.5" r="1.4"/>',
        'caixa'  => '<path d="M21 8 12 3 3 8v8l9 5 9-5V8Z"/><path d="m3 8 9 5 9-5"/><path d="M12 13v8"/>',
        'engre'  => '<circle cx="12" cy="12" r="3.2"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M19.1 4.9 17 7M7 17l-2.1 2.1"/>',
        'sair'   => '<path d="M15 17v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7a2 2 0 0 1 2 2v2"/><path d="M19 12H9m10 0-3-3m3 3-3 3"/>',
    ];
    ?>
    <a href="<?= e($href) ?>" class="<?= $activeNav === $key ? 'is-active' : '' ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true"><?= $icons[$icon] ?></svg>
        <?= e($label) ?>
    </a>
    <?php
};
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($pageTitle) ?> - Jo Modas</title>
    <meta name="theme-color" content="#1c1c1e">
    <link rel="icon" href="<?= e(asset_url('images/logo.svg')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('css/admin.css')) ?>">
</head>
<body class="admin">

<aside class="side" id="admin-side">
    <a class="side-logo" href="<?= e(base_url('admin/index.php')) ?>">
        <img src="<?= e(asset_url('images/logo-light.svg')) ?>" alt="Jo Modas" width="300" height="78">
    </a>

    <nav class="side-nav" aria-label="Secoes do painel">
        <?php
        $navItem('dashboard',     'Painel',        base_url('admin/index.php'),      'painel');
        $navItem('categorias',    'Categorias',    base_url('admin/categories.php'), 'tag');
        $navItem('produtos',      'Produtos',      base_url('admin/products.php'),   'caixa');
        $navItem('configuracoes', 'Configuracoes', base_url('admin/settings.php'),   'engre');
        ?>
    </nav>

    <div class="side-foot">
        <form method="post" action="<?= e(base_url('admin/logout.php')) ?>">
            <?= csrf_field() ?>
            <button type="submit">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M15 17v2a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7a2 2 0 0 1 2 2v2"/>
                    <path d="M19 12H9m10 0-3-3m3 3-3 3"/>
                </svg>
                Sair
            </button>
        </form>
    </div>
</aside>

<div class="side-scrim" id="admin-scrim" hidden></div>

<header class="admin-top">
    <button type="button" class="admin-burger" id="admin-toggle"
            aria-label="Abrir menu" aria-expanded="false" aria-controls="admin-side">
        <span></span><span></span><span></span>
    </button>

    <a class="admin-top-logo" href="<?= e(base_url('admin/index.php')) ?>">
        <img src="<?= e(asset_url('images/logo.svg')) ?>" alt="Jo Modas" width="300" height="78">
    </a>

    <div class="admin-user">
        <span><?= e($admin['name']) ?></span>
        <span class="avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($admin['name'], 0, 1))) ?></span>
    </div>
</header>

<main class="admin-main">
<?php foreach ($flashes as $flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endforeach; ?>
