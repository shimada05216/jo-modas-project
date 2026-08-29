<?php
/**
 * Jo Modas - Funcoes auxiliares compartilhadas
 */

require_once __DIR__ . '/../config/database.php';

// =========================================================
// Saida / escape
// =========================================================

/**
 * Escapa texto para exibicao em HTML.
 */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Formata um valor no padrão brasileiro: R$ 1.234,56
 */
function format_price($value): string
{
    return 'R$ ' . number_format((float) $value, 2, ',', '.');
}

/**
 * Devolve o preco que deve ser cobrado (promocional, se houver).
 */
function effective_price(array $product): float
{
    $promo = $product['promo_price'] ?? null;

    if ($promo !== null && (float) $promo > 0 && (float) $promo < (float) $product['price']) {
        return (float) $promo;
    }

    return (float) $product['price'];
}

// =========================================================
// URLs
// =========================================================

function base_url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function asset_url(string $path): string
{
    return BASE_URL . '/assets/' . ltrim($path, '/');
}

/**
 * URL de uma imagem de produto. Sem arquivo, devolve o placeholder.
 */
function product_image_url(?string $filename): string
{
    if ($filename === null || $filename === '') {
        return asset_url('images/placeholder.svg');
    }

    return UPLOAD_URL . '/' . rawurlencode($filename);
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

// =========================================================
// Entrada
// =========================================================

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

/**
 * Le um campo do POST como string, já com trim.
 */
function post(string $key, string $default = ''): string
{
    return isset($_POST[$key]) && is_scalar($_POST[$key])
        ? trim((string) $_POST[$key])
        : $default;
}

/**
 * Le um campo do GET como string, já com trim.
 */
function get(string $key, string $default = ''): string
{
    return isset($_GET[$key]) && is_scalar($_GET[$key])
        ? trim((string) $_GET[$key])
        : $default;
}

/**
 * Le um inteiro do POST/GET. Devolve $default quando ausente ou inválido.
 */
function input_int(string $key, ?int $default = null): ?int
{
    $value = $_POST[$key] ?? $_GET[$key] ?? null;

    if ($value === null || $value === '' || !is_scalar($value)) {
        return $default;
    }

    return filter_var($value, FILTER_VALIDATE_INT) !== false
        ? (int) $value
        : $default;
}

/**
 * Converte checkbox / valor enviado em 0 ou 1.
 */
function to_bool_int($value): int
{
    return !empty($value) && $value !== '0' ? 1 : 0;
}

/**
 * Converte texto de preco ("1.234,56" ou "1234.56") em float.
 * Devolve null para texto vazio ou inválido.
 */
function parse_price(string $value): ?float
{
    $value = trim($value);

    if ($value === '') {
        return null;
    }

    // O espaco sem quebra (\xC2\xA0) aparece ao colar valores copiados da web.
    $value = str_replace(['R$', ' ', "\xC2\xA0"], '', $value);

    if (strpos($value, ',') !== false) {
        // Formato brasileiro completo: ponto de milhar, virgula decimal.
        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);
    } elseif (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $value)) {
        // Sem virgula, mas com pontos separando grupos exatos de tres
        // digitos: "1.234" e mil duzentos e trinta e quatro, não 1,234.
        // Sem esta regra o valor seria gravado como 1.23.
        $value = str_replace('.', '', $value);
    }

    return is_numeric($value) ? round((float) $value, 2) : null;
}

// =========================================================
// Slugs
// =========================================================

/**
 * Gera um slug com apenas letras minusculas, números e hifens.
 */
function slugify(string $text): string
{
    $text = trim($text);

    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($converted !== false) {
            $text = $converted;
        }
    }

    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim((string) $text, '-');

    return $text !== '' ? $text : 'item';
}

/**
 * Garante que o slug seja único na tabela informada.
 * $ignoreId permite editar um registro sem conflitar consigo mesmo.
 */
function unique_slug(string $table, string $slug, ?int $ignoreId = null): string
{
    $allowed = ['categories', 'products'];

    if (!in_array($table, $allowed, true)) {
        throw new InvalidArgumentException('Tabela inválida para slug: ' . $table);
    }

    $base    = $slug;
    $suffix  = 1;
    $current = $slug;

    while (true) {
        $sql    = "SELECT id FROM `{$table}` WHERE slug = ?";
        $params = [$current];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $ignoreId;
        }

        $stmt = db()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);

        if ($stmt->fetch() === false) {
            return $current;
        }

        $current = $base . '-' . (++$suffix);
    }
}

