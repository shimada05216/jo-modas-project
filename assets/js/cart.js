/**
 * Jo Modas - Carrinho no localStorage
 *
 * Guarda variant_id, e nao apenas product_id: o que se compra e uma
 * combinacao cor + tamanho, e e nela que existe estoque e SKU.
 *
 * Nenhuma etapa daqui desconta estoque. O fechamento apenas abre o
 * WhatsApp com a mensagem do pedido pronta.
 */
(function () {
    'use strict';

    var CART_KEY = 'jomodas_cart_v1';

    // ---------------------------------------------------------
    // Armazenamento
    // ---------------------------------------------------------

    // O carrinho guarda dois tipos de linha:
    //
    //   variante -> variantId inteiro positivo. E a combinacao cor+tamanho,
    //               como sempre foi, com estoque proprio.
    //   produto  -> variantId ausente ou null. A pessoa comprou sem escolher
    //               cor nem tamanho (ver REQUIRE_VARIANT_SELECTION).
    //
    // Os dois precisam de productId, que e o que amarra a linha ao produto
    // de verdade. Carrinho antigo, gravado antes disto, so tem linhas de
    // variante e continua valendo sem conversao.
    function isValidItem(item) {
        if (!item || typeof item !== 'object') {
            return false;
        }

        if (!Number.isInteger(item.qty) || item.qty <= 0) {
            return false;
        }

        if (!Number.isInteger(item.productId) || item.productId <= 0) {
            return false;
        }

        // Sem variante e um caso legitimo; com variante ela precisa ser real.
        if (item.variantId === null || item.variantId === undefined) {
            return true;
        }

        return Number.isInteger(item.variantId) && item.variantId > 0;
    }

    // Identidade estavel da linha. Duas linhas se somam so quando apontam
    // para a mesma coisa; produto solto nunca se mistura com variante, nem
    // um produto com outro.
    function itemKey(item) {
        return (item.variantId === null || item.variantId === undefined)
            ? 'product:' + item.productId
            : 'variant:' + item.variantId;
    }

    function readCart() {
        try {
            var raw = window.localStorage.getItem(CART_KEY);
            var data = raw ? JSON.parse(raw) : [];

            return Array.isArray(data) ? data.filter(isValidItem) : [];
        } catch (err) {
            // Modo privado, cota cheia ou conteudo corrompido: segue vazio
            // em vez de derrubar a pagina.
            return [];
        }
    }

    function writeCart(items) {
        try {
            window.localStorage.setItem(CART_KEY, JSON.stringify(items));
        } catch (err) {
            /* sem persistencia; a pagina continua funcionando na sessao atual */
        }

        updateBadge(items);
    }

    function updateBadge(items) {
        var badge = document.getElementById('cart-count');

        if (!badge) {
            return;
        }

        var list = items || readCart();
        var total = list.reduce(function (sum, item) { return sum + item.qty; }, 0);

        badge.textContent = String(total);
        badge.hidden = total === 0;
    }

    // ---------------------------------------------------------
    // Formatacao
    // ---------------------------------------------------------

    /**
     * Formata no padrao brasileiro: R$ 1.234,56
     *
     * Feito a mao, sem toLocaleString, para nao depender da tabela de
     * locales do navegador: em builds sem ICU completo o pt-BR cai para
     * o formato americano e o pedido sairia com "R$ 1,234.56".
     */
    function money(value) {
        var amount = Number(value);

        if (!isFinite(amount)) {
            amount = 0;
        }

        var negative = amount < 0;
        var parts = Math.abs(amount).toFixed(2).split('.');
        var whole = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');

        return (negative ? '-' : '') + 'R$ ' + whole + ',' + parts[1];
    }

    // =========================================================
    // Pagina do produto
    // =========================================================

    function initProduct(data) {
        var form      = document.getElementById('buy-form');
        var colorList = document.getElementById('color-list');
        var sizeList  = document.getElementById('size-list');
        var stockLine = document.getElementById('stock-line');
        var qtyInput  = document.getElementById('qty');
        var addButton = document.getElementById('add-to-cart');
        var buyNow    = document.getElementById('buy-now');
        var addedMsg  = document.getElementById('added-msg');

        // Sem numero cadastrado o "comprar agora" nao tem para onde ir.
        var storeNumber = (data.whatsapp || '');

        if (buyNow && storeNumber.length < 10) {
            buyNow.hidden = true;
        }

        // Sem o formulario nao ha o que ligar. Ja os seletores de cor e
        // tamanho podem simplesmente nao existir: produto sem variacao, ou
        // modo de compra simples. Nesse caso o resto da pagina continua
        // funcionando -- era aqui que a compra sem variacao morria.
        if (!form) {
            return;
        }

        // Modo estrito exige cor e tamanho. Na duvida (dado antigo, sem o
        // campo), assume estrito: e o comportamento mais conservador.
        var strict = data.strict !== false;

        var selectedColor = null;
        var selectedVariant = null;

        // O que esta selecionado agora, do jeito que o carrinho entende.
        // No modo simples, nao ter escolhido nada e uma resposta valida:
        // vira uma linha de produto, sem cor, sem tamanho e sem estoque
        // reservado. Nada e escolhido por conta propria.
        function currentChoice() {
            if (selectedVariant) {
                return {
                    variantId: selectedVariant.id,
                    color: selectedVariant.color,
                    size: selectedVariant.size,
                    stock: selectedVariant.stock
                };
            }

            if (strict) {
                return null;
            }

            return { variantId: null, color: '', size: '', stock: null };
        }

        function variantsForColor(color) {
            return data.variants.filter(function (variant) {
                return variant.color === color;
            });
        }

        function clearSelection() {
            selectedVariant = null;
            qtyInput.value = 1;

            // No modo simples a compra nunca depende da escolha, entao os
            // controles seguem ligados mesmo sem cor e tamanho.
            qtyInput.disabled = strict;
            qtyInput.removeAttribute('max');
            addButton.disabled = strict;

            if (buyNow) { buyNow.disabled = strict; }
        }

        function renderSizes(color) {
            sizeList.innerHTML = '';

            variantsForColor(color).forEach(function (variant) {
                var button = document.createElement('button');

                button.type = 'button';
                button.className = 'option';
                button.dataset.variantId = String(variant.id);

                if (variant.stock <= 0) {
                    // Regra central: sem estoque nao e comprado. O tamanho
                    // continua visivel, mas nao pode ser escolhido.
                    button.classList.add('is-out');
                    button.disabled = true;
                    button.textContent = variant.size + ' (esgotado)';
                } else {
                    button.textContent = variant.size;
                }

                button.addEventListener('click', function () {
                    selectSize(variant, button);
                });

                sizeList.appendChild(button);
            });
        }

        function selectSize(variant, button) {
            selectedVariant = variant;

            Array.prototype.forEach.call(sizeList.children, function (el) {
                el.classList.remove('is-active');
            });
            button.classList.add('is-active');

            stockLine.textContent = variant.stock === 1
                ? 'Última peça disponível.'
                : variant.stock + ' peças disponíveis.';

            qtyInput.disabled = false;
            qtyInput.max = String(variant.stock);
            qtyInput.value = 1;
            addButton.disabled = false;

            if (buyNow) { buyNow.disabled = false; }

            addedMsg.hidden = true;
        }

        function selectColor(color, button) {
            selectedColor = color;

            Array.prototype.forEach.call(colorList.children, function (el) {
                el.classList.remove('is-active');
            });
            button.classList.add('is-active');

            clearSelection();
            renderSizes(color);

            var anyStock = variantsForColor(color).some(function (variant) {
                return variant.stock > 0;
            });

            stockLine.textContent = anyStock
                ? 'Escolha o tamanho.'
                : 'Todos os tamanhos desta cor estão esgotados.';
        }

        // Os seletores so existem quando o produto tem variacao. Sem eles a
        // pagina segue: e a compra simples.
        if (colorList && sizeList) {
            Array.prototype.forEach.call(colorList.children, function (button) {
                button.addEventListener('click', function () {
                    selectColor(button.dataset.color, button);
                });
            });
        }

        // Estado inicial: no modo simples ja nasce pronto para comprar.
        clearSelection();

        // "Comprar agora" / peça única: monta a mensagem so com este item e
        // abre o WhatsApp, sem tocar no carrinho. Usa o mesmo buildMessage
        // do carrinho, para as duas mensagens sairem no mesmo formato.
        //
        // Nada de estoque e alterado aqui, como em todo o resto do site.
        if (buyNow) {
            buyNow.addEventListener('click', function () {
                var choice = currentChoice();

                if (!choice || storeNumber.length < 10) {
                    return;
                }

                // Variacao escolhida sem estoque nao passa. Sem variacao
                // nao ha estoque a respeitar: e interesse no produto, e a
                // disponibilidade se confirma na conversa.
                if (choice.stock !== null && choice.stock <= 0) {
                    return;
                }

                var qty = parseInt(qtyInput.value, 10);

                if (!Number.isInteger(qty) || qty < 1) {
                    qty = 1;
                }

                if (choice.stock !== null && qty > choice.stock) {
                    qty = choice.stock;
                }

                var message = buildMessage([{
                    available: true,
                    qty: qty,
                    name: data.name,
                    color: choice.color,
                    size: choice.size,
                    price: data.price
                }], data.greeting || 'Ola, gostaria de fazer este pedido:');

                window.open(
                    'https://wa.me/' + storeNumber + '?text=' + encodeURIComponent(message),
                    '_blank',
                    'noopener'
                );
            });
        }

        qtyInput.addEventListener('input', function () {
            var value = parseInt(qtyInput.value, 10);

            if (!Number.isInteger(value) || value < 1) {
                value = 1;
            }

            // O teto so existe quando ha variacao escolhida. Sem ela nao se
            // inventa estoque: a quantidade e um pedido, nao uma reserva.
            if (selectedVariant && value > selectedVariant.stock) {
                value = selectedVariant.stock;
            }

            qtyInput.value = String(value);
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            var choice = currentChoice();

            if (!choice) {
                return;
            }

            if (choice.stock !== null && choice.stock <= 0) {
                return;
            }

            var qty = parseInt(qtyInput.value, 10);

            if (!Number.isInteger(qty) || qty < 1) {
                qty = 1;
            }

            var items = readCart();
            var novo = {
                variantId: choice.variantId,
                productId: data.id,
                name: data.name,
                url: data.url,
                image: data.image,
                color: choice.color,
                size: choice.size,
                price: data.price,
                qty: qty
            };
            var chave = itemKey(novo);
            var existing = null;

            for (var i = 0; i < items.length; i++) {
                if (itemKey(items[i]) === chave) {
                    existing = items[i];
                    break;
                }
            }

            var alreadyMaxed = false;

            if (existing) {
                var wanted = existing.qty + qty;

                // Teto so quando ha variacao: e o estoque dela que manda.
                if (choice.stock !== null && wanted > choice.stock) {
                    wanted = choice.stock;
                    alreadyMaxed = existing.qty >= choice.stock;
                }

                existing.qty = wanted;
            } else {
                if (choice.stock !== null && qty > choice.stock) {
                    qty = choice.stock;
                }

                novo.qty = qty;
                items.push(novo);
            }

            writeCart(items);

            addedMsg.hidden = false;
            addedMsg.firstChild.textContent = alreadyMaxed
                ? 'Você já tem todo o estoque disponível no carrinho. '
                : 'Produto adicionado. ';
        });
    }

    // =========================================================
    // Pagina do carrinho
    // =========================================================

    function initCart(root) {
        var items = readCart();

        if (items.length === 0) {
            renderEmpty(root);
            return;
        }

        // O servidor confere as duas coisas: variacoes e produtos soltos.
        // "ids" continua indo com o mesmo nome de antes, para um carrinho
        // antigo seguir sendo conferido igual.
        var ids = items
            .map(function (item) { return item.variantId; })
            .filter(function (id) { return Number.isInteger(id) && id > 0; });

        var productIds = items
            .filter(function (item) { return item.variantId === null || item.variantId === undefined; })
            .map(function (item) { return item.productId; });

        fetch(root.dataset.endpoint, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ ids: ids, productIds: productIds })
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('resposta invalida');
                }

                return response.json();
            })
            .then(function (payload) {
                renderCart(root, reconcile(items, payload.items || []));
            })
            .catch(function () {
                root.innerHTML = '<p class="notice notice-warn">Não foi possível conferir '
                    + 'o carrinho agora. Atualize a página e tente de novo.</p>';
            });
    }

    /**
     * Cruza o carrinho local com o que o banco devolveu.
     * O servidor manda; o localStorage so guarda a quantidade escolhida.
     */
    function reconcile(localItems, serverItems) {
        var byId = {};

        // Indexado pela mesma identidade das duas pontas, senao uma linha
        // de produto e uma de variacao com numeros iguais se confundiriam.
        serverItems.forEach(function (item) {
            byId[itemKey(item)] = item;
        });

        var merged = [];

        localItems.forEach(function (localItem) {
            var fresh = byId[itemKey(localItem)];

            // Variacao ou produto que saiu do ar: deixa o carrinho sem alarde.
            if (!fresh) {
                return;
            }

            var qty = localItem.qty;

            // Estoque caiu desde que o item entrou no carrinho. So vale para
            // linha de variacao: produto solto nao tem estoque reservado.
            if (fresh.available && fresh.stock !== null && qty > fresh.stock) {
                qty = fresh.stock;
            }

            merged.push({
                variantId: fresh.variantId,
                productId: fresh.productId,
                name: fresh.name,
                url: fresh.url,
                image: fresh.image,
                color: fresh.color,
                size: fresh.size,
                price: fresh.price,
                stock: fresh.stock,
                available: fresh.available,
                qty: qty
            });
        });

        // Grava de volta ja corrigido, sem os campos de conferencia.
        writeCart(merged.map(function (item) {
            return {
                variantId: item.variantId,
                productId: item.productId,
                name: item.name,
                url: item.url,
                image: item.image,
                color: item.color,
                size: item.size,
                price: item.price,
                qty: item.qty
            };
        }));

        return merged;
    }

    function renderEmpty(root) {
        root.innerHTML = '<p class="notice">Seu carrinho está vazio.</p>';
        updateBadge([]);
    }

    function renderCart(root, items) {
        if (items.length === 0) {
            renderEmpty(root);
            return;
        }

        root.innerHTML = '';

        var list = document.createElement('div');
        list.className = 'cart-list';

        var total = 0;
        var blocked = 0;

        items.forEach(function (item) {
            if (item.available) {
                total += item.price * item.qty;
            } else {
                blocked++;
            }

            list.appendChild(renderRow(root, item, items));
        });

        root.appendChild(list);

        var summary = document.createElement('div');
        summary.className = 'cart-summary';

        var totalLine = document.createElement('p');
        totalLine.className = 'cart-total';
        totalLine.textContent = 'Total: ' + money(total);
        summary.appendChild(totalLine);

        if (blocked > 0) {
            var warn = document.createElement('p');
            warn.className = 'notice notice-warn';
            warn.textContent = blocked === 1
                ? 'Um item saiu de estoque e precisa ser removido antes de enviar o pedido.'
                : blocked + ' itens saíram de estoque e precisam ser removidos antes de enviar o pedido.';
            summary.appendChild(warn);
        }

        var whatsapp = root.dataset.whatsapp || '';
        var greeting = root.dataset.greeting || 'Olá, gostaria de fazer este pedido:';

        var buyable = items.filter(function (item) { return item.available; });

        var send = document.createElement('button');

        send.type = 'button';
        send.className = 'btn-buy';
        send.textContent = 'Enviar pedido pelo WhatsApp';

        if (blocked > 0 || buyable.length === 0 || total <= 0 || whatsapp.length < 10) {
            send.disabled = true;
        } else {
            send.addEventListener('click', function () {
                // Segunda conferencia, no instante do clique: o carrinho pode
                // ter sido esvaziado noutra aba desde que a pagina abriu, e uma
                // mensagem de pedido vazia nao deve chegar ao lojista.
                var current = readCart();

                if (current.length === 0 || buyable.length === 0) {
                    renderCart(root, []);
                    return;
                }

                var message = buildMessage(buyable, greeting);
                var url = 'https://wa.me/' + whatsapp + '?text=' + encodeURIComponent(message);

                window.open(url, '_blank', 'noopener');
            });
        }

        summary.appendChild(send);
        root.appendChild(summary);

        updateBadge(items);
    }

    function renderRow(root, item, allItems) {
        var row = document.createElement('div');
        row.className = 'cart-row' + (item.available ? '' : ' is-unavailable');

        var img = document.createElement('img');
        img.src = item.image;
        img.alt = item.name;
        img.loading = 'lazy';
        row.appendChild(img);

        var info = document.createElement('div');
        info.className = 'cart-info';

        var title = document.createElement('a');
        title.href = item.url;
        title.className = 'cart-name';
        title.textContent = item.name;
        info.appendChild(title);

        var opts = document.createElement('p');
        opts.className = 'cart-options';
        opts.textContent = item.color + ' / ' + item.size;
        info.appendChild(opts);

        var unit = document.createElement('p');
        unit.className = 'cart-unit';
        unit.textContent = money(item.price);
        info.appendChild(unit);

        if (!item.available) {
            var out = document.createElement('p');
            out.className = 'cart-out';
            out.textContent = 'Indisponível no momento';
            info.appendChild(out);
        }

        row.appendChild(info);

        var controls = document.createElement('div');
        controls.className = 'cart-controls';

        if (item.available) {
            var qty = document.createElement('input');
            qty.type = 'number';
            qty.min = '1';
            // Produto solto nao tem teto: nao existe estoque reservado nele.
            if (item.stock !== null && item.stock !== undefined) {
                qty.max = String(item.stock);
            }
            qty.step = '1';
            qty.value = String(item.qty);
            qty.className = 'cart-qty';

            qty.addEventListener('change', function () {
                var value = parseInt(qty.value, 10);

                if (!Number.isInteger(value) || value < 1) {
                    value = 1;
                }

                if (item.stock !== null && item.stock !== undefined && value > item.stock) {
                    value = item.stock;
                }

                qty.value = String(value);
                item.qty = value;

                persist(allItems);
                renderCart(root, allItems);
            });

            controls.appendChild(qty);

            var sub = document.createElement('span');
            sub.className = 'cart-subtotal';
            sub.textContent = money(item.price * item.qty);
            controls.appendChild(sub);
        }

        var remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'cart-remove';
        remove.textContent = 'Remover';

        remove.addEventListener('click', function () {
            var index = allItems.indexOf(item);

            if (index > -1) {
                allItems.splice(index, 1);
            }

            persist(allItems);
            renderCart(root, allItems);
        });

        controls.appendChild(remove);
        row.appendChild(controls);

        return row;
    }

    function persist(items) {
        writeCart(items.map(function (item) {
            return {
                variantId: item.variantId,
                productId: item.productId,
                name: item.name,
                url: item.url,
                image: item.image,
                color: item.color,
                size: item.size,
                price: item.price,
                qty: item.qty
            };
        }));
    }

    /**
     * Monta a mensagem do pedido.
     *
     *   Ola, gostaria de fazer este pedido na Jo Modas:
     *
     *   1x Vestido Floral
     *   Cor: Preto
     *   Tamanho: M
     *   Valor: R$ 89,90
     *
     *   Total: R$ 89,90
     *
     * A saudacao chega pronta do PHP, com acento e com o nome da loja
     * vindo das configuracoes do painel.
     *
     * Itens indisponiveis nunca entram: o botao ja fica bloqueado quando
     * existe algum, e aqui eles sao filtrados de novo.
     */
    function buildMessage(items, greeting) {
        var lines = [greeting, ''];
        var total = 0;

        items.forEach(function (item) {
            if (!item.available) {
                return;
            }

            var subtotal = item.price * item.qty;
            total += subtotal;

            lines.push(item.qty + 'x ' + item.name);

            // Cor e tamanho so entram quando existem de verdade. Compra
            // sem variacao nao inventa linha, e nunca sai "Cor: undefined"
            // no pedido que chega ao lojista.
            if (item.color) {
                lines.push('Cor: ' + item.color);
            }

            if (item.size) {
                lines.push('Tamanho: ' + item.size);
            }

            lines.push('Valor: ' + money(item.price));

            // Com mais de uma peca o valor unitario sozinho nao fecha a
            // conta, entao a linha do subtotal entra para nao dar duvida.
            if (item.qty > 1) {
                lines.push('Subtotal: ' + money(subtotal));
            }

            lines.push('');
        });

        lines.push('Total: ' + money(total));

        return lines.join('\n');
    }

    // =========================================================
    // Inicio
    // =========================================================

    // =========================================================
    // Comprar direto do cartao (modo simples)
    //
    // O botao so existe quando REQUIRE_VARIANT_SELECTION e false. Toca,
    // entra no carrinho, o contador do cabecalho sobe e o proprio botao
    // avisa. Nao navega: quem quiser escolher cor e tamanho abre o
    // produto pela imagem, pelo nome ou pelo preco.
    // =========================================================

    function initCardBuy() {
        document.addEventListener('click', function (event) {
            var botao = event.target.closest ? event.target.closest('.js-card-buy') : null;

            if (!botao) {
                return;
            }

            event.preventDefault();

            var productId = parseInt(botao.dataset.productId, 10);

            if (!Number.isInteger(productId) || productId <= 0) {
                return;
            }

            var items = readCart();
            var chave = 'product:' + productId;
            var achou = false;

            for (var i = 0; i < items.length; i++) {
                if (itemKey(items[i]) === chave) {
                    items[i].qty += 1;
                    achou = true;
                    break;
                }
            }

            if (!achou) {
                items.push({
                    variantId: null,
                    productId: productId,
                    name: botao.dataset.name || '',
                    url: botao.dataset.url || '',
                    image: botao.dataset.image || '',
                    color: '',
                    size: '',
                    price: Number(botao.dataset.price) || 0,
                    qty: 1
                });
            }

            writeCart(items);

            // Retorno visivel, sem tirar a pessoa da vitrine.
            var original = botao.dataset.rotulo || botao.textContent;

            botao.dataset.rotulo = original;
            botao.textContent = 'Adicionado';
            botao.classList.add('is-added');

            window.clearTimeout(botao._voltar);
            botao._voltar = window.setTimeout(function () {
                botao.textContent = botao.dataset.rotulo;
                botao.classList.remove('is-added');
            }, 1600);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        updateBadge();
        initCardBuy();

        var productData = document.getElementById('jm-product-data');

        if (productData) {
            try {
                initProduct(JSON.parse(productData.textContent));
            } catch (err) {
                /* dados do produto ilegiveis: a pagina segue so como vitrine */
            }
        }

        var cartRoot = document.getElementById('cart-root');

        if (cartRoot) {
            initCart(cartRoot);
        }

        var thumbs = document.querySelectorAll('.gallery-thumbs .thumb');
        var main = document.getElementById('gallery-main');

        if (main && thumbs.length > 0) {
            Array.prototype.forEach.call(thumbs, function (thumb) {
                thumb.addEventListener('click', function () {
                    main.src = thumb.dataset.full;

                    Array.prototype.forEach.call(thumbs, function (el) {
                        el.classList.remove('is-active');
                    });

                    thumb.classList.add('is-active');
                });
            });
        }
    });
}());
