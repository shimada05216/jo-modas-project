# Jo Modas — Instalação e atualização

Catálogo de moda feminina com painel administrativo próprio.
Os pedidos são finalizados pelo WhatsApp; o site não processa pagamento.


---

## Qual caminho seguir

Este pacote serve para duas situações diferentes. Siga **só uma** delas:

- **Já tem a loja no ar** e quer aplicar esta atualização →
  vá para **"Atualização de uma instalação existente"**, logo abaixo.
- **Instalação nova, do zero**, num servidor onde a loja nunca rodou →
  pule a seção seguinte e comece no **Passo 1**.

Misturar os dois caminhos apaga dados. A seção de atualização explica
exatamente o que não tocar.

---

## Atualização de uma instalação existente

> **Este é o caminho para quem já está com a loja funcionando.**
>
> **Esta atualização não mexe no banco de dados.** É troca de arquivos e
> nada mais. Nenhum produto, categoria, imagem, variação, configuração
> ou administrador é alterado.

### 1. Faça backup — do banco e dos arquivos

Antes de qualquer coisa, e sem exceção.

- **Banco:** hPanel → phpMyAdmin, selecione o banco, aba **Exportar** →
  **Executar**. Guarde o `.sql`.
- **Arquivos:** baixe uma cópia da pasta do site, ou use o backup do
  próprio hPanel.

É por aqui que se volta atrás se algo der errado.

### 2. Substitua os arquivos do site

Envie o conteúdo desta pasta para `public_html` (ou a pasta do domínio),
por cima dos arquivos atuais.

Envie **inclusive os arquivos que começam com ponto** (`.htaccess`).
Muitos programas de FTP escondem esses arquivos por padrão, e são eles
que protegem as pastas internas.

### 3. NÃO substitua o seu `config/config.php`

> ### ⚠️ O `config/config.php` que já está no servidor deve permanecer como está.
>
> É ele que guarda as credenciais do seu banco e o endereço da loja.
> O pacote traz apenas o `config/config.example.php`, que é um modelo —
> **não renomeie nem copie por cima do seu.**

Também **não envie a pasta `uploads/products/`** do pacote: as fotos dos
seus produtos reais estão na do servidor. As imagens que vêm aqui são
apenas de demonstração.

### 4. NÃO importe nenhum arquivo SQL

Esta atualização **não precisa de banco de dados**. Não abra o
phpMyAdmin para importar nada.

| Arquivo | O que fazer |
|---|---|
| `database/schema.sql` | **não importe** — apaga e recria todas as tabelas |
| `database/seed.sql` | **não importe** — sobrescreve configurações |
| `database/demo_data.sql` | **não importe** — insere produtos de demonstração |
| `database/migration_001` a `004` | **não importe** — já foram aplicados |

A `migration_004_show_without_stock.sql` foi aplicada na atualização
anterior; ela continua no pacote apenas para quem for instalar do zero.
Rodar de novo só produziria um erro de coluna duplicada — inofensivo,
mas desnecessário.

Se quiser confirmar que ela já está aplicada, rode na aba **SQL** do
phpMyAdmin:

```sql
SHOW COLUMNS FROM `products` LIKE 'show_without_stock';
```

Uma linha de resposta = tudo certo, não há nada a fazer. Nenhuma linha
= importe **apenas** `database/migration_004_show_without_stock.sql`.

### 5. Abra a loja e confira

Acesse o endereço da loja. Deve aparecer o logo em **preto e dourado**,
a faixa de categorias e os produtos.

> **Continua com as cores antigas?** É o navegador guardando os arquivos
> anteriores. Recarregue com **Ctrl + F5**, ou abra numa janela anônima.
> A partir desta versão o próprio sistema evita isso sozinho nas próximas
> atualizações.

### 6. Teste o painel

Entre em **Painel → Produtos → Novo produto** e cadastre uma peça com
nome, categoria, preço e uma imagem, **sem preencher variação**. Deve
salvar de uma vez e voltar para a lista com a imagem no lugar.

Depois teste o carrinho e o envio pelo WhatsApp.

