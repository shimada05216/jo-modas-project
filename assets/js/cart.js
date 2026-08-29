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

    function isValidItem(item) {
        return item
            && typeof item === 'object'
            && Number.isInteger(item.variantId) && item.variantId > 0
            && Number.isInteger(item.qty) && item.qty > 0;
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
        var addedMsg  = document.getElementById('added-msg');

        if (!form || !colorList || !sizeList) {
            return;
        }

        var selectedColor = null;
        var selectedVariant = null;

        function variantsForColor(color) {
            return data.variants.filter(function (variant) {
                return variant.color === color;
            });
        }

        function clearSelection() {
            selectedVariant = null;
            qtyInput.value = 1;
            qtyInput.disabled = true;
            addButton.disabled = true;
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

        Array.prototype.forEach.call(colorList.children, function (button) {
            button.addEventListener('click', function () {
                selectColor(button.dataset.color, button);
            });
        });

        qtyInput.addEventListener('input', function () {
            if (!selectedVariant) {
                return;
            }

            var value = parseInt(qtyInput.value, 10);

            if (!Number.isInteger(value) || value < 1) {
                value = 1;
            }

            if (value > selectedVariant.stock) {
                value = selectedVariant.stock;
            }

            qtyInput.value = String(value);
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            if (!selectedVariant || selectedVariant.stock <= 0) {
                return;
            }

            var qty = parseInt(qtyInput.value, 10);

            if (!Number.isInteger(qty) || qty < 1) {
                qty = 1;
            }

            var items = readCart();
            var existing = null;

            for (var i = 0; i < items.length; i++) {
                if (items[i].variantId === selectedVariant.id) {
                    existing = items[i];
                    break;
                }
            }

            var alreadyMaxed = false;

            if (existing) {
                var wanted = existing.qty + qty;

                if (wanted > selectedVariant.stock) {
                    wanted = selectedVariant.stock;
                    alreadyMaxed = existing.qty >= selectedVariant.stock;
                }

                existing.qty = wanted;
            } else {
                if (qty > selectedVariant.stock) {
                    qty = selectedVariant.stock;
                }

                items.push({
                    variantId: selectedVariant.id,
                    productId: data.id,
                    name: data.name,
                    url: data.url,
                    image: data.image,
                    color: selectedVariant.color,
                    size: selectedVariant.size,
                    price: data.price,
                    qty: qty
                });
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

        var ids = items.map(function (item) { return item.variantId; });

        fetch(root.dataset.endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ids: ids })
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

        serverItems.forEach(function (item) {
            byId[item.variantId] = item;
        });

        var merged = [];

        localItems.forEach(function (localItem) {
            var fresh = byId[localItem.variantId];

            // Variacao apagada no painel: sai do carrinho sem alarde.
            if (!fresh) {
                return;
            }

            var qty = localItem.qty;

            // Estoque caiu desde que o item entrou no carrinho.
            if (fresh.available && qty > fresh.stock) {
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
            qty.max = String(item.stock);
            qty.step = '1';
            qty.value = String(item.qty);
            qty.className = 'cart-qty';

            qty.addEventListener('change', function () {
                var value = parseInt(qty.value, 10);

                if (!Number.isInteger(value) || value < 1) {
                    value = 1;
                }

                if (value > item.stock) {
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
            lines.push('Cor: ' + item.color);
            lines.push('Tamanho: ' + item.size);
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

    document.addEventListener('DOMContentLoaded', function () {
        updateBadge();

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
