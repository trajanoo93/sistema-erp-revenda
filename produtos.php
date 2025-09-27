<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'backend/config/db.php';

// Buscar todos os produtos, incluindo o campo 'tipo'
$stmtProdutos = $conn->prepare("SELECT * FROM produtos");
$stmtProdutos->execute();
$produtos = $stmtProdutos->fetchAll(PDO::FETCH_ASSOC);
?>

<h1>Produtos</h1>

<!-- Campo de busca -->
<div class="search-container">
    <input type="text" id="searchProduto" class="search-input" placeholder="Buscar produtos...">
</div>

<!-- Botão flutuante -->
<button id="btnNovoProduto" class="floating-button"><i class="fas fa-plus"></i> </button>

<!-- Contêiner da tabela -->
<div class="tabela-container">
    <table class="tabela-generica" id="tabelaProdutos">
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Valor</th>
                <th>Quantidade</th>
                <th>Tipo</th> <!-- Nova coluna para Tipo -->
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($produtos as $produto): ?>
            <tr data-id="<?= $produto['id'] ?>">
                <td><?= $produto['id'] ?></td>
                <td><?= htmlspecialchars($produto['nome']) ?></td>
                <td>R$ <?= number_format($produto['valor'], 2, ',', '.') ?></td>
                <td><?= $produto['quantidade_estoque'] ?></td>
                <td><?= $produto['tipo'] ?></td> <!-- Exibir o valor da coluna Tipo -->
                <td>
                    <button class="btn btn-edit btnEditar" data-id="<?= $produto['id'] ?>">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-add-stock btnAddStock" style="background-color: blue; color: white;" data-id="<?= $produto['id'] ?>">
                        <i class="fas fa-plus"></i> <!-- Ícone de adicionar -->
                    </button>
                    <button class="btn btn-delete btnExcluir" data-id="<?= $produto['id'] ?>">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal de Adicionar Estoque com classes exclusivas -->
<div id="modalAddStock" class="modal-add-stock">
    <div class="modal-add-stock-content">
        <span class="close-modal-add-stock">×</span>
        <h2>Adicionar ao Estoque</h2>
        <form id="formAddStock">
            <input type="hidden" name="produto_id" id="produto_id">
            <div class="form-group">
                <label for="quantidade_add">Quantidade a Adicionar</label>
                <input type="number" step="0.001" name="quantidade_add" id="quantidade_add" required>
            </div>
            <div class="form-group">
                <label for="observacao_add">Observação</label>
                <textarea name="observacao_add" id="observacao_add" required></textarea>
            </div>
            <button type="submit" class="btn-submit">Adicionar ao Estoque</button>
        </form>
    </div>
</div>

<style>
    /* Estilos para o fundo do modal de adicionar estoque */
.modal-add-stock {
    display: none; /* Oculta o modal por padrão */
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0, 0, 0, 0.5);
}

/* Estilos para o conteúdo do modal de adicionar estoque */
.modal-add-stock-content {
    background-color: #fff;
    margin: 10% auto;
    padding: 20px;
    border: 1px solid #888;
    width: 50%;
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    border-radius: 5px;
    position: relative;
}

/* Estilos para o botão de fechar do modal de adicionar estoque */
.close-modal-add-stock {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
}

.close-modal-add-stock:hover,
.close-modal-add-stock:focus {
    color: black;
    text-decoration: none;
    cursor: pointer;
}
</style>

<script>
$(document).ready(function() {
    // Abrir o modal ao clicar no botão de adicionar estoque
    $(document).on('click', '.btnAddStock', function() {
        var produtoId = $(this).data('id');
        $('#produto_id').val(produtoId);
        $('#modalAddStock').show();
    });

    // Fechar o modal ao clicar no botão de fechar
    $('.close-modal-add-stock').on('click', function() {
        $('#modalAddStock').hide();
    });

    $(document).on('click', '#modalAddStock', function(event) {
        if ($(event.target).is('#modalAddStock')) {
            $('#modalAddStock').hide();
        }
    });

    // Enviar o formulário de adicionar estoque
    $('#formAddStock').on('submit', function(e) {
        e.preventDefault();
        var formData = $(this).serialize();

        $.ajax({
            url: 'adicionar_estoque.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    alert(response.message);
                    $('#modalAddStock').hide();
                    location.reload(); // Recarrega a página para atualizar a quantidade em estoque
                } else {
                    alert('Erro: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error("Erro ao adicionar estoque:", error);
            }
        });
    });

    // Função de busca
    $("#searchProduto").on("keyup", function() {
        var value = $(this).val().toLowerCase();
        $("#tabelaProdutos tbody tr").filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });
    });
});
</script>