### 7. Apague os arquivos de instalação

Se `install.php` e `test_connection.php` estiverem no servidor e você já
tem administrador criado, **apague os dois**.

### Pronto

A atualização termina aqui. Os passos numerados adiante são para
**instalação nova** e não se aplicam a você.

---

## Limite de tamanho das imagens

O sistema **detecta sozinho** o limite configurado no PHP do servidor e
mostra esse valor na tela de cadastro de produto. Se a hospedagem estiver
com `upload_max_filesize = 2M`, o painel dirá **2 MB** — e vai avisar
antes do envio se a imagem passar disso, em vez de deixar o envio falhar
sem explicação.

Se quiser permitir imagens maiores, até 5 MB, ajuste no
**hPanel → PHP Configuration** (quando disponível no seu plano):

```
upload_max_filesize = 5M
post_max_size = 16M
```

`post_max_size` precisa ser maior, porque vale para o envio inteiro —
várias imagens de uma vez somam.

**Isto é opcional.** A loja funciona normalmente sem mexer em nada; só
respeita o limite que o servidor já tiver.

---

## O que mudou nesta versão

| Novidade | Onde |
|---|---|
| **Compra simples**: o cliente compra sem escolher cor e tamanho | Loja |
| **Comprar** direto do cartão, sem abrir o produto | Loja |
| Variação passa a ser **opcional** no cadastro | Painel → Produtos |
| Correção: a imagem não se perde mais ao cadastrar o primeiro produto | Painel → Produtos |
| Ao salvar, volta para a lista de produtos | Painel → Produtos |
| O limite de tamanho de imagem mostrado é o do servidor | Painel → Produtos |
| Cabeçalho do computador numa linha só, tudo alinhado | Loja |
| Logo e painel em preto e dourado | Loja e painel |
| Correções não são mais escondidas pelo cache do navegador | Loja e painel |
| Limite de tentativas de login mais folgado (8 por 15 minutos) | Painel |

As telas antigas de **Imagens** e **Variações** continuam existindo e
funcionando, para quem preferir editar por elas.

### Compra simples e variações

A loja vem no **modo de compra simples**: o cliente clica em Comprar e o
produto entra no carrinho, sem precisar escolher cor e tamanho. Se a peça
tiver variações cadastradas, elas aparecem como **"Cor (opcional)"** e
**"Tamanho (opcional)"** — quem escolher, leva a escolha no pedido do
WhatsApp; quem não escolher, compra do mesmo jeito.

Cadastrar variação deixou de ser obrigatório. Uma peça sem cor e tamanho
é vendida normalmente.

Para voltar a **exigir** cor e tamanho, abra `config/config.php` e troque
uma linha:

```php
define('REQUIRE_VARIANT_SELECTION', true);
```

Só isso. Não mexe em banco, nem em produto já cadastrado, nem em carrinho
que o cliente já tenha montado.

### Sobre "Exibir produto mesmo sem estoque cadastrado"

Essa caixa controla se o produto **aparece** na vitrine quando não há
nenhuma variação com estoque:

- **marcada** → o produto aparece;
- **desmarcada** → o produto some da loja enquanto não houver estoque.

Ao cadastrar um produto novo no modo de compra simples ela já vem
marcada, porque nesse modo variação é opcional e o produto precisa
aparecer. Ao editar, vale exatamente o que você deixou salvo.

No modo de compra estrita (`REQUIRE_VARIANT_SELECTION = true`) o produto
sem estoque aparece como **"Indisponível no momento"**, com um botão para
o cliente consultar disponibilidade pelo WhatsApp.

---

## Antes de começar

| Requisito | Valor |
|---|---|
| PHP | **8.0 ou superior** (testado em 8.5) |
| Banco | **MySQL 5.7+** ou **MariaDB 10.4+** |
| Extensões PHP | `pdo_mysql`, `mbstring`, `fileinfo`, `iconv` |
| URL amigável | **Não é necessária.** O site usa endereços `.php` diretos, sem `mod_rewrite` |
| Cron / tarefa agendada | **Nenhuma** |
| Certificado SSL | Recomendado (a Hostinger fornece gratuitamente) |

