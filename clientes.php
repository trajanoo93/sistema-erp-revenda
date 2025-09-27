<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'backend/config/db.php';

// Buscar todos os clientes
$stmtClientes = $conn->prepare("SELECT * FROM clientes");
$stmtClientes->execute();
$clientes = $stmtClientes->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Carregar jQuery primeiro -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Em seguida, carregar o jQuery Mask Plugin -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>

<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
<!-- Inclusão do Font Awesome CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css">
    
<link rel="stylesheet" href="public/css/style.css?v=162.0">

<!-- Carregar o jQuery UI (se necessário para autocomplete ou outros recursos de UI) -->
<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>

<!-- Carregar o script clientes.js por último -->
<script src="public/js/clientes.js?v=1.1"></script>

<div class="clientes-header">
    <h1>Clientes</h1>
    <!-- Botão Adicionar Novo Cliente -->
    <button id="btnNovoCliente" class="floating-button">
        <i class="fas fa-plus"></i> 
    </button>
</div>

<!-- Campo de busca -->
<div class="search-container">
    <input type="text" id="searchCliente" class="search-input" placeholder="Buscar clientes...">
</div>

<!-- Contêiner da tabela -->
<div class="tabela-container">
    <table class="tabela-generica" id="tabelaClientes">
        <thead>
            <tr>
                <th>Nome</th>
                <th>Telefone</th>
                <th>Cidade</th>
                <th>CNPJ/CPF</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($clientes as $cliente): ?>
            <tr data-id="<?= $cliente['id'] ?>">
                <td><?= htmlspecialchars($cliente['nome']) ?></td>
                <td><?= htmlspecialchars($cliente['telefone']) ?></td>
                <td><?= htmlspecialchars($cliente['cidade']) ?></td>
                <td><?= htmlspecialchars($cliente['documento']) ?></td>
                <td>
                    <button class="btn btn-edit btnEditarCliente" data-id="<?= $cliente['id'] ?>">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-delete btnExcluirCliente" data-id="<?= $cliente['id'] ?>">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Painel de adicionar novo cliente (novoClienteContainer) -->
<div id="novoClienteContainer" class="novo-cliente-container">
    <div class="panel-content">
        <span class="close-btn">×</span>
        <div id="formNovoClienteContent">
            <!-- O formulário de adição de cliente será carregado aqui via AJAX -->
        </div>
    </div>
</div>

<!-- Painel de edição de cliente (editClienteContainer) -->
<div id="editClienteContainer" class="edit-cliente-container">
    <div class="panel-content">
        <span class="close-btn">×</span>
        <div id="formClienteContent">
            <!-- O formulário de edição do cliente será carregado aqui via AJAX -->
        </div>
    </div>
</div>

<!-- Script para busca -->
<script>
    // Função de busca
    $("#searchCliente").on("keyup", function() {
        var value = $(this).val().toLowerCase();
        $("#tabelaClientes tbody tr").filter(function() {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });
    });
</script>