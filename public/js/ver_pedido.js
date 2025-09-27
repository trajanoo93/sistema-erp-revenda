console.log("pedidos.js: Arquivo carregado com sucesso (versão 174.13).");
$(document).ready(function() {
    console.log("pedidos.js: $(document).ready executado.");

    // Função para mostrar notificações
    function exibirNotificacao(mensagem, tipo = 'success') {
        var $notificacao = $('#notificacao');
        $notificacao.removeClass('error success').addClass(tipo).text(mensagem).css('top', '0').fadeIn();
        setTimeout(function() {
            $notificacao.fadeOut('slow', function() {
                $notificacao.css('top', '-50px');
            });
        }, 3000);
    }

    // Adicionar estilo CSS para a notificação, spinner e modal
    if (!$('style#notificacao-style').length) {
        $('<style>').attr('id', 'notificacao-style').text(`
            #notificacao {
                position: fixed;
                top: -50px;
                left: 50%;
                transform: translateX(-50%);
                padding: 10px 20px;
                border-radius: 5px;
                color: white;
                z-index: 1060 !important;
                transition: all 0.3s ease;
            }
            #notificacao.success {
                background-color: #28a745;
            }
            #notificacao.error {
                background-color: #dc3545;
            }
            .loading-spinner {
                font-size: 16px;
                color: #333;
                text-align: center;
                padding: 20px;
            }
            .loading-spinner .fa-spinner {
                font-size: 24px;
                margin-right: 10px;
            }
            .nota-fiscal {
                padding: 20px;
                background: #fff;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                max-width: 800px;
                margin: 20px auto;
            }
            .nf-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
            }
            .nf-info-empresa img {
                max-width: 150px;
            }
            .nf-info-pedido p {
                margin: 5px 0;
            }
            .nf-info-cliente, .nf-observacoes, .comprovantes-list {
                margin-bottom: 20px;
            }
            .nf-info-cliente h3, .nf-observacoes h3, .comprovantes-list h3 {
                font-size: 1.25rem;
                margin-bottom: 10px;
            }
            .custom-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }
            .custom-table th, .custom-table td {
                padding: 10px;
                border: 1px solid #ddd;
                text-align: left;
            }
            .custom-table th {
                background: #f8f9fa !important;
                color: #1F2937 !important;
            }
            .highlight-divergencia {
                background: #fff3cd;
            }
            .edit-pedido-header {
                margin-bottom: 15px;
            }
            .btn-edit-pedido {
                display: block !important;
            }
        `).appendTo('head');
    }
    console.log("Arquivo pedidos.js carregado corretamente");

    // Função de debounce
    function debounce(func, wait) {
        let timeout;
        return function(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    // Função para carregar Bootstrap dinamicamente
    function loadBootstrap() {
        return new Promise((resolve, reject) => {
            if (typeof $.fn.modal === 'function') {
                console.log("Método modal do Bootstrap já disponível.");
                resolve();
            } else {
                console.log("Método modal não encontrado. Carregando Bootstrap dinamicamente...");
                $.getScript("https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js")
                    .done(function() {
                        console.log("Bootstrap carregado dinamicamente com sucesso.");
                        resolve();
                    })
                    .fail(function(jqxhr, settings, exception) {
                        console.error("Erro ao carregar Bootstrap dinamicamente:", exception);
                        reject(exception);
                    });
            }
        });
    }

    // Função para reaplicar o evento do botão Novo Pedido
    function bindNovoPedidoEvent() {
        console.log("bindNovoPedidoEvent chamado: reaplicando evento para #btnNovoPedido...");
        if ($('#btnNovoPedido').length) {
            console.log("Botão #btnNovoPedido encontrado no DOM.");
        } else {
            console.error("Botão #btnNovoPedido NÃO encontrado no DOM!");
        }
        $(document).off("click", "#btnNovoPedido").on("click", "#btnNovoPedido", function(e) {
            e.preventDefault();
            console.log("Botão Novo Pedido clicado - Verificando modal...");
            if ($('#modalPedido').length) {
                console.log("Modal #modalPedido encontrado no DOM. Tentando abrir...");
                loadBootstrap().then(() => {
                    if (typeof $('#modalPedido').modal === 'function') {
                        console.log("Método modal disponível. Abrindo modal...");
                        $('#modalPedido').modal('show');
                        console.log("Modal #modalPedido aberto (tentativa).");
                    } else {
                        console.error("Método modal AINDA não disponível após carregamento dinâmico!");
                    }
                }).catch(error => {
                    console.error("Falha ao carregar Bootstrap:", error);
                    exibirNotificacao("Erro ao abrir modal de novo pedido.", 'error');
                });
            } else {
                console.error("Modal #modalPedido NÃO encontrado no DOM!");
                exibirNotificacao("Erro: Modal de novo pedido não encontrado.", 'error');
            }
        });
    }

    // Filtragem de pedidos automaticamente com eventos de mudança
    function filtrarPedidos() {
        var status = $("#statusFilter").val();
        var dataInicio = $("#data_inicio").val();
        var dataFim = $("#data_fim").val();
        console.log("Filtrando pedidos:", status, dataInicio, dataFim);
        $.ajax({
            url: "/atacado/pedidos.php",
            method: "GET",
            data: {
                ajax: 1,
                status: status,
                data_inicio: dataInicio,
                data_fim: dataFim
            },
            success: function(response) {
                $("#pedidosContainer").html(response);
                console.log("Tabela de pedidos recarregada, reaplicando eventos...");
                bindNovoPedidoEvent();
            },
            error: function(xhr, status, error) {
                console.error("Erro ao carregar pedidos:", error);
                exibirNotificacao("Erro ao carregar pedidos.", 'error');
            }
        });
    }

    // Reaplicar eventos de filtro
    function bindFilterEvents() {
        $("#statusFilter, #data_inicio, #data_fim").off("change").on("change", filtrarPedidos);
    }
    bindFilterEvents();

    // Evento para abrir o modal de detalhes do pedido
    $(document).on("click", ".detalhesPedido", function() {
        console.log("Botão de visualizar clicado!");
        var pedidoId = $(this).data("id");
        console.log("Enviando requisição AJAX para ver_pedido.php com ID:", pedidoId);

        // Adicionar spinner de loading
        $("#verPedidoContent").html(`
            <div class="loading-spinner">
                <i class="fas fa-spinner fa-spin"></i> Carregando detalhes do pedido...
            </div>
        `);

        // Verificar se o modal existe
        if ($('#verPedidoModal').length) {
            console.log("Modal #verPedidoModal encontrado no DOM. Abrindo com loading...");
            loadBootstrap().then(() => {
                if (typeof $('#verPedidoModal').modal === 'function') {
                    $('#verPedidoModal').modal('show');
                    console.log("Modal #verPedidoModal aberto com spinner.");
                } else {
                    console.error("Método modal não disponível após carregamento dinâmico!");
                    exibirNotificacao("Erro ao abrir modal.", 'error');
                }

                // Carregar o HTML completo de ver_pedido.php
                $.ajax({
                    url: "/atacado/ver_pedido.php",
                    type: "GET",
                    data: { id: pedidoId },
                    success: function(response) {
                        console.log("Resposta recebida de ver_pedido.php:", response);
                        $("#verPedidoContent").html(response);
                        console.log("Conteúdo do modal preenchido para pedido ID:", pedidoId);
                        if (typeof inicializarVerPedido === 'function') {
                            inicializarVerPedido();
                            console.log("inicializarVerPedido chamado com sucesso após preenchimento.");
                        } else {
                            console.error("Função inicializarVerPedido não encontrada após preenchimento.");
                        }
                        $('#verPedidoModal').modal('handleUpdate');
                    },
                    error: function(xhr, status, error) {
                        console.error("Erro ao carregar detalhes do pedido:", error);
                        console.error("Status:", status);
                        console.error("Response Text:", xhr.responseText);
                        exibirNotificacao("Erro ao carregar detalhes do pedido.", 'error');
                        $("#verPedidoContent").html("<p>Erro ao carregar detalhes do pedido.</p>");
                    }
                });
            }).catch(error => {
                console.error("Falha ao carregar Bootstrap:", error);
                exibirNotificacao("Erro ao abrir modal.", 'error');
            });
        } else {
            console.error("Modal #verPedidoModal NÃO encontrado no DOM!");
            exibirNotificacao("Erro: Modal de visualização não encontrado.", 'error');
        }
    });

    // Evento para fechar o modal de visualização
    $(document).on("click", ".pedido-close", function() {
        console.log("Fechando modal de visualização (#verPedidoModal)...");
        if (typeof $('#verPedidoModal').modal === 'function') {
            $('#verPedidoModal').modal('hide');
        } else {
            $('#verPedidoModal').hide();
            console.warn("Método modal não disponível, usando hide() como fallback.");
        }
    });

    // Garantir que o backdrop seja removido quando o modal for fechado
    $('#verPedidoModal').on('hidden.bs.modal', function () {
        console.log("Modal #verPedidoModal completamente fechado. Removendo backdrop...");
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open');
        console.log("Backdrop removido e classe modal-open retirada do body.");
    });

    // Verificação de preenchimento de campo no formulário de novo pedido
    $(document).on("input", "#buscarCliente", function() {
        if ($(this).val().trim() === "") {
            $(this).addClass("is-invalid");
        } else {
            $(this).removeClass("is-invalid");
        }
    });

    // Função para enviar o pedido
    $(document).on('submit', '#formPedido', function(e) {
        e.preventDefault();
        let valid = true;
        $('.produto-selecionado').each(function() {
            var produtoTipo = $(this).data('produto-tipo') || 'KG';
            var quantidadeInput = $(this).find('.quantidade-produto');
            var valor = quantidadeInput.val();
            console.log("Validando quantidade para produto tipo:", produtoTipo, "Valor:", valor);
            if (produtoTipo === "UND") {
                if (!Number.isInteger(Number(valor)) || Number(valor) <= 0) {
                    exibirNotificacao('A quantidade de produtos do tipo UND deve ser um número inteiro maior que 0 (ex.: 1, 2).', 'error');
                    valid = false;
                }
            } else {
                valor = valor.replace(',', '.');
                if (!/^\d{1,3}\.\d{1,3}$/.test(valor) || parseFloat(valor) <= 0) {
                    exibirNotificacao('A quantidade de produtos do tipo KG deve ser no formato X,YYY (ex.: 10,850) e maior que 0.', 'error');
                    valid = false;
                }
            }
        });
        console.log("Cliente ID:", $('#cliente_id').val());
        console.log("Data de Retirada:", $('#data_retirada').val());
        if (!valid || $('#cliente_id').val() === '' || $('#data_retirada').val() === '') {
            exibirNotificacao('Por favor, preencha todos os campos obrigatórios corretamente.', 'error');
            return;
        }
        var dadosForm = $(this).serialize();
        $.ajax({
            url: "/atacado/criar_pedido.php",
            method: "POST",
            data: dadosForm,
            dataType: "json",
            success: function(response) {
                if (response.status === "success") {
                    exibirNotificacao(response.message, 'success');
                    $('#modalPedido').modal('hide');
                    atualizarListaPedidos();
                } else {
                    exibirNotificacao(response.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error("Erro na requisição:", error);
                exibirNotificacao("Erro ao processar a solicitação.", 'error');
            }
        });
    });

    // Evento para "Excluir Pedido"
    $(document).on("click", ".excluirPedido", function(e) {
        e.preventDefault();
        var id = $(this).data("id");
        if (confirm('Tem certeza que deseja excluir este pedido?')) {
            console.log("Excluindo pedido ID:", id);
            $.ajax({
                url: "/atacado/deletar_pedido.php",
                method: "POST",
                data: { id: id },
                dataType: "json",
                success: function(response) {
                    if (response.status === "success") {
                        exibirNotificacao('Pedido excluído com sucesso!', 'success');
                        $("tr[data-id='" + id + "']").fadeOut(500, function() {
                            $(this).remove();
                        });
                    } else {
                        exibirNotificacao('Erro ao excluir pedido: ' + response.message, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error("Erro ao excluir o pedido:", error);
                    exibirNotificacao('Erro ao processar a solicitação.', 'error');
                }
            });
        }
    });

    // Inicializar o evento do botão Novo Pedido
    bindNovoPedidoEvent();

    // Inicializar o Datepicker e a máscara de data
    $('#modalPedido').on('shown.bs.modal', function() {
        console.log("Modal #modalPedido exibido - Inicializando Datepicker e máscara...");
        try {
            $('#data_retirada').datepicker({
                dateFormat: 'dd/mm/yy',
                minDate: 0,
                changeMonth: true,
                changeYear: true
            });
            console.log("Datepicker inicializado com sucesso.");
            $('#data_retirada').mask('00/00/0000');
            console.log("Máscara de data aplicada com sucesso.");
        } catch (error) {
            console.error("Erro ao inicializar Datepicker ou máscara:", error);
        }
    });

    // Evento para fechar o modal de criação
    $(document).on("click", "#modalPedido .close", function() {
        console.log("Fechando modal de novo pedido (#modalPedido)...");
        if (typeof $('#modalPedido').modal === 'function') {
            $('#modalPedido').modal('hide');
        } else {
            $('#modalPedido').hide();
            console.warn("Método modal não disponível, usando hide() como fallback.");
        }
        setTimeout(function() {
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open');
        }, 300);
    });

    // Função para buscar clientes com debounce
    $(document).on("keyup", "#buscarCliente", debounce(function() {
        var query = $(this).val().toLowerCase();
        $("#listaClientes").empty();
        if (query.length > 0) {
            $.ajax({
                url: "/atacado/buscar_cliente.php",
                method: "GET",
                data: { query: query },
                success: function(response) {
                    try {
                        var clientes = typeof response === 'string' ? JSON.parse(response) : response;
                        if (clientes.length > 0) {
                            const clientesUnicos = new Set();
                            clientes.forEach(function(cliente) {
                                const chave = `${cliente.nome}|${cliente.cidade}`;
                                if (!clientesUnicos.has(chave)) {
                                    clientesUnicos.add(chave);
                                    var clienteHTML = `
                                        <div class="cliente-sugestao" data-cliente-id="${cliente.id}">
                                            ${cliente.nome} | ${cliente.cidade}
                                        </div>
                                    `;
                                    $("#listaClientes").append(clienteHTML);
                                }
                            });
                        } else {
                            $("#listaClientes").html("<div>Nenhum cliente encontrado</div>");
                        }
                    } catch (e) {
                        console.error("Erro ao processar a resposta JSON (clientes): ", e);
                    }
                },
                error: function(xhr, status, error) {
                    console.log("Erro na requisição (clientes): " + error);
                }
            });
        } else {
            $("#listaClientes").empty();
        }
    }, 300));

    // Função para selecionar cliente
    $(document).on("click", ".cliente-sugestao", function() {
        var clienteNome = $(this).text();
        var clienteId = $(this).data("cliente-id");
        console.log("Cliente selecionado - Nome:", clienteNome, "ID:", clienteId);
        $("#buscarCliente").val(clienteNome);
        $("#cliente_id").val(clienteId);
        $("#listaClientes").empty();
    });

    // Função para buscar produtos com debounce
    $(document).on("input", "#buscarProduto", debounce(function() {
        var query = $(this).val().toLowerCase();
        $("#listaProdutos").empty();
        if (query.length > 0) {
            $.ajax({
                url: "/atacado/buscar_produtos.php",
                method: "GET",
                data: { query: query },
                success: function(response) {
                    try {
                        var produtos = typeof response === 'string' ? JSON.parse(response) : response;
                        if (produtos.length > 0) {
                            const produtosUnicos = new Set();
                            produtos.forEach(function(produto) {
                                if (!produtosUnicos.has(produto.nome)) {
                                    produtosUnicos.add(produto.nome);
                                    var produtoHTML = `
                                        <div class="produto-sugestao" data-produto-id="${produto.id}" data-produto-nome="${produto.nome}" data-produto-tipo="${produto.tipo}">
                                            ${produto.nome}
                                        </div>
                                    `;
                                    $("#listaProdutos").append(produtoHTML);
                                }
                            });
                        } else {
                            $("#listaProdutos").append("<div>Nenhum produto encontrado</div>");
                        }
                    } catch (e) {
                        console.error("Erro ao processar a resposta JSON (produtos): ", e);
                    }
                },
                error: function(xhr, status, error) {
                    console.log("Erro na requisição (produtos): " + error);
                }
            });
        } else {
            $("#listaProdutos").empty();
        }
    }, 300));

    // Função para adicionar produto à lista
    $(document).on("click", ".produto-sugestao", function() {
        var produtoNome = $(this).data("produto-nome");
        var produtoId = $(this).data("produto-id");
        var produtoTipo = $(this).data("produto-tipo");
        console.log("Adicionando produto:", produtoNome, "Tipo:", produtoTipo);
        if ($("#produtosSelecionados input[value='" + produtoId + "']").length === 0) {
            var inputHTML = '';
            var tipoLabel = '';
            if (produtoTipo === "UND") {
                inputHTML = `
                    <input type="number" name="quantidade[]" class="quantidade-produto form-control form-control-sm" step="1" min="1" placeholder="1" value="1" required>
                `;
                tipoLabel = '<span class="tipo-label text-muted ml-2">(UND)</span>';
                console.log("Gerado label para UND:", tipoLabel);
            } else {
                inputHTML = `
                    <input type="text" name="quantidade[]" class="quantidade-produto form-control form-control-sm" placeholder="0," value="0," required>
                `;
                tipoLabel = '<span class="tipo-label text-muted ml-2">(KG)</span>';
                console.log("Gerado label para KG:", tipoLabel);
            }
            var produtoHTML = `
                <div class="produto-selecionado d-flex align-items-center mb-2" data-produto-tipo="${produtoTipo}">
                    <input type="hidden" name="produto_id[]" value="${produtoId}">
                    <span class="produto-nome flex-grow-1" style="padding-right: 10px;">${produtoNome}</span>
                    ${inputHTML}
                    ${tipoLabel}
                    <button type="button" class="removerProduto btn btn-danger btn-sm" style="margin-left: 10px;"><i class="fas fa-trash-alt"></i></button>
                </div>
            `;
            $("#produtosSelecionados").append(produtoHTML);
            if (produtoTipo !== "UND") {
                $('.quantidade-produto').mask('000.000', {
                    reverse: false,
                    placeholder: "0,000",
                    translation: {
                        '0': { pattern: /[0-9]/, optional: true }
                    }
                });
            }
        }
        $("#buscarProduto").val('');
        $("#listaProdutos").empty();
    });

    // Remover produto da lista
    $(document).on("click", ".removerProduto", function() {
        $(this).parent().remove();
    });

    // Função para atualizar a lista de pedidos
    function atualizarListaPedidos() {
        $.ajax({
            url: '/atacado/pedidos.php',
            method: 'GET',
            data: { ajax: 1 },
            success: function(response) {
                $('#pedidosContainer').html(response);
                console.log("Tabela de pedidos recarregada, reaplicando eventos...");
                bindNovoPedidoEvent();
            },
            error: function(xhr, status, error) {
                console.error('Erro ao carregar pedidos: ', error);
                exibirNotificacao("Erro ao carregar pedidos.", 'error');
            }
        });
    }

    // Limpar o backdrop e o formulário ao fechar o modal
    $('#modalPedido').on('hidden.bs.modal', function() {
        console.log("Modal completamente fechado - Limpando backdrop e formulário...");
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open');
        $('#formPedido')[0].reset();
        $("#produtosSelecionados").empty();
        $("#listaClientes").empty();
        $("#listaProdutos").empty();
    });

    // Garantir que o foco seja gerenciado corretamente
    $('#verPedidoModal').on('shown.bs.modal', function() {
        $(this).find('.modal-title').focus();
    });
});