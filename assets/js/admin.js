/**
 * Jo Modas - Painel
 *
 * Tres coisas:
 *   - gaveta da barra lateral no celular;
 *   - cadastro de produto: slug automatico, linhas de variacao;
 *   - criacao rapida de categoria, sem sair do formulario.
 *
 * Sem framework. Tudo degrada: se o JavaScript nao rodar, o formulario
 * continua sendo um POST comum que o PHP sabe tratar.
 */
(function () {
    'use strict';

    // ---------------------------------------------------------
    // Barra lateral (celular)
    // ---------------------------------------------------------

    function initSidebar() {
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
    }

    // ---------------------------------------------------------
    // Slug a partir do nome
    //
    // Mesma regra do slugify() do PHP: sem acento, minusculo, hifens.
    // O PHP refaz e garante unicidade na gravacao; aqui e so para o
    // lojista ver o endereco se formando e nao precisar digitar nada.
    // ---------------------------------------------------------

    function slugify(text) {
        return text
            .normalize('NFD').replace(/[̀-ͯ]/g, '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    function initSlug() {
        var name = document.getElementById('product-name');
        var slug = document.getElementById('product-slug');
        var cfg  = window.JOMODAS_ADMIN || {};

        if (!name || !slug) {
            return;
        }

        // Ao editar, ou se o campo ja tem valor, nao mexe: mudar o
        // endereco de um produto publicado quebraria links existentes.
        var auto = cfg.slugAuto !== false && slug.value.trim() === '';

        slug.addEventListener('input', function () { auto = false; });

        name.addEventListener('input', function () {
            if (auto) {
                slug.value = slugify(name.value);
            }
        });
    }

    // ---------------------------------------------------------
    // Linhas de variacao
    // ---------------------------------------------------------

    function initVariants() {
        var wrap = document.getElementById('variant-rows');
        var add  = document.getElementById('variant-add');

        if (!wrap || !add) {
            return;
        }

        // Proximo indice livre. Nao reaproveita indices de linhas
        // removidas, para nao sobrescrever campos de outra linha.
        var next = wrap.querySelectorAll('.variant-line').length;

        function renameRow(row, index) {
            row.dataset.index = String(index);

            row.querySelectorAll('input').forEach(function (input) {
                if (input.name) {
                    input.name = input.name.replace(/variants\[\d+\]/, 'variants[' + index + ']');
                }
            });
        }

        function blankRow(source) {
            var row = source.cloneNode(true);

            renameRow(row, next++);

            row.querySelectorAll('input').forEach(function (input) {
                if (input.type === 'checkbox') {
                    input.checked = true;              // variacao nova nasce ativa
                } else if (input.type === 'hidden') {
                    input.value = '';                  // sem id: e um registro novo
                } else if (input.type === 'number') {
                    input.value = '0';
                } else {
                    input.value = '';
                }
            });

            return row;
        }

        function duplicateRow(source) {
            var row = source.cloneNode(true);

            renameRow(row, next++);

            // Copia cor e tamanho, mas nunca o id nem o SKU: os dois sao
            // unicos, e duplicar traria conflito na hora de salvar.
            row.querySelectorAll('input').forEach(function (input) {
                if (input.type === 'hidden') {
                    input.value = '';
                } else if (/\[sku\]$/.test(input.name)) {
                    input.value = '';
                }
            });

            return row;
        }

        add.addEventListener('click', function () {
            var last = wrap.querySelector('.variant-line:last-child');

            if (last) {
                wrap.appendChild(blankRow(last));
            }
        });

        // Delegacao: vale tambem para as linhas criadas depois.
        wrap.addEventListener('click', function (event) {
            var row = event.target.closest('.variant-line');

            if (!row) {
                return;
            }

            if (event.target.classList.contains('js-variant-del')) {
                // Nunca deixa o formulario sem nenhuma linha: em vez de
                // remover a ultima, limpa os campos dela.
                if (wrap.querySelectorAll('.variant-line').length > 1) {
                    row.remove();
                } else {
                    var fresh = blankRow(row);
                    wrap.replaceChild(fresh, row);
                }
            }

            if (event.target.classList.contains('js-variant-dup')) {
                row.parentNode.insertBefore(duplicateRow(row), row.nextSibling);
            }
        });
    }

    // ---------------------------------------------------------
    // Criacao rapida de categoria
    // ---------------------------------------------------------

    function initCategoryModal() {
        var cfg    = window.JOMODAS_ADMIN || {};
        var open   = document.getElementById('category-add');
        var modal  = document.getElementById('category-modal');
        var input  = document.getElementById('category-modal-name');
        var save   = document.getElementById('category-modal-save');
        var cancel = document.getElementById('category-modal-cancel');
        var errBox = document.getElementById('category-modal-error');
        var select = document.getElementById('category-select');

        if (!open || !modal || !select || !cfg.categoryEndpoint) {
            return;
        }

        function showError(message) {
            errBox.textContent = message;
            errBox.hidden = false;
        }

        function openModal() {
            errBox.hidden = true;
            input.value = '';
            modal.hidden = false;
            input.focus();
        }

        function closeModal() {
            modal.hidden = true;
            open.focus();
        }

        open.addEventListener('click', openModal);
        cancel.addEventListener('click', closeModal);

        modal.addEventListener('click', function (event) {
            if (event.target === modal) { closeModal(); }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !modal.hidden) { closeModal(); }
        });

        // Enter dentro do campo cria, em vez de enviar o formulario do
        // produto por engano.
        input.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                submit();
            }
        });

        save.addEventListener('click', submit);

        function submit() {
            var name = input.value.trim();

            if (name === '') {
                showError('Informe o nome da categoria.');
                return;
            }

            save.disabled = true;
            errBox.hidden = true;

            var body = new FormData();
            body.append('name', name);
            body.append('csrf_token', cfg.csrf);

            fetch(cfg.categoryEndpoint, { method: 'POST', body: body, credentials: 'same-origin' })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    save.disabled = false;

                    if (!data || !data.ok) {
                        showError((data && data.error) || 'Não foi possível criar a categoria.');
                        return;
                    }

                    // Acrescenta e ja seleciona, sem recarregar a pagina:
                    // o resto do formulario continua preenchido.
                    var option = document.createElement('option');
                    option.value = String(data.id);
                    option.textContent = data.name;
                    select.appendChild(option);
                    select.value = String(data.id);

                    closeModal();
                })
                .catch(function () {
                    save.disabled = false;
                    showError('Falha de conexão. Tente novamente.');
                });
        }
    }

    // ---------------------------------------------------------
    // Conferencia antes de enviar
    //
    // Nome, categoria e preco ja sao obrigatorios pelo proprio HTML.
    // Aqui ficam as regras que o HTML nao sabe expressar: preco valido,
    // promocional menor que o normal, limites dos arquivos e linha de
    // variacao preenchida pela metade.
    //
    // O motivo de existir: quando o servidor recusa, o navegador limpa
    // os arquivos ja escolhidos -- e obrigado a isso, por seguranca --
    // e quem salva de novo grava o produto sem as imagens. Barrando o
    // erro previsivel ANTES do envio, a selecao de arquivos sobrevive.
    //
    // Isto NAO substitui a validacao do servidor, que continua inteira.
    // ---------------------------------------------------------

    function initFormCheck() {
        var form = document.getElementById('product-form');

        if (!form) {
            return;
        }

        var cfg      = window.JOMODAS_ADMIN || {};
        var maxBytes = Number(cfg.maxUpload) || 0;   // por arquivo, limite REAL do servidor
        var maxPost  = Number(cfg.maxPost) || 0;     // total da requisicao
        var maxFiles = Number(cfg.maxImages) || 0;
        var tipos    = ['image/jpeg', 'image/png', 'image/webp'];

        function tamanho(bytes) {
            return bytes >= 1048576
                ? String(Math.round(bytes / 1048576 * 10) / 10).replace('.', ',') + ' MB'
                : Math.round(bytes / 1024) + ' KB';
        }

        // Aceita 199,90 / 1.234,56 / 199 -- a mesma folga do parse_price.
        function toNumber(text) {
            var limpo = String(text).trim().replace(/\s/g, '');

            if (limpo === '' || !/^[0-9.,]+$/.test(limpo)) {
                return null;
            }

            // A virgula manda: se existe, o que vem depois e centavo.
            if (limpo.indexOf(',') !== -1) {
                limpo = limpo.replace(/\./g, '').replace(',', '.');
            } else if (/^[0-9]{1,3}(\.[0-9]{3})+$/.test(limpo)) {
                limpo = limpo.replace(/\./g, '');   // 1.234 e milhar, nao decimal
            }

            var n = Number(limpo);

            return isFinite(n) ? n : null;
        }

        function falhar(campo, mensagem) {
            campo.setCustomValidity(mensagem);
            campo.reportValidity();
            campo.focus();
        }

        form.addEventListener('submit', function (event) {
            var preco = form.querySelector('[name="price"]');
            var promo = form.querySelector('[name="promo_price"]');
            var arqs  = form.querySelector('input[type="file"]');

            [preco, promo].forEach(function (c) { if (c) { c.setCustomValidity(''); } });

            var vPreco = preco ? toNumber(preco.value) : null;

            if (preco && preco.value.trim() !== '' && (vPreco === null || vPreco <= 0)) {
                event.preventDefault();
                falhar(preco, 'Informe um preço válido e maior que zero, por exemplo 199,90.');
                return;
            }

            if (promo && promo.value.trim() !== '') {
                var vPromo = toNumber(promo.value);

                if (vPromo === null || vPromo <= 0) {
                    event.preventDefault();
                    falhar(promo, 'Preço promocional inválido. Deixe em branco se não houver.');
                    return;
                }

                if (vPreco !== null && vPromo >= vPreco) {
                    event.preventDefault();
                    falhar(promo, 'O preço promocional precisa ser menor que o preço normal.');
                    return;
                }
            }

            if (arqs && arqs.files && arqs.files.length > 0) {
                if (maxFiles > 0 && arqs.files.length > maxFiles) {
                    event.preventDefault();
                    falhar(arqs, 'Escolha no máximo ' + maxFiles + ' imagens.');
                    return;
                }

                var soma = 0;

                for (var i = 0; i < arqs.files.length; i++) {
                    var f = arqs.files[i];

                    soma += f.size;

                    if (f.type && tipos.indexOf(f.type) === -1) {
                        event.preventDefault();
                        falhar(arqs, '"' + f.name + '" não é JPG, PNG ou WEBP.');
                        return;
                    }

                    if (maxBytes > 0 && f.size > maxBytes) {
                        event.preventDefault();
                        falhar(arqs, '"' + f.name + '" excede o limite permitido pelo '
                            + 'servidor (' + tamanho(maxBytes) + ').');
                        return;
                    }
                }

                // Cada arquivo pode caber sozinho e o envio estourar mesmo
                // assim. Passando de post_max_size o PHP descarta a
                // requisicao inteira antes de qualquer validacao: o
                // formulario voltaria em branco, sem explicacao nenhuma.
                // A margem de 5% cobre os outros campos e o cabecalho do
                // multipart, que tambem contam no total.
                if (maxPost > 0 && soma > maxPost * 0.95) {
                    event.preventDefault();
                    falhar(arqs, 'As imagens somam ' + tamanho(soma) + ' e o servidor '
                        + 'aceita no máximo ' + tamanho(maxPost) + ' por envio. '
                        + 'Envie menos imagens de cada vez.');
                    return;
                }
            }

            // Linha de variacao pela metade. Linha totalmente vazia e
            // ignorada aqui e no servidor: variacao e opcional.
            var linhas = form.querySelectorAll('.variant-line');

            for (var j = 0; j < linhas.length; j++) {
                var cor = linhas[j].querySelector('[name$="[color]"]');
                var tam = linhas[j].querySelector('[name$="[size]"]');
                var est = linhas[j].querySelector('[name$="[stock]"]');
                var sku = linhas[j].querySelector('[name$="[sku]"]');

                if (!cor || !tam) { continue; }

                var c = cor.value.trim(), t = tam.value.trim();
                var s = est ? est.value.trim() : '';
                var k = sku ? sku.value.trim() : '';

                if (c === '' && t === '' && k === '' && (s === '' || s === '0')) {
                    continue;   // intocada
                }

                if (c === '' || t === '') {
                    event.preventDefault();
                    falhar(c === '' ? cor : tam,
                        'Preencha cor e tamanho juntos, ou limpe a linha inteira.');
                    return;
                }
            }
        });

        // Mensagem propria sai assim que a pessoa corrige o campo.
        form.addEventListener('input', function (event) {
            if (event.target && event.target.setCustomValidity) {
                event.target.setCustomValidity('');
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initSidebar();
        initSlug();
        initVariants();
        initCategoryModal();
        initFormCheck();
    });
}());