// =========================================================
// Upload de imagens
// =========================================================

/**
 * Valida um arquivo vindo de $_FILES.
 * Devolve null se estiver tudo certo, ou a mensagem de erro.
 */
function validate_image_upload(array $file): ?string
{
    if (!isset($file['error']) || is_array($file['error'])) {
        return 'Envio de arquivo inválido.';
    }

    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            return 'Nenhum arquivo enviado.';
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'A imagem excede o tamanho máximo permitido.';
        default:
            return 'Falha no envio do arquivo.';
    }

    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return 'A imagem excede ' . round(MAX_UPLOAD_SIZE / 1048576) . ' MB.';
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        return 'Arquivo temporario inválido.';
    }

    $info = @getimagesize($file['tmp_name']);

    if ($info === false) {
        return 'O arquivo enviado não é uma imagem válida.';
    }

    // Formatos aceitos, com o tipo MIME que cada um deve apresentar.
    // SVG fica de fora de proposito: e XML e pode carregar JavaScript.
    $allowed = [
        IMAGETYPE_JPEG => 'image/jpeg',
        IMAGETYPE_PNG  => 'image/png',
        IMAGETYPE_WEBP => 'image/webp',
    ];

    if (!isset($allowed[$info[2]])) {
        return 'Formato não permitido. Use JPG, PNG ou WEBP.';
    }

    // Segunda opiniao, independente do cabecalho lido pelo getimagesize.
    // Um arquivo montado para enganar uma das duas checagens dificilmente
    // engana as duas, que olham o conteudo por caminhos diferentes.
    if (class_exists('finfo')) {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);

        if ($mime !== $allowed[$info[2]]) {
            return 'O conteudo do arquivo não corresponde a um JPG, PNG ou WEBP.';
        }
    }

    // Limite de dimensoes: uma imagem de poucos KB pode declarar milhoes de
    // pixels e derrubar quem tentar abri-la (decompression bomb).
    if ((int) $info[0] * (int) $info[1] > 50000000) {
        return 'A imagem tem dimensoes grandes demais.';
    }

    return null;
}

/**
 * Move a imagem enviada para uploads/products com um nome seguro.
 * Devolve o nome do arquivo gravado ou null em caso de falha.
 */
function save_uploaded_image(array $file, string $prefix = 'produto'): ?string
{
    $info = @getimagesize($file['tmp_name']);

    if ($info === false) {
        return null;
    }

    $extensions = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_WEBP => 'webp',
    ];

    if (!isset($extensions[$info[2]])) {
        return null;
    }

    if (!is_dir(UPLOAD_PATH) && !@mkdir(UPLOAD_PATH, 0755, true) && !is_dir(UPLOAD_PATH)) {
        return null;
    }

    $filename = slugify($prefix) . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4))
              . '.' . $extensions[$info[2]];

    $destination = UPLOAD_PATH . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return null;
    }

    @chmod($destination, 0644);

    return $filename;
}

/**
 * Apaga do disco uma imagem de produto.
 */
function delete_product_image_file(?string $filename): void
{
    if ($filename === null || $filename === '') {
        return;
    }

    // basename() impede qualquer tentativa de sair da pasta de uploads.
    $path = UPLOAD_PATH . '/' . basename($filename);

    if (is_file($path)) {
        @unlink($path);
    }
}

/**
 * Reorganiza $_FILES['campo'] (envio multiplo) numa lista de arquivos individuais.
 */
function normalize_files_array(array $files): array
{
    $result = [];

    if (!isset($files['name']) || !is_array($files['name'])) {
        return $result;
    }

    foreach (array_keys($files['name']) as $i) {
        $result[] = [
            'name'     => $files['name'][$i],
            'type'     => $files['type'][$i],
            'tmp_name' => $files['tmp_name'][$i],
            'error'    => $files['error'][$i],
            'size'     => $files['size'][$i],
        ];
    }

    return $result;
}

// =========================================================
// Configurações da loja (tabela settings)
// =========================================================

/**
 * Carrega a tabela settings inteira, uma única vez por requisicao.
 * Sao poucas linhas, entao vale trazer tudo de uma vez em vez de
 * consultar o banco a cada chave.
 */