Todas essas extensões já vêm ativas no plano padrão da Hostinger.
Na dúvida, o passo 6 confere isso para você.

---

## Passo 1 — Criar o banco de dados MySQL

No **hPanel → Bancos de Dados → MySQL**, crie um banco novo.

Anote os quatro dados que aparecem:

- nome do banco (algo como `u123456789_jomodas`)
- usuário
- senha
- servidor (normalmente `localhost`)

Escolha a codificação **utf8mb4** se o painel perguntar.

---

## Passo 2 — Importar o SQL

Abra o **phpMyAdmin** pelo hPanel, selecione o banco criado e importe
**nesta ordem**:

1. `database/schema.sql` — cria as tabelas
2. `database/seed.sql` — cria as configurações iniciais e as categorias
3. `database/demo_data.sql` — **opcional**: produtos de demonstração

> O `demo_data.sql` traz os produtos de teste que acompanham este pacote,
> com as imagens da pasta `uploads/products`. Serve para você ver a loja
> funcionando antes de cadastrar os produtos reais. Depois é só apagar
> esses produtos pelo painel.

**Atenção:** `schema.sql` apaga e recria todas as tabelas. Use apenas
numa instalação nova. Se um dia precisar reinstalar num banco que já tem
dados, faça backup antes.

Os arquivos `migration_00*.sql` **não** são usados numa instalação nova.
Eles servem apenas para atualizar um banco antigo, e o `schema.sql` já
contém tudo que eles fazem.

---

## Passo 3 — Configurar as credenciais do banco

Na pasta `config/`, copie `config.example.php` para `config.php` e
preencha:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'u123456789_jomodas');
define('DB_USER', 'u123456789_jomodas');
define('DB_PASS', 'sua_senha_do_banco');

define('BASE_URL', 'https://www.seudominio.com.br');
```

`BASE_URL` é o endereço público da loja, **sem barra no final**.

Deixe `DEBUG_MODE` como `false` no site publicado.

**Este é o único arquivo que você precisa editar.**

---

## Passo 4 — Enviar os arquivos

Envie todo o conteúdo desta pasta para `public_html` (ou para a pasta do
domínio), por FTP ou pelo Gerenciador de Arquivos do hPanel.

Envie **inclusive os arquivos que começam com ponto**, como o
`.htaccess`. Muitos programas de FTP escondem esses arquivos por padrão.
Eles são o que impede o acesso direto às pastas `config/`, `includes/` e
`database/`, e o que impede a execução de código na pasta de imagens.

---

## Passo 5 — Permissão de escrita nas imagens

A pasta abaixo precisa aceitar gravação, porque é onde o painel salva as
fotos dos produtos:

```
uploads/products/
```

Permissão **755** costuma bastar na Hostinger. Se o upload falhar, tente
**775**.

Nenhuma outra pasta precisa de permissão especial.

---

## Passo 6 — Conferir a instalação

Para liberar as páginas de instalação, abra `config/config.php` e defina
uma chave qualquer, longa e aleatória:

```php
define('INSTALL_KEY', 'troque-isto-por-algo-aleatorio-9f3a2b');
```

Depois acesse, trocando pelo seu domínio e pela sua chave:

```
https://www.seudominio.com.br/test_connection.php?key=troque-isto-por-algo-aleatorio-9f3a2b
```

Todas as linhas devem aparecer como **OK**: versão do PHP, extensões,
conexão com o banco, tabelas e permissão de escrita.

Se alguma linha falhar, a própria página diz o que está faltando.

---

## Passo 7 — Criar o administrador

```
https://www.seudominio.com.br/install.php?key=SUA_CHAVE
```

Informe nome, e-mail e uma senha de pelo menos 10 caracteres.

Essa página **se desliga sozinha** depois que o administrador é criado:
se alguém abrir de novo, ela recusa.

### Depois de criar o administrador

1. **Apague `install.php` e `test_connection.php`** do servidor
2. Volte `INSTALL_KEY` para vazio: `define('INSTALL_KEY', '');`

Esses dois arquivos são só para a instalação e não devem ficar num site
publicado.

### Se esquecer a senha

No phpMyAdmin, rode o comando abaixo, envie o `install.php` de novo e
repita este passo:

```sql
UPDATE admins SET password_hash = 'DEFINIR_HASH' WHERE email = 'seu@email.com';
```

---

## Passo 8 — Abrir a loja e o painel

- Loja: `https://www.seudominio.com.br/`
- Painel: `https://www.seudominio.com.br/admin/index.php`

