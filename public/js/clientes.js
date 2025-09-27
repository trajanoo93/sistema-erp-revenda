console.log("Arquivo clientes.js carregado com sucesso (versão 174.8)");

$(document).ready(function () {
    // Vincular eventos uma única vez na carga inicial
    bindEvents();

    // Função para reaplicar eventos que não precisam de delegação
    reapplyClientButtonEvents();
});

function bindEvents() {
    // Evento para excluir clientes (vinculado uma única vez)
    $(document).off("click", ".btnExcluirCliente").on("click", ".btnExcluirCliente", function (e) {
        e.preventDefault();
        var id = $(this).data("id");

        if (confirm('Tem certeza que deseja excluir este cliente?')) {
            // Enviar o pedido de exclusão via AJAX
            $.ajax({
                url: "deletar_cliente.php",
                method: "POST",
                data: { id: id },
                dataType: "json",
                success: function (response) {
                    console.log("Resposta do servidor:", response);

                    if (response.status === "success") {
                        alert(response.message);
                        $("tr[data-id='" + id + "']").remove();
                    } else {
                        alert("Erro ao excluir cliente: " + response.message);
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Erro ao excluir o cliente:', xhr.responseText);
                    alert("Erro ao excluir o cliente.");
                }
            });
        }
    });

    // Evento para abrir o painel de edição de cliente
    $(document).off("click", ".btnEditarCliente").on("click", ".btnEditarCliente", function(e) {
        e.preventDefault();
        var id = $(this).data("id");
        console.log('Editando cliente ID:', id);

        $.ajax({
            url: 'editar_cliente.php?id=' + id,
            method: 'GET',
            success: function(data) {
                $('#formClienteContent').html(data);
                $('#editClienteContainer').addClass('active');
                console.log('Painel de edição de cliente aberto.');

                applyMasks();
            },
            error: function() {
                console.log('Erro ao carregar o formulário de edição.');
            }
        });
    });

    // Fechar o painel ao clicar no botão de fechar, mas apenas se a aba "Clientes" estiver ativa
    $(document).off("click", ".close-btn").on("click", ".close-btn", function(event) {
        console.log("Evento de clique em .close-btn disparado. Verificando aba ativa...");
        var activePage = localStorage.getItem('activePage');
        console.log("Aba ativa:", activePage);

        if (activePage !== 'clientes.php') {
            console.log("Aba ativa não é 'clientes.php'. Ignorando evento.");
            return; // Só executar se a aba "Clientes" estiver ativa
        }

        console.log("Aba 'clientes.php' ativa. Fechando painéis de clientes...");

        var $editClienteContainer = $('#editClienteContainer');
        var $novoClienteContainer = $('#novoClienteContainer');

        if ($editClienteContainer.hasClass('active')) {
            $editClienteContainer.removeClass('active');
            console.log('Painel de edição de cliente fechado.');
        }

        if ($novoClienteContainer.hasClass('active')) {
            $novoClienteContainer.removeClass('active');
            console.log('Painel de adição de cliente fechado.');
        }
    });

    // Fechar o painel ao clicar fora dele, mas apenas se a aba "Clientes" estiver ativa
    $(document).off("click", ".close-panel-cliente").on("click", ".close-panel-cliente", function(event) {
        console.log("Evento de clique em .close-panel-cliente disparado. Verificando aba ativa...");
        var activePage = localStorage.getItem('activePage');
        console.log("Aba ativa:", activePage);

        if (activePage !== 'clientes.php') {
            console.log("Aba ativa não é 'clientes.php'. Ignorando evento.");
            return; // Só executar se a aba "Clientes" estiver ativa
        }

        console.log("Aba 'clientes.php' ativa. Verificando clique fora do painel...");

        var $editClienteContainer = $('#editClienteContainer');
        var $novoClienteContainer = $('#novoClienteContainer');
        
        if (!$editClienteContainer.is(event.target) && $editClienteContainer.has(event.target).length === 0 && $editClienteContainer.hasClass('active')) {
            $editClienteContainer.removeClass('active');
            console.log('Painel de edição fechado ao clicar fora.');
        }

        if (!$novoClienteContainer.is(event.target) && $novoClienteContainer.has(event.target).length === 0 && $novoClienteContainer.hasClass('active')) {
            $novoClienteContainer.removeClass('active');
            console.log('Painel de adição de cliente fechado ao clicar fora.');
        }
    });
}

function reapplyClientButtonEvents() {
    console.log("Reaplicando eventos de clientes...");

    // Evento para o botão "Adicionar Novo Cliente"
    $("#btnNovoCliente").off("click").on("click", function () {
        console.log("Botão 'Adicionar Novo Cliente' clicado!");

        $.ajax({
            url: "criar_cliente.php",
            method: "GET",
            success: function (data) {
                $("#formNovoClienteContent").html(data);
                $("#novoClienteContainer").addClass("active");

                applyMasks();

                $("#formNovoCliente").off("submit").on("submit", function (e) {
                    e.preventDefault();

                    var dadosForm = $(this).serialize();

                    $.ajax({
                        url: "salvar_cliente.php",
                        method: "POST",
                        data: dadosForm,
                        dataType: "json",
                        success: function (response) {
                            if (response.status === "success") {
                                alert(response.message);

                                $.ajax({
                                    url: "clientes.php",
                                    method: "GET",
                                    success: function (data) {
                                        $("#conteudo").html(data);
                                        reapplyClientButtonEvents();
                                    }
                                });
                            } else {
                                alert("Erro ao criar cliente: " + response.message);
                            }
                        },
                        error: function (xhr, status, error) {
                            console.error("Erro na requisição AJAX:", error);
                            console.error(xhr.responseText);
                            alert("Erro ao criar o cliente. Veja o console para mais detalhes.");
                        }
                    });
                });
            },
            error: function () {
                console.log("Erro ao carregar o formulário de criação de cliente.");
            }
        });
    });
}

function applyMasks() {
    $('#telefone').mask('(00) 00000-0000');
    $('#documento').mask('00.000.000/0000-00');
}

$("#cidade").autocomplete({
    source: function (request, response) {
        $.ajax({
            url: "buscar_cidades.php",
            dataType: "json",
            data: {
                term: request.term
            },
            success: function (data) {
                response($.map(data, function (item) {
                    return {
                        label: item,
                        value: item
                    };
                }));
            }
        });
    },
    appendTo: "#conteudo",
    minLength: 2,
    select: function (event, ui) {
        event.stopPropagation();
        $("#cidade").val(ui.item.value);
    }
});