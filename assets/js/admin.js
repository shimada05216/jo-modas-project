/**
 * Jo Modas - Painel
 *
 * O que faz:
 *   - gaveta da barra lateral no celular;
 *   - cadastro de produto: slug automatico, linhas de variacao,
 *     conferencia antes do envio;
 *   - criacao rapida de categoria, sem sair do formulario;
 *   - otimizacao de fotos grandes antes do envio.
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

    // ---------------------------------------------------------
    // Otimizacao de fotos antes do envio
    //
    // Foto de celular tem 4 a 10 MB, e hospedagem comum aceita 2 MB por
    // arquivo. Comprimir no PHP nao resolve: quem passa do limite e
    // descartado pelo proprio PHP ANTES de o codigo da loja rodar, entao
    // nao ha arquivo nenhum para comprimir. A reducao tem de acontecer
    // aqui, no navegador, antes do envio.
    //
    // Na hora em que as fotos sao escolhidas:
    //   - o que ja cabe no limite e fica intacto;
    //   - o que passa e redimensionado (lado maior ate 1920 px, nunca
    //     ampliado) e recomprimido ate caber com folga;
    //   - o arquivo no campo e trocado pelo otimizado, e o formulario
    //     segue sendo o mesmo POST de sempre.
    //
    // A validacao do servidor continua inteira: isto so melhora a
    // experiencia, nao e barreira de seguranca.
    //
    // Liga em qualquer <input type="file" data-optimize-images>, com os
    // limites nos atributos data-max-bytes e data-max-post.
    // ---------------------------------------------------------

    function tamanho(bytes) {
        return bytes >= 1048576
            ? String(Math.round(bytes / 1048576 * 10) / 10).replace('.', ',') + ' MB'
            : Math.max(1, Math.round(bytes / 1024)) + ' KB';
    }

    var OTIMIZA = {
        lados: [1920, 1600, 1400, 1200],       // lado maior, em px
        qualidades: [0.85, 0.78, 0.70, 0.62],  // piso em 0,62
        folga: 0.90,                           // alvo = 90% do limite
        megapixels: 50000000                   // mesmo teto do servidor
    };

    var TIPOS_ACEITOS = ['image/jpeg', 'image/png', 'image/webp'];
    var EXTENSOES     = ['jpg', 'jpeg', 'png', 'webp'];

    function extensao(nome) {
        var partes = String(nome).split('.');

        return partes.length > 1 ? partes.pop().toLowerCase() : '';
    }

    // HEIC/HEIF (iPhone) e outros formatos que a loja nao aceita. No
    // iPhone o proprio sistema costuma converter para JPG, porque o campo
    // so pede JPG/PNG/WEBP; quando nao converte, o aviso aparece aqui.
    function formatoRecusado(arquivo) {
        var ext = extensao(arquivo.name);

        if (/^image\/hei[cf]/i.test(arquivo.type) || ext === 'heic' || ext === 'heif') {
            return true;
        }

        if (arquivo.type) {
            return TIPOS_ACEITOS.indexOf(arquivo.type) === -1;
        }

        return EXTENSOES.indexOf(ext) === -1;
    }

    // Decodifica respeitando a orientacao gravada pela camera (EXIF).
    // Sem isso a foto tirada em pe chegaria deitada.
    function decodificar(arquivo) {
        function viaImagem() {
            return new Promise(function (resolve, reject) {
                var url = URL.createObjectURL(arquivo);
                var img = new Image();

                img.onload = function () {
                    resolve({
                        fonte: img,
                        largura: img.naturalWidth,
                        altura: img.naturalHeight,
                        fechar: function () { URL.revokeObjectURL(url); }
                    });
                };
                img.onerror = function () {
                    URL.revokeObjectURL(url);
                    reject(new Error('ilegivel'));
                };
                img.src = url;
            });
        }

        if (typeof window.createImageBitmap !== 'function') {
            return viaImagem();
        }

        return window.createImageBitmap(arquivo, { imageOrientation: 'from-image' })
            .then(function (bitmap) {
                return {
                    fonte: bitmap,
                    largura: bitmap.width,
                    altura: bitmap.height,
                    fechar: function () { if (bitmap.close) { bitmap.close(); } }
                };
            })
            // Navegador antigo que nao conhece a opcao, ou que nao decodifica
            // por aqui: tenta pela tag <img>, que tambem respeita o EXIF.
            .catch(viaImagem);
    }

    function desenhar(imagem, lado, fundoBranco) {
        var escala = Math.min(1, lado / Math.max(imagem.largura, imagem.altura));
        var canvas = document.createElement('canvas');

        canvas.width  = Math.max(1, Math.round(imagem.largura * escala));
        canvas.height = Math.max(1, Math.round(imagem.altura * escala));

        var ctx = canvas.getContext('2d');

        ctx.imageSmoothingEnabled = true;
        ctx.imageSmoothingQuality = 'high';

        // JPEG nao tem transparencia: sem o fundo, o que era transparente
        // viraria preto.
        if (fundoBranco) {
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
        }

        ctx.drawImage(imagem.fonte, 0, 0, canvas.width, canvas.height);

        return canvas;
    }

    // Amostra reduzida: qualquer pixel com alfa abaixo de 255 conta.
    function temTransparencia(imagem) {
        var canvas = desenhar(imagem, 256, false);
        var dados  = canvas.getContext('2d').getImageData(0, 0, canvas.width, canvas.height).data;

        for (var i = 3; i < dados.length; i += 4) {
            if (dados[i] < 255) {
                return true;
            }
        }

        return false;
    }

    function codificar(canvas, tipo, qualidade) {
        return new Promise(function (resolve) {
            canvas.toBlob(resolve, tipo, qualidade);
        });
    }

    // Alguns navegadores (Safari antigo) nao geram WEBP e devolvem PNG
    // calados. Descobre uma vez so.
    var geraWebp = null;

    function suportaWebp() {
        if (!geraWebp) {
            var c = document.createElement('canvas');

            c.width = c.height = 2;
            geraWebp = codificar(c, 'image/webp', 0.8).then(function (b) {
                return !!b && b.type === 'image/webp';
            });
        }

        return geraWebp;
    }

    function renomear(nome, tipo) {
        var base = String(nome).replace(/\.[^.]+$/, '') || 'imagem';
        var ext  = { 'image/jpeg': 'jpg', 'image/png': 'png', 'image/webp': 'webp' }[tipo];

        return base + '.' + ext;
    }

    function falha(tipo, arquivo) {
        var erro = new Error(tipo);

        erro.tipo = tipo;
        erro.nome = arquivo.name;

        return erro;
    }

    /**
     * Devolve { arquivo, mudou, antes, depois, largura, altura }.
     * Rejeita com erro.tipo = 'formato' | 'ilegivel' | 'grande'.
     */
    function otimizarImagem(arquivo, limite) {
        if (formatoRecusado(arquivo)) {
            return Promise.reject(falha('formato', arquivo));
        }

        var alvo = Math.floor(limite * OTIMIZA.folga);

        return decodificar(arquivo).catch(function () {
            // Arquivo que nao abre como imagem: texto renomeado para .jpg,
            // download interrompido, formato que o navegador nao le.
            throw falha('ilegivel', arquivo);
        }).then(function (imagem) {
            var pixels = imagem.largura * imagem.altura;

            // Ja cabe e tem tamanho razoavel: segue exatamente como veio,
            // sem recomprimir. Recompressao a toa so perde qualidade.
            if (arquivo.size <= limite && pixels <= OTIMIZA.megapixels) {
                imagem.fechar();

                return {
                    arquivo: arquivo, mudou: false,
                    antes: arquivo.size, depois: arquivo.size,
                    largura: imagem.largura, altura: imagem.altura
                };
            }

            // Formato de saida. Foto (JPEG) continua JPEG; WEBP continua
            // WEBP quando o navegador sabe gerar. PNG vira JPEG se nao tiver
            // transparencia -- foto salva em PNG e so desperdicio -- e WEBP se
            // tiver, para nao perder o recorte.
            return suportaWebp().then(function (webp) {
                var saida = 'image/jpeg';

                if (arquivo.type === 'image/webp' || arquivo.type === 'image/png') {
                    var transparente = temTransparencia(imagem);

                    if (transparente) {
                        saida = webp ? 'image/webp' : 'image/png';
                    } else if (arquivo.type === 'image/webp' && webp) {
                        saida = 'image/webp';
                    }
                }

                var qualidades = saida === 'image/png' ? [undefined] : OTIMIZA.qualidades;
                var tentativas = [];

                OTIMIZA.lados.forEach(function (lado) {
                    qualidades.forEach(function (q) {
                        tentativas.push({ lado: lado, q: q });
                    });
                });

                // Tenta em ordem: primeiro baixa a qualidade, depois o tamanho.
                // Para na primeira que couber.
                function proxima(i) {
                    if (i >= tentativas.length) {
                        imagem.fechar();
                        throw falha('grande', arquivo);
                    }

                    var t      = tentativas[i];
                    var canvas = desenhar(imagem, t.lado, saida === 'image/jpeg');

                    return codificar(canvas, saida, t.q).then(function (blob) {
                        if (blob && blob.type === saida && blob.size <= alvo) {
                            imagem.fechar();

                            return {
                                arquivo: new File([blob], renomear(arquivo.name, saida), {
                                    type: saida,
                                    lastModified: Date.now()
                                }),
                                mudou: true,
                                antes: arquivo.size,
                                depois: blob.size,
                                largura: canvas.width,
                                altura: canvas.height
                            };
                        }

                        return proxima(i + 1);
                    });
                }

                return proxima(0);
            });
        });
    }

    function initImageOptimizer() {
        // Sem DataTransfer nao da para trocar o arquivo do campo. Nesse
        // navegador vale o comportamento anterior: o aviso de limite.
        var trocaArquivos = true;

        try { new DataTransfer(); } catch (e) { trocaArquivos = false; }

        if (!trocaArquivos) {
            return;
        }

        var travados = [];

        document.querySelectorAll('input[type="file"][data-optimize-images]').forEach(function (campo) {
            var form    = campo.form;
            var limite  = Number(campo.dataset.maxBytes) || 0;
            var maxPost = Number(campo.dataset.maxPost) || 0;
            var botoes  = form ? form.querySelectorAll('button[type="submit"]') : [];
            var rodada  = 0;

            if (!form || limite <= 0) {
                return;
            }

            // Linha de retorno logo abaixo do campo. Fora do <label>, senao
            // clicar no texto abriria a escolha de arquivos.
            var aviso = document.createElement('p');
            var dono  = campo.closest('label') || campo;

            aviso.className = 'upload-status';
            aviso.setAttribute('aria-live', 'polite');
            aviso.hidden = true;
            dono.parentNode.insertBefore(aviso, dono.nextSibling);

            // Erros em vermelho, resultado em cinza. Montado com textContent:
            // o nome do arquivo vem de quem escolheu a foto.
            function mostrar(erros, info) {
                aviso.textContent = '';

                [[erros, 'upload-erro'], [info, 'upload-info']].forEach(function (par) {
                    if (!par[0] || par[0].length === 0) {
                        return;
                    }

                    var span = document.createElement('span');

                    span.className = par[1];
                    span.textContent = par[0].join('\n');
                    aviso.appendChild(span);
                });

                aviso.hidden = aviso.childNodes.length === 0;
            }

            function ocupado(sim) {
                Array.prototype.forEach.call(botoes, function (botao) {
                    if (sim) {
                        if (!botao.dataset.rotulo) {
                            botao.dataset.rotulo = botao.textContent;
                        }
                        botao.textContent = 'Otimizando imagens…';
                    } else if (botao.dataset.rotulo) {
                        botao.textContent = botao.dataset.rotulo;
                    }
                    botao.disabled = sim;
                });
            }

            campo.addEventListener('change', function () {
                var escolhidos = Array.prototype.slice.call(campo.files || []);
                var minha      = ++rodada;

                if (escolhidos.length === 0) {
                    campo.setCustomValidity('');
                    mostrar([], []);
                    return;
                }

                // Enquanto processa, o proprio formulario recusa o envio.
                campo.setCustomValidity('Aguarde: as imagens ainda estão sendo otimizadas.');
                ocupado(true);
                mostrar([], ['Otimizando imagens…']);

                var prontos = [];
                var linhas  = [];
                var erros   = [];

                // Uma de cada vez: varias fotos de 12 MP abertas juntas
                // estouram a memoria de celular modesto.
                var fila = escolhidos.reduce(function (anterior, arquivo) {
                    return anterior.then(function () {
                        return otimizarImagem(arquivo, limite).then(function (r) {
                            prontos.push(r.arquivo);

                            if (r.mudou) {
                                linhas.push(arquivo.name + ': ' + tamanho(r.antes)
                                    + ' → ' + tamanho(r.depois)
                                    + ' (' + r.largura + ' × ' + r.altura + ')');
                            }
                        }, function (e) {
                            var msg = {
                                formato:  'este formato de imagem não é compatível. Use JPG, PNG ou WEBP.',
                                ilegivel: 'não foi possível abrir este arquivo como imagem.',
                                grande:   'não foi possível reduzir esta imagem para até '
                                          + tamanho(limite) + '.'
                            }[e && e.tipo] || 'não foi possível preparar esta imagem.';

                            erros.push('“' + arquivo.name + '”: ' + msg);
                        });
                    });
                }, Promise.resolve());

                fila.then(function () {
                    // A pessoa trocou a selecao no meio: este resultado ja
                    // nao vale.
                    if (minha !== rodada) {
                        return;
                    }

                    var dt = new DataTransfer();

                    prontos.forEach(function (f) { dt.items.add(f); });
                    campo.files = dt.files;

                    var soma = prontos.reduce(function (s, f) { return s + f.size; }, 0);

                    // Cada foto pode caber e o envio inteiro nao. Passando de
                    // post_max_size o PHP descarta tudo antes de validar.
                    if (maxPost > 0 && soma > maxPost * 0.95) {
                        erros.push('As imagens ainda somam ' + tamanho(soma)
                            + ' após a otimização, acima do limite total permitido pelo '
                            + 'servidor (' + tamanho(maxPost) + '). Envie menos imagens de cada vez.');
                    }

                    ocupado(false);

                    if (erros.length > 0) {
                        // Nada vai pela metade: o envio fica bloqueado ate a
                        // selecao ser refeita. Assim nenhuma foto some calada.
                        campo.setCustomValidity(erros[0] + (erros.length > 1
                            ? ' (e mais ' + (erros.length - 1) + ')' : '')
                            + ' Escolha as imagens novamente.');
                        mostrar(erros, linhas.length > 0
                            ? ['Já otimizadas. Para enviar, escolha as imagens de novo sem o arquivo acima:'].concat(linhas)
                            : []);
                        return;
                    }

                    campo.setCustomValidity('');
                    mostrar([], linhas.length > 0
                        ? ['Imagens otimizadas para o envio:'].concat(linhas)
                        : []);
                });
            });

            // Evita o segundo clique criar um produto duplicado enquanto o
            // envio esta em andamento. Registrado depois das demais
            // conferencias: so trava se o envio de fato seguir.
            form.addEventListener('submit', function (event) {
                if (event.defaultPrevented) {
                    return;
                }

                Array.prototype.forEach.call(botoes, function (botao) {
                    botao.disabled = true;
                    travados.push(botao);
                });
            });
        });

        // Voltar pelo historico devolve a pagina como estava: botao travado.
        window.addEventListener('pageshow', function (event) {
            if (event.persisted) {
                travados.forEach(function (b) { b.disabled = false; });
                travados = [];
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initSidebar();
        initSlug();
        initVariants();
        initCategoryModal();
        initFormCheck();
        initImageOptimizer();
    });
}());
