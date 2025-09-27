<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Usuário não autenticado.']);
    exit;
}

require_once 'backend/config/db.php';

$usuario_id = $_SESSION['usuario_id'];
$produto_id = $_POST['produto_id'];
$quantidade = $_POST['quantidade_add'];
$observacao = $_POST['observacao_add'];

try {
    // Buscar o nome do usuário
    $stmtUsuario = $conn->prepare("SELECT nome FROM usuarios WHERE id = :usuario_id");
    $stmtUsuario->bindParam(':usuario_id', $usuario_id);
    $stmtUsuario->execute();
    $usuario = $stmtUsuario->fetch(PDO::FETCH_ASSOC);

    // Buscar o nome e o tipo do produto
    $stmtProduto = $conn->prepare("SELECT nome, tipo FROM produtos WHERE id = :produto_id");
    $stmtProduto->bindParam(':produto_id', $produto_id);
    $stmtProduto->execute();
    $produto = $stmtProduto->fetch(PDO::FETCH_ASSOC);

    // Atualizar a quantidade em estoque
    $stmtUpdate = $conn->prepare("UPDATE produtos SET quantidade_estoque = quantidade_estoque + :quantidade WHERE id = :produto_id");
    $stmtUpdate->bindParam(':quantidade', $quantidade);
    $stmtUpdate->bindParam(':produto_id', $produto_id);
    $stmtUpdate->execute();

    // Construir a mensagem de log com o tipo incluído
    $acao = "{$usuario['nome']} adicionou {$quantidade} {$produto['tipo']} ao produto {$produto['nome']}. Motivo: {$observacao}";

    // Inserir no log
    $stmtLog = $conn->prepare("INSERT INTO logs (usuario_id, acao, data) VALUES (:usuario_id, :acao, NOW())");
    $stmtLog->bindParam(':usuario_id', $usuario_id);
    $stmtLog->bindParam(':acao', $acao);
    $stmtLog->execute();

    echo json_encode(['status' => 'success', 'message' => 'Estoque atualizado com sucesso.']);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Erro ao atualizar estoque: ' . $e->getMessage()]);
}
?>