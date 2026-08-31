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
 * Cabecalho compacto de proposito: a maior parte do trafego e de celular,
 * entao a barra ocupa uma linha so e as categorias ficam num submenu, em
 * vez de uma faixa larga empurrando os produtos para baixo da dobra.
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

<a class="skip-link" href="#conteudo">Ir para o conteúdo</a>

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

    <?php
    /**
     * A mesma lista serve de gaveta no celular e de barra com submenu no
     * desktop. No celular as categorias aparecem ja abertas, para nao
     * exigir dois toques; no desktop viram um menu suspenso.
     */
    ?>
    <nav class="nav" id="menu-drawer" aria-label="Categorias">
        <div class="nav-head">
            <span>Menu</span>
            <button type="button" class="nav-close" id="menu-close" aria-label="Fechar menu">&times;</button>
        </div>

        <ul class="nav-list">
            <li>
                <a href="<?= e(base_url('index.php')) ?>"
                   class="nav-link <?= $activeSlug === '' ? 'is-active' : '' ?>">Início</a>
            </li>

            <?php if ($navItems !== []): ?>
                <li class="nav-drop">
                    <button type="button" class="nav-link nav-drop-toggle" id="cat-toggle"
                            aria-expanded="false" aria-controls="cat-submenu">
                        Categorias
                        <svg class="nav-chevron" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="m7 10 5 5 5-5"/>
                        </svg>
                    </button>

                    <ul class="nav-sub" id="cat-submenu">
                        <?php foreach ($navItems as $item): ?>
                            <li>
                                <a href="<?= e(base_url('categoria.php?slug=' . rawurlencode($item['slug']))) ?>"
                                   class="<?= $activeSlug === $item['slug'] ? 'is-active' : '' ?>">
                                    <?= e($item['name']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </li>
            <?php endif; ?>

            <li>
                <a href="<?= e(base_url('carrinho.php')) ?>" class="nav-link">Carrinho</a>
            </li>
        </ul>
    </nav>
</header>

<div class="hdr-scrim" id="menu-scrim" hidden></div>

<main class="site-main" id="conteudo">
