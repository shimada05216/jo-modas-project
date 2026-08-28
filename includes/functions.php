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
 * Formata um valor no padrao brasileiro: R$ 1.234,56
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
        return asset_url('images/placeholder.png');
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
 * Le um campo do POST como string, ja com trim.
 */
function post(string $key, string $default = ''): string
{
    return isset($_POST[$key]) && is_scalar($_POST[$key])
        ? trim((string) $_POST[$key])
        : $default;
}

/**
 * Le um campo do GET como string, ja com trim.
 */
function get(string $key, string $default = ''): string
{
    return isset($_GET[$key]) && is_scalar($_GET[$key])
        ? trim((string) $_GET[$key])
        : $default;
}

/**
 * Le um inteiro do POST/GET. Devolve $default quando ausente ou invalido.
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
 * Devolve null para texto vazio ou invalido.
 */
function parse_price(string $value): ?float
{
    $value = trim($value);

    if ($value === '') {
        return null;
    }

    $value = str_replace(['R$', ' '], '', $value);

    // Formato brasileiro: ponto de milhar, virgula decimal.
    if (strpos($value, ',') !== false) {
        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);
    }

    return is_numeric($value) ? round((float) $value, 2) : null;
}

// =========================================================
// Slugs
// =========================================================

/**
 * Gera um slug com apenas letras minusculas, numeros e hifens.
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
 * Garante que o slug seja unico na tabela informada.
 * $ignoreId permite editar um registro sem conflitar consigo mesmo.
 */
function unique_slug(string $table, string $slug, ?int $ignoreId = null): string
{
    $allowed = ['categories', 'products'];

    if (!in_array($table, $allowed, true)) {
        throw new InvalidArgumentException('Tabela invalida para slug: ' . $table);
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
        return 'Envio de arquivo invalido.';
    }

    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            return 'Nenhum arquivo enviado.';
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'A imagem excede o tamanho maximo permitido.';
        default:
            return 'Falha no envio do arquivo.';
    }

    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return 'A imagem excede ' . round(MAX_UPLOAD_SIZE / 1048576) . ' MB.';
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        return 'Arquivo temporario invalido.';
    }

    $info = @getimagesize($file['tmp_name']);

    if ($info === false) {
        return 'O arquivo enviado nao e uma imagem valida.';
    }

    $allowed = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];

    if (!in_array($info[2], $allowed, true)) {
        return 'Formato nao permitido. Use JPG, PNG ou WEBP.';
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
