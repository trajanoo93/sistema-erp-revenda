// Função para inicializar o carregamento dinâmico do conteúdo
$(document).ready(function () {
    // Carregar páginas via AJAX quando os links do menu lateral forem clicados
    $(".sidebar ul li a").click(function (e) {
        e.preventDefault(); // Prevenir comportamento padrão do link
        var page = $(this).data("page");

        // Exibir um indicador de carregamento
        $("#conteudo").html('<div class="loading">Carregando...</div>');

        // Requisitar a página correspondente via AJAX
        $.ajax({
            url: page + ".php",
            method: "GET",
            success: function (data) {
                // Carregar o conteúdo recebido
                $("#conteudo").html(data);

                // Reaplicar eventos específicos da página carregada, como os eventos de clientes, produtos ou pedidos
                if (typeof reapplyButtonEvents === "function") {
                    reapplyButtonEvents(); // Certifique-se de que essa função esteja definida nos scripts específicos (clientes.js, produtos.js, etc.)
                }
            },
            error: function () {
                $("#conteudo").html('<p>Erro ao carregar conteúdo.</p>');
            }
        });
    });
});