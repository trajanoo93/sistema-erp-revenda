console.log("pedidos.js: Arquivo carregado com sucesso (versão 174.26).");

function debounce(func, wait) {
    let timeout;
    return function (...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

// Função para carregar jQuery Mask dinamicamente
function loadJQueryMask() {
    return new Promise((resolve, reject) => {
        if (typeof $.fn.mask === 'function') {
            console.log("Método mask do jQuery Mask já disponível.");
            resolve();
        } else {
            console.log("Método mask não encontrado. Carregando jQuery Mask dinamicamente...");
            $.getScript("https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js")
                .done(function () {
                    console.log("jQuery Mask carregado dinamicamente com sucesso.");
                    resolve();
                })
                .fail(function (jqxhr, settings, exception) {
                    console.error("Erro ao carregar jQuery Mask dinamicamente:", exception);
                    reject(exception);
                });
        }
    });
}


$(document).ready(function () {
    console.log("pedidos.js: $(document).ready executado.");

    // Função para mostrar notificações
    function exibirNotificacao(mensagem, tipo = 'success') {
        var $notificacao = $('#notificacao');
        if ($notificacao.length) {
            $notificacao.removeClass('error success').addClass(tipo).text(mensagem).css('top', '0').fadeIn();
            setTimeout(function () {
                $notificacao.fadeOut('slow', function () {
                    $notificacao.css('top', '-50px');
                });
            }, 3000);
        } else {
            console.warn("Elemento #notificacao não encontrado no DOM. Usando alert como fallback.");
            alert(mensagem);
        }
    }

    // Adicionar estilo CSS para a notificação, spinner, modal e dropdowns
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
            .datepicker {
                cursor: pointer;
            }
            .list-group {
                max-height: 200px;
                overflow-y: auto;
                border: 1px solid #ddd;
                border-radius: 4px;
                background: #fff;
                box-shadow: 0 2px 5px rgba(0,0,0,0.1);
                position: absolute;
                width: 100%;
                z-index: 1050;
                display: none;
            }
            .list-group.show {
                display: block;
            }
            .list-group-item {
                cursor: pointer;
                padding: 8px 12px;
                border-bottom: 1px solid #eee;
                transition: background-color 0.2s ease;
            }
            .list-group-item:hover {
                background-color: #f8f9fa;
            }
            .list-group-item:last-child {
                border-bottom: none;
            }
            .list-group-item.searching {
                color: #888;
                cursor: default;
            }
        `).appendTo('head');
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
                    .done(function () {
                        console.log("Bootstrap carregado dinamicamente com sucesso.");
                        resolve();
                    })
                    .fail(function (jqxhr, settings, exception) {
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
            $(document).off("click", "#btnNovoPedido").on("click", "#btnNovoPedido", function (e) {
                e.preventDefault();
                console.log("Botão Novo Pedido clicado - Verificando modal...");
                if ($('#modalPedido').length) {
                    console.log("Modal #modalPedido encontrado no DOM. Tentando abrir...");
                    loadBootstrap().then(() => {
                        if (typeof $.fn.modal === 'function') {
                            console.log("Método modal disponível. Abrindo modal...");
                            $('#modalPedido').modal('show');
                            console.log("Modal #modalPedido aberto.");
                        } else {
                            console.error("Método modal AINDA não disponível após carregamento dinâmico!");
                            exibirNotificacao("Erro ao abrir modal de novo pedido.", 'error');
                        }
                    }).catch(error => {
                        console.error("Falha ao carregar Bootstrap:", error);
                        exibirNotificacao("Erro ao abrir modal de novo pedido: " + error, 'error');
                    });
                } else {
                    console.error("Modal #modalPedido NÃO encontrado no DOM!");
                    exibirNotificacao("Erro: Modal de novo pedido não encontrado.", 'error');
                }
            });
        } else {
            console.error("Botão #btnNovoPedido NÃO encontrado no DOM!");
            exibirNotificacao("Erro: Botão de novo pedido não encontrado.", 'error');
        }
    }

    // Função para atualizar a tabela de pedidos
    function atualizarTabela() {
        var status = $('#statusFilter').val();
        var dataInicio = $('#data_inicio').val();
        var dataFim = $('#data_fim').val();
        console.log("Filtrando pedidos:", { status, dataInicio, dataFim });
        $.ajax({
            url: '/atacado/pedidos.php',
            method: 'GET',
            dataType: 'html',
            data: {
                ajax: 1,
                status: status,
                data_inicio: dataInicio,
                data_fim: dataFim
            },
            success: function (data) {
                try {
                    $('#pedidosContainer').html(data);
                    console.log("Tabela de pedidos recarregada.");
                    bindNovoPedidoEvent();
                    bindFilterEvents();
                } catch (e) {
                    console.error("Erro ao inserir conteúdo em #pedidosContainer:", e);
                    exibirNotificacao("Erro ao atualizar a tabela de pedidos.", 'error');
                }
            },
            error: function (xhr, status, error) {
                console.error('Erro ao atualizar tabela:', error);
                console.error("Status:", status);
                console.error("Response Text:", xhr.responseText);
                exibirNotificacao('Erro ao atualizar a tabela de pedidos: ' + error, 'error');
            }
        });
    }

    // Função para associar eventos aos filtros
    function bindFilterEvents() {
        $('#statusFilter, #data_inicio, #data_fim').off('change').on('change', atualizarTabela);
    }

    // Evento para abrir o modal de detalhes do pedido
    $(document).on("click", ".detalhesPedido", function () {
        console.log("Botão de visualizar clicado!");
        var pedidoId = $(this).data("id");
        console.log("Enviando requisição AJAX para ver_pedido.php com ID:", pedidoId);
        $("#verPedidoContent").html(`
            <div class="loading-spinner">
                <i class="fas fa-spinner fa-spin"></i> Carregando detalhes do pedido...
            </div>
        `);
        if ($('#verPedidoModal').length) {
            console.log("Modal #verPedidoModal encontrado no DOM. Abrindo com loading...");
            loadBootstrap().then(() => {
                if (typeof $.fn.modal === 'function') {
                    $('#verPedidoModal').modal('show');
                    console.log("Modal #verPedidoModal aberto com spinner.");
                } else {
                    console.error("Método modal não disponível após carregamento dinâmico!");
                    exibirNotificacao("Erro ao abrir modal.", 'error');
                }
                $.ajax({
                    url: "/atacado/ver_pedido.php",
                    type: "GET",
                    dataType: "html",
                    data: { id: pedidoId },
                    success: function (response) {
                        console.log("Resposta recebida de ver_pedido.php:", response.substring(0, 100) + "...");
                        try {
                            $("#verPedidoContent").html(response);
                            console.log("Conteúdo do modal preenchido para pedido ID:", pedidoId);
                            if (typeof window.inicializarVerPedido === 'function') {
                                window.inicializarVerPedido();
                                console.log("inicializarVerPedido chamado com sucesso após preenchimento.");
                            } else {
                                console.error("Função inicializarVerPedido não encontrada após preenchimento.");
                            }
                            if (typeof $.fn.modal === 'function') {
                                $('#verPedidoModal').modal('handleUpdate');
                            }
                        } catch (e) {
                            console.error("Erro ao inserir conteúdo em #verPedidoContent:", e);
                            exibirNotificacao("Erro ao carregar detalhes do pedido.", 'error');
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error("Erro ao carregar detalhes do pedido:", error);
                        console.error("Status:", status);
                        console.error("Response Text:", xhr.responseText);
                        exibirNotificacao("Erro ao carregar detalhes do pedido: " + error, 'error');
                        $("#verPedidoContent").html("<p>Erro ao carregar detalhes do pedido.</p>");
                    }
                });
            }).catch(error => {
                console.error("Falha ao carregar Bootstrap:", error);
                exibirNotificacao("Erro ao abrir modal: " + error, 'error');
            });
        } else {
            console.error("Modal #verPedidoModal NÃO encontrado no DOM!");
            exibirNotificacao("Erro: Modal de visualização não encontrado.", 'error');
        }
    });

    // Evento para fechar o modal de visualização
    $(document).on("click", ".pedido-close", function () {
        console.log("Fechando modal de visualização (#verPedidoModal)...");
        if (typeof $.fn.modal === 'function') {
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
    $(document).on("input", "#buscarCliente", function () {
        var query = $(this).val().trim();
        $("#listaClientes").html('<div class="list-group-item searching">Pesquisando...</div>').addClass('show');
        if (query === "") {
            $(this).addClass("is-invalid");
            $("#listaClientes").empty().removeClass('show');
        } else {
            $(this).removeClass("is-invalid");
        }
    });

   // Handler para criar pedido
$(document).off('submit', '#formPedido').on('submit', '#formPedido', function (e) {
    e.preventDefault();
    e.stopPropagation();
    var $form = $(this);
    var $submitButton = $('#btnSalvarPedido');
    if ($form.hasClass('submitting') || $submitButton.prop('disabled')) {
        console.log("Submissão ignorada: Formulário já está sendo processado ou botão desativado.");
        return;
    }
    $form.addClass('submitting');
    $submitButton.prop('disabled', true).text('Salvando...');
    console.log("Submissão do formulário #formPedido iniciada via AJAX.");

    let valid = true;
    const produtosSelecionados = $('.produto-selecionado');
    const produtosData = [];
    produtosSelecionados.each(function () {
        var produtoTipo = $(this).data('produto-tipo') || 'KG';
        var quantidadeInput = $(this).find('.quantidade-produto');
        var produtoId = $(this).find('input[name="produto_id[]"]').val();
        var quantidade = quantidadeInput.val().trim();
        console.log("Validando produto ID:", produtoId, "Tipo:", produtoTipo, "Quantidade:", quantidade);
        if (!produtoId || !quantidade) {
            console.error("Produto ou quantidade ausente:", { produtoId, quantidade });
            valid = false;
            return true; // Continua o loop, mas marca como inválido
        }
        if (produtoTipo === "UND") {
            if (!Number.isInteger(Number(quantidade)) || Number(quantidade) <= 0) {
                console.error("Quantidade inválida para UND:", quantidade);
                exibirNotificacao('A quantidade de produtos do tipo UND deve ser um número inteiro maior que 0 (ex.: 1, 2).', 'error');
                valid = false;
            }
        } else {
            if (!/^\d+(?:\.\d{1,3})?$/.test(quantidade) || parseFloat(quantidade) <= 0) {
                console.error("Quantidade inválida para KG:", quantidade);
                exibirNotificacao('A quantidade de produtos do tipo KG deve ser no formato X.YYY (ex.: 0.850 ou 12) e maior que 0.', 'error');
                valid = false;
            }
        }
        produtosData.push({ produto_id: produtoId, quantidade: quantidade });
    });

    const clienteId = $('#cliente_id').val();
    const dataRetirada = $('#data_retirada').val();
    console.log("Dados a serem enviados:", {
        cliente_id: clienteId,
        data_retirada: dataRetirada,
        observacoes: $('#observacoes').val(),
        produtos: produtosData
    });

    if (!valid) {
        console.error("Validação falhou: Erro nos produtos.");
        exibirNotificacao('Por favor, corrija as quantidades dos produtos.', 'error');
        $form.removeClass('submitting');
        $submitButton.prop('disabled', false).text('Salvar Pedido');
        return;
    }
    if (!clienteId) {
        console.error("Validação falhou: clienteId ausente:", clienteId);
        exibirNotificacao('Por favor, selecione um cliente.', 'error');
        $form.removeClass('submitting');
        $submitButton.prop('disabled', false).text('Salvar Pedido');
        return;
    }
    if (!dataRetirada) {
        console.error("Validação falhou: dataRetirada ausente:", dataRetirada);
        exibirNotificacao('Por favor, insira a data de retirada.', 'error');
        $form.removeClass('submitting');
        $submitButton.prop('disabled', false).text('Salvar Pedido');
        return;
    }
    if (produtosSelecionados.length === 0) {
        console.error("Validação falhou: Nenhum produto selecionado.");
        exibirNotificacao('Por favor, adicione pelo menos um produto.', 'error');
        $form.removeClass('submitting');
        $submitButton.prop('disabled', false).text('Salvar Pedido');
        return;
    }

    var formData = new FormData($form[0]);
    formData.append('produtos', JSON.stringify(produtosData));
    console.log("Dados serializados:", Array.from(formData.entries()));

    $.ajax({
        url: "/atacado/criar_pedido.php",
        method: "POST",
        data: formData,
        contentType: false,
        processData: false,
        dataType: "json",
        beforeSend: function () {
            console.log("Enviando requisição AJAX para criar pedido:", Array.from(formData.entries()));
        },
        success: function (response) {
            console.log("Resposta de criar_pedido.php:", response);
            if (response.status === "success") {
                exibirNotificacao(response.message, 'success');
                $('#modalPedido').modal('hide');
                atualizarTabela();
            } else {
                exibirNotificacao(response.message, 'error');
                console.error("Erro na criação do pedido:", response.message);
            }
            $form.removeClass('submitting');
            $submitButton.prop('disabled', false).text('Salvar Pedido');
        },
        error: function (xhr, status, error) {
            console.error("Erro na requisição AJAX:", error);
            console.error("Status:", status);
            console.error("Response Text:", xhr.responseText);
            console.error("Status Code:", xhr.status);
            exibirNotificacao("Erro ao processar a solicitação: " + (xhr.responseText || error), 'error');
            $form.removeClass('submitting');
            $submitButton.prop('disabled', false).text('Salvar Pedido');
        }
    });
});

    // Handler para clique no botão de salvar pedido
    $(document).off('click', '#btnSalvarPedido').on('click', '#btnSalvarPedido', function (e) {
        e.preventDefault();
        console.log("Botão Salvar Pedido clicado - disparando submit via jQuery.");
        $('#formPedido').trigger('submit');
    });

   // Handler para editar pedido
$(document).off('submit', '#formEditarPedido').on('submit', '#formEditarPedido', function (e) {
    e.preventDefault();
    e.stopPropagation();
    var $form = $(this);
    var $submitButton = $('#btnSalvarEdicao');
    if ($form.hasClass('submitting') || $submitButton.prop('disabled')) {
        console.log("Submissão ignorada: Formulário de edição já está sendo processado.");
        return;
    }
    $form.addClass('submitting');
    $submitButton.prop('disabled', true).text('Salvando...');
    console.log("Submissão do formulário #formEditarPedido iniciada.");

    let valid = true;
    const produtosSelecionados = $('.produto-selecionado');
    const produtosData = [];
    produtosSelecionados.each(function () {
        var produtoTipo = $(this).data('produto-tipo') || 'KG';
        var quantidadeInput = $(this).find('.quantidade-produto');
        var quantidadeSeparadaInput = $(this).find('.quantidade-separada');
        var produtoId = $(this).find('input[name="produto_id[]"]').val();
        var quantidade = quantidadeInput.val().trim();
        var quantidade_separada = quantidadeSeparadaInput.length ? quantidadeSeparadaInput.val().trim() : null;
        console.log("Validando produto ID:", produtoId, "Tipo:", produtoTipo, "Quantidade:", quantidade, "Separada:", quantidade_separada);
        if (!produtoId || !quantidade) {
            console.error("Produto ou quantidade ausente:", { produtoId, quantidade });
            valid = false;
            exibirNotificacao('Produto ou quantidade ausente.', 'error');
            return true;
        }
        if (produtoTipo === "UND") {
            if (!/^\d+$/.test(quantidade) || parseInt(quantidade) <= 0) {
                console.error("Quantidade inválida para UND:", quantidade);
                exibirNotificacao('A quantidade de produtos do tipo UND deve ser um número inteiro maior que 0 (ex.: 1, 2).', 'error');
                valid = false;
                return true;
            }
            if (quantidade_separada && (!/^\d+$/.test(quantidade_separada) || parseInt(quantidade_separada) < 0)) {
                console.error("Quantidade separada inválida para UND:", quantidade_separada);
                exibirNotificacao('A quantidade separada de produtos do tipo UND deve ser um número inteiro maior ou igual a 0.', 'error');
                valid = false;
                return true;
            }
        } else {
            if (!/^\d+(?:\.\d{1,3})?$/.test(quantidade) || parseFloat(quantidade) <= 0) {
                console.error("Quantidade inválida para KG:", quantidade);
                exibirNotificacao('A quantidade de produtos do tipo KG deve ser no formato X.YYY (ex.: 0.850) e maior que 0.', 'error');
                valid = false;
                return true;
            }
            if (quantidade_separada && (!/^\d+(?:\.\d{1,3})?$/.test(quantidade_separada) || parseFloat(quantidade_separada) < 0)) {
                console.error("Quantidade separada inválida para KG:", quantidade_separada);
                exibirNotificacao('A quantidade separada de produtos do tipo KG deve ser no formato X.YYY (ex.: 0.850) maior ou igual a 0.', 'error');
                valid = false;
                return true;
            }
        }
        produtosData.push({
            produto_id: produtoId,
            quantidade: quantidade,
            quantidade_separada: quantidade_separada || 'N/A'
        });
    });

    const pedidoId = $('#pedido_id').val()?.trim();
    const dataRetirada = $('#data_retirada').val()?.trim();
    const status = $('#status').val()?.trim();
    console.log("Dados a serem enviados:", {
        id: pedidoId,
        data_retirada: dataRetirada,
        observacoes: $('#observacoes').val(),
        status: status,
        produtos: produtosData
    });

    if (!valid) {
        console.error("Validação falhou: Erro nos produtos.");
        exibirNotificacao('Por favor, corrija as quantidades dos produtos.', 'error');
        $form.removeClass('submitting');
        $submitButton.prop('disabled', false).text('Salvar Edição');
        return;
    }
    if (!pedidoId) {
        console.error("Validação falhou: pedidoId ausente:", pedidoId);
        exibirNotificacao('Por favor, forneça o ID do pedido.', 'error');
        $form.removeClass('submitting');
        $submitButton.prop('disabled', false).text('Salvar Edição');
        return;
    }
    if (!dataRetirada || !/^\d{2}\/\d{2}\/\d{4}$/.test(dataRetirada)) {
        console.error("Validação falhou: dataRetirada inválida:", dataRetirada);
        exibirNotificacao('Por favor, insira a data de retirada no formato DD/MM/YYYY.', 'error');
        $form.removeClass('submitting');
        $submitButton.prop('disabled', false).text('Salvar Edição');
        return;
    }
    if (!status) {
        console.error("Validação falhou: status ausente:", status);
        exibirNotificacao('Por favor, selecione um status.', 'error');
        $form.removeClass('submitting');
        $submitButton.prop('disabled', false).text('Salvar Edição');
        return;
    }

    var formData = new FormData($form[0]);
    formData.append('produtos', JSON.stringify(produtosData));
    console.log("Dados serializados:", Array.from(formData.entries()));

    $.ajax({
        url: "/atacado/editar_pedido.php",
        method: "POST",
        data: formData,
        contentType: false,
        processData: false,
        dataType: "json",
        beforeSend: function () {
            console.log("Enviando requisição AJAX para editar pedido:", pedidoId);
        },
        success: function (response) {
            console.log("Resposta de editar_pedido.php:", response);
            if (response.success) {
                exibirNotificacao(response.message, 'success');
                $('#verPedidoModal').modal('hide');
                atualizarTabela();
            } else {
                exibirNotificacao(response.message, 'error');
                console.error("Erro na edição do pedido:", response.message);
            }
            $form.removeClass('submitting');
            $submitButton.prop('disabled', false).text('Salvar Edição');
        },
        error: function (xhr, status, error) {
            console.error("Erro na requisição AJAX:", error, "Status:", status, "Response:", xhr.responseText);
            exibirNotificacao("Erro ao processar a solicitação: " + (xhr.responseText || error), 'error');
            $form.removeClass('submitting');
            $submitButton.prop('disabled', false).text('Salvar Edição');
        }
    });
});
    // Evento para excluir pedido
    $(document).off("click", ".excluirPedido").on("click", ".excluirPedido", function (e) {
        e.preventDefault();
        var $button = $(this);
        if ($button.hasClass('disabled')) {
            console.log("Clique ignorado: Botão de exclusão já está desativado.");
            return;
        }
        $button.addClass('disabled');
        console.log("Botão de exclusão clicado.");
        var id = $button.data("id");
        console.log("Iniciando exclusão do pedido ID:", id);
        if (confirm('Tem certeza que deseja excluir este pedido?')) {
            $.ajax({
                url: "/atacado/deletar_pedido.php",
                method: "POST",
                data: { id: id },
                dataType: "json",
                beforeSend: function () {
                    console.log("Enviando requisição AJAX para excluir pedido ID:", id);
                },
                success: function (response) {
                    console.log("Resposta recebida para exclusão do pedido ID:", id, response);
                    if (response.status === "success") {
                        exibirNotificacao('Pedido excluído com sucesso!', 'success');
                        $("tr[data-id='" + id + "']").fadeOut(500, function () {
                            $(this).remove();
                            console.log("Linha do pedido ID:", id, "removida do DOM.");
                        });
                    } else {
                        exibirNotificacao('Erro ao excluir pedido: ' + response.message, 'error');
                        console.error("Erro na exclusão do pedido ID:", id, response.message);
                    }
                    $button.removeClass('disabled');
                },
                error: function (xhr, status, error) {
                    console.error("Erro na requisição AJAX para excluir pedido ID:", id, "Erro:", error, "Status:", status, "Response:", xhr.responseText);
                    exibirNotificacao('Erro ao processar a solicitação: ' + (xhr.responseText || error), 'error');
                    $button.removeClass('disabled');
                }
            });
        } else {
            $button.removeClass('disabled');
            console.log("Exclusão do pedido ID:", id, "cancelada pelo usuário.");
        }
    });

    // Função para buscar clientes com debounce
    $(document).on("keyup", "#buscarCliente", debounce(function () {
        var query = $(this).val().toLowerCase().trim();
        console.log("Busca de cliente iniciada. Query:", query);
        if (!$("#listaClientes").length) {
            console.error("Elemento #listaClientes não encontrado no DOM!");
            exibirNotificacao("Erro: Lista de clientes não encontrada.", 'error');
            return;
        }
        $("#listaClientes").html('<div class="list-group-item searching">Pesquisando...</div>').addClass('show');
        console.log("Dropdown #listaClientes inicializado com 'Pesquisando...'");
        if (query.length > 0) {
            $.ajax({
                url: "/atacado/buscar_cliente.php",
                method: "GET",
                data: { query: query },
                dataType: "json",
                beforeSend: function () {
                    console.log("Enviando requisição AJAX para buscar clientes com query:", query);
                },
                success: function (response) {
                    console.log("Resposta recebida de buscar_cliente.php:", response);
                    try {
                        $("#listaClientes").empty();
                        console.log("Elemento #listaClientes limpo.");
                        if (Array.isArray(response) && response.length > 0) {
                            const clientesUnicos = new Map();
                            response.forEach(function (cliente) {
                                const chave = `${cliente.nome.toLowerCase().trim()}|${cliente.cidade.toLowerCase().trim()}|${cliente.id}`;
                                if (!clientesUnicos.has(chave)) {
                                    clientesUnicos.set(chave, cliente);
                                }
                            });
                            console.log("Clientes únicos encontrados:", clientesUnicos.size);
                            if (clientesUnicos.size > 0) {
                                clientesUnicos.forEach(function (cliente) {
                                    var clienteHTML = `
                                        <div class="cliente-sugestao list-group-item" data-cliente-id="${cliente.id}">
                                            ${cliente.nome} | ${cliente.cidade}
                                        </div>
                                    `;
                                    $("#listaClientes").append(clienteHTML);
                                });
                                $("#listaClientes").addClass('show');
                                console.log("Dropdown #listaClientes preenchido com", clientesUnicos.size, "clientes.");
                            } else {
                                $("#listaClientes").html('<div class="list-group-item">Nenhum cliente encontrado</div>').addClass('show');
                                console.log("Nenhum cliente único encontrado.");
                            }
                        } else {
                            $("#listaClientes").html('<div class="list-group-item">Nenhum cliente encontrado</div>').addClass('show');
                            console.log("Resposta vazia ou inválida:", response);
                        }
                    } catch (e) {
                        console.error("Erro ao processar resposta JSON (clientes):", e);
                        $("#listaClientes").html('<div class="list-group-item">Erro ao carregar clientes</div>').addClass('show');
                        exibirNotificacao("Erro ao carregar clientes.", 'error');
                    }
                },
                error: function (xhr, status, error) {
                    console.error("Erro na requisição AJAX (clientes):", error, "Status:", status, "Response:", xhr.responseText);
                    $("#listaClientes").html('<div class="list-group-item">Erro ao carregar clientes</div>').addClass('show');
                    exibirNotificacao("Erro ao carregar clientes: " + error, 'error');
                }
            });
        } else {
            $("#listaClientes").empty().removeClass('show');
            console.log("Query vazia, dropdown #listaClientes fechado.");
        }
    }, 300));

    // Seleção de cliente
    $(document).on("click", ".cliente-sugestao", function (e) {
        e.stopPropagation();
        console.log("Handler de seleção de cliente disparado.");
        var clienteNome = $(this).text().trim();
        var clienteId = $(this).data("cliente-id");
        console.log("Cliente selecionado - Nome:", clienteNome, "ID:", clienteId);
        $("#buscarCliente").val(clienteNome);
        $("#cliente_id").val(clienteId);
        $("#listaClientes").empty().removeClass('show').hide();
        console.log("Dropdown de clientes fechado.");
        $("#buscarCliente").focus();
    });

    // Função para buscar produtos com debounce
    $(document).on("input", "#buscarProduto", debounce(function () {
        var query = $(this).val().toLowerCase().trim();
        console.log("Busca de produto iniciada. Query:", query);
        if (!$("#listaProdutos").length) {
            console.error("Elemento #listaProdutos não encontrado no DOM!");
            exibirNotificacao("Erro: Lista de produtos não encontrada.", 'error');
            return;
        }
        $("#listaProdutos").html('<div class="list-group-item searching">Pesquisando...</div>').addClass('show');
        console.log("Dropdown #listaProdutos inicializado com 'Pesquisando...'");
        if (query.length > 0) {
            $.ajax({
                url: "/atacado/buscar_produtos.php",
                method: "GET",
                data: { query: query },
                dataType: "json",
                beforeSend: function () {
                    console.log("Enviando requisição AJAX para buscar produtos com query:", query);
                },
                success: function (response) {
                    console.log("Resposta recebida de buscar_produtos.php:", response);
                    try {
                        $("#listaProdutos").empty();
                        console.log("Elemento #listaProdutos limpo.");
                        if (Array.isArray(response) && response.length > 0) {
                            const produtosUnicos = new Map();
                            response.forEach(function (produto) {
                                const chave = `${produto.nome.toLowerCase().trim()}|${produto.id}`;
                                if (!produtosUnicos.has(chave)) {
                                    produtosUnicos.set(chave, produto);
                                }
                            });
                            console.log("Produtos únicos encontrados:", produtosUnicos.size);
                            if (produtosUnicos.size > 0) {
                                produtosUnicos.forEach(function (produto) {
                                    var produtoHTML = `
                                        <div class="produto-sugestao list-group-item" data-produto-id="${produto.id}" data-produto-nome="${produto.nome}" data-produto-tipo="${produto.tipo || 'KG'}">
                                            ${produto.nome}
                                        </div>
                                    `;
                                    $("#listaProdutos").append(produtoHTML);
                                });
                                $("#listaProdutos").addClass('show');
                                console.log("Dropdown #listaProdutos preenchido com", produtosUnicos.size, "produtos.");
                            } else {
                                $("#listaProdutos").html('<div class="list-group-item">Nenhum produto encontrado</div>').addClass('show');
                                console.log("Nenhum produto único encontrado.");
                            }
                        } else {
                            $("#listaProdutos").html('<div class="list-group-item">Nenhum produto encontrado</div>').addClass('show');
                            console.log("Resposta vazia ou inválida:", response);
                        }
                    } catch (e) {
                        console.error("Erro ao processar resposta JSON (produtos):", e);
                        $("#listaProdutos").html('<div class="list-group-item">Erro ao carregar produtos</div>').addClass('show');
                        exibirNotificacao("Erro ao carregar produtos.", 'error');
                    }
                },
                error: function (xhr, status, error) {
                    console.error("Erro na requisição AJAX (produtos):", error, "Status:", status, "Response:", xhr.responseText);
                    $("#listaProdutos").html('<div class="list-group-item">Erro ao carregar produtos</div>').addClass('show');
                    exibirNotificacao("Erro ao carregar produtos: " + error, 'error');
                }
            });
        } else {
            $("#listaProdutos").empty().removeClass('show');
            console.log("Query vazia, dropdown #listaProdutos fechado.");
        }
    }, 300));

 // Adicionar produto à lista
$(document).on("click", ".produto-sugestao", function (e) {
    e.stopPropagation();
    if ($(this).hasClass('disabled')) return;
    $(this).addClass('disabled');
    console.log("Handler de seleção de produto disparado.");
    var produtoNome = $(this).data("produto-nome");
    var produtoId = parseInt($(this).data("produto-id"));
    var produtoTipo = $(this).data("produto-tipo") || 'KG';
    console.log("Tentando adicionar produto - Nome:", produtoNome, "ID:", produtoId, "Tipo:", produtoTipo, "Tipo de produtoId:", typeof produtoId);
    console.log("Estado atual de #produtosSelecionados:", $("#produtosSelecionados").html());
    console.log("Produtos atuais em #produtosSelecionados:", $("#produtosSelecionados .produto-selecionado").map(function () { return $(this).data('produto-id'); }).get());
    var alreadyAdded = $("#produtosSelecionados .produto-selecionado").filter(function () {
        var existingId = parseInt($(this).data('produto-id'));
        console.log("Comparando produtoId:", produtoId, "com existingId:", existingId, "Tipos:", typeof produtoId, typeof existingId);
        return existingId === produtoId;
    }).length > 0;
    console.log("Produto já adicionado? ", alreadyAdded);
    if (!alreadyAdded) {
        var inputHTML = '';
        var tipoLabel = '';
        if (produtoTipo === "UND") {
            inputHTML = `
                <input type="number" name="quantidade[]" class="quantidade-produto form-control form-control-sm" step="1" min="1" placeholder="1" value="1" required>
            `;
            tipoLabel = '<span class="tipo-label text-muted ml-2">(UND)</span>';
            console.log("Gerado input para UND:", tipoLabel);
        } else {
            inputHTML = `
                <input type="text" name="quantidade[]" class="quantidade-produto form-control form-control-sm" placeholder="0.000" value="0.000" required>
            `;
            tipoLabel = '<span class="tipo-label text-muted ml-2">(KG)</span>';
            console.log("Gerado input para KG:", tipoLabel);
        }
        var produtoHTML = `
            <div class="produto-selecionado mb-3" data-produto-tipo="${produtoTipo}" data-produto-id="${produtoId}">
                <input type="hidden" name="produto_id[]" value="${produtoId}">
                <div class="form-group">
                    <label>Produto</label>
                    <span class="form-control">${produtoNome}</span>
                </div>
                <div class="form-group">
                    <label>Quantidade ${tipoLabel}</label>
                    ${inputHTML}
                </div>
                <button type="button" class="btn btn-danger btn-sm removerProduto">Remover</button>
            </div>
        `;
        try {
            $("#produtosSelecionados").append(produtoHTML);
            if (produtoTipo !== "UND") {
                loadJQueryMask().then(() => {
                    $('.quantidade-produto').last().mask('000.000', {
                        reverse: true,
                        placeholder: "0.000",
                        translation: { '0': { pattern: /[0-9]/, optional: true } }
                    });
                    console.log("Máscara aplicada ao campo de quantidade para produto ID:", produtoId);
                }).catch(error => {
                    console.error("Erro ao carregar jQuery Mask:", error);
                    exibirNotificacao("Erro ao formatar o campo de quantidade: " + error.message, 'error');
                });
            }
            console.log("Produto adicionado ao DOM:", produtoId);
        } catch (e) {
            console.error("Erro ao adicionar produto ao DOM:", e);
            exibirNotificacao("Erro ao adicionar produto: " + e.message, 'error');
        }
        $("#buscarProduto").val('');
        $("#listaProdutos").empty().removeClass('show').hide();
        console.log("Dropdown de produtos fechado após adição.");
    } else {
        console.warn("Produto ID:", produtoId, "Nome:", produtoNome, "já está na lista.");
        exibirNotificacao(`Produto "${produtoNome}" já adicionado.`, 'error');
        $("#listaProdutos").empty().removeClass('show').hide();
        console.log("Dropdown de produtos fechado após mensagem.");
    }
    setTimeout(() => $(this).removeClass('disabled'), 500);
});

    // Inicializar o evento do botão Novo Pedido com retry
    function inicializarEventos() {
        let attempt = 1;
        const maxAttempts = 10;
        const retryInterval = setInterval(function () {
            if ($('#btnNovoPedido').length) {
                bindNovoPedidoEvent();
                bindFilterEvents();
                console.log("Eventos de pedidos inicializados com sucesso.");
                clearInterval(retryInterval);
            } else {
                console.log(`Tentativa ${attempt}/${maxAttempts} de inicializar eventos: #btnNovoPedido não encontrado.`);
                attempt++;
                if (attempt > maxAttempts) {
                    console.error("Falha ao inicializar eventos: #btnNovoPedido não encontrado após tentativas.");
                    exibirNotificacao("Erro: Não foi possível carregar o botão de novo pedido.", 'error');
                    clearInterval(retryInterval);
                }
            }
        }, 1000);
    }
    setTimeout(inicializarEventos, 1000);

   // Inicializar o Datepicker e a máscara de data
