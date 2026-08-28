# Jo Modas

Loja virtual em PHP 8 + MySQL, com painel administrativo proprio.
Checkout via WhatsApp, sem gateway de pagamento e sem cadastro de clientes.

## Stack

PHP 8+, MySQL, PDO, HTML5, CSS3, JavaScript puro, sessoes PHP,
carrinho persistido em localStorage. Sem frameworks e sem Composer.

## Instalacao local

1. Instale o Laragon ou o XAMPP (PHP 8.1+, MySQL 8 ou MariaDB 10.4+).
2. Coloque o projeto em `htdocs/jo-modas` (ou `www/jo-modas` no Laragon).
3. No phpMyAdmin, crie o banco `jo_modas` com collation `utf8mb4_unicode_ci`.
4. Importe, nesta ordem:
   - `database/schema.sql`
   - `database/seed.sql`
5. Copie `config/config.example.php` para `config/config.php` e ajuste
   as credenciais e a constante `BASE_URL`.
6. Acesse `http://localhost/jo-modas/test_connection.php` e confira o resultado.

## Estrutura

```
admin/       painel administrativo
assets/      css, js e imagens do site
config/      credenciais e conexao PDO
database/    scripts SQL
includes/    funcoes, autenticacao e layout compartilhado
uploads/     imagens dos produtos enviadas pelo painel
```

## Publicacao na Hostinger

Envie todo o conteudo do projeto para `public_html`, importe os scripts SQL,
ajuste `config/config.php` com os dados do banco da hospedagem e o `BASE_URL`
do dominio, defina `DEBUG_MODE` como `false`, deixe `uploads/products` com
permissao 755 e apague `test_connection.php`.
