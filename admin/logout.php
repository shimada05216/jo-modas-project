<?php
/**
 * Jo Modas - Encerra a sessao do painel
 *
 * Aceita apenas POST com token CSRF. Como link GET, bastava a um site
 * qualquer embutir <img src=".../logout.php"> para derrubar a sessao do
 * administrador enquanto ele trabalhava.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

if (!is_post() || !csrf_valid($_POST['csrf_token'] ?? null)) {
    redirect(base_url('admin/index.php'));
}

admin_logout();

// O aviso vai pela URL, e nao por flash: iniciar uma sessao nova logo apos
// destruir a anterior reaproveitaria o id que acabou de ser invalidado.
redirect(base_url('admin/login.php?saiu=1'));
