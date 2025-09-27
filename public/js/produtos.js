console.log("produtos.js: Arquivo carregado com sucesso.");

$(document).ready(function () {
    console.log("produtos.js: $(document).ready executado.");
    reapplyButtonEvents();
    $(document).on('click', fecharModalAoClicarFora);
});

let isSubmittingProduct = false; // Variável de controle para impedir dupla submissão

function reapplyButtonEvents() {
    console.log("Reaplicando eventos para #btnNovoProduto e .btnEditar...");

    // Verificar se os elementos estão presentes no DOM
    const btnNovoProdutoExists = $('#btnNovoProduto').length > 0;
    const btnEditarExists = $('.btnEditar').length > 0;

    if (btnNovoProdutoExists) {
        console.log("Botão #btnNovoProduto encontrado no DOM.");
    } else {
        console.warn("Botão #btnNovoProduto NÃO encontrado no DOM!");
    }
    if (btnEditarExists) {
        console.log(`Botões .btnEditar encontrados no DOM (${$('.btnEditar').length} elementos).`);
    } else {
        console.warn("Botões .btnEditar NÃO encontrados no DOM!");
    }

    function exibirNotificacao(mensagem, tipo = 'success') {
        var $notificacao = $('#notificacao');
        $notificacao.removeClass('error success').addClass(tipo).text(mensagem).css('top', '0').fadeIn();

        setTimeout(function() {
            $notificacao.fadeOut('slow', function() {
                $notificacao.css('top', '-50px');
            });
        }, 3000);
    }

    // Evento para o botão 'Adicionar Novo Produto'
    $(document).off("click", "#btnNovoProduto").on("click", "#btnNovoProduto", function (e) {
        e.preventDefault();
        console.log("Botão 'Adicionar Novo Produto' clicado!");
        console.log("Carregando criar_produto.php via AJAX...");

        $.ajax({
            url: "criar_produto.php",
            method: "GET",
            success: function (data) {
                console.log("criar_produto.php carregado com sucesso.");
                $("#formProdutoContent").html(data);
                $("#novoProdutoContainer").addClass("active");
            },
            error: function (xhr, status, error) {
                console.error("Erro ao carregar criar_produto.php:", error);
            }
        });
    });

    // Submissão do formulário de novo produto
    $(document).off("submit", "#formNovoProduto").on("submit", "#formNovoProduto", function (e) {
        e.preventDefault();

        if (isSubmittingProduct) return;
        isSubmittingProduct = true;
        $('#btnSubmit').prop('disabled', true);

        console.log("Submetendo formulário para criar_produto.php...");

        var formData = new FormData(this);
        var mensagemDiv = $('#mensagem');

        $.ajax({
            url: "criar_produto.php",
            method: "POST",
            data: formData,
            processData: false,
            contentType: false,
            dataType: "json",
            success: function (response) {
                console.log("Resposta de criar_produto.php:", response);
                mensagemDiv.text(response.message);
                if (response.status === "success") {
                    mensagemDiv.removeClass("error").addClass("success");
                    $('#formNovoProduto')[0].reset();
                    setTimeout(function() {
                        $('#novoProdutoContainer').removeClass('active');
                        atualizarListaProdutos();
                    }, 2000); // Atraso de 2 segundos para exibir a mensagem
                } else {
                    mensagemDiv.removeClass("success").addClass("error");
                    isSubmittingProduct = false;
                    $('#btnSubmit').prop('disabled', false);
                }
            },
            error: function (xhr, status, error) {
                console.error("Erro ao criar o produto:", error);
                console.log("Resposta do servidor:", xhr.responseText);
                mensagemDiv.text("Erro ao criar o produto: " + (xhr.responseJSON?.message || "Erro desconhecido."));
                mensagemDiv.removeClass("success").addClass("error");
                isSubmittingProduct = false;
                $('#btnSubmit').prop('disabled', false);
            }
        });
    });

    // Evento para editar um produto
    $(document).off("click", ".btnEditar").on("click", ".btnEditar", function (e) {
        e.preventDefault();
        var idProduto = $(this).data("id");
        console.log("Botão 'Editar Produto' clicado para o produto ID: " + idProduto);
        console.log("Carregando editar_produto.php via AJAX...");

        $.ajax({
            url: "editar_produto.php",
            method: "GET",
            data: { id: idProduto },
            success: function (data) {
                console.log("editar_produto.php carregado com sucesso.");
                $("#formProdutoContent").html(data);
                $("#novoProdutoContainer").addClass("active");
            },
            error: function (xhr, status, error) {
                console.error("Erro ao carregar editar_produto.php:", error);
                console.log("Resposta do servidor:", xhr.responseText);
            }
        });
    });

    // Submissão do formulário de edição de produto
    $(document).off("submit", "#formEditarProduto").on("submit", "#formEditarProduto", function (e) {
        e.preventDefault();

        if (isSubmittingProduct) return;
        isSubmittingProduct = true;
        $(this).find('button[type="submit"]').prop('disabled', true);

        console.log("Submetendo formulário para editar_produto.php...");
        var dadosForm = $(this).serialize();
        console.log("Dados enviados:", dadosForm);

        var mensagemDiv = $('#mensagem');

        $.ajax({
            url: "editar_produto.php",
            method: "POST",
            data: dadosForm,
            dataType: "json",
            success: function (response) {
                console.log("Resposta de editar_produto.php:", response);
                mensagemDiv.text(response.message);
                if (response.status === "success") {
                    mensagemDiv.removeClass("error").addClass("success");
                    setTimeout(function() {
                        $('#novoProdutoContainer').removeClass('active');
                        atualizarListaProdutos();
                    }, 2000); // Atraso de 2 segundos para exibir a mensagem
                } else {
                    mensagemDiv.removeClass("success").addClass("error");
                    isSubmittingProduct = false;
                    $(this).find('button[type="submit"]').prop('disabled', false);
                }
            }.bind(this),
            error: function (xhr, status, error) {
                console.error("Erro na requisição AJAX:", error);
                console.log("Resposta do servidor:", xhr.responseText);
                mensagemDiv.text("Erro ao editar o produto: " + (xhr.responseJSON?.message || "Erro desconhecido."));
                mensagemDiv.removeClass("success").addClass("error");
                isSubmittingProduct = false;
                $(this).find('button[type="submit"]').prop('disabled', false);
            }.bind(this)
        });
    });

    // Evento para excluir um produto
    $(document).off("click", ".btnExcluir").on("click", ".btnExcluir", function (e) {
        e.preventDefault();
        var id = $(this).data("id");
        console.log("Excluindo produto ID:", id);

        if (confirm("Tem certeza que deseja excluir este produto?")) {
            $.ajax({
                url: "deletar_produto.php",
                method: "POST",
                data: { id: id },
                dataType: "json",
                success: function (response) {
                    console.log("Resposta de deletar_produto.php:", response);
                    if (response.status === "success") {
                        exibirNotificacao(response.message, 'success');
                        $("tr[data-id='" + id + "']").remove();
                    } else {
                        exibirNotificacao(response.message, 'error');
                    }
                },
                error: function (xhr, status, error) {
                    console.error("Erro na requisição AJAX:", error);
                    exibirNotificacao("Erro ao excluir o produto. Veja o console para mais detalhes.", 'error');
                }
            });
        }
    });
}

function fecharModalAoClicarFora(event) {
    var $novoProdutoContainer = $('#novoProdutoContainer');
    if ($novoProdutoContainer.hasClass('active') && !$novoProdutoContainer.is(event.target) && $novoProdutoContainer.has(event.target).length === 0) {
        $novoProdutoContainer.removeClass('active');
        console.log('Painel de adição/edição de produto fechado ao clicar fora.');
    }
}

function atualizarListaProdutos() {
    console.log("Atualizando lista de produtos...");
    $.ajax({
        url: "produtos.php",
        method: "GET",
        success: function (data) {
            console.log("Lista de produtos atualizada com sucesso.");
            $("#conteudo").html(data);
            reapplyButtonEvents(); // Reaplicar eventos após atualizar a lista
        },
        error: function (xhr, status, error) {
            console.error("Erro ao carregar a lista de produtos:", error);
        }
    });
}