---

## Passo 9 — Configurar o WhatsApp

No painel, em **Configurações**, informe o número que vai receber os
pedidos.

Pode digitar do jeito que preferir — `+55 42 99987-4363` ou
`42999874363`. O sistema guarda só os dígitos e acrescenta o código do
país sozinho.

A página mostra o link gerado; clique nele para confirmar que abre a
conversa certa.

---

## Passo 10 — Cadastrar categorias e produtos

No painel:

1. **Categorias** — as categorias ativas viram o menu do site e a faixa
   da página inicial, automaticamente
2. **Produtos → Novo produto** — tudo numa tela só:
   - nome, endereço, SKU e descrição
   - categoria (o botão **+** cria uma nova ali mesmo, sem sair da página)
   - preço e preço promocional
   - imagens (várias; a primeira vira a principal, e dá para trocar)
   - variações: cor + tamanho, cada combinação com seu estoque
   - marcadores Destaque / Novidade / Mais vendido

O produto é gravado inteiro de uma vez: ou tudo entra, ou nada entra.
Se algo falhar no meio, nenhuma imagem solta fica no servidor e nenhum
produto pela metade aparece no painel.

> Um produto sem variação com estoque aparece como **Esgotado** e não
> pode ser comprado, porque o estoque pertence à combinação de cor e
> tamanho. Cadastre as variações para o produto ficar disponível — ou
> marque **"Exibir produto mesmo sem estoque cadastrado"** para deixá-lo
> na vitrine como **Indisponível no momento**, com consulta pelo WhatsApp.

As telas separadas de **Imagens** e **Variações** continuam disponíveis
na lista de produtos, para ajustes rápidos num produto já criado.

---

## Passo 11 — Testar o fluxo completo

1. Abra a loja e veja os produtos na primeira tela
2. Entre num produto, escolha cor e tamanho
3. Confira que um tamanho sem estoque aparece riscado e não pode ser escolhido
4. Adicione ao carrinho e atualize a página — o carrinho continua lá
5. Altere a quantidade
6. Clique em **Enviar pedido pelo WhatsApp** e confira a mensagem
7. Teste também o **Comprar agora**, que envia só aquele item
8. Repita tudo pelo celular

---

## Como funciona o estoque

O site mostra a disponibilidade e **impede a compra de uma combinação
sem estoque**, mas **não dá baixa automática** quando o cliente abre o
WhatsApp — abrir a conversa ainda não é uma venda confirmada.

A baixa é feita por você no painel, em **Variações**, depois que o
pedido se confirmar.

---

## Onde fica cada coisa

| O quê | Onde |
|---|---|
| Credenciais do banco | `config/config.php` |
| Fotos dos produtos | `uploads/products/` |
| Estrutura do banco | `database/schema.sql` |
| Dados iniciais | `database/seed.sql` |
| Produtos de demonstração | `database/demo_data.sql` |
| Painel | `admin/` |
| Estilos e scripts | `assets/` |

---

## Segurança

Já vem configurado: senhas com hash, consultas preparadas contra SQL
injection, proteção CSRF nos formulários do painel, escape de HTML,
bloqueio após 5 tentativas de login erradas, validação de upload e
pastas internas protegidas por `.htaccess`.

Do seu lado, três coisas importam:

1. **Ative o SSL** e depois coloque `FORCE_HTTPS` como `true`
2. **Apague `install.php` e `test_connection.php`** após instalar
3. **Mantenha `DEBUG_MODE` como `false`**

Faça backup do banco de tempos em tempos — a Hostinger oferece isso no
painel.
