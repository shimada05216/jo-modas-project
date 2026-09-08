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

    document.addEventListener('DOMContentLoaded', function () {
        initSidebar();
        initSlug();
        initVariants();
        initCategoryModal();
    });
}());
