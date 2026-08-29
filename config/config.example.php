<?php
/**
 * Jo Modas - Configuracao do ambiente
 *
 * Copie este arquivo para config/config.php e ajuste os valores.
 * O config.php NAO e versionado: e nele que ficam as credenciais.
 *
 * Aqui vao apenas VALORES. Comportamento (tratamento de erros, sessao,
 * conexao) fica em includes/bootstrap.php e nos arquivos que ele carrega.
 */

// ---------------------------------------------------------
// Banco de dados
// Na Hostinger, use o usuario e o banco criados no hPanel.
// ---------------------------------------------------------
define('DB_HOST', 'localhost');
define('DB_NAME', 'jo_modas');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ---------------------------------------------------------
// URLs e caminhos
//
// BASE_URL: endereco publico da loja, SEM barra no final.
//   Local    : http://localhost/jo-modas
//   Hostinger: https://www.seudominio.com.br
//
// Os links internos NAO usam o host daqui: base_url() aproveita so a
// PASTA (o "/jo-modas" do exemplo) e devolve endereco relativo, do tipo
// "/produto.php". Assim a mesma instalacao responde por localhost, por
// um tunel do Cloudflare e pelo dominio final sem precisar editar nada.
//
// O host so e usado por absolute_url(), nos links que entram na mensagem
// do WhatsApp, e mesmo esses preferem o host da requisicao atual.
// ---------------------------------------------------------
define('BASE_URL', 'http://localhost/jo-modas');
define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_PATH', ROOT_PATH . '/uploads/products');
define('UPLOAD_URL', BASE_URL . '/uploads/products');

// ---------------------------------------------------------
// Upload de imagens
// ---------------------------------------------------------
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5 MB por imagem

// ---------------------------------------------------------
// Fuso horario
// ---------------------------------------------------------
define('APP_TIMEZONE', 'America/Sao_Paulo');

// ---------------------------------------------------------
// Painel administrativo
// Tempo de inatividade, em segundos, ate a sessao do admin
// expirar. 7200 = 2 horas. Use 0 para desligar a expiracao.
// ---------------------------------------------------------
define('ADMIN_SESSION_TIMEOUT', 7200);

// ---------------------------------------------------------
// Ambiente
//
// O padrao aqui e false, o valor seguro. Ligue true apenas na sua
// maquina: quem copia este arquivo e esquece de ajustar fica com a
// loja protegida, e nao com a pilha de chamadas exposta ao publico.
//
// true  desenvolvimento: a pagina de erro mostra mensagem, arquivo,
//       linha e pilha de chamadas, e qualquer aviso interrompe a
//       execucao em vez de passar despercebido.
//
// false producao: a tela mostra apenas um aviso generico e o detalhe
//       vai para o log do servidor.
// ---------------------------------------------------------
define('DEBUG_MODE', false);

// ---------------------------------------------------------
// Forcar HTTPS
//
// Com true, qualquer acesso por http:// e redirecionado para https://
// e o navegador passa a exigir HTTPS por conta propria (HSTS).
//
// Ligue somente depois que o certificado estiver funcionando: ativar
// antes disso deixa a loja inacessivel. Em producao isto deve ser
// true, senao a senha do painel trafega em texto puro.
// ---------------------------------------------------------
define('FORCE_HTTPS', false);

// ---------------------------------------------------------
// Chave das paginas de instalacao
//
// Protege install.php e test_connection.php, que sao arquivos
// temporarios mas ficam acessiveis a qualquer um enquanto existirem.
//
// Vazio: as duas paginas so respondem para quem acessa da propria
//        maquina (localhost). E o padrao, e o mais seguro.
//
// Preenchido: elas tambem respondem pela internet, desde que a URL
//        traga ?key=VALOR. Use um valor longo e aleatorio, e apague
//        os dois arquivos assim que terminar a instalacao.
// ---------------------------------------------------------
define('INSTALL_KEY', '');
