/**
 * Jo Modas - Comportamento do cabecalho
 *
 * Cuida apenas do menu de categorias no celular. O carrinho fica em
 * cart.js, para as duas coisas nao se misturarem.
 *
 * Sem framework: o menu e uma gaveta que abre com uma classe no <body>.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var toggle = document.getElementById('menu-toggle');
        var drawer = document.getElementById('menu-drawer');
        var scrim  = document.getElementById('menu-scrim');
        var close  = document.getElementById('menu-close');

        if (!toggle || !drawer || !scrim) {
            return;
        }

        function openMenu() {
            document.body.classList.add('menu-open');
            scrim.hidden = false;
            toggle.setAttribute('aria-expanded', 'true');

            var firstLink = drawer.querySelector('a');

            if (firstLink) {
                firstLink.focus();
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

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && document.body.classList.contains('menu-open')) {
                closeMenu(true);
            }
        });

        // Ao voltar para a largura de desktop o menu vira barra horizontal,
        // entao a gaveta precisa sair do estado aberto.
        window.addEventListener('resize', function () {
            if (window.innerWidth >= 900 && document.body.classList.contains('menu-open')) {
                closeMenu(false);
            }
        });
    });
}());
