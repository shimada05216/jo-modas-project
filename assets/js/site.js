/**
 * Jo Modas - Comportamento do cabecalho
 *
 * Duas coisas, so:
 *   - a gaveta de menu no celular;
 *   - o submenu de categorias no desktop.
 *
 * O carrinho fica em cart.js, para as duas coisas nao se misturarem.
 * Sem framework: tudo por classe no elemento.
 */
(function () {
    'use strict';

    var DESKTOP = 900; // mesmo ponto de corte usado no CSS

    document.addEventListener('DOMContentLoaded', function () {
        var toggle = document.getElementById('menu-toggle');
        var drawer = document.getElementById('menu-drawer');
        var scrim  = document.getElementById('menu-scrim');
        var close  = document.getElementById('menu-close');

        var catToggle = document.getElementById('cat-toggle');
        var catDrop   = catToggle ? catToggle.closest('.nav-drop') : null;

        function isDesktop() {
            return window.innerWidth >= DESKTOP;
        }

        // ---------------------------------------------------------
        // Gaveta (celular)
        // ---------------------------------------------------------

        function openMenu() {
            document.body.classList.add('menu-open');
            scrim.hidden = false;
            toggle.setAttribute('aria-expanded', 'true');

            var first = drawer.querySelector('a, button');

            if (first) {
                first.focus();
            }
        }

        function closeMenu(returnFocus) {
            document.body.classList.remove('menu-open');
            scrim.hidden = true;
            toggle.setAttribute('aria-expanded', 'false');

            if (returnFocus) {
                toggle.focus();
            }
        }

        if (toggle && drawer && scrim) {
            toggle.addEventListener('click', function () {
                if (document.body.classList.contains('menu-open')) {
                    closeMenu(false);
                } else {
                    openMenu();
                }
            });

            scrim.addEventListener('click', function () { closeMenu(false); });

            if (close) {
                close.addEventListener('click', function () { closeMenu(true); });
            }
        }

        // ---------------------------------------------------------
        // Submenu de categorias
        //
        // So age no desktop. No celular a lista ja aparece aberta
        // dentro da gaveta, entao o botao nao deve escondê-la.
        // ---------------------------------------------------------

        // Quem manda no estado visivel muda com a largura: no desktop e a
        // classe is-open; no celular, is-collapsed. O aria-expanded tem de
        // seguir a mesma regra, senao o leitor de tela anuncia "recolhido"
        // numa lista que esta na tela.
        function syncCatAria() {
            if (!catToggle || !catDrop) {
                return;
            }

            var open = isDesktop()
                ? catDrop.classList.contains('is-open')
                : !catDrop.classList.contains('is-collapsed');

            catToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        function closeCategories() {
            if (catDrop) {
                catDrop.classList.remove('is-open');
                syncCatAria();
            }
        }

        if (catToggle && catDrop) {
            // O HTML nasce com aria-expanded="false", mas na gaveta do
            // celular a sanfona ja aparece aberta.
            syncCatAria();

            catToggle.addEventListener('click', function () {
                if (!isDesktop()) {
                    // Na gaveta funciona como sanfona: comeca aberta e o
                    // botao recolhe/expande. Antes ele nao fazia nada, o
                    // que dava a impressao de menu travado.
                    var collapsed = catDrop.classList.toggle('is-collapsed');
                    catToggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                    return;
                }

                var open = catDrop.classList.toggle('is-open');
                catToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            });

            // Clicar fora fecha o submenu.
            document.addEventListener('click', function (event) {
                if (isDesktop() && !catDrop.contains(event.target)) {
                    closeCategories();
                }
            });
        }

        // ---------------------------------------------------------
        // Teclado e redimensionamento
        // ---------------------------------------------------------

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') {
                return;
            }

            closeCategories();

            if (document.body.classList.contains('menu-open')) {
                closeMenu(true);
            }
        });

        window.addEventListener('resize', function () {
            if (isDesktop()) {
                // Ao voltar para desktop a gaveta perde o sentido.
                if (document.body.classList.contains('menu-open')) {
                    closeMenu(false);
                }
            } else {
                // E no celular o submenu volta a ser lista fixa.
                closeCategories();
            }
        });
    });
}());
