<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'backend/config/db.php';

// Verifique se o formulário foi enviado e processado apenas uma vez
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty($_POST['nome']) && !empty($_POST['tipo'])) {
    $nome = $_POST['nome'];
    $valor = $_POST['valor'];
    $quantidade_estoque = $_POST['quantidade_estoque'];
    $tipo = $_POST['tipo'];

    // Validação extra para o campo tipo
    if (empty($tipo)) {
        echo json_encode(['status' => 'error', 'message' => 'Tipo é obrigatório.']);
        exit;
    }

    // Usando uma transação para evitar duplicação
    try {
        $conn->beginTransaction();

        // Inserção do produto
        $stmt = $conn->prepare("INSERT INTO produtos (nome, valor, quantidade_estoque, tipo) 
                                VALUES (:nome, :valor, :quantidade_estoque, :tipo)");
        $stmt->bindParam(':nome', $nome);
        $stmt->bindParam(':valor', $valor);
        $stmt->bindParam(':quantidade_estoque', $quantidade_estoque);
        $stmt->bindParam(':tipo', $tipo);

        if ($stmt->execute()) {
            $conn->commit();
            echo json_encode(['status' => 'success', 'message' => 'Produto criado com sucesso!']);
        } else {
            $conn->rollBack();
            echo json_encode(['status' => 'error', 'message' => 'Erro ao criar o produto.']);
        }
    } catch (PDOException $e) {
        $conn->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Erro ao criar o produto.']);
    }
    exit;
}
?>

<h2 class="modal-title">Novo Produto</h2>

<form id="formNovoProduto" method="POST" class="form-produto">
    <div class="form-group">
        <label for="nome">Nome:</label>
        <input type="text" name="nome" id="nome" placeholder="Digite o nome do produto" required>
    </div>

    <div class="form-group">
        <label for="valor">Valor:</label>
        <input type="number" name="valor" id="valor" placeholder="Digite o valor" step="0.01" required>
    </div>

    <div class="form-group">
        <label for="quantidade_estoque">Quantidade em Estoque:</label>
        <input type="number" name="quantidade_estoque" id="quantidade_estoque" placeholder="Digite a quantidade em estoque" step="0.001" min="0" required>
    </div>

    <div class="form-group">
        <label for="tipo">Tipo:</label>
        <select name="tipo" id="tipo" required>
            <option value="" disabled selected>Selecione o tipo</option>
            <option value="KG">KG</option>
            <option value="UND">UND</option>
        </select>
    </div>

    <button type="submit" id="btnSubmit" class="btn-submit">Salvar</button>
</form>

<div id="mensagem" class="mensagem"></div>