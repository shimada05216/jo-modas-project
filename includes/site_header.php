<?php
/**
 * Jo Modas - Cabecalho das paginas publicas
 *
 * Antes de incluir, defina:
 *   $pageTitle    titulo da aba
 *   $activeSlug   slug da categoria em destaque no menu (opcional)
 *   $metaDesc     descricao para buscadores (opcional)
 *
 * O menu vem da tabela categories: cadastrar uma categoria no painel ja
 * a coloca aqui, sem mexer em codigo.
 *
 * Nao abre sessao: o carrinho vive no localStorage do navegador.
 */

$pageTitle  = $pageTitle ?? 'Jo Modas';
$activeSlug = $activeSlug ?? '';
$metaDesc   = $metaDesc ?? '';
$storeName  = setting('store_name', 'Jo Modas');
$navItems   = active_categories();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> &middot; <?= e($storeName) ?></title>
    <?php if ($metaDesc !== ''): ?>
        <meta name="description" content="<?= e($metaDesc) ?>">
    <?php endif; ?>
    <meta name="theme-color" content="#e65875">
    <link rel="icon" href="<?= e(asset_url('images/logo.svg')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('css/site.css')) ?>">
</head>
<body class="site">

<a class="skip-link" href="#conteudo">Ir para o conteudo</a>

<p class="topbar">Atendimento e pedidos pelo WhatsApp</p>

<header class="hdr">
    <div class="hdr-bar">
        <button type="button" class="hdr-burger" id="menu-toggle"
                aria-label="Abrir menu" aria-expanded="false" aria-controls="menu-drawer">
            <span></span><span></span><span></span>
        </button>

        <a class="hdr-logo" href="<?= e(base_url('index.php')) ?>">
            <img src="<?= e(asset_url('images/logo.svg')) ?>"
                 alt="<?= e($storeName) ?> - moda feminina" width="300" height="78">
        </a>

        <a class="hdr-cart" href="<?= e(base_url('carrinho.php')) ?>" aria-label="Ver carrinho">
            <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="M6 7h12l-1.2 11.1a2 2 0 0 1-2 1.9H9.2a2 2 0 0 1-2-1.9L6 7Z"/>
                <path d="M9 7V5.8a3 3 0 0 1 6 0V7"/>
            </svg>
            <span class="hdr-cart-count" id="cart-count" hidden>0</span>
        </a>
    </div>

    <nav class="hdr-nav" id="menu-drawer" aria-label="Categorias">
        <div class="hdr-nav-head">
            <span>Categorias</span>
            <button type="button" class="hdr-close" id="menu-close" aria-label="Fechar menu">&times;</button>
        </div>

        <ul class="hdr-nav-list">
            <li>
                <a href="<?= e(base_url('index.php')) ?>"
                   class="<?= $activeSlug === '' ? 'is-active' : '' ?>">Inicio</a>
            </li>
            <?php foreach ($navItems as $item): ?>
                <li>
                    <a href="<?= e(base_url('categoria.php?slug=' . rawurlencode($item['slug']))) ?>"
                       class="<?= $activeSlug === $item['slug'] ? 'is-active' : '' ?>">
                        <?= e($item['name']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>
</header>

<div class="hdr-scrim" id="menu-scrim" hidden></div>

<main class="site-main" id="conteudo">