function settings_all(bool $refresh = false): array
{
    static $cache = null;

    if ($cache !== null && !$refresh) {
        return $cache;
    }

    $cache = [];
    $stmt  = db()->query('SELECT setting_key, setting_value FROM settings');

    foreach ($stmt as $row) {
        $cache[$row['setting_key']] = $row['setting_value'];
    }

    return $cache;
}

/**
 * Grava uma configuracao. Cria a chave se ela ainda não existir.
 * Recarrega o cache em seguida, para que a mesma requisicao já leia
 * o valor novo.
 */
function set_setting(string $key, ?string $value): void
{
    $stmt = db()->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([$key, $value]);

    settings_all(true);
}

/**
 * Le uma configuracao da loja, por exemplo whatsapp_number.
 */
function setting(string $key, ?string $default = null): ?string
{
    $all = settings_all();

    return array_key_exists($key, $all) && $all[$key] !== null
        ? $all[$key]
        : $default;
}

// =========================================================
// WhatsApp
// =========================================================

/**
 * Reduz o que o lojista digitou a somente digitos.
 *
 * O painel aceita "+55 42 99987-4363", que e como o número costuma ser
 * escrito, mas o link wa.me não aceita sinal, espaco nem hifen. Entra
 * "+55 42 99987-4363", sai "5542999874363".
 */
function whatsapp_digits(string $value): string
{
    return (string) preg_replace('/\D+/', '', $value);
}

/**
 * Poe um número brasileiro no formato internacional que o wa.me exige.
 *
 * Formatos aceitos:
 *   nacional      DDD + 8 (fixo) ou 9 (celular)   10 ou 11 digitos
 *   internacional 55 + DDD + 8 ou 9               12 ou 13 digitos
 *
 * O que manda e o COMPRIMENTO, nunca o prefixo. O DDD 55 existe (Santa
 * Maria, RS), entao "55999874363" tem 11 digitos e comeca com 55 mas
 * ainda e um número nacional: vira "5555999874363". Decidir pelo prefixo
 * geraria um link quebrado justamente para essa regiao.
 *
 * Devolve '' quando o valor não e um número brasileiro válido. Quem
 * chama trata o vazio como "sem número", e não como número qualquer.
 */
function normalize_whatsapp_number(string $value): string
{
    $digits = whatsapp_digits($value);
    $length = strlen($digits);

    // Formato nacional: falta o código do pais, entao acrescenta.
    if ($length === 10 || $length === 11) {
        $digits = '55' . $digits;
        $length = strlen($digits);
    }

    if (($length !== 12 && $length !== 13) || strncmp($digits, '55', 2) !== 0) {
        return '';
    }

    // DDD brasileiro vai de 11 a 99 e nunca termina em zero.
    $ddd = (int) substr($digits, 2, 2);

    if ($ddd < 11 || $ddd > 99 || $ddd % 10 === 0) {
        return '';
    }

    // Celular tem 9 digitos e, desde 2016, sempre comeca com 9.
    if ($length === 13 && $digits[4] !== '9') {
        return '';
    }

    return $digits;
}

/**
 * Número da loja, pronto para montar o link do wa.me.
 *
 * Normaliza também na leitura, e não só na gravacao: um valor antigo ou
 * editado direto no banco e corrigido ou descartado aqui, de modo que a
 * loja nunca chegue a montar uma URL inválida.
 */
function store_whatsapp_number(): string
{
    return normalize_whatsapp_number((string) setting('whatsapp_number', ''));
}

// =========================================================
// Paginas temporarias de instalacao
// =========================================================

/**
 * A requisicao veio da própria maquina?
 * Serve para liberar as páginas de instalacao no desenvolvimento sem
 * abri-las para a internet.
 */
function is_local_request(): bool
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

    return in_array($ip, ['127.0.0.1', '::1', '::ffff:127.0.0.1'], true);
}

/**
 * Fecha install.php e test_connection.php para o mundo.
 *
 * Enquanto esses arquivos existirem no servidor, qualquer um pode
 * abri-los. O install.php chega a criar um administrador quando ainda
 * não existe nenhum, o que entrega a loja inteira a quem passar por ali
 * primeiro logo depois da publicacao.
 *
 * Passa quem acessa de localhost ou quem traz ?key= igual a INSTALL_KEY.
 * Para os demais a resposta e 404, e não 403: não confirma que o arquivo
 * existe.
 */
