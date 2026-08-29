/**
 * Jo Modas - Painel: gaveta da barra lateral no celular
 * No desktop a lateral e fixa e este arquivo nao faz nada.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var toggle = document.getElementById('admin-toggle');
        var scrim  = document.getElementById('admin-scrim');

        if (!toggle || !scrim) {
            return;
        }

        function close() {
            document.body.classList.remove('nav-open');
            scrim.hidden = true;
            toggle.setAttribute('aria-expanded', 'false');
        }

        function open() {
            document.body.classList.add('nav-open');
            scrim.hidden = false;
            toggle.setAttribute('aria-expanded', 'true');
        }

        toggle.addEventListener('click', function () {
            document.body.classList.contains('nav-open') ? close() : open();
        });

        scrim.addEventListener('click', close);

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { close(); }
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth >= 1000) { close(); }
        });
    });
}());