$('#modalPedido').on('shown.bs.modal', function () {
    console.log("Modal #modalPedido exibido - Inicializando Datepicker e máscara...");
    try {
        $('#formPedido')[0].reset();
        $("#produtosSelecionados").empty();
        console.log("Elemento #produtosSelecionados limpo:", $("#produtosSelecionados .produto-selecionado").length === 0, "HTML:", $("#produtosSelecionados").html());
        $("#listaClientes").empty().removeClass('show').hide();
        $("#listaProdutos").empty().removeClass('show').hide();
        $("#buscarCliente").val('');
        $("#cliente_id").val('');
        $("#buscarProduto").val('');
        $("#observacoes").val('');
        console.log("Formulário e dropdowns limpos ao abrir o modal.");
    } catch (e) {
        console.error("Erro ao limpar modal:", e);
        exibirNotificacao("Erro ao limpar modal: " + e.message, 'error');
    }
    if ($('#data_retirada').length) {
        console.log("Campo #data_retirada encontrado no DOM.");
        try {
            if (typeof $.fn.datepicker === 'function') {
                console.log("Método datepicker do jQuery UI disponível.");
                $('#data_retirada').datepicker('destroy').datepicker({
                    dateFormat: 'dd/mm/yy',
                    minDate: 0,
                    changeMonth: true,
                    changeYear: true,
                    showAnim: 'slideDown',
                    beforeShow: function (input, inst) {
                        setTimeout(function () {
                            inst.dpDiv.css({
                                'z-index': 1070,
                                top: $(input).offset().top + $(input).outerHeight(),
                                left: $(input).offset().left
                            });
                        }, 0);
                    }
                });
                console.log("Datepicker inicializado com sucesso para #data_retirada.");
                loadJQueryMask().then(() => {
                    $('#data_retirada').mask('00/00/0000', { placeholder: "DD/MM/YYYY" });
                    console.log("Máscara de data aplicada com sucesso para #data_retirada.");
                }).catch(error => {
                    console.error("Erro ao carregar jQuery Mask para data_retirada:", error);
                    exibirNotificacao("Erro ao formatar o campo de data: " + error.message, 'error');
                });
            } else {
                console.error("Método datepicker do jQuery UI NÃO disponível!");
                exibirNotificacao("Erro ao carregar o calendário.", 'error');
            }
        } catch (error) {
            console.error("Erro ao inicializar Datepicker ou máscara:", error);
            exibirNotificacao("Erro ao carregar o calendário: " + error.message, 'error');
        }
    } else {
        console.error("Campo #data_retirada NÃO encontrado no DOM!");
        exibirNotificacao("Erro: Campo de data de retirada não encontrado.", 'error');
    }
});
});