function require_setup_access(): void
{
    if (is_local_request()) {
        return;
    }

    $expected = defined('INSTALL_KEY') ? (string) INSTALL_KEY : '';
    $given    = get('key');

    if ($expected !== '' && $given !== '' && hash_equals($expected, $given)) {
        return;
    }

    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');

    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">'
       . '<title>404</title></head><body><h1>404</h1>'
       . '<p>Página não encontrada.</p></body></html>';

    exit;
}

// =========================================================
// Consultas da loja publica
// =========================================================

/**
 * Categorias ativas, na ordem definida no painel.
 * Alimenta o menu do cabecalho e a lista do rodape, entao e consultada
 * uma única vez por requisicao.
 */
function active_categories(): array
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $cache = db()->query(
        'SELECT id, name, slug FROM categories
          WHERE active = 1
          ORDER BY sort_order ASC, name ASC'
    )->fetchAll();

    return $cache;
}

/**
 * Produtos para as vitrines, já com a imagem principal e o estoque somado.
 *
 * $filter aceita apenas os valores da lista abaixo. O trecho de SQL vem
 * dessa lista fixa, nunca do que chega pela URL.
 */
function showcase_products(
    string $filter,
    int $limit = 8,
    ?int $categoryId = null,
    int $offset = 0
): array {
    $conditions = [
        'featured' => 'p.featured = 1',
        'new'      => 'p.is_new = 1',
        'best'     => 'p.best_seller = 1',
        'recent'   => '1 = 1',
        'category' => 'p.category_id = ?',
    ];

    if (!isset($conditions[$filter])) {
        throw new InvalidArgumentException('Filtro de vitrine inválido: ' . $filter);
    }

    $params = [];

    if ($filter === 'category') {
        $params[] = (int) $categoryId;
    }

    // LIMIT e OFFSET não aceitam parametro em prepared statement, entao vao
    // no texto da consulta. Sao inteiros já limitados, nunca texto da URL.
    $limit  = max(1, min(60, $limit));
    $offset = max(0, $offset);

    $stmt = db()->prepare(
        'SELECT p.id, p.name, p.slug, p.price, p.promo_price,
                p.featured, p.is_new, p.best_seller,
                c.name AS category_name, c.slug AS category_slug,
                (SELECT pi.filename FROM product_images pi
                  WHERE pi.product_id = p.id
                  ORDER BY pi.is_main DESC, pi.sort_order ASC, pi.id ASC
                  LIMIT 1) AS image,
                (SELECT COALESCE(SUM(pv.stock), 0) FROM product_variants pv
                  WHERE pv.product_id = p.id AND pv.active = 1) AS total_stock
           FROM products p
           JOIN categories c ON c.id = p.category_id
          -- c.active também entra: desativar uma categoria no painel deve
          -- tirar os produtos dela da loja, e não apenas some-la do menu.
          WHERE p.active = 1 AND c.active = 1 AND ' . $conditions[$filter] . '
          ORDER BY p.created_at DESC, p.id DESC
          LIMIT ' . $limit . ' OFFSET ' . $offset
    );
    $stmt->execute($params);

    return $stmt->fetchAll();
}

/**
 * Quantos produtos ativos a categoria tem. Usado na paginacao.
 */
function count_category_products(int $categoryId): int
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM products WHERE category_id = ? AND active = 1'
    );
    $stmt->execute([$categoryId]);

    return (int) $stmt->fetchColumn();
}

// =========================================================
// Variações
// =========================================================

/**
 * Traduz o nome do tamanho na ordem em que ele deve aparecer.
 *
 * Sem isto a ordenacao seria alfabetica, que coloca GG antes de G e M
 * antes de P. O valor calculado aqui vai para product_variants.size_order,
 * entao o lojista não precisa preencher esse campo a mao.
 *
 * Tamanhos numericos (36, 38, 40) vem depois das letras, em ordem de
 * número. Qualquer coisa desconhecida vai para o fim da lista.
 */
function size_sort_order(string $size): int
{
    // slugify tira acentos e baixa a caixa, entao "Único" chega como "único".
    $key = strtoupper(slugify($size));

    $known = [
        'PP'    => 10,
        'P'     => 20,
        'M'     => 30,
        'G'     => 40,
        'GG'    => 50,
        'XG'    => 60,
        'XGG'   => 70,
        'EG'    => 60,
        'U'     => 80,
        'UNICO' => 80,
    ];

    if (isset($known[$key])) {
        return $known[$key];
    }

    if (ctype_digit($key)) {
        return 100 + (int) $key;
    }

    return 900;